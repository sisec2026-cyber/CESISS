<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
include __DIR__ . '/../../includes/db.php';
ob_start();

/* =========================
   CONFIGURACIÓN
========================= */
$COLLATION = 'utf8mb4_general_ci'; // En MySQL 8: utf8mb4_0900_ai_ci

// Etiquetas de status (ajústalas a tu tabla `status.status_equipo`)
$STATUS_HECHO     = 'Mantenimiento hecho';
$STATUS_PENDIENTE = 'Mantenimiento pendiente';
$STATUS_PROCESO   = 'Mantenimiento en proceso';
$STATUS_SIGUIENTE = 'Siguiente mantenimiento';

// Umbral para “siguiente mantenimiento” si no manejas ese status en BD
$NEXT_MAINTENANCE_FALLBACK_DAYS = 180;

/* =========================
   HELPERS
========================= */
function esc_like_prefixes(string $col, string $collation, array $prefixes): string {
  $likes = [];
  foreach ($prefixes as $p) {
    $p = str_replace("'", "''", $p);
    $likes[] = "$col COLLATE $collation LIKE '$p'";
  }
  return '(' . implode(' OR ', $likes) . ')';
}

function normalize_key_es(string $s): string {
  $s = trim($s);
  if (function_exists('iconv')) {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) $s = $t;
  }
  $s = preg_replace('/[^A-Za-z0-9\s]/', '', $s);
  $s = mb_strtolower($s, 'UTF-8');
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

/* =========================
   PREFIJOS POR CATEGORÍA
========================= */
$PREFIJOS_CCTV   = ['racks%','camara%','dvr%','nvr%','servidor%','monitor%','biometrico%','videoportero%','videotelefono%','ups%'];
$PREFIJOS_ALARMA = ['sensor%','dh%','pir%','cm%','oh%','estrobo%','rep%','drc%','estacion%','teclado%','sirena%','boton%'];

$whereCCTV   = esc_like_prefixes('e.nom_equipo', $COLLATION, $PREFIJOS_CCTV);
$whereAlarma = esc_like_prefixes('e.nom_equipo', $COLLATION, $PREFIJOS_ALARMA);

$CASE_CCTV   = "CASE WHEN $whereCCTV THEN 1 ELSE 0 END";
$CASE_ALARMA = "CASE WHEN $whereAlarma THEN 1 ELSE 0 END";

/* =========================
   KPIs (tarjetas)
========================= */
$qCamaras = "
  SELECT COUNT(*) AS total
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  WHERE $whereCCTV
";
$camaras = (int)($conn->query($qCamaras)->fetch_assoc()['total'] ?? 0);

$qSensores = "
  SELECT COUNT(*) AS total
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  WHERE $whereAlarma
";
$sensores = (int)($conn->query($qSensores)->fetch_assoc()['total'] ?? 0);

$usuarios = (int)($conn->query("SELECT COUNT(*) AS total FROM usuarios")->fetch_assoc()['total'] ?? 0);

// Cámaras en “mantenimiento” (si tu BD usa PROCESO como “Mantenimiento en proceso”)
$qMto = "
  SELECT COUNT(*) AS total
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  INNER JOIN status  s ON d.estado = s.id
  WHERE $whereCCTV
    AND s.status_equipo = '{$conn->real_escape_string($STATUS_PROCESO)}'
";
$mantenimiento = (int)($conn->query($qMto)->fetch_assoc()['total'] ?? 0);

/* =========================
   COROPLETA POR REGIÓN
========================= */
/*
 Cadena de ubicación:
   sucursales (municipio_id)
   -> municipios (ciudad_id)
   -> ciudades (region_id)
   -> regiones
*/
$sqlEstados = "
  SELECT
    r.nom_region                         AS estado,
    SUM($CASE_CCTV)                      AS cctv,
    SUM($CASE_ALARMA)                    AS alarma
  FROM sucursales s
  INNER JOIN municipios m ON s.municipio_id = m.id
  INNER JOIN ciudades   c ON m.ciudad_id    = c.id
  INNER JOIN regiones   r ON c.region_id    = r.id
  LEFT  JOIN dispositivos d ON d.sucursal   = s.id
  LEFT  JOIN equipos     e ON d.equipo      = e.id
  GROUP BY r.nom_region
";
$porEstado = [];
if ($res = $conn->query($sqlEstados)) {
  while ($row = $res->fetch_assoc()) {
    $k = normalize_key_es($row['estado'] ?? '');
    $cctv   = (int)($row['cctv']   ?? 0);
    $alarma = (int)($row['alarma'] ?? 0);
    $porEstado[$k] = [
      'cctv'  => $cctv,
      'alarma'=> $alarma,
      'total' => $cctv + $alarma,
      'label' => $row['estado'],
    ];
  }
}

/* =========================
   SUCURSALES (marcadores)
========================= */
/*
  Además de CCTV+Alarma y disp_count, traemos conteos por status de mantenimiento.
  Si tu tabla `status.status_equipo` no tiene alguno de estos textos, el conteo quedará en 0.
*/
$stHecho     = $conn->real_escape_string($STATUS_HECHO);
$stPendiente = $conn->real_escape_string($STATUS_PENDIENTE);
$stProceso   = $conn->real_escape_string($STATUS_PROCESO);
$stSig       = $conn->real_escape_string($STATUS_SIGUIENTE);

