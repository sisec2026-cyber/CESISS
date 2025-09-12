<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin', 'Prevencion','Administrador','Técnico', 'Distrital','Mantenimientos']);
include __DIR__ . '/../../includes/db.php';
ob_start();

/* ======== Config ======== */
$collation    = 'utf8mb4_general_ci'; // En MySQL 8: 'utf8mb4_0900_ai_ci'
$estadoMto    = 'En mantenimiento';

/* ======== KPIs ======== */
$camaras = $conn->query("
  SELECT COUNT(*) AS total
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  WHERE e.nom_equipo COLLATE $collation LIKE 'camara%'
")->fetch_assoc()['total'] ?? 0;

$sensores = $conn->query("
  SELECT COUNT(*) AS total
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  WHERE e.nom_equipo COLLATE $collation LIKE 'sensor%'
")->fetch_assoc()['total'] ?? 0;

$usuarios = $conn->query("SELECT COUNT(*) AS total FROM usuarios")->fetch_assoc()['total'] ?? 0;

$mantenimiento = $conn->query("
  SELECT COUNT(*) AS total
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  INNER JOIN status s  ON d.estado = s.id
  WHERE e.nom_equipo COLLATE $collation LIKE 'camara%'
    AND s.status_equipo = '$estadoMto'
")->fetch_assoc()['total'] ?? 0;

/* ======== Prefijos por categoría (CCTV/Alarma) ======== */
$prefijosCCTV = ["racks%", "camara%", "dvr%", "nvr%", "servidor%", "monitor%", "biometrico%", "videoportero%", "videotelefono%", "ups%"];
$prefijosAlarma = ["sensor%", "dh%", "pir%", "cm%", "oh%", "estrobo%", "rep%", "drc%", "estacion%", "teclado%", "sirena%", "boton%"];

/* Helper para armar ORs de LIKE seguros */
function build_likes($col, $collation, $prefixes) {
  $likes = [];
  foreach ($prefixes as $p) {
    $p = str_replace("'", "''", $p);
    $likes[] = "$col COLLATE $collation LIKE '$p'";
  }
  return '(' . implode(' OR ', $likes) . ')';
}
$whereCCTV = build_likes('e.nom_equipo', $collation, $prefijosCCTV);
$whereAlarma = build_likes('e.nom_equipo', $collation, $prefijosAlarma);
$caseCCTV   = "CASE WHEN $whereCCTV THEN 1 ELSE 0 END";
$caseALARMA = "CASE WHEN $whereAlarma THEN 1 ELSE 0 END";

/* ======== Normalizador (acentos fuera, lower) ======== */
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

/* ======== Métricas por estado (coropleta) ======== */
$sqlEstados = "
  SELECT r.nom_region AS estado,
         SUM($caseCCTV)   AS cctv,
         SUM($caseALARMA) AS alarma
  FROM sucursales s
  INNER JOIN municipios m  ON s.municipio_id = m.id
  INNER JOIN ciudades c    ON m.ciudad_id    = c.id
  INNER JOIN regiones r    ON c.region_id    = r.id
  LEFT JOIN dispositivos d ON d.sucursal = s.id
  LEFT JOIN equipos e      ON d.equipo   = e.id
  GROUP BY r.nom_region
";
$porEstado = [];
$resEstados = $conn->query($sqlEstados);
while ($r = $resEstados->fetch_assoc()) {
  $key = normalize_key_es($r['estado']);
  $cctv   = (int)($r['cctv'] ?? 0);
  $alarma = (int)($r['alarma'] ?? 0);
  $porEstado[$key] = [
    'cctv'   => $cctv,
    'alarma' => $alarma,
    'total'  => $cctv + $alarma,
    'label'  => $r['estado'],
  ];
}

/* ======== Sucursales con lat/lng (marcadores) ======== */
$sqlSucursales = "
  SELECT 
    r.nom_region    AS estado,
    c.nom_ciudad    AS ciudad,
    m.nom_municipio AS municipio,
    s.id            AS sucursal_id,
    s.nom_sucursal  AS sucursal,
    dtr.nom_determinante AS determinante,
    s.lat, s.lng,
    SUM($caseCCTV)   AS cctv,
    SUM($caseALARMA) AS alarma,
    COUNT(disp.id)   AS disp_count
  FROM sucursales s
  INNER JOIN municipios m      ON s.municipio_id = m.id
  INNER JOIN ciudades c        ON m.ciudad_id    = c.id
  INNER JOIN regiones r        ON c.region_id    = r.id
  LEFT JOIN determinantes dtr  ON dtr.sucursal_id = s.id
  LEFT JOIN dispositivos disp  ON disp.sucursal   = s.id
  LEFT JOIN equipos e          ON disp.equipo     = e.id
  GROUP BY r.nom_region, c.nom_ciudad, m.nom_municipio, 
           s.id, s.nom_sucursal, dtr.nom_determinante, s.lat, s.lng
";
$sucursales = [];
$resSuc = $conn->query($sqlSucursales);
while ($r = $resSuc->fetch_assoc()) {
  $r['cctv']       = (int)($r['cctv'] ?? 0);
  $r['alarma']     = (int)($r['alarma'] ?? 0);
  $r['total']      = $r['cctv'] + $r['alarma'];
  $r['disp_count'] = (int)($r['disp_count'] ?? 0);
  $sucursales[] = $r;
}
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

<!-- ===== TARJETA: Gráfica de barras por sucursal ===== -->
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

<!-- ===== MAPA INTERACTIVO MÉXICO ===== -->
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
      </div>
    </div>

    <div id="mapSection">
      <!-- Skeleton de carga -->
      <div id="mapSkeleton" class="mt-3 w-100" style="height:520px;border-radius:10px;background:linear-gradient(90deg,#f3f6fb 25%,#e9eef6 37%,#f3f6fb 63%);background-size:400% 100%;animation:shimmer 1.4s infinite;"></div>
      <div id="map" class="mt-3" style="height: 520px; border-radius: 10px; overflow: hidden; display:none;"></div>
      <small class="text-muted d-block mt-2">Tip: haz clic en un estado para hacer zoom; los marcadores se agrupan automáticamente.</small>
    </div>
  </div>
</div>

<!-- Botones rápidos -->
<div class="d-flex justify-content-center gap-3">
  <div class="col-md-3">
    <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador', 'Mantenimientos','Capturista','Técnico'])): ?>
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

<!-- Estilos -->
<style>
  /* Tarjetas y badges */
  .card { border: 1px solid #eef2f7; }
  .card .card-title { font-weight: 700; letter-spacing: .2px; }
  .text-bg-light { background: #f5f7fb !important; }
  .badge.text-bg-light { background:#eef3fb !important; color:#334 !important; border:1px solid #dae4f5; }

  /* Leaflet (tipografías y controles) */
  .leaflet-container {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
  }
  .leaflet-control-layers,
  .legend {
    background:#fff;
    border-radius:8px;
    box-shadow:0 6px 16px rgba(0,0,0,.12);
    padding:.5rem .75rem;
    border:1px solid #e6eef5;
  }

  /* Buscador */
  #searchResults .list-group-item { cursor: pointer; }
  #searchResults .list-group-item.active { background:#e9f2ff; }
  #searchResults .match-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 12px;
  }

  /* Chips determinante */
  .det-icon { background: transparent; border: none; cursor: pointer; }
  .det-chip {
    display:flex; align-items:center; justify-content:center;
    height:28px; padding:0 14px; min-width:72px;
    border-radius:999px; background:#fff; border:1px solid #d5e1f0;
    box-shadow:0 6px 14px rgba(0,0,0,.15);
    font:600 13px/1 system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Helvetica Neue";
    text-align:center; white-space:nowrap; user-select:none;
  }
  .det-chip.glow {
    box-shadow:0 0 0 3px rgba(56,132,255,.25), 0 6px 14px rgba(0,0,0,.18) !important;
  }
  .det-chip.green  { background:#01a806; border-color:#01a806; color:#fff; } /* CCTV */
  .det-chip.yellow { background:#f1c40f; border-color:#f1c40f; color:#000; } /* Alarma */
  .det-chip.blue   { background:#2980b9; border-color:#2980b9; color:#fff; } /* Ambos */
  .det-chip.red    { background:#e74c3c; border-color:#e74c3c; color:#fff; } /* Pendiente */
  .det-chip.pending { border-style: dashed !important; border-width: 2px; }

  /* Leyenda del mapa */
  .legend .legend-title { display: none !important; }
  .legend { font-size:13px; }
  .legend-chips { display:flex; flex-wrap:wrap; gap:6px; }

  /* Skeleton shimmer */
  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

  /* Contenedor fijo para la gráfica (Chart.js ajusta al 100%) */
  .bar-chart-container{
    position: relative;
    height: 360px;  /* desktop */
    width: 100%;
    overflow: hidden;
  }
  .bar-chart-container canvas{
    width: 100% !important;
    height: 100% !important;
    display: block;
  }

  /* Responsive */
  @media (max-width: 768px){
    #map, #mapSkeleton { height: 420px !important; }
    .card .card-title { font-size: .95rem; }
    .bar-chart-container{ height: 260px; }
  }
</style>

<!-- Leaflet & plugins -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<!-- Chart.js para la gráfica de barras -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
// ===== Datos inyectados desde PHP =====
const metricasPorEstadoRaw = <?php
  echo json_encode($porEstado ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>;
const sucursales = <?php
  echo json_encode($sucursales ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>;

// ===== Normalización de llaves =====
const normalizeKey = s => (s||'').toString()
  .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
  .replace(/[^A-Za-z0-9\s]/g,'').trim().replace(/\s+/g,' ').toLowerCase();

// Reconstruye un objeto con llaves normalizadas
const metricasPorEstado = {};
for (const [k,v] of Object.entries(metricasPorEstadoRaw)) {
  metricasPorEstado[normalizeKey(k)] = v;
}

// ===== UI comunes =====
const breadcrumbEl   = document.getElementById('breadcrumb');
const btnVolver      = document.getElementById('btnVolver');
const metricSelect   = document.getElementById('metricSelect');
const searchInput    = document.getElementById('searchInput');
const clearSearch    = document.getElementById('clearSearch');
const searchResults  = document.getElementById('searchResults');

const barRegionSelect= document.getElementById('barRegionSelect');
const barSortBy      = document.getElementById('barSortBy');
const barTopN        = document.getElementById('barTopN');
const toggleChartBtn = document.getElementById('toggleChart');
const toggleMapBtn   = document.getElementById('toggleMap');
const chartSection   = document.getElementById('chartSection');
const mapSection     = document.getElementById('mapSection');

// Persistencia de métrica (mapa)
const savedMetric = localStorage.getItem('metricSelectValue');
if (savedMetric && ['total','cctv','alarma'].includes(savedMetric)) {
  metricSelect.value = savedMetric;
}
metricSelect.addEventListener('change', () => {
  localStorage.setItem('metricSelectValue', metricSelect.value);
});

// ===== Estado de navegación (mapa) =====
let nivel = "pais";           // pais -> estado
let estadoActual = null;      // etiqueta visible
let estadoActualKey = null;   // clave normalizada

// ===== Límites de México =====
const mexicoBounds = L.latLngBounds([14.0, -119.0], [33.5, -86.0]);

// ===== Mapa =====
const map = L.map('map', {
  preferCanvas: true,
  maxBounds: mexicoBounds,
  maxBoundsViscosity: 1.0,
  worldCopyJump: false,
  inertia: false,
  minZoom: 4,
  maxZoom: 18
});

const osm = L.tileLayer(
  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  { maxZoom: 18, attribution: '&copy; OpenStreetMap', noWrap: true, bounds: mexicoBounds }
);

const cluster = L.markerClusterGroup({ disableClusteringAtZoom: 13, maxClusterRadius: 55 });
let estadosLayer = null;

function colorScale(v, max){ const t = max? v/max : 0; const a = 0.12 + 0.68*t; return `rgba(30,102,197,${a})`; }
function breadcrumbSet() {
  const parts = ['México'];
  if (estadoActual) parts.push(estadoActual);
  breadcrumbEl.textContent = parts.join(' > ');
}
function normalizeText(str){
  return (str || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
}

// Decide color del chip por conteos
function getDetColor(s) {
  if ((s.disp_count || 0) === 0) return 'red';   // Pendiente
  const hasCCTV   = (s.cctv   || 0) > 0;
  const hasAlarma = (s.alarma || 0) > 0;
  if (hasCCTV && hasAlarma) return 'blue';
  if (hasCCTV) return 'green';
  if (hasAlarma) return 'yellow';
  return 'blue';
}

// Leyenda categorías
let legendCtrl = null;
function addLegend(){
  if (legendCtrl) { legendCtrl.remove(); legendCtrl = null; }
  legendCtrl = L.control({position:'bottomright'});
  legendCtrl.onAdd = function(){
    const div = L.DomUtil.create('div','legend legend--minimal');
    div.innerHTML = `
      <div class="legend-chips">
        <span class="det-chip green">CCTV</span>
        <span class="det-chip yellow">Alarma</span>
        <span class="det-chip blue">Ambos</span>
        <span class="det-chip red pending">Pendiente</span>
      </div>
    `;
    return div;
  };
  legendCtrl.addTo(map);
}

// Re-estiliza choropleth (mapa)
function updateChoroplethStyle(){
  if (!estadosLayer) return;
  const allVals = Object.values(metricasPorEstado).map(m => (m[metricSelect.value] ?? m.total ?? 0));
  const max = Math.max(1, ...allVals, 1);
  estadosLayer.setStyle(f => {
    const nombreRaw = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
    const key = normalizeKey(nombreRaw);
    const registro = metricasPorEstado[key];
    const v = registro ? (registro[metricSelect.value] ?? registro.total ?? 0) : 0;
    return { color:'#e0e7f1', weight:1, fillColor:colorScale(v, max), fillOpacity:1 };
  });
}

// Debounce utilitario
function debounce(fn, ms=200){ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), ms); }; }
const onSearchInput = debounce((val)=>{ doSearch(val); filtrarSucursales(val); updateBarChartWithQuery(val); }, 150);

// ====== Mapa: Estados (coropleta) ======
function renderEstados() {
  if (estadosLayer) map.removeLayer(estadosLayer);
  const mapEl = document.getElementById('map');
  const skel = document.getElementById('mapSkeleton');

  fetch('/sisec-ui/assets/geo/mexico_estados.geojson')
    .then(r => r.json())
    .then(geo => {
      skel.style.display='none'; mapEl.style.display='block';
      map.setView([23.6345, -102.5528], 5);
      osm.addTo(map);
      map.addLayer(cluster);

      const allVals = Object.values(metricasPorEstado).map(m => (m[metricSelect.value] ?? m.total ?? 0));
      const max = Math.max(1, ...allVals, 1);

      estadosLayer = L.geoJSON(geo, {
        style: f => {
          const nombreRaw = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
          const key = normalizeKey(nombreRaw);
          const registro = metricasPorEstado[key];
          const v = registro ? (registro[metricSelect.value] ?? registro.total ?? 0) : 0;
          return { color:'#e0e7f1', weight:1, fillColor:colorScale(v, max), fillOpacity:1 };
        },
        onEachFeature: (f, layer) => {
          const nombreRaw = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
          const key = normalizeKey(nombreRaw);
          const reg = metricasPorEstado[key] || {total:0, cctv:0, alarma:0, label:nombreRaw};
          layer.bindTooltip(`<b>${reg.label}</b><br>Total: ${reg.total}<br>CCTV: ${reg.cctv}<br>Alarma: ${reg.alarma}`, { sticky:true });
          layer.on('click', () => {
            estadoActual = reg.label;
            estadoActualKey = key;
            nivel = 'estado';
            breadcrumbSet();
            map.fitBounds(layer.getBounds(), { padding:[20,20], maxZoom: 10 });
            btnVolver.classList.remove('d-none');
            renderSucursales();
            // La gráfica sigue filtrada por Región (select), no por estado del mapa
            // Si quieres que también se acote por el estado clicado, házmelo saber.
          });
        }
      }).addTo(map);

      addLegend();
      renderSucursales();

      // Inicializa región + gráfica (CENTRO por defecto si existe)
      initRegionSelect();
      initBarChart();
    })
    .catch(()=>{
      const skel = document.getElementById('mapSkeleton');
      skel.textContent = 'No se pudo cargar el mapa';
    });
}

// ====== Marcadores y buscador ======
const markersById = new Map(); // sucursal_id -> marker

function buildIconFor(s) {
  const det = (s.determinante && String(s.determinante).trim() !== '') ? String(s.determinante).trim() : '—';
  const colorClass = getDetColor(s);
  const pendingClass = ((s.disp_count || 0) === 0) ? 'pending' : '';
  return L.divIcon({
    className: 'det-icon',
    html: `<div class="det-chip ${colorClass} ${pendingClass}">${det}</div>`,
    iconSize: [64, 28],
    iconAnchor: [32, 14],
    popupAnchor: [0, -14]
  });
}

function addMarkerFor(s) {
  if (s.lat == null || s.lng == null) return;
  const icon = buildIconFor(s);
  const m = L.marker([s.lat, s.lng], { icon });

  const detBadge = s.determinante ? ` <span class="badge bg-light text-dark ms-1 match-code">#${s.determinante}</span>` : '';
  const lineaEstado = `<small>${s.municipio}, ${s.ciudad} · ${s.estado}</small>`;

  const bloqueDatos = (s.disp_count || 0) === 0
    ? `<div class="mt-1"><span class="badge bg-danger">Pendiente por capturar</span></div>`
    : `
      <div>Total: <b>${s.total}</b></div>
      <div>CCTV: ${s.cctv} · Alarma: ${s.alarma}</div>
    `;

  m.bindPopup(`
    <b>${s.sucursal}</b>${detBadge}<br>
    ${lineaEstado}
    <hr style="margin:.5rem 0" />
    ${bloqueDatos}
    <div class="mt-2">
      <a class="btn btn-sm btn-outline-primary" href="/sisec-ui/views/dispositivos/listar.php?sucursal_id=${encodeURIComponent(s.sucursal_id)}#filtros">Ver dispositivos</a>
    </div>
  `);
  markersById.set(String(s.sucursal_id), m);
  cluster.addLayer(m);
}

function renderSucursales() {
  cluster.clearLayers();
  markersById.clear();
  const base = sucursales
    .filter(s => s.lat != null && s.lng != null)
    .filter(s => !estadoActualKey || normalizeKey(s.estado) === estadoActualKey);
  base.forEach(addMarkerFor);
}

/* ====== Índice & búsqueda ====== */
const searchIndex = sucursales.map(s => {
  const key = [s.sucursal||'', s.determinante||'', s.municipio||'', s.ciudad||'', s.estado||''].join(' | ');
  return { id: String(s.sucursal_id), display: s, norm: normalizeText(key) };
});
const normById = new Map(searchIndex.map(it => [it.id, it.norm]));

function filtrarSucursales(q, options = { global: true }) {
  const qn = normalizeText(q);

  let base = sucursales.filter(s => s.lat != null && s.lng != null);
  if (estadoActualKey) base = base.filter(s => normalizeKey(s.estado) === estadoActualKey);

  if (!qn) {
    cluster.clearLayers(); markersById.clear();
    base.forEach(addMarkerFor);
    return;
  }

  const matches = base.filter(s => (normById.get(String(s.sucursal_id)) || '').includes(qn));

  if (!matches.length && options.global && estadoActualKey) {
    const all = sucursales.filter(s => s.lat != null && s.lng != null);
    const fallback = all.filter(s => (normById.get(String(s.sucursal_id)) || '').includes(qn));
    if (fallback.length) {
      estadoActual = null; estadoActualKey = null; breadcrumbSet(); btnVolver.classList.add('d-none');
      return filtrarSucursales(q, { global: false });
    }
  }

  cluster.clearLayers(); markersById.clear();
  matches.forEach(addMarkerFor);

  if (matches.length) {
    const group = L.featureGroup([...markersById.values()]);
    map.fitBounds(group.getBounds(), { padding:[20,20], maxZoom: 12 });
  }
}

function doSearch(q){
  const qn = normalizeText(q);
  if (!qn) { searchResults.classList.add('d-none'); searchResults.innerHTML=''; return; }
  const results = searchIndex.filter(it => it.norm.includes(qn)).slice(0, 8);
  if (!results.length) {
    searchResults.innerHTML = `<div class="list-group-item small text-muted">Sin resultados</div>`;
    searchResults.classList.remove('d-none');
    return;
  }
  searchResults.innerHTML = results.map(r => {
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
  }).join('');
  searchResults.classList.remove('d-none');
}

searchInput.addEventListener('input', (e) => onSearchInput(e.target.value));
clearSearch.addEventListener('click', () => {
  searchInput.value = '';
  searchResults.classList.add('d-none');
  filtrarSucursales('');
  updateBarChartWithQuery('');
});
document.addEventListener('click', (e) => {
  if (!searchResults.contains(e.target) && e.target !== searchInput) {
    searchResults.classList.add('d-none');
  }
});
searchResults.addEventListener('click', (e) => {
  const item = e.target.closest('.list-group-item'); if (!item) return;
  const id = item.getAttribute('data-id');
  focusSucursalById(id);
  searchResults.classList.add('d-none');
  highlightBarBySucursalId(id);
});
let activeIndex = -1;
searchInput.addEventListener('keydown', (e) => {
  const items = [...searchResults.querySelectorAll('.list-group-item')];
  if (e.key === 'Enter') {
    if (items.length) {
      e.preventDefault();
      items[activeIndex>=0?activeIndex:0].click();
    }
    return;
  }
  if (!items.length) return;
  if (e.key === 'ArrowDown'){ e.preventDefault(); activeIndex = (activeIndex+1)%items.length; }
  else if (e.key === 'ArrowUp'){ e.preventDefault(); activeIndex = (activeIndex-1+items.length)%items.length; }
  items.forEach((el,i)=> el.classList.toggle('active', i===activeIndex));
});

function focusSucursalById(id){
  const marker = markersById.get(String(id));
  if (!marker) {
    const rec = sucursales.find(s => String(s.sucursal_id) === String(id));
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
    if (el) {
      const chip = el.querySelector('.det-chip');
      chip?.classList.add('glow');
      setTimeout(() => chip?.classList.remove('glow'), 2000);
    }
  });
}

btnVolver.addEventListener('click', () => {
  if (nivel === 'estado') {
    estadoActual = null;
    estadoActualKey = null;
    nivel = 'pais';
    map.setView([23.6345, -102.5528], 5);
    btnVolver.classList.add('d-none');
  }
  breadcrumbSet();
  renderSucursales();
});

// ====== GRÁFICA DE BARRAS (Chart.js) ======
let barChart = null;
let currentBarItems = []; // { id, label, total, cctv, alarma }

function initRegionSelect() {
  // Únicas desde los datos ya inyectados
  let regions = Array.from(new Set(sucursales.map(s => s.estado).filter(Boolean)));

  // Orden sugerido si existen
  const order = ['Centro','Norte','Occidente','Oriente','Poniente','Sur'];
  regions.sort((a,b) => {
    const ia = order.indexOf(a), ib = order.indexOf(b);
    if (ia === -1 && ib === -1) return a.localeCompare(b);
    if (ia === -1) return 1;
    if (ib === -1) return -1;
    return ia - ib;
  });

  // Rellena opciones
  barRegionSelect.innerHTML = regions.map(r =>
    `<option value="${normalizeKey(r)}">${(r||'').toString().toUpperCase()}</option>`
  ).join('');

  // Default: CENTRO si existe; si no, la primera
  const defaultKey = regions.map(normalizeKey).includes(normalizeKey('Centro'))
    ? normalizeKey('Centro')
    : (regions[0] ? normalizeKey(regions[0]) : '');

  const saved = localStorage.getItem('barRegionSelectValue');
  barRegionSelect.value = saved || defaultKey;

  barRegionSelect.addEventListener('change', () => {
    localStorage.setItem('barRegionSelectValue', barRegionSelect.value);
    updateBarChartByRegion();
  });
}

function buildBarSource(list){
  let base = Array.from(list || sucursales);

  // Filtro por REGIÓN (select)
  const regKey = (barRegionSelect?.value || '').trim();
  if (regKey) base = base.filter(s => normalizeKey(s.estado) === regKey);

  // (Si quisieras además acotar por estado del mapa, podríamos añadir aquí una condición extra)

  return base.map(s => ({
    id: String(s.sucursal_id),
    label: `${s.sucursal}${s.determinante ? ' (#'+s.determinante+')':''}`,
    total: Number(s.total || 0),
    cctv: Number(s.cctv || 0),
    alarma: Number(s.alarma || 0),
  }));
}

function sortItems(items, sortBy){
  const key = (sortBy==='cctv'||sortBy==='alarma') ? sortBy : 'total';
  return items.sort((a,b)=> b[key]-a[key]);
}

function sliceTop(items, n){
  const top = Math.max(5, Math.min(200, Number(n)||20));
  return items.slice(0, top);
}

function initBarChart(){
  const items = sliceTop(sortItems(buildBarSource(), barSortBy.value), barTopN.value);
  currentBarItems = items;

  const data = {
    labels: items.map(i=>i.label),
    datasets: [
      {
        label: 'CCTV',
        data: items.map(i=>i.cctv),
        backgroundColor: '#01a806',     // verde CCTV
        stack: 'total'
      },
      {
        label: 'Alarma',
        data: items.map(i=>i.alarma),
        backgroundColor: '#f1c40f',     // amarillo Alarma
        stack: 'total'
      }
    ]
  };

  const options = {
    responsive: true,
    maintainAspectRatio: false, // respeta la altura del contenedor
    onClick: (evt, elements) => {
      if (!elements.length) return;
      const idx = elements[0].index;
      const item = currentBarItems[idx];
      if (!item) return;
      window.location.href = `/sisec-ui/views/dispositivos/listar.php?sucursal_id=${encodeURIComponent(item.id)}#filtros`;
    },
    plugins: {
      tooltip: {
        callbacks: {
          footer: (ttItems) => {
            const idx = ttItems[0].dataIndex;
            const it = currentBarItems[idx];
            const total = (it?.cctv || 0) + (it?.alarma || 0);
            return `Total: ${total}`;
          }
        }
      },
      legend: { display: true }
    },
    scales: {
      x: {
        stacked: true,
        ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 }
      },
      y: {
        stacked: true,
        beginAtZero: true,
        precision: 0
      }
    }
  };

  if (barChart) { barChart.destroy(); }
  const ctx = document.getElementById('sucursalesBarChart').getContext('2d');
  barChart = new Chart(ctx, { type: 'bar', data, options });
}

function updateBarChart(items){
  if (!barChart) return;
  currentBarItems = items;
  barChart.data.labels = items.map(i=>i.label);
  barChart.data.datasets[0].data = items.map(i=>i.cctv);
  barChart.data.datasets[1].data = items.map(i=>i.alarma);
  barChart.update();
}

// Cambios de orden y TopN
barSortBy.addEventListener('change', () => {
  const all = buildBarSource();
  const sorted = sortItems(all, barSortBy.value);
  const top = sliceTop(sorted, barTopN.value);
  updateBarChart(top);
});

barTopN.addEventListener('change', () => {
  const all = sortItems(buildBarSource(), barSortBy.value);
  const top = sliceTop(all, barTopN.value);
  updateBarChart(top);
});

// Filtrar gráfica por región
function updateBarChartByRegion(){
  const itemsAll = buildBarSource();
  const sorted = sortItems(itemsAll, barSortBy.value);
  const top = sliceTop(sorted, barTopN.value);
  updateBarChart(top);
}

// Filtrado por buscador compartido
function updateBarChartWithQuery(q){
  const qn = normalizeText(q);
  let base = buildBarSource();
  if (qn) base = base.filter(it => normalizeText(it.label).includes(qn));
  const sorted = sortItems(base, barSortBy.value);
  const top = sliceTop(sorted, barTopN.value);
  updateBarChart(top);
}

// Resaltar barra desde el buscador
function highlightBarBySucursalId(id){
  const idx = currentBarItems.findIndex(i => i.id === String(id));
  if (idx < 0 || !barChart) return;
  const active = [{datasetIndex:0, index: idx}, {datasetIndex:1, index: idx}];
  barChart.setActiveElements(active);
  barChart.tooltip.setActiveElements(active, {x:0,y:0});
  barChart.update();
}

// ===== Toggles de visibilidad (persistentes) =====
function applyChartToggleUI() {
  const hidden = localStorage.getItem('chartHidden') === '1';
  chartSection.style.display = hidden ? 'none' : 'block';
  toggleChartBtn.textContent = hidden ? 'Mostrar gráfica' : 'Ocultar gráfica';
  if (!hidden) {
    // Ajusta tamaños al volver a mostrar
    try { barChart?.resize(); } catch(e){}
  }
}
function applyMapToggleUI() {
  const hidden = localStorage.getItem('mapHidden') === '1';
  mapSection.style.display = hidden ? 'none' : 'block';
  toggleMapBtn.textContent = hidden ? 'Mostrar mapa' : 'Ocultar mapa';
  if (!hidden) {
    // Recalcula mapa al volver a mostrarse
    setTimeout(() => { try { map.invalidateSize(); } catch(e){} }, 0);
  }
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

// ===== Inicializa mapa + gráfica =====
breadcrumbSet();
renderEstados();

// Estado inicial de toggles
applyChartToggleUI();
applyMapToggleUI();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
