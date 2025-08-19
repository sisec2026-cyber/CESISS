<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // Verifica si hay sesión iniciada
verificarRol(['Superadmin', 'Administrador','Técnico', 'Mantenimientos','Distrital']);

include __DIR__ . '/../../includes/db.php';

ob_start();

/* ======== Filtros y configuración ======== */
$collation   = 'utf8mb4_general_ci'; // En MySQL 8: 'utf8mb4_0900_ai_ci'
$filtroCamara = "e.nom_equipo COLLATE $collation LIKE 'camara%'";
$filtroSensor = "e.nom_equipo COLLATE $collation LIKE 'sensor%'";
$estadoMto    = 'En mantenimiento';

/* ======== KPIs ======== */
// Cámaras (todas las variantes: Camara Bullet, Camara Domo, etc.)
$camaras = $conn->query("
    SELECT COUNT(*) AS total 
    FROM dispositivos d
    INNER JOIN equipos e ON d.equipo = e.id
    WHERE $filtroCamara
")->fetch_assoc()['total'] ?? 0;

// Sensores (por coherencia con FK a 'equipos')
$sensores = $conn->query("
    SELECT COUNT(*) AS total
    FROM dispositivos d
    INNER JOIN equipos e ON d.equipo = e.id
    WHERE $filtroSensor
")->fetch_assoc()['total'] ?? 0;

// Usuarios
$usuarios = $conn->query("SELECT COUNT(*) AS total FROM usuarios")->fetch_assoc()['total'] ?? 0;

// Cámaras en mantenimiento (solo cámaras con estado "En mantenimiento")
$mantenimiento = $conn->query("
    SELECT COUNT(*) AS total
    FROM dispositivos d 
    INNER JOIN equipos e ON d.equipo = e.id
    INNER JOIN status s  ON d.estado = s.id
    WHERE $filtroCamara
      AND s.status_equipo = '$estadoMto'
")->fetch_assoc()['total'] ?? 0;

/* ======== Gráfica de barras (RESTABLECIDA): todos los dispositivos por sucursal ======== */
$equipos_result = $conn->query("
    SELECT s.nom_sucursal AS nombre_sucursal, COUNT(*) as cantidad 
    FROM dispositivos d
    INNER JOIN sucursales s ON d.sucursal = s.id
    GROUP BY s.nom_sucursal 
    ORDER BY s.nom_sucursal ASC
");
$equipos_dispositivos = [];
while ($row = $equipos_result->fetch_assoc()) {
    $equipos_dispositivos[$row['nombre_sucursal']] = (int)$row['cantidad'];
}

/* ======== Actividad demo (como lo tenías) ======== */
$actividad = [27, 17, 16, 28, 13, 4, 0];

/* ======== NUEVO: datos para “Cámaras en mantenimiento” ======== */
// Listado detallado de cámaras en mantenimiento
$listado_mto = $conn->query("
  SELECT 
    d.id,
    e.nom_equipo                      AS equipo,
    COALESCE(su.nom_sucursal, '—')    AS sucursal,
    s.status_equipo                   AS estado
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  INNER JOIN status s  ON d.estado = s.id
  LEFT  JOIN sucursales su ON d.sucursal = su.id
  WHERE $filtroCamara
    AND s.status_equipo = '$estadoMto'
  ORDER BY su.nom_sucursal ASC, d.id DESC
");

// Agregación por sucursal para la nueva gráfica
$mto_por_sucursal = $conn->query("
  SELECT COALESCE(su.nom_sucursal, 'Sin sucursal') AS nombre_sucursal, COUNT(*) AS cantidad
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  INNER JOIN status s  ON d.estado = s.id
  LEFT  JOIN sucursales su ON d.sucursal = su.id
  WHERE $filtroCamara
    AND s.status_equipo = '$estadoMto'
  GROUP BY nombre_sucursal
  ORDER BY nombre_sucursal ASC
");
$mtoLabels = [];
$mtoData   = [];
while ($r = $mto_por_sucursal->fetch_assoc()) {
  $mtoLabels[] = $r['nombre_sucursal'];
  $mtoData[]   = (int)$r['cantidad'];
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

<!-- Gráficas -->
<div class="row g-4 mb-4">
  <!-- (RESTABLECIDA) Dispositivos por sucursal -->
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="card-title mb-3">Dispositivos por sucursal</h6>
        <canvas id="chartDispositivos" height="200"></canvas>
      </div>
    </div>
  </div>

  <!-- Actividad semanal (como lo tenías) -->
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="card-title mb-3">Actividad de los últimos 7 días</h6>
        <canvas id="chartActividad" height="200"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- NUEVO: Sección de Cámaras en mantenimiento -->
<div class="row g-4 mb-4">
  <div class="col-md-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="card-title mb-3">Cámaras en mantenimiento por sucursal</h6>
        <canvas id="chartMtoPorSucursal" height="220"></canvas>
      </div>
    </div>
  </div>

  <div class="col-md-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="card-title mb-3">Listado de cámaras en mantenimiento</h6>

        <?php if (($mantenimiento ?? 0) == 0): ?>
          <div class="alert alert-info mb-0">No hay cámaras en mantenimiento.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width: 80px;">ID</th>
                  <th>Equipo</th>
                  <th>Sucursal</th>
                  <th>Estado</th>
                  <th style="width: 120px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($d = $listado_mto->fetch_assoc()): ?>
                  <tr>
                    <td><?= (int)$d['id'] ?></td>
                    <td><?= htmlspecialchars($d['equipo'] ?? 'Camara', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($d['sucursal'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                        <?= htmlspecialchars($d['estado'] ?? $estadoMto, ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    </td>
                    <td>
                      <a class="btn btn-sm btn-outline-primary"
                         href="/sisec-ui/views/dispositivos/device.php?id=<?= (int)$d['id'] ?>">
                        Ver
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<!-- Botones rápidos -->
<div class="d-flex justify-content-center gap-3">
  <div class="col-md-3">
    <a href="/sisec-ui/views/dispositivos/registro.php" class="btn btn-outline-primary w-100 py-3 rounded shadow-sm">
      <i class="fas fa-qrcode fa-lg me-2"></i> Registro nuevo dispositivo
    </a>
  </div>
  <div class="col-md-3">
    <a href="/sisec-ui/views/usuarios/registrar.php" class="btn btn-outline-success w-100 py-3 rounded shadow-sm">
      <i class="fas fa-user-plus fa-lg me-2"></i> Registro rápido de usuario
    </a>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // (RESTABLECIDA) Gráfica: Dispositivos por sucursal
  const ctx1 = document.getElementById('chartDispositivos').getContext('2d');
  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($equipos_dispositivos), JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{
        label: 'Cantidad',
        data: <?= json_encode(array_values($equipos_dispositivos)) ?>,
        backgroundColor: '#4aa3df'
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } },
        y: { beginAtZero: true, precision: 0 }
      }
    }
  });

  // Gráfica: Actividad de los últimos 7 días
  const ctx2 = document.getElementById('chartActividad').getContext('2d');
  new Chart(ctx2, {
    type: 'line',
    data: {
      labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
      datasets: [{
        label: 'Registros',
        data: <?= json_encode($actividad) ?>,
        fill: true,
        backgroundColor: 'rgba(0,123,255,0.2)',
        borderColor: '#007bff',
        tension: 0.25
      }]
    },
    options: { responsive: true }
  });

  // NUEVA: Gráfica: Cámaras en mantenimiento por sucursal
  const ctx3 = document.getElementById('chartMtoPorSucursal').getContext('2d');
  new Chart(ctx3, {
    type: 'bar',
    data: {
      labels: <?= json_encode($mtoLabels, JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{
        label: 'Cámaras en mantenimiento',
        data: <?= json_encode($mtoData) ?>,
        backgroundColor: '#dc3545'
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } },
        y: { beginAtZero: true, precision: 0 }
      }
    }
  });
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