$sqlSucursales = "
  SELECT
    r.nom_region           AS estado,
    c.nom_ciudad           AS ciudad,
    m.nom_municipio        AS municipio,
    s.id                   AS sucursal_id,
    s.nom_sucursal         AS sucursal,
    dtr.nom_determinante   AS determinante,
    s.lat, s.lng,
    s.status_manual,

    -- Desglose categorías por prefijo
    SUM($CASE_CCTV)        AS cctv,
    SUM($CASE_ALARMA)      AS alarma,
    COUNT(disp.id)         AS disp_count,

    -- Conteos por status de mantenimiento
    SUM(CASE WHEN st.status_equipo = '$stHecho'     THEN 1 ELSE 0 END) AS mto_hecho,
    SUM(CASE WHEN st.status_equipo = '$stPendiente' THEN 1 ELSE 0 END) AS mto_pendiente,
    SUM(CASE WHEN st.status_equipo = '$stProceso'   THEN 1 ELSE 0 END) AS mto_proceso,
    SUM(CASE WHEN st.status_equipo = '$stSig'       THEN 1 ELSE 0 END) AS mto_siguiente,

    -- Última fecha capturada (para fallback del 'siguiente mantenimiento')
    MAX(disp.fecha)        AS last_fecha
  FROM sucursales s
  INNER JOIN municipios m       ON s.municipio_id = m.id
  INNER JOIN ciudades   c       ON m.ciudad_id    = c.id
  INNER JOIN regiones   r       ON c.region_id    = r.id
  LEFT  JOIN determinantes dtr  ON dtr.sucursal_id = s.id
  LEFT  JOIN dispositivos disp  ON disp.sucursal   = s.id
  LEFT  JOIN equipos      e     ON disp.equipo     = e.id
  LEFT  JOIN status       st    ON disp.estado     = st.id
  GROUP BY
    r.nom_region, c.nom_ciudad, m.nom_municipio,
    s.id, s.nom_sucursal, dtr.nom_determinante, s.lat, s.lng, s.status_manual
";


$sucursales = [];
if ($res = $conn->query($sqlSucursales)) {
  while ($row = $res->fetch_assoc()) {
    $row['cctv']          = (int)($row['cctv'] ?? 0);
    $row['alarma']        = (int)($row['alarma'] ?? 0);
    $row['total']         = $row['cctv'] + $row['alarma'];
    $row['disp_count']    = (int)($row['disp_count'] ?? 0);
    $row['mto_hecho']     = (int)($row['mto_hecho'] ?? 0);
    $row['mto_pendiente'] = (int)($row['mto_pendiente'] ?? 0);
    $row['mto_proceso']   = (int)($row['mto_proceso'] ?? 0);
    $row['mto_siguiente'] = (int)($row['mto_siguiente'] ?? 0);
    $row['last_fecha']    = $row['last_fecha'] ?? null;
    $sucursales[] = $row;
  }
}

/* =========================
   RENDER
========================= */
?>
<h2 class="mb-4">Panel de control</h2>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <i class="fas fa-video fa-2x text-primary mb-2"></i>
        <h5 class="card-title mb-0"><?= $camaras ?></h5>
        <small class="text-muted">Cámaras registradas</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <i class="fas fa-wifi fa-2x text-info mb-2"></i>
        <h5 class="card-title mb-0"><?= $sensores ?></h5>
        <small class="text-muted">Sensores activos</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <i class="fas fa-user fa-2x text-success mb-2"></i>
        <h5 class="card-title mb-0"><?= $usuarios ?></h5>
        <small class="text-muted">Usuarios registrados</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <i class="fas fa-tools fa-2x text-danger mb-2"></i>
        <h5 class="card-title mb-0"><?= $mantenimiento ?></h5>
        <small class="text-muted">Cámaras en mantenimiento</small>
      </div>
    </div>
  </div>
</div>

<!-- ===== Gráfica de barras ===== -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
      <div class="d-flex align-items-center gap-3">
        <h6 class="card-title mb-0">Sucursales por total de dispositivos</h6>
        <div class="badge text-bg-light">CCTV + Alarma</div>
      </div>

      <div class="d-flex align-items-center flex-wrap gap-2">
        <label class="small text-muted me-1">Región</label>
        <select id="barRegionSelect" class="form-select form-select-sm" style="min-width:140px;"></select>

        <label class="small text-muted ms-2 me-1">Ordenar por</label>
        <select id="barSortBy" class="form-select form-select-sm">
          <option value="total" selected>Total</option>
          <option value="cctv">CCTV</option>
          <option value="alarma">Alarma</option>
        </select>

        <label class="small text-muted ms-2 me-1">Top</label>
        <input id="barTopN" type="number" class="form-control form-control-sm" value="20" min="5" max="200" style="width:90px;">

        <button id="toggleChart" class="btn btn-sm btn-outline-secondary ms-2">Ocultar gráfica</button>
      </div>
    </div>

    <div id="chartSection" class="bar-chart-container">
      <canvas id="sucursalesBarChart"></canvas>
    </div>
    <small class="text-muted d-block mt-2">
      Tip: haz clic en cualquier barra para abrir <b>Ver dispositivos</b> de esa sucursal.
    </small>
  </div>
</div>

<!-- ===== MAPA ===== -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-3">
        <h6 class="card-title mb-0">Mapa de dispositivos</h6>
        <div class="badge text-bg-light" id="breadcrumb">México</div>
      </div>

      <!-- Buscador -->
      <div class="position-relative" style="min-width:280px;">
        <input id="searchInput" class="form-control form-control-sm pe-5" type="text" placeholder="Buscar sucursal o determinante...">
        <button id="clearSearch" type="button" class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-1 px-2" style="line-height:1;">✕</button>
        <div id="searchResults" class="list-group position-absolute w-100 d-none" style="z-index: 1000; max-height: 260px; overflow:auto;"></div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <select id="metricSelect" class="form-select form-select-sm">
          <option value="total" selected>Total</option>
          <option value="cctv">CCTV</option>
          <option value="alarma">Alarma</option>
        </select>
        <button id="btnVolver" class="btn btn-sm btn-outline-secondary d-none">← Volver</button>
        <button id="toggleMap" class="btn btn-sm btn-outline-secondary">Ocultar mapa</button>
  <!-- NUEVO: botón de edición (opcional: restringe por rol) -->
  <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Técnico'])): ?>
    <button id="toggleEdit" class="btn btn-sm btn-outline-warning">Editar estados</button>
  <?php endif; ?>
      </div>
    </div>

    <!-- Filtros (leyenda clicable + contadores) -->
    <div id="mapFilters" class="map-filters d-flex flex-wrap align-items-center gap-2 mt-2">
      <button type="button" class="mf-pill mf-all active" data-chip="all">
        Todos <span class="mf-badge" id="cnt-all">0</span>
      </button>
      <button type="button" class="mf-pill mf-green active" data-chip="green" title="Mantenimiento hecho">
        Hecho <span class="mf-badge" id="cnt-green">0</span>
      </button>
      <button type="button" class="mf-pill mf-orange active" data-chip="orange" title="Mantenimiento pendiente">
        Pendiente <span class="mf-badge" id="cnt-orange">0</span>
      </button>
      <button type="button" class="mf-pill mf-yellow active" data-chip="yellow" title="Mantenimiento en proceso">
        En proceso <span class="mf-badge" id="cnt-yellow">0</span>
      </button>
      <button type="button" class="mf-pill mf-blue active" data-chip="blue" title="Siguiente mantenimiento">
        Siguiente <span class="mf-badge" id="cnt-blue">0</span>
      </button>
    </div>

    <!-- Mapa -->
    <div id="mapSection">
      <div id="mapSkeleton" class="mt-3 w-100" style="height:520px;border-radius:10px;background:linear-gradient(90deg,#f3f6fb 25%,#e9eef6 37%,#f3f6fb 63%);background-size:400% 100%;animation:shimmer 1.4s infinite;"></div>
      <div id="map" class="mt-3" style="height:520px;border-radius:10px;overflow:hidden;display:none;"></div>
      <small class="text-muted d-block mt-2">Tip: haz clic en un estado para hacer zoom; los marcadores se agrupan automáticamente.</small>
    </div>
  </div>
