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

/* Prefijos por categoría */
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

/* CASE para clasificar en SQL */
$caseCCTV   = "CASE WHEN $whereCCTV THEN 1 ELSE 0 END";
$caseALARMA = "CASE WHEN $whereAlarma THEN 1 ELSE 0 END";

/* ======== Consulta jerárquica ======== */
$sqlJerarquico = "SELECT
r.nom_region    AS region,
c.nom_ciudad    AS ciudad,
m.nom_municipio AS municipio,
s.nom_sucursal  AS sucursal,
SUM($caseCCTV)   AS cctv,
SUM($caseALARMA) AS alarma
FROM dispositivos d
INNER JOIN equipos e     ON d.equipo   = e.id
INNER JOIN sucursales s  ON d.sucursal = s.id
INNER JOIN municipios m  ON s.municipio_id = m.id
INNER JOIN ciudades c    ON m.ciudad_id    = c.id
INNER JOIN regiones r    ON c.region_id    = r.id
WHERE $whereCategorias
GROUP BY r.nom_region, c.nom_ciudad, m.nom_municipio, s.nom_sucursal";

$datosJerarquicos = [];
$resJer = $conn->query($sqlJerarquico);
while ($r = $resJer->fetch_assoc()) {
  $region    = $r['region'];
  $ciudad    = $r['ciudad'];
  $municipio = $r['municipio'];
  $sucursal  = $r['sucursal'];
  if (!isset($datosJerarquicos[$region])) $datosJerarquicos[$region] = [];
  if (!isset($datosJerarquicos[$region][$ciudad])) $datosJerarquicos[$region][$ciudad] = [];
  if (!isset($datosJerarquicos[$region][$ciudad][$municipio])) $datosJerarquicos[$region][$ciudad][$municipio] = [];
  
  $datosJerarquicos[$region][$ciudad][$municipio][$sucursal] = [
    "cctv"   => (int)$r['cctv'],
    "alarma" => (int)$r['alarma']
  ];
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

<!-- Gráfica Pastel Drilldown -->
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h6 class="card-title mb-0">Dispositivos por Región / Ciudad / Municipio / Sucursal</h6>
          <button id="btnVolver" class="btn btn-sm btn-outline-secondary d-none">← Volver</button>
        </div>
        <div class="mt-3" style="height: 450px;">
          <canvas id="chartPastel"></canvas>
        </div>
      </div>
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

<!-- Estilos extra -->
<style>
.card { border: 1px solid #eef2f7; }
.card .card-title { font-weight: 700; letter-spacing: .2px; }
.text-bg-light { background: #f5f7fb !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const datosJerarquicos = <?php echo json_encode($datosJerarquicos, JSON_UNESCAPED_UNICODE); ?>;
let nivel = "region";
let ruta  = [];
let miChart = null;

function sumaTotales(nodo) {
    if (nodo && typeof nodo === 'object' && 'cctv' in nodo && 'alarma' in nodo) {
        return (nodo.cctv || 0) + (nodo.alarma || 0);
    }
    return Object.values(nodo || {}).reduce((acc, hijo) => acc + sumaTotales(hijo), 0);
}

function renderChart(nodo) {
    const etiquetas = Object.keys(nodo || {});
    const coloresPastel = ["#6dcdf0ff","#FFD1DC","#FDFD96","#77DD77","#CBAACB","#FFB347"];
    const valores = etiquetas.map(k => sumaTotales(nodo[k]));
    if (miChart) miChart.destroy();
    const ctx = document.getElementById('chartPastel').getContext('2d');
    miChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: etiquetas,
            datasets: [{
                data: valores,
                backgroundColor: etiquetas.map((_, i) => coloresPastel[i % coloresPastel.length])
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.label}: ${ctx.parsed}`
                    }
                }
            },
            onClick: (evt, elems) => {
                if (!elems.length) return;
                const i = elems[0].index;
                const clave = etiquetas[i];
                ruta.push(clave);
                if (nivel === "region") { nivel = "ciudad"; renderChart(nodo[clave]); }
                else if (nivel === "ciudad") { nivel = "municipio"; renderChart(nodo[clave]); }
                else if (nivel === "municipio") { nivel = "sucursal"; renderChart(nodo[clave]); }
                else { renderSucursal(nodo[clave]); }
                document.getElementById('btnVolver').classList.remove('d-none');
            }
        }
    });
}

function renderSucursal(datos) {
  if (miChart) miChart.destroy();
  const ctx = document.getElementById('chartPastel').getContext('2d');
  const cctv = datos.cctv || 0;
  const alarma = datos.alarma || 0;
  miChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ["CCTV", "Alarma"],
      datasets: [{ data: [cctv, alarma], backgroundColor: ["#96c7fcff", "#79ac85ff"] }]
    }, options: {
      responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
}

document.getElementById('btnVolver').addEventListener('click', () => {
  ruta.pop();
  if (ruta.length === 0) {
    nivel = "region"; renderChart(datosJerarquicos); document.getElementById('btnVolver').classList.add('d-none');
  } else if (ruta.length === 1) { nivel = "ciudad"; renderChart(datosJerarquicos[ruta[0]]); }
  else if (ruta.length === 2) { nivel = "municipio"; renderChart(datosJerarquicos[ruta[0]][ruta[1]]); }
  else if (ruta.length === 3) { nivel = "sucursal"; renderChart(datosJerarquicos[ruta[0]][ruta[1]][ruta[2]]); }
});
// Inicializar
renderChart(datosJerarquicos);
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>