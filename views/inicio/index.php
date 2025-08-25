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


/* ======== Prefijos por categoría (lista actual) ======== */
/* CCTV */
$prefijosCCTV = [
  "racks%", "camara%", "dvr%", "nvr%", "servidor%", "monitor%", "biometrico%",
  "videoportero%", "videotelefono%", "ups%"
];
/* ALARMA */
$prefijosAlarma = [
  "sensor%", "dh%", "pir%", "cm%", "oh%", "estrobo%", "rep%", "drc%", "estacion%",
  "teclado%", "sirena%", "boton%"
];

/* Helper para armar ORs de LIKE seguros */
function build_likes($col, $collation, $prefixes) {
  $likes = [];
  foreach ($prefixes as $p) {
    $p = str_replace("'", "''", $p); // escapar comillas por seguridad
    $likes[] = "$col COLLATE $collation LIKE '$p'";
  }
  return '(' . implode(' OR ', $likes) . ')';
}

/* WHERE combinado: sólo categorías de interés (CCTV o ALARMA) */
$whereCCTV   = build_likes('e.nom_equipo', $collation, $prefijosCCTV);
$whereAlarma = build_likes('e.nom_equipo', $collation, $prefijosAlarma);
$whereCategorias = "($whereCCTV OR $whereAlarma)";

/* CASE para clasificar en SQL */
$caseCCTV   = "CASE WHEN $whereCCTV THEN 1 ELSE 0 END";
$caseALARMA = "CASE WHEN $whereAlarma THEN 1 ELSE 0 END";

/* ======== Consulta: Dispositivos CCTV vs Alarma por sucursal ======== */
$sql = "
  SELECT s.nom_sucursal AS sucursal,
         SUM($caseCCTV)   AS cctv,
         SUM($caseALARMA) AS alarma
  FROM dispositivos d
  INNER JOIN equipos e    ON d.equipo   = e.id
  INNER JOIN sucursales s ON d.sucursal = s.id
  WHERE $whereCategorias
  GROUP BY s.nom_sucursal
";
$labels = []; $cctvData = []; $alarmaData = [];
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) {
  $labels[]     = $r['sucursal'];
  $cctvData[]   = (int)$r['cctv'];
  $alarmaData[] = (int)$r['alarma'];
}

/* Ordenar por total desc manteniendo correspondencia */
$rows = [];
for ($i = 0; $i < count($labels); $i++) {
  $rows[] = [
    'label'  => $labels[$i],
    'cctv'   => $cctvData[$i],
    'alarma' => $alarmaData[$i],
    'total'  => $cctvData[$i] + $alarmaData[$i]
  ];
}
usort($rows, fn($a,$b) => $b['total'] <=> $a['total']);
$labels     = array_column($rows, 'label');
$cctvData   = array_column($rows, 'cctv');
$alarmaData = array_column($rows, 'alarma');

/* Alto fijo recomendado para vertical (evita “saltos”) */
$chartHeight = 420;
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

<!-- ÚNICA GRÁFICA: Dispositivos por sucursal (CCTV vs Alarma, apilado vertical) -->
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h6 class="card-title mb-0">Dispositivos por sucursal</h6>
          <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill text-bg-light">Ordenado por total</span>
          </div>
        </div>
        <div class="mt-3" style="height: <?= (int)$chartHeight ?>px;">
          <canvas id="chartStacked"></canvas>
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

<!-- Estilos extra (look pro y llamativo, similar al anterior) -->
<style>
  .card { border: 1px solid #eef2f7; }
  .card .card-title { font-weight: 700; letter-spacing: .2px; }
  .text-bg-light { background: #f5f7fb !important; }
  /* Sombra suave al canvas y bordes redondeados */
  #chartStacked { border-radius: 14px; box-shadow: 0 0 0 1px #eef2f7 inset; }
</style>

<!-- Chart.js + DataLabels -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<script>
(function () {
  const labels     = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const cctvData   = <?= json_encode($cctvData) ?>;
  const alarmaData = <?= json_encode($alarmaData) ?>;

  const canvas = document.getElementById('chartStacked');
  const ctx = canvas.getContext('2d');

// Gradientes más fuertes (vertical: arriba->abajo)
const gradCCTV = ctx.createLinearGradient(0, 0, 0, canvas.height);
gradCCTV.addColorStop(0, 'rgba(0,123,255,0.95)');  // azul intenso
gradCCTV.addColorStop(1, 'rgba(0,123,255,0.35)');  

const gradAlarma = ctx.createLinearGradient(0, 0, 0, canvas.height);
gradAlarma.addColorStop(0, 'rgba(40,167,69,0.95)'); // verde intenso
gradAlarma.addColorStop(1, 'rgba(40,167,69,0.35)'); 

  const totales = labels.map((_, i) => (cctvData[i] || 0) + (alarmaData[i] || 0));

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'CCTV',
          data: cctvData,
          backgroundColor: gradCCTV,
          borderColor: '#0056b3',
          borderWidth: 1.5,
          borderRadius: 10,
          barThickness: 'flex',
          maxBarThickness: 48,
          hoverBorderWidth: 2
        },
        {
          label: 'Alarma',
          data: alarmaData,
          backgroundColor: gradAlarma,
          borderColor: '#1e7e34',
          borderWidth: 1.5,
          borderRadius: 10,
          barThickness: 'flex',
          maxBarThickness: 48,
          hoverBorderWidth: 2
        }
      ]
    },
    options: {
      // Vertical (sin indexAxis:'y')
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { top: 6, right: 14, left: 6, bottom: 0 } },
      plugins: {
        legend: {
          position: 'top',
          labels: { usePointStyle: true, pointStyle: 'rectRounded' }
        },
        tooltip: {
          backgroundColor: 'rgba(25, 28, 36, 0.9)',
          padding: 10,
          displayColors: true,
          callbacks: {
            footer: (items) => {
              const i = items[0].dataIndex;
              return 'Total: ' + totales[i];
            }
          }
        },
        datalabels: {
          color: '#0f172a',
          anchor: 'end',
          align: 'end',   // arriba de la barra
          clamp: true,
          offset: 6,
          font: { weight: '700', size: 11 },
          formatter: (val, ctx) => {
            const dsIndex = ctx.datasetIndex;
            const dataIndex = ctx.dataIndex;
            // Total solo en el último dataset (Alarma) para no duplicar números
            if (dsIndex === (ctx.chart.data.datasets.length - 1)) {
              return totales[dataIndex];
            }
            return null;
          }
        }
      },
      scales: {
        x: {
          stacked: true,
          ticks: { autoSkip: true, maxRotation: 45, minRotation: 0 },
          grid: { color: 'rgba(0,0,0,0.06)', borderDash: [4, 4] }
        },
        y: {
          stacked: true,
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { display: false }
        }
      },
      animation: { duration: 650, easing: 'easeOutCubic' }
    },
    plugins: [ChartDataLabels]
  });
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