</div>

<!-- Botones rápidos -->
<div class="d-flex justify-content-center gap-3">
  <div class="col-md-3">
    <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Capturista','Técnico'])): ?>
      <a href="/sisec-ui/views/dispositivos/registro.php" class="btn btn-outline-primary w-100 py-3 rounded shadow-sm">
        <i class="fas fa-qrcode fa-lg me-2"></i> Registro nuevo dispositivo
      </a>
    <?php endif; ?>
  </div>
  <div class="col-md-3">
    <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador'])): ?>
      <a href="/sisec-ui/views/usuarios/registrar.php" class="btn btn-outline-success w-100 py-3 rounded shadow-sm">
        <i class="fas fa-user-plus fa-lg me-2"></i> Registro rápido de usuario
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- ===== Estilos ===== -->
<style>
  .card { border:1px solid #eef2f7; }
  .card .card-title { font-weight:700; letter-spacing:.2px; }
  .text-bg-light { background:#f5f7fb !important; }
  .badge.text-bg-light { background:#eef3fb !important; color:#334 !important; border:1px solid #dae4f5; }

  .leaflet-container { font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial; }
  .leaflet-control-layers, .legend {
    background:#fff; border-radius:8px; box-shadow:0 6px 16px rgba(0,0,0,.12);
    padding:.5rem .75rem; border:1px solid #e6eef5;
  }

  #searchResults .list-group-item { cursor:pointer; }
  #searchResults .list-group-item.active { background:#e9f2ff; }

  /* Chips determinante (marcador) */
  .det-icon { background:transparent; border:none; cursor:pointer; }
  .det-chip {
    display:flex; align-items:center; justify-content:center;
    height:28px; padding:0 14px; min-width:72px;
    border-radius:999px; background:#fff; border:1px solid #d5e1f0;
    box-shadow:0 6px 14px rgba(0,0,0,.15);
    font:600 13px/1 system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Helvetica Neue";
    white-space:nowrap; user-select:none;
  }
  .det-chip.glow { box-shadow:0 0 0 3px rgba(56,132,255,.25),0 6px 14px rgba(0,0,0,.18)!important; }

  /* Colores requeridos */
  .det-chip.green  { background:#01a806; border-color:#01a806; color:#fff; } /* Hecho */
  .det-chip.orange { background:#f39c12; border-color:#f39c12; color:#000; } /* Pendiente */
  .det-chip.yellow { background:#f1c40f; border-color:#f1c40f; color:#000; } /* En proceso */
  .det-chip.blue   { background:#2980b9; border-color:#2980b9; color:#fff; } /* Siguiente */
  .det-chip.pending { border-style:dashed; border-width:2px; }

  .legend .legend-title { display:none!important; }
  .legend { font-size:13px; }
  .legend-chips { display:flex; flex-wrap:wrap; gap:6px; }

  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

  .bar-chart-container{ position:relative; height:360px; width:100%; overflow:hidden; }
  .bar-chart-container canvas{ width:100%!important; height:100%!important; display:block; }

  @media (max-width:768px){
    #map, #mapSkeleton { height:420px!important; }
    .card .card-title { font-size:.95rem; }
    .bar-chart-container{ height:260px; }
  }

  /* Filtros del mapa (píldoras) */
  .map-filters .mf-pill{
    border:1px solid #d5e1f0; background:#fff; color:#223; border-radius:999px;
    padding:6px 12px; font:600 13px/1 system-ui,-apple-system,"Segoe UI",Roboto,Arial;
    display:inline-flex; align-items:center; gap:8px; cursor:pointer;
    box-shadow:0 2px 6px rgba(0,0,0,.06);
  }
  .map-filters .mf-pill:hover{ background:#f8fbff; }
  .map-filters .mf-pill.active{ box-shadow:0 0 0 3px rgba(30,102,197,.12); }

  .map-filters .mf-badge{
    min-width:24px; padding:2px 6px; text-align:center; border-radius:999px;
    background:#eef3fb; color:#223; border:1px solid #dae4f5; font-weight:700;
  }

  /* Colores guía en las píldoras (borde superior fino) */
  .mf-green{ border-top:3px solid #01a806; }
  .mf-orange{ border-top:3px solid #f39c12; }
  .mf-yellow{ border-top:3px solid #f1c40f; }
  .mf-blue{ border-top:3px solid #2980b9; }
  .mf-all{ border-top:3px solid #6c757d; }

  /* Clusters por color dominante */
  .marker-cluster.marker-cluster-green div {
    background: rgba(1,168,6,0.6); border: 2px solid #01a806; color:#fff;
  }
  .marker-cluster.marker-cluster-orange div {
    background: rgba(243,156,18,0.6); border: 2px solid #f39c12; color:#000;
  }
  .marker-cluster.marker-cluster-yellow div {
    background: rgba(241,196,15,0.6); border: 2px solid #f1c40f; color:#000;
  }
  .marker-cluster.marker-cluster-blue div {
    background: rgba(41,128,185,0.65); border: 2px solid #2980b9; color:#fff;
  }

  .marker-cluster div span { font-weight: 800; }
</style>

<!-- Leaflet & plugins -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<?php
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

<script>
/* ===== Datos del backend ===== */
const NEXT_MAINTENANCE_FALLBACK_DAYS = <?= (int)$NEXT_MAINTENANCE_FALLBACK_DAYS ?>;

const metricasPorEstadoRaw = <?php
  echo json_encode($porEstado, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>;

const sucursales = <?php
  echo json_encode($sucursales, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>;

/* ===== Utilidades Front ===== */
const normalizeKey = s => (s||'').toString()
  .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
  .replace(/[^A-Za-z0-9\s]/g,'').trim().replace(/\s+/g,' ').toLowerCase();

const normalizeText = s => (s||'').toString()
  .normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();

const metricasPorEstado = {};
for (const [k,v] of Object.entries(metricasPorEstadoRaw || {})) {
  metricasPorEstado[normalizeKey(k)] = v;
}

/* ===== DOM refs ===== */
const breadcrumbEl = document.getElementById('breadcrumb');
const btnVolver    = document.getElementById('btnVolver');
const metricSelect = document.getElementById('metricSelect');
const searchInput  = document.getElementById('searchInput');
const clearSearch  = document.getElementById('clearSearch');
const searchResults= document.getElementById('searchResults');

const barRegionSelect= document.getElementById('barRegionSelect');
const barSortBy      = document.getElementById('barSortBy');
const barTopN        = document.getElementById('barTopN');
const toggleChartBtn = document.getElementById('toggleChart');
const toggleMapBtn   = document.getElementById('toggleMap');
const chartSection   = document.getElementById('chartSection');
const mapSection     = document.getElementById('mapSection');

const toggleEditBtn  = document.getElementById('toggleEdit');
let editMode = false;

const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/* ===== Preferencias ===== */
const savedMetric = localStorage.getItem('metricSelectValue');
if (savedMetric && ['total','cctv','alarma'].includes(savedMetric)) metricSelect.value = savedMetric;
metricSelect.addEventListener('change', () => {
  localStorage.setItem('metricSelectValue', metricSelect.value);
  updateChoroplethStyle();
});

/* ===== Estado navegación mapa ===== */
let nivel = 'pais';
let estadoActual = null;
let estadoActualKey = null;

/* ===== Mapa ===== */
const mexicoBounds = L.latLngBounds([14.0, -119.0], [33.5, -86.0]);
const map = L.map('map', {
  preferCanvas: true,
  maxBounds: mexicoBounds,
  maxBoundsViscosity: 1.0,
  worldCopyJump: false,
  inertia: false,
  minZoom: 4,
  maxZoom: 18
});
const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap',noWrap:true,bounds:mexicoBounds});
const cluster = L.markerClusterGroup({
  disableClusteringAtZoom: 13,
  maxClusterRadius: 55,
  iconCreateFunction: function (cl) {
    const markers = cl.getAllChildMarkers();
    const counts = { green:0, orange:0, yellow:0, blue:0 };
    for (const m of markers) {
      const chip = m?.options?._chip || 'green';
      counts[chip] = (counts[chip] || 0) + 1;
    }
    const dominant = Object.entries(counts).sort((a,b)=> b[1]-a[1])[0][0] || 'green';
    const n = markers.length;
    const size = n < 10 ? 'small' : (n < 100 ? 'medium' : 'large');
    return L.divIcon({
      html: `<div><span>${n}</span></div>`,
      className: `marker-cluster marker-cluster-${size} marker-cluster-${dominant}`,
      iconSize: L.point(40, 40)
    });
  }
});

let estadosLayer = null;

/* ===== Breadcrumb ===== */
function breadcrumbSet(){
  const parts = ['México'];
  if (estadoActual) parts.push(estadoActual);
  breadcrumbEl.textContent = parts.join(' > ');
}

/* ===== Color escala choropleth ===== */
function colorScale(v, max){
  const t = max ? v / max : 0;
  const a = 0.12 + 0.68 * t;
  return `rgba(30,102,197,${a})`;
}

/* ===== Lógica de color de chip (manual primero, luego automático) ===== */
function chipClassForSucursal(s) {
  // Override manual por sucursal
  const man = (s.status_manual || '').toLowerCase();
  if (man === 'hecho')     return 'green';
  if (man === 'pendiente') return 'orange';
  if (man === 'proceso')   return 'yellow';
  if (man === 'siguiente') return 'blue';

  // Automático (tu lógica original)
  const disp = Number(s.disp_count || 0);
  const hecho = Number(s.mto_hecho || 0);
  const pendiente = Number(s.mto_pendiente || 0);
  const proceso = Number(s.mto_proceso || 0);
  const siguiente = Number(s.mto_siguiente || 0);

  if (pendiente > 0) return 'orange';
  if (proceso   > 0) return 'yellow';
  if (siguiente > 0) return 'blue';

  if (disp > 0) {
    if (!s.last_fecha) return 'blue';
    const last = new Date(String(s.last_fecha) + 'T00:00:00');
    const now  = new Date();
    const diff = Math.floor((now - last) / (1000*60*60*24));
    if (diff >= NEXT_MAINTENANCE_FALLBACK_DAYS) return 'blue';
    return (hecho > 0 || diff < NEXT_MAINTENANCE_FALLBACK_DAYS) ? 'green' : 'blue';
  }
  return 'orange';
}

/* ===== Debounce ===== */
function debounce(fn, ms=200){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }

/* =========================
   Filtros de mapa (chips)
========================= */
const filtersEl = document.getElementById('mapFilters');
const cntAll    = document.getElementById('cnt-all');
const cntGreen  = document.getElementById('cnt-green');
const cntOrange = document.getElementById('cnt-orange');
const cntYellow = document.getElementById('cnt-yellow');
const cntBlue   = document.getElementById('cnt-blue');

// Estado actual del filtro por chip
const activeChips = new Set(['green','orange','yellow','blue']);
let currentQuery = ''; // texto del buscador

// === Helpers globales para sincronizar UI y estado de chips ===
let legendContainer = null; // se setea en addLegend()

function setActiveChips(arr){
  activeChips.clear();
  arr.forEach(c => activeChips.add(c));
  renderSucursales();
  updateFilterCounters();
  syncChipsUI();
}

function syncChipsUI(){
  // Actualiza las clases .active en los chips superiores
  const rootTop = document.getElementById('mapFilters');
  if (rootTop){
    rootTop.querySelectorAll('.mf-pill').forEach(el=>{
      const k = el.getAttribute('data-chip');
      if (k === 'all'){
        el.classList.toggle('active', activeChips.size === 4);
      } else {
        el.classList.toggle('active', activeChips.has(k));
      }
    });
  }
  // Re-renderiza la leyenda con el estado actual
  if (legendContainer){
    legendContainer.innerHTML = renderLegendContent(activeChips);
  }
}

/* ===== Alcance base según estado/consulta ===== */
function scopeSucursales(){
  let base = (sucursales || []).filter(s => s.lat != null && s.lng != null);
  if (estadoActualKey) base = base.filter(s => normalizeKey(s.estado) === estadoActualKey);
  if (currentQuery) {
    const qn = normalizeText(currentQuery);
    base = base.filter(s => {
      const key = [s.sucursal||'', s.determinante||'', s.municipio||'', s.ciudad||'', s.estado||''].join(' | ');
      return normalizeText(key).includes(qn);
    });
  }
  return base;
}

/* ===== Contadores ===== */
function updateFilterCounters(){
  const base = scopeSucursales();
  const counts = { green:0, orange:0, yellow:0, blue:0 };
  for (const s of base) counts[chipClassForSucursal(s)]++;
  const total = base.length;

  cntAll.textContent    = String(total);
  cntGreen.textContent  = String(counts.green);
  cntOrange.textContent = String(counts.orange);
  cntYellow.textContent = String(counts.yellow);
  cntBlue.textContent   = String(counts.blue);
}

/* ===== Marcadores ===== */
const markersById = new Map();

function buildIconFor(s) {
  const det = (s.determinante && String(s.determinante).trim() !== '') ? String(s.determinante).trim() : '—';
  const colorClass = chipClassForSucursal(s);
  const pendingClass = (colorClass === 'orange' && Number(s.disp_count||0) === 0) ? 'pending' : '';
  return L.divIcon({
    className: 'det-icon',
    html: `<div class="det-chip ${colorClass} ${pendingClass}">${det}</div>`,
    iconSize: [64,28],
    iconAnchor: [32,14],
    popupAnchor: [0,-14]
  });
}

function addMarkerFor(s) {
  if (s.lat == null || s.lng == null) return;
  const icon = buildIconFor(s);
  const m = L.marker([s.lat, s.lng], { icon });
  m.options._chip = chipClassForSucursal(s);

  const detBadge = s.determinante ? ` <span class="badge bg-light text-dark ms-1">#${s.determinante}</span>` : '';
  const linea    = `<small>${s.municipio}, ${s.ciudad} · ${s.estado}</small>`;

  const label = (() => {
    const c = chipClassForSucursal(s);
    if (c==='orange') return 'Mantenimiento pendiente';
    if (c==='yellow') return 'Mantenimiento en proceso';
    if (c==='blue')   return 'Siguiente mantenimiento';
    return 'Mantenimiento hecho';
  })();

  const manualHint = s.status_manual ? `<span class="badge rounded-pill text-bg-warning ms-2">• Manual</span>` : '';

  const editor = `
    <div class="mt-2 ${editMode ? '' : 'd-none'}" data-editor="status">
      <div class="btn-group btn-group-sm w-100" role="group" aria-label="Set status">
        <button class="btn btn-outline-success"   data-set="hecho">Hecho</button>
        <button class="btn btn-outline-warning"   data-set="pendiente">Pendiente</button>
        <button class="btn btn-outline-secondary" data-set="proceso">En proceso</button>
        <button class="btn btn-outline-info"      data-set="siguiente">Siguiente</button>
        <button class="btn btn-outline-dark"      data-set="auto" title="Quitar override">Auto</button>
      </div>
      <small class="text-muted d-block mt-1">Este cambio solo afecta el color/estado visible de la sucursal.</small>
    </div>
  `;

  m.bindPopup(`
    <b>${s.sucursal}</b>${detBadge}${manualHint}<br>
    ${linea}
    <hr style="margin:.5rem 0"/>
    <div>Total disp.: <b>${s.total}</b> (CCTV: ${s.cctv} · Alarma: ${s.alarma})</div>
    <div class="mt-1"><span class="badge bg-secondary">${label}</span></div>
    ${editor}
    <div class="mt-2">
      <a class="btn btn-sm btn-outline-primary" href="/sisec-ui/views/dispositivos/listar.php?sucursal_id=${encodeURIComponent(s.sucursal_id)}#filtros">Ver dispositivos</a>
    </div>
  `);

  m.on('popupopen', () => {
    const p = m.getPopup()?.getElement();
    if (!p) return;
    const wrap = p.querySelector('[data-editor="status"]');
    if (!wrap) return;
    wrap.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('button[data-set]'); if (!btn) return;
      const newState = btn.getAttribute('data-set'); // hecho|pendiente|proceso|siguiente|auto
      await setManualStatus(s, newState, m);
    });
  });

  markersById.set(String(s.sucursal_id), m);
  cluster.addLayer(m);
}

async function setManualStatus(sucursalRecord, newState, marker){
  if (!CSRF) {
    alert('No se encontró token CSRF. Refresca la página.');
    return;
  }
  try {
    const body = new URLSearchParams();
    body.set('csrf', CSRF);
    body.set('sucursal_id', String(sucursalRecord.sucursal_id));
    body.set('estado', newState); // auto|hecho|pendiente|proceso|siguiente

    const resp = await fetch('/sisec-ui/views/api/sucursales_set_status.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    });

    let data = null;
    try { data = await resp.json(); } catch(_) {}

    if (!resp.ok || !data || !data.ok) {
      const serverMsg = data?.error ? `\nDetalle: ${data.error}` : '';
      throw new Error(`HTTP ${resp.status}${serverMsg}`);
    }

    // Actualiza en memoria
    const local = (sucursales || []).find(x => String(x.sucursal_id) === String(sucursalRecord.sucursal_id));
    if (local){
      local.status_manual = (newState === 'auto') ? null : newState;
    }

    // Repinta
    const rec = local || sucursalRecord;
    const newIcon = buildIconFor(rec);
    marker.setIcon(newIcon);
    marker.options._chip = chipClassForSucursal(rec);

    renderSucursales();
    updateFilterCounters();

  } catch (e) {
    console.error('Error al guardar estado:', e);
    alert('No se pudo guardar el estado.' + (e?.message ? `\n${e.message}` : ''));
  }
}


/* ===== Render principal (chips + búsqueda + estado) ===== */
function renderSucursales() {
  const filtered = scopeSucursales().filter(s => activeChips.has(chipClassForSucursal(s)));

  cluster.clearLayers();
  markersById.clear();
  filtered.forEach(addMarkerFor);

  if (filtered.length){
    const group = L.featureGroup([...markersById.values()]);
    try{ map.fitBounds(group.getBounds(), { padding:[20,20], maxZoom: Math.max(map.getZoom(), 12) }); }catch(_){}
  }
}

/* ===== Chips superiores: clic (exclusivo / multi) ===== */
filtersEl?.addEventListener('click', (e) => {
  const pill = e.target.closest('.mf-pill'); if (!pill) return;
  const type = pill.getAttribute('data-chip');

  if (type === 'all'){
    setActiveChips(['green','orange','yellow','blue']);
    return;
  }

  const multi = e.ctrlKey || e.metaKey || e.shiftKey;

  if (multi){
    if (activeChips.has(type)) activeChips.delete(type); else activeChips.add(type);
    if (activeChips.size === 0){
      setActiveChips(['green','orange','yellow','blue']);
    } else {
      renderSucursales();
      updateFilterCounters();
      syncChipsUI();
    }
    return;
  }

  if (activeChips.size === 1 && activeChips.has(type)){
    setActiveChips(['green','orange','yellow','blue']);
  } else {
    setActiveChips([type]);
  }
});

/* ===== Leyenda clicable ===== */
let legendCtrl = null;

function renderLegendContent(activeSet){
  const isOn = (k) => activeSet.has(k) ? 'active' : '';
  return `
    <div class="legend legend--minimal">
      <div class="legend-chips">
        <button type="button" class="mf-pill mf-green ${isOn('green')}"  data-chip="green"  title="Mantenimiento hecho">
          <span class="det-chip green">Mto. hecho</span>
        </button>
        <button type="button" class="mf-pill mf-orange ${isOn('orange')}" data-chip="orange" title="Mantenimiento pendiente">
          <span class="det-chip orange">Mto. pendiente</span>
        </button>
        <button type="button" class="mf-pill mf-yellow ${isOn('yellow')}" data-chip="yellow" title="Mantenimiento en proceso">
          <span class="det-chip yellow">En proceso</span>
        </button>
        <button type="button" class="mf-pill mf-blue ${isOn('blue')}"   data-chip="blue"   title="Siguiente mantenimiento">
          <span class="det-chip blue">Sig. mantenimiento</span>
        </button>
      </div>
    </div>
  `;
}

function addLegend(){
  if (legendCtrl) legendCtrl.remove();
  legendCtrl = L.control({position:'bottomright'});
  legendCtrl.onAdd = function(){
    const div = L.DomUtil.create('div');
    legendContainer = div; // referencia global para syncChipsUI()
    div.innerHTML = renderLegendContent(activeChips);

    div.addEventListener('click', (ev) => {
      const pill = ev.target.closest('.mf-pill');
      if (!pill) return;
      const k = pill.getAttribute('data-chip');

      if (k === 'all'){
        setActiveChips(['green','orange','yellow','blue']);
        return;
      }

      const multi = ev.ctrlKey || ev.metaKey || ev.shiftKey;

      if (multi){
        if (activeChips.has(k)) activeChips.delete(k); else activeChips.add(k);
        if (activeChips.size === 0){
          setActiveChips(['green','orange','yellow','blue']);
        } else {
          renderSucursales();
          updateFilterCounters();
          syncChipsUI();
        }
        return;
      }

      if (activeChips.size === 1 && activeChips.has(k)){
        setActiveChips(['green','orange','yellow','blue']);
      } else {
        setActiveChips([k]);
      }
    });

    L.DomEvent.disableClickPropagation(div);
    return div;
  };
  legendCtrl.addTo(map);
}

/* ===== Choropleth ===== */
function updateChoroplethStyle(){
  if (!estadosLayer) return;
  const vals = Object.values(metricasPorEstado).map(m => (m[metricSelect.value] ?? m.total ?? 0));
  const max  = Math.max(1, ...vals, 1);
  estadosLayer.setStyle(f => {
    const raw = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
    const key = normalizeKey(raw);
    const rec = metricasPorEstado[key];
    const v   = rec ? (rec[metricSelect.value] ?? rec.total ?? 0) : 0;
    return { color:'#e0e7f1', weight:1, fillColor:colorScale(v,max), fillOpacity:1 };
  });
}

/* ===== Búsqueda ===== */
const index = (sucursales || []).map(s => {
  const k = [s.sucursal||'', s.determinante||'', s.municipio||'', s.ciudad||'', s.estado||''].join(' | ');
  return { id:String(s.sucursal_id), display:s, norm:normalizeText(k) };
});
const normById = new Map(index.map(x => [x.id, x.norm]));

function doSearch(q){
  const qn = normalizeText(q);
  if (!qn) { searchResults.classList.add('d-none'); searchResults.innerHTML=''; return; }
  const results = index.filter(it => it.norm.includes(qn)).slice(0,8);
  searchResults.innerHTML = results.length
    ? results.map(r => {
        const s = r.display;
        const det = s.determinante ? `<span class="match-code">#${s.determinante}</span>` : '';
        return `
          <a class="list-group-item list-group-item-action" data-id="${r.id}">
            <div class="d-flex justify-content-between align-items-center">
              <div><b>${s.sucursal}</b> ${det}</div>
              <small class="text-muted">${s.municipio}, ${s.estado}</small>
            </div>
          </a>
        `;
      }).join('')
    : `<div class="list-group-item small text-muted">Sin resultados</div>`;
  searchResults.classList.remove('d-none');
}

function focusSucursalById(id){
  const marker = markersById.get(String(id));
  if (!marker) {
    const rec = (sucursales || []).find(s => String(s.sucursal_id) === String(id));
    if (rec) {
      estadoActual = rec.estado;
      estadoActualKey = normalizeKey(rec.estado);
      btnVolver.classList.remove('d-none');
      breadcrumbSet();
      renderSucursales();
      setTimeout(() => focusSucursalById(id), 50);
    }
    return;
  }
  cluster.zoomToShowLayer(marker, () => {
    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16));
    marker.openPopup();
    const el = marker.getElement();
    el?.querySelector('.det-chip')?.classList.add('glow');
    setTimeout(() => el?.querySelector('.det-chip')?.classList.remove('glow'), 2000);
  });
}

searchInput.addEventListener('input', debounce(e => {
  const val = typeof e === 'string' ? e : e.target.value;
  currentQuery = val || '';
  doSearch(val);
  renderSucursales();
  updateBarChartWithQuery(val);
  updateFilterCounters();
}, 150));

clearSearch.addEventListener('click', () => {
  currentQuery = '';
  searchInput.value = '';
  searchResults.classList.add('d-none');
  renderSucursales();
  updateBarChartWithQuery('');
  updateFilterCounters();
});

document.addEventListener('click', (e) => {
  if (!searchResults.contains(e.target) && e.target !== searchInput) searchResults.classList.add('d-none');
});

searchResults.addEventListener('click', (e) => {
  const item = e.target.closest('.list-group-item'); if (!item) return;
  const id = item.getAttribute('data-id');
  focusSucursalById(id);
  searchResults.classList.add('d-none');
  highlightBarBySucursalId(id);
});

/* ===== Botón Volver ===== */
btnVolver.addEventListener('click', () => {
  if (nivel === 'estado') {
    estadoActual = null; estadoActualKey = null; nivel = 'pais';
    map.setView([23.6345,-102.5528], 5);
    btnVolver.classList.add('d-none');
  }
  breadcrumbSet();
  renderSucursales();
  updateFilterCounters();
});

/* ===== Toggle Modo Edición ===== */
toggleEditBtn?.addEventListener('click', () => {
  editMode = !editMode;
  toggleEditBtn.classList.toggle('btn-outline-warning', !editMode);
  toggleEditBtn.classList.toggle('btn-warning', editMode);
  toggleEditBtn.textContent = editMode ? 'Salir de edición' : 'Editar estados';

  // Reabrir popups abiertos para que muestren/oculten el editor
  cluster.eachLayer(m => {
    if (m.isPopupOpen && m.isPopupOpen()) {
      m.closePopup();
      setTimeout(()=>m.openPopup(), 0);
    }
  });
});

/* ===== Estados / Init ===== */
function renderEstados() {
  if (estadosLayer) map.removeLayer(estadosLayer);
  const mapEl = document.getElementById('map');
  const skel  = document.getElementById('mapSkeleton');

  fetch('/sisec-ui/assets/geo/mexico_estados.geojson')
    .then(r => r.json())
    .then(geo => {
      skel.style.display='none'; mapEl.style.display='block';
      map.setView([23.6345,-102.5528], 5);
      osm.addTo(map);
      map.addLayer(cluster);

      const vals = Object.values(metricasPorEstado).map(m => (m[metricSelect.value] ?? m.total ?? 0));
      const max  = Math.max(1, ...vals, 1);

      estadosLayer = L.geoJSON(geo, {
        style: f => {
          const raw = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
          const key = normalizeKey(raw);
          const rec = metricasPorEstado[key];
          const v   = rec ? (rec[metricSelect.value] ?? rec.total ?? 0) : 0;
          return { color:'#e0e7f1', weight:1, fillColor:colorScale(v,max), fillOpacity:1 };
        },
        onEachFeature: (f, layer) => {
          const raw = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
          const key = normalizeKey(raw);
          const rec = metricasPorEstado[key] || {total:0,cctv:0,alarma:0,label:raw};
          layer.bindTooltip(`<b>${rec.label}</b><br>Total: ${rec.total}<br>CCTV: ${rec.cctv}<br>Alarma: ${rec.alarma}`, {sticky:true});
          layer.on('click', () => {
            estadoActual = rec.label;
            estadoActualKey = key;
            nivel = 'estado';
            breadcrumbSet();
            map.fitBounds(layer.getBounds(), { padding:[20,20], maxZoom: 10 });
            btnVolver.classList.remove('d-none');
            renderSucursales();
            updateFilterCounters();
          });
        }
      }).addTo(map);

      addLegend();
      renderSucursales();
      updateFilterCounters();
      initRegionSelect();
      initBarChart();
    })
    .catch(() => {
      document.getElementById('mapSkeleton').textContent = 'No se pudo cargar el mapa';
    });
}

/* ===== Inicialización del mapa ===== */
breadcrumbSet();
(() => {
  const skel = document.getElementById('mapSkeleton');
  const mapEl= document.getElementById('map');
  skel.style.display='block'; mapEl.style.display='none';
  renderEstados();
})();

/* ===== Gráfica ===== */
let barChart = null;
let currentBarItems = [];

function initRegionSelect(){
  const regions = Array.from(new Set((sucursales||[]).map(s => s.estado).filter(Boolean)));
  const order = ['Centro','Norte','Occidente','Oriente','Poniente','Sur'];
  regions.sort((a,b) => {
    const ia = order.indexOf(a), ib = order.indexOf(b);
    if (ia === -1 && ib === -1) return String(a).localeCompare(String(b));
    if (ia === -1) return 1;
    if (ib === -1) return -1;
    return ia - ib;
  });
  barRegionSelect.innerHTML = regions.map(r => `<option value="${normalizeKey(r)}">${String(r).toUpperCase()}</option>`).join('');
  const def = regions.map(normalizeKey).includes(normalizeKey('Centro')) ? normalizeKey('Centro') : (regions[0] ? normalizeKey(regions[0]) : '');
  barRegionSelect.value = localStorage.getItem('barRegionSelectValue') || def;
  barRegionSelect.addEventListener('change', () => {
    localStorage.setItem('barRegionSelectValue', barRegionSelect.value);
    updateBarChartByRegion();
  });
}

function buildBarSource(list){
  let base = Array.from(list || (sucursales||[]));
  const regKey = (barRegionSelect?.value || '').trim();
  if (regKey) base = base.filter(s => normalizeKey(s.estado) === regKey);
  return base.map(s => ({
    id: String(s.sucursal_id),
    label: `${s.sucursal}${s.determinante ? ' (#'+s.determinante+')' : ''}`,
    total: Number(s.total || 0),
    cctv: Number(s.cctv || 0),
    alarma: Number(s.alarma || 0),
  }));
}

function sortItems(items, by){
  const k = (by==='cctv' || by==='alarma') ? by : 'total';
  return items.sort((a,b) => b[k] - a[k]);
}

function sliceTop(items, n){
  const top = Math.max(5, Math.min(200, Number(n)||20));
  return items.slice(0, top);
}

function initBarChart(){
  const items = sliceTop(sortItems(buildBarSource(), barSortBy.value), barTopN.value);
  currentBarItems = items;

  const data = {
    labels: items.map(i => i.label),
    datasets: [
      { label:'CCTV',   data: items.map(i=>i.cctv),   backgroundColor:'#01a806', stack:'total' },
      { label:'Alarma', data: items.map(i=>i.alarma), backgroundColor:'#f1c40f', stack:'total' }
    ]
  };
  const options = {
    responsive:true, maintainAspectRatio:false,
    onClick:(evt,els)=>{
      if(!els.length) return;
      const idx = els[0].index, it = currentBarItems[idx];
      if (!it) return;
      window.location.href = `/sisec-ui/views/dispositivos/listar.php?sucursal_id=${encodeURIComponent(it.id)}#filtros`;
    },
    plugins:{
      tooltip:{ callbacks:{ footer:(tt)=>{
        const idx = tt[0].dataIndex, it = currentBarItems[idx];
        return `Total: ${(it?.cctv||0) + (it?.alarma||0)}`;
      }}},
      legend:{ display:true }
    },
    scales:{
      x:{ stacked:true, ticks:{ autoSkip:false, maxRotation:45, minRotation:0 } },
      y:{ stacked:true, beginAtZero:true, precision:0 }
    }
  };
  const ctx = document.getElementById('sucursalesBarChart').getContext('2d');
  if (barChart) barChart.destroy();
  barChart = new Chart(ctx, { type:'bar', data, options });
}

function updateBarChart(items){
  if (!barChart) return;
  currentBarItems = items;
  barChart.data.labels = items.map(i=>i.label);
  barChart.data.datasets[0].data = items.map(i=>i.cctv);
  barChart.data.datasets[1].data = items.map(i=>i.alarma);
  barChart.update();
}

barSortBy.addEventListener('change', () => {
  const all = buildBarSource();
  updateBarChart(sliceTop(sortItems(all, barSortBy.value), barTopN.value));
});
barTopN.addEventListener('change', () => {
  const all = sortItems(buildBarSource(), barSortBy.value);
  updateBarChart(sliceTop(all, barTopN.value));
});

function updateBarChartByRegion(){
  const all = sortItems(buildBarSource(), barSortBy.value);
  updateBarChart(sliceTop(all, barTopN.value));
}

function updateBarChartWithQuery(q){
  const qn = normalizeText(q);
  let base = buildBarSource();
  if (qn) base = base.filter(it => normalizeText(it.label).includes(qn));
  const sorted = sortItems(base, barSortBy.value);
  updateBarChart(sliceTop(sorted, barTopN.value));
}

function highlightBarBySucursalId(id){
  const idx = currentBarItems.findIndex(i => i.id === String(id));
  if (idx < 0 || !barChart) return;
  const active = [{datasetIndex:0, index:idx}, {datasetIndex:1, index:idx}];
  barChart.setActiveElements(active);
  barChart.tooltip.setActiveElements(active, {x:0,y:0});
  barChart.update();
}

/* ===== Toggles ===== */
function applyChartToggleUI(){
  const hidden = localStorage.getItem('chartHidden') === '1';
  chartSection.style.display = hidden ? 'none' : 'block';
  toggleChartBtn.textContent = hidden ? 'Mostrar gráfica' : 'Ocultar gráfica';
  if (!hidden) { try { barChart?.resize(); } catch(e){} }
}
function applyMapToggleUI(){
  const hidden = localStorage.getItem('mapHidden') === '1';
  mapSection.style.display = hidden ? 'none' : 'block';
  toggleMapBtn.textContent = hidden ? 'Mostrar mapa' : 'Ocultar mapa';
  if (!hidden) setTimeout(() => { try { map.invalidateSize(); } catch(e){} }, 0);
}

toggleChartBtn.addEventListener('click', () => {
  const willHide = chartSection.style.display !== 'none';
  localStorage.setItem('chartHidden', willHide ? '1' : '0');
  applyChartToggleUI();
});
toggleMapBtn.addEventListener('click', () => {
  const willHide = mapSection.style.display !== 'none';
  localStorage.setItem('mapHidden', willHide ? '1' : '0');
  applyMapToggleUI();
});

/* ===== Inits finales ===== */
document.addEventListener('DOMContentLoaded', () => {
  updateFilterCounters();
});
applyChartToggleUI();
applyMapToggleUI();
</script>



<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
