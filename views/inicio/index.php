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
$whereCategorias = "($whereCCTV OR $whereAlarma)";
$caseCCTV   = "CASE WHEN $whereCCTV THEN 1 ELSE 0 END";
$caseALARMA = "CASE WHEN $whereAlarma THEN 1 ELSE 0 END";

/* ======== Métricas por estado (para coropleta) ========
   Nota: asumimos que r.nom_region es el nombre de ESTADO.
*/
$sqlEstados = "
  SELECT r.nom_region AS estado,
         SUM($caseCCTV)   AS cctv,
         SUM($caseALARMA) AS alarma
  FROM dispositivos d
  INNER JOIN equipos e     ON d.equipo   = e.id
  INNER JOIN sucursales s  ON d.sucursal = s.id
  INNER JOIN municipios m  ON s.municipio_id = m.id
  INNER JOIN ciudades c    ON m.ciudad_id    = c.id
  INNER JOIN regiones r    ON c.region_id    = r.id
  WHERE $whereCategorias
  GROUP BY r.nom_region
";
$porEstado = [];
$resEstados = $conn->query($sqlEstados);
while ($r = $resEstados->fetch_assoc()) {
  $porEstado[$r['estado']] = [
    'cctv'   => (int)$r['cctv'],
    'alarma' => (int)$r['alarma'],
    'total'  => (int)$r['cctv'] + (int)$r['alarma'],
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
    d.nom_determinante AS determinante,   -- viene de la tabla determinantes
    s.lat, s.lng,
    SUM($caseCCTV)   AS cctv,
    SUM($caseALARMA) AS alarma
  FROM dispositivos disp
  INNER JOIN equipos e       ON disp.equipo   = e.id
  INNER JOIN sucursales s    ON disp.sucursal = s.id
  INNER JOIN municipios m    ON s.municipio_id = m.id
  INNER JOIN ciudades c      ON m.ciudad_id    = c.id
  INNER JOIN regiones r      ON c.region_id    = r.id
  LEFT JOIN determinantes d  ON d.sucursal_id  = s.id   -- JOIN con determinantes
  WHERE $whereCategorias
  GROUP BY r.nom_region, c.nom_ciudad, m.nom_municipio, 
           s.id, s.nom_sucursal, d.nom_determinante, s.lat, s.lng
";


$sucursales = [];
$resSuc = $conn->query($sqlSucursales);
while ($r = $resSuc->fetch_assoc()) {
  // Puedes filtrar si faltan coordenadas:
  // if (is_null($r['lat']) || is_null($r['lng'])) continue;
  $r['cctv']   = (int)$r['cctv'];
  $r['alarma'] = (int)$r['alarma'];
  $r['total']  = $r['cctv'] + $r['alarma'];
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

<!-- ===== MAPA INTERACTIVO MÉXICO ===== -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-3">
        <h6 class="card-title mb-0">Mapa de dispositivos por Estado / Municipio / Sucursal</h6>
        <div class="badge text-bg-light" id="breadcrumb">México</div>
      </div>
        <!-- Buscador -->
  <div class="position-relative">
    <input id="searchInput" class="form-control form-control-sm" type="text" placeholder="Buscar sucursal o determinante...">
    <div id="searchResults" class="list-group position-absolute w-100 d-none" style="z-index: 1000; max-height: 260px; overflow:auto;"></div>
  </div>
      <div class="d-flex align-items-center gap-2">
        <select id="metricSelect" class="form-select form-select-sm">
          <option value="total" selected>Total</option>
          <option value="cctv">CCTV</option>
          <option value="alarma">Alarma</option>
        </select>
        <button id="btnVolver" class="btn btn-sm btn-outline-secondary d-none">← Volver</button>
      </div>
    </div>
    <div id="map" class="mt-3" style="height: 520px; border-radius: 10px; overflow: hidden;"></div>
    <small class="text-muted d-block mt-2">Tip: haz clic en un estado para hacer zoom; los marcadores se agrupan automáticamente.</small>
  </div>
</div>
<div class="d-flex align-items-center gap-2">

<!-- 
  <select id="metricSelect" class="form-select form-select-sm">
    <option value="total" selected>Total</option>
    <option value="cctv">CCTV</option>
    <option value="alarma">Alarma</option>
  </select>
  <button id="btnVolver" class="btn btn-sm btn-outline-secondary d-none">← Volver</button> -->
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

<!-- Estilos extra -->
<style>
.card { border: 1px solid #eef2f7; }
.card .card-title { font-weight: 700; letter-spacing: .2px; }
.text-bg-light { background: #f5f7fb !important; }

.leaflet-container { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
.leaflet-control-layers, .legend {
  background:#fff; border-radius:8px; box-shadow:0 6px 16px rgba(0,0,0,.12);
  padding:.5rem .75rem; border:1px solid #e6eef5;
}
.badge.text-bg-light { background:#eef3fb !important; color:#334 !important; border:1px solid #dae4f5; }
</style>

<style>
#searchResults .list-group-item { cursor: pointer; }
#searchResults .match-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
.marker-kpi.glow { box-shadow: 0 0 0 3px rgba(56,132,255,.25), 0 6px 14px rgba(0,0,0,.18) !important; }
</style>


<!-- Leaflet & plugins -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
// ===== Datos inyectados desde PHP =====
const metricasPorEstado = <?php echo json_encode($porEstado ?? [], JSON_UNESCAPED_UNICODE); ?>;
const sucursales = <?php echo json_encode($sucursales ?? [], JSON_UNESCAPED_UNICODE); ?>;

// ===== UI =====
const breadcrumbEl = document.getElementById('breadcrumb');
const btnVolver = document.getElementById('btnVolver');
const metricSelect = document.getElementById('metricSelect');

const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');

// ===== Estado de navegación =====
let nivel = "pais";    // pais -> estado
let estadoActual = null;

// ===== Mapa =====
const map = L.map('map', { preferCanvas:true }).setView([23.6345, -102.5528], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:18, attribution:'&copy; OpenStreetMap' }).addTo(map);

const cluster = L.markerClusterGroup({ disableClusteringAtZoom: 13 });
map.addLayer(cluster);

let estadosLayer = null;

// ----- Helpers -----
function colorScale(v, max){ const t = max? v/max : 0; const a = 0.12 + 0.68*t; return `rgba(30,102,197,${a})`; }
function breadcrumbSet() {
  const parts = ['México'];
  if (estadoActual) parts.push(estadoActual);
  breadcrumbEl.textContent = parts.join(' > ');
}
// Normaliza para búsqueda (quita acentos y pasa a minúsculas)
function normalizeText(str){
  return (str || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
}

// ====== Mapa: Estados (coropleta) ======
function renderEstados() {
  if (estadosLayer) map.removeLayer(estadosLayer);

  fetch('/sisec-ui/assets/geo/mexico_estados.geojson')
    .then(r => r.json())
    .then(geo => {
      const max = Math.max(1, ...Object.values(metricasPorEstado).map(m => (m[metricSelect.value] ?? m.total ?? 0)));
      estadosLayer = L.geoJSON(geo, {
        style: f => {
          const nombre = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
          const v = (metricasPorEstado[nombre]?.[metricSelect.value]) ?? (metricasPorEstado[nombre]?.total ?? 0);
          return { color:'#e0e7f1', weight:1, fillColor:colorScale(v, max), fillOpacity:1 };
        },
        onEachFeature: (f, layer) => {
          const nombre = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
          const m = metricasPorEstado[nombre] || {total:0, cctv:0, alarma:0};
          layer.bindTooltip(`<b>${nombre}</b><br>Total: ${m.total}<br>CCTV: ${m.cctv}<br>Alarma: ${m.alarma}`, { sticky:true });
          layer.on('click', () => {
            estadoActual = nombre; nivel = 'estado';
            breadcrumbSet();
            map.fitBounds(layer.getBounds(), { padding:[20,20] });
            btnVolver.classList.remove('d-none');
            renderSucursales();
          });
        }
      }).addTo(map);

      renderSucursales(); // pinta marcadores acorde a la métrica/estado actual
    });
}

// ====== Marcadores y buscador ======
const markersById = new Map();     // sucursal_id -> marker
let lastGlow = null;               // para destacar el último marcador seleccionado

function renderSucursales() {
  cluster.clearLayers();
  markersById.clear();

  const metrica = metricSelect.value;

  sucursales
    .filter(s => !estadoActual || s.estado === estadoActual) // si usas normalización: normalizaEstado(s.estado) === estadoActual
    .forEach(s => {
      if (s.lat == null || s.lng == null) return;

      const value = s[metrica] ?? s.total ?? 0;
      const icon = L.divIcon({
        className:'marker-kpi',
        html:`<div class="marker-body" style="background:#fff;border:1px solid #d5e1f0;border-radius:10px;padding:.25rem .5rem;box-shadow:0 6px 14px rgba(0,0,0,.12);font-size:12px;line-height:1;">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${metrica==='alarma'?'#ff944d':(metrica==='cctv'?'#3cb371':'#3C92A6')};margin-right:6px;"></span>${value}
              </div>`,
        iconSize:[10,10], iconAnchor:[10,10]
      });

      const m = L.marker([s.lat, s.lng], { icon });
      m.bindPopup(`
        <b>${s.sucursal}</b>${s.determinante ? ` <span class="badge bg-light text-dark ms-1 match-code">#${s.determinante}</span>` : ''}
        <br><small>${s.municipio}, ${s.ciudad} · ${s.estado}</small>
        <hr style="margin:.5rem 0" />
        <div>Total: <b>${s.total}</b></div>
        <div>CCTV: ${s.cctv} · Alarma: ${s.alarma}</div>
        <div class="mt-2">
          <a class="btn btn-sm btn-outline-primary" href="/sisec-ui/views/dispositivos/listar.php?SucursalID=${encodeURIComponent(s.sucursal_id)}">Ver dispositivos</a>
        </div>
      `);

      markersById.set(String(s.sucursal_id), m);
      cluster.addLayer(m);
    });
}

/**
 * Filtra y pinta sólo las sucursales que hagan match con la consulta.
 * - q: texto de búsqueda
 * - options.global=true: si no hay resultados en el estado actual, intenta en todo el país.
 */
function filtrarSucursales(q, options = { global: true }) {
  const qn = normalizeText(q);
  const metrica = metricSelect.value;

  // Base de datos a filtrar (por estado o todo el país)
  let base = sucursales.filter(s => s.lat != null && s.lng != null);
  if (estadoActual) {
    base = base.filter(s => s.estado === estadoActual);
  }

  // Si no hay query, equivalemos a renderSucursales()
  if (!qn) {
    cluster.clearLayers();
    markersById.clear();
    base.forEach(s => {
      const value = s[metrica] ?? s.total ?? 0;
      const icon = L.divIcon({
        className:'marker-kpi',
        html:`<div class="marker-body" style="background:#fff;border:1px solid #d5e1f0;border-radius:10px;padding:.25rem .5rem;box-shadow:0 6px 14px rgba(0,0,0,.12);font-size:12px;line-height:1;">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${metrica==='alarma'?'#ff944d':(metrica==='cctv'?'#3cb371':'#3C92A6')};margin-right:6px;"></span>${value}
              </div>`,
        iconSize:[10,10], iconAnchor:[10,10]
      });
      const m = L.marker([s.lat, s.lng], { icon });
      m.bindPopup(`
        <b>${s.sucursal}</b>${s.determinante ? ` <span class="badge bg-light text-dark ms-1 match-code">#${s.determinante}</span>` : ''}
        <br><small>${s.municipio}, ${s.ciudad} · ${s.estado}</small>
        <hr style="margin:.5rem 0" />
        <div>Total: <b>${s.total}</b></div>
        <div>CCTV: ${s.cctv} · Alarma: ${s.alarma}</div>
        <div class="mt-2">
          <a class="btn btn-sm btn-outline-primary" href="/sisec-ui/views/dispositivos/listar.php?SucursalID=${encodeURIComponent(s.sucursal_id)}">Ver dispositivos</a>
        </div>
      `);
      markersById.set(String(s.sucursal_id), m);
      cluster.addLayer(m);
    });
    return;
  }

  // Con query: usar el índice normalizado
  const matches = base.filter(s => {
    const norm = normById.get(String(s.sucursal_id)) || '';
    return norm.includes(qn);
  });

  // Si no hay matches en el estado actual y está permitido, intentamos país completo
  if (!matches.length && options.global && estadoActual) {
    const all = sucursales.filter(s => s.lat != null && s.lng != null);
    const fallback = all.filter(s => {
      const norm = normById.get(String(s.sucursal_id)) || '';
      return norm.includes(qn);
    });
    if (fallback.length) {
      estadoActual = null; // ampliamos a país
      breadcrumbSet();
      btnVolver.classList.add('d-none'); // estamos en país
      return filtrarSucursales(q, { global: false }); // repite ya en país
    }
  }

  // Pintar resultados encontrados
  cluster.clearLayers();
  markersById.clear();

  matches.forEach(s => {
    const value = s[metrica] ?? s.total ?? 0;
    const icon = L.divIcon({
      className:'marker-kpi',
      html:`<div class="marker-body" style="background:#fff;border:1px solid #d5e1f0;border-radius:10px;padding:.25rem .5rem;box-shadow:0 6px 14px rgba(0,0,0,.12);font-size:12px;line-height:1;">
              <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${metrica==='alarma'?'#ff944d':(metrica==='cctv'?'#3cb371':'#3C92A6')};margin-right:6px;"></span>${value}
            </div>`,
      iconSize:[10,10], iconAnchor:[10,10]
    });
    const m = L.marker([s.lat, s.lng], { icon });
    m.bindPopup(`
      <b>${s.sucursal}</b>${s.determinante ? ` <span class="badge bg-light text-dark ms-1 match-code">#${s.determinante}</span>` : ''}
      <br><small>${s.municipio}, ${s.ciudad} · ${s.estado}</small>
      <hr style="margin:.5rem 0" />
      <div>Total: <b>${s.total}</b></div>
      <div>CCTV: ${s.cctv} · Alarma: ${s.alarma}</div>
      <div class="mt-2">
        <a class="btn btn-sm btn-outline-primary" href="/sisec-ui/views/dispositivos/listar.php?SucursalID=${encodeURIComponent(s.sucursal_id)}">Ver dispositivos</a>
      </div>
    `);
    markersById.set(String(s.sucursal_id), m);
    cluster.addLayer(m);
  });

  // Ajusta el mapa a los resultados
  if (matches.length) {
    const group = L.featureGroup([...markersById.values()]);
    map.fitBounds(group.getBounds(), { padding:[20,20] });
  }
}

// Índice de búsqueda (cliente)
const searchIndex = sucursales.map(s => {
  const key = [
    s.sucursal || '',
    s.determinante || '',
    s.municipio || '',
    s.ciudad || '',
    s.estado || ''
  ].join(' | ');

  return {
    id: String(s.sucursal_id),
    display: s,
    norm: normalizeText(key)
  };
});

// Mapa rápido: sucursal_id -> texto normalizado (del índice)
const normById = new Map(searchIndex.map(it => [it.id, it.norm]));

// Busca y renderiza sugerencias
function doSearch(q){
  const qn = normalizeText(q);
  if (!qn) { searchResults.classList.add('d-none'); searchResults.innerHTML=''; return; }

  // Filtrado sencillo (puedes cambiar a Fuse.js si crece mucho)
  const results = searchIndex
    .filter(it => it.norm.includes(qn))
    .slice(0, 8); // top 8 sugerencias

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

searchInput.addEventListener('input', (e) => {
  const val = e.target.value;
  doSearch(val);              // mantiene las sugerencias desplegables
  filtrarSucursales(val);     // filtra marcadores en el mapa
});


document.addEventListener('click', (e) => {
  // cierra lista si clic fuera
  if (!searchResults.contains(e.target) && e.target !== searchInput) {
    searchResults.classList.add('d-none');
  }
});

// Click en sugerencia → volar al marcador
searchResults.addEventListener('click', (e) => {
  const item = e.target.closest('.list-group-item');
  if (!item) return;
  const id = item.getAttribute('data-id');
  focusSucursalById(id);
  searchResults.classList.add('d-none');
});

// Enter en el input = primer resultado
searchInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    const first = searchResults.querySelector('.list-group-item');
    if (first) {
      e.preventDefault();
      const id = first.getAttribute('data-id');
      focusSucursalById(id);
      searchResults.classList.add('d-none');
    }
  }
});

function focusSucursalById(id){
  const marker = markersById.get(String(id));
  if (!marker) {
    // si estamos en nivel país y la sucursal está en otro estado, cambiaremos de nivel automáticamente
    const rec = sucursales.find(s => String(s.sucursal_id) === String(id));
    if (rec) {
      estadoActual = rec.estado;
      btnVolver.classList.remove('d-none');
      breadcrumbSet();
      renderSucursales(); // repinta marcadores filtrados por estado
      setTimeout(() => focusSucursalById(id), 50); // reintenta cuando ya exista el marker
    }
    return;
  }

  // Abre el cluster y centra
  cluster.zoomToShowLayer(marker, () => {
    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16));
    marker.openPopup();

    // Resalta suavemente el marcador
    const el = marker.getElement();
    if (lastGlow) lastGlow.classList.remove('glow');
    if (el) {
      const body = el.querySelector('.marker-body');
      if (body) {
        body.classList.add('glow');
        lastGlow = body;
        setTimeout(() => body.classList.remove('glow'), 2000);
      }
    }
  });
}

btnVolver.addEventListener('click', () => {
  if (nivel === 'estado') {
    estadoActual = null; nivel = 'pais';
    map.setView([23.6345, -102.5528], 5);
    btnVolver.classList.add('d-none');
  }
  breadcrumbSet();
  renderSucursales();
});

metricSelect.addEventListener('change', () => {
  const val = searchInput.value.trim();
  if (val) filtrarSucursales(val);
  else     renderEstados(); // esto internamente vuelve a llamar a renderSucursales
});


// Inicializa
breadcrumbSet();
renderEstados();
</script>




<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
