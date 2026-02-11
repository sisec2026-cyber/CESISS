<?php
// /sisec-ui/views/usuarios/index.php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
/* agregar genente zonal  */
verificarRol(['Superadmin','Administrador']);

$pageTitle  = "Usuarios";
$pageHeader = "Gestión de usuarios";
$activePage = "usuarios";

require_once __DIR__ . '/../../includes/conexion.php';
ob_start();

/* =========================
   HELPERS
========================= */
function h(?string $s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_date_relativa(?string $dt): string {
  
  if (!$dt) return '—';
  date_default_timezone_set('America/Mexico_City');

  $ts = strtotime($dt);
  if ($ts === false) return '—';

  $diff = time() - $ts;

  // Evitar valores negativos si el reloj del server varía por segundos
  if ($diff < 0)    $diff = 0; 
  
  if ($diff < 60)   return 'Hace segundos';
  if ($diff < 3600) return 'Hace '.floor($diff/60).' min';
  if ($diff < 86400) return 'Hace '.floor($diff/3600).' h';
  
  // Diccionario para traducir el formato 'M' (Ene, Feb, Mar...)
  $meses = [
    'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr',
    'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
    'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
  ];

  $fecha = date('d/M/Y H:i', $ts);
  // Reemplazamos el mes en inglés por el de nuestro array
  return strtr($fecha, $meses);
}
function badgeStatus(int $aprobado, ?string $ultimoLogin): string {
  if ($aprobado !== 1) {
    return '<span class="chip chip-warn" title="Cuenta pendiente de aprobación">Pendiente</span>';
  }
  
  $activo = $ultimoLogin && (strtotime($ultimoLogin) >= strtotime('-30 days'));
  return $activo
    ? '<span class="chip chip-ok" title="Inició sesión en los últimos 30 días">Activo</span>'
    : '<span class="chip chip-muted" title="Sin inicio de sesión en 30+ días">Inactivo</span>';
}


/* =========================
   FILTROS (GET)
========================= */
$roleFilter     = isset($_GET['role'])     ? trim($_GET['role'])     : '';
$statusFilter   = isset($_GET['status'])   ? trim($_GET['status'])   : ''; // aprobado|pendiente|activo|inactivo
$companyFilter  = isset($_GET['company'])  ? trim($_GET['company'])  : '';
$q              = isset($_GET['q'])        ? trim($_GET['q'])        : '';
$perPage        = (isset($_GET['per_page']) && ctype_digit($_GET['per_page'])) ? (int)$_GET['per_page'] : 10;
$page           = (isset($_GET['page']) && ctype_digit($_GET['page']) && (int)$_GET['page']>0) ? (int)$_GET['page'] : 1;
$perPage        = in_array($perPage, [10,25,50,100]) ? $perPage : 10;

/* =========================
   ANALÍTICAS (CESISS)
========================= */
$totalUsuarios = (int)($conexion->query("SELECT COUNT(*) c FROM usuarios")->fetch_assoc()['c'] ?? 0);
$aprobados     = (int)($conexion->query("SELECT COUNT(*) c FROM usuarios WHERE esta_aprobado=1")->fetch_assoc()['c'] ?? 0);
$pendientes    = (int)($conexion->query("SELECT COUNT(*) c FROM usuarios WHERE esta_aprobado=0")->fetch_assoc()['c'] ?? 0);

$activosSemana = (int)($conexion->query("
  SELECT COUNT(*) c FROM usuarios
  WHERE last_activity IS NOT NULL
    AND last_activity >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['c'] ?? 0);

$activosSemanaPrev = (int)($conexion->query("
  SELECT COUNT(*) c FROM usuarios
  WHERE last_activity IS NOT NULL
    AND last_activity >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    AND last_activity <  DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['c'] ?? 0);

$deltaActivos = $activosSemanaPrev > 0
  ? round(($activosSemana - $activosSemanaPrev) / $activosSemanaPrev * 100)
  : ($activosSemana > 0 ? 100 : 0);


/* =========================
   SELECTS (ROL / EMPRESA)
========================= */
$rolesRes = $conexion->query("SELECT DISTINCT rol FROM usuarios WHERE rol IS NOT NULL AND rol<>'' ORDER BY rol ASC");
$roles = [];
while($r = $rolesRes->fetch_assoc()) $roles[] = $r['rol'];

$empRes = $conexion->query("SELECT DISTINCT empresa FROM usuarios WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
$empresas = [];
while($e = $empRes->fetch_assoc()) $empresas[] = $e['empresa'];

/* =========================
   LISTADO (filtros + paginación)
========================= */
$where = []; $params = [];

if ($roleFilter !== '')    { $where[] = "u.rol = ?";       $params[] = $roleFilter; }
if ($companyFilter !== '') { $where[] = "u.empresa = ?";   $params[] = $companyFilter; }

/* $sqlLastActi = $conexion->query("SELECT last_activity FROM usuarios "); */

 if ($statusFilter !== '') {
  switch ($statusFilter) {
    case 'aprobado':  $where[] = "u.esta_aprobado = 1"; break;
    case 'pendiente': $where[] = "u.esta_aprobado = 0"; break;
    case 'activo':    $where[] = "u.last_activity >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; break;
    case 'inactivo':  $where[] = "u.last_activity < DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; break;
  }
}


if ($q !== '') {
  $where[] = "(u.nombre LIKE ? OR u.email LIKE ? OR u.empresa LIKE ? OR u.cargo LIKE ? OR s.nom_sucursal LIKE ?)";
  $like = '%'.$q.'%'; array_push($params, $like,$like,$like,$like,$like);
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* Conteo total */
$sqlCount = "SELECT COUNT(*) c FROM usuarios u LEFT JOIN sucursales s ON u.sucursal=s.ID $whereSql";
$stC = $conexion->prepare($sqlCount);
if ($params){ $types = str_repeat('s', count($params)); $stC->bind_param($types, ...$params); }
$stC->execute(); $totalFiltered = (int)($stC->get_result()->fetch_assoc()['c'] ?? 0);
$stC->close();

$offset = ($page - 1) * $perPage;

/* Datos (incluimos correo y puesto) */
$sqlData = "
  SELECT u.id, u.nombre, u.rol, u.email, u.empresa, u.cargo,
       COALESCE(s.nom_sucursal,'SIN SUCURSAL') sucursal,
       u.esta_aprobado, u.last_activity,
       n.mensaje AS ultimo_mensaje,
       n.dispositivo_id,
       e.nom_equipo AS equipo_nombre
FROM usuarios u
LEFT JOIN (
    -- Definimos 'n' primero
    SELECT n1.usuario_id, n1.mensaje, n1.fecha, n1.dispositivo_id
    FROM notificaciones n1
    INNER JOIN (
        SELECT usuario_id, MAX(fecha) as max_fecha
        FROM notificaciones
        GROUP BY usuario_id
    ) n2 ON n1.usuario_id = n2.usuario_id AND n1.fecha = n2.max_fecha
) n ON u.id = n.usuario_id
LEFT JOIN dispositivos d ON d.id = n.dispositivo_id
LEFT JOIN equipos e      ON e.id = d.equipo
LEFT JOIN sucursales s   ON u.sucursal = s.ID
  
  $whereSql
  ORDER BY u.nombre ASC
  LIMIT ? OFFSET ?
";

$st = $conexion->prepare($sqlData);
$currentParams = $params ? $params : [];
$types = str_repeat('s', count($currentParams)); // Tipos para el WHERE (asumimos string)
$types .= "ii"; 
$currentParams[] = (int)$perPage;
$currentParams[] = (int)$offset;

// 3. Ejecutamos el bind de una sola vez
$st->bind_param($types, ...$currentParams);

$st->execute();
$listado = $st->get_result();

/* =========================
   EXPORT CSV (respeta filtros)
========================= */
function limpiarMsg(?string $msg): string {
  $msg = preg_replace('/\b(?:con\s+)?ID\s*#\s*\d+\b/i', '', $msg);   // quita ID #123
  $msg = preg_replace('/\s{2,}/', ' ', $msg);                        // colapsa espacios
  $msg = preg_replace('/\s+([,.;:!?)])/u', '$1', $msg);              // espacios antes de puntuación
  return trim($msg);
}
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=usuarios_cesiss.csv');
  $out = fopen('php://output','w');
  fputcsv($out, ['ID','Nombre','Rol','Correo','Empresa','Puesto','Sucursal','Estatus','Ultima conexion', 'Ultima actividad']);

  $sqlAll = "
    SELECT u.id, u.nombre, u.rol, u.email, u.empresa, u.cargo,
       COALESCE(s.nom_sucursal,'SIN SUCURSAL') sucursal,
       u.esta_aprobado, u.last_activity,
       n.mensaje AS ultimo_mensaje,
       n.di spositivo_id,
       e.nom_equipo AS equipo_nombre
FROM usuarios u
LEFT JOIN (
    -- Definimos 'n' primero
    SELECT n1.usuario_id, n1.mensaje, n1.fecha, n1.dispositivo_id
    FROM notificaciones n1
    INNER JOIN (
        SELECT usuario_id, MAX(fecha) as max_fecha
        FROM notificaciones
        GROUP BY usuario_id
    ) n2 ON n1.usuario_id = n2.usuario_id AND n1.fecha = n2.max_fecha
) n ON u.id = n.usuario_id
LEFT JOIN dispositivos d ON d.id = n.dispositivo_id
LEFT JOIN equipos e      ON e.id = d.equipo
LEFT JOIN sucursales s   ON u.sucursal = s.ID
   
    $whereSql
    ORDER BY u.nombre ASC
  ";
  $stAll = $conexion->prepare($sqlAll);
  if ($params){ $types = str_repeat('s', count($params)); $stAll->bind_param($types, ...$params); }
  $stAll->execute(); $rs = $stAll->get_result();
  while($row = $rs->fetch_assoc()){
    $modeloNombre  = $row ?? null; 
    $statusTxt = ($row['esta_aprobado']==1) ? 'Aprobado' : 'Pendiente';
    $mensaje = limpiarMsg($row['ultimo_mensaje']);
    $dispId        = (int)($row['dispositivo_id'] ?? 0);
    if ($modeloNombre && stripos($mensaje, $modeloNombre) === false) {
              $mensaje .= " — Modelo: " . $modeloNombre;
            }
    fputcsv($out, [
      $row['id'],$row['nombre'],$row['rol'],$row['email'],$row['empresa'],$row['cargo'],
      $row['sucursal'],$statusTxt,$row['last_activity']?:'', $mensaje
     
    ]);
  }
  fclose($out); exit;
}
/* =========================
   UI
========================= */
?>
<style>
:root{
  --topbar-h: 70px;
  --brand: #24A3C1;
  --brand-600: #3C92A6;
  --ink: #111827;
  --muted: #6B7280;
  --line: #E5E7EB;
  --bg: #F8FAFC;
  --ok: #16a34a;
  --warn: #f59e0b;
  --muted-badge: #9CA3AF;
}
body { background: var(--bg); }
.main-content-users{ margin-top: calc(var(--topbar-h) + 8px); }
.btn-primary, .btn-success { background: var(--brand); border-color: var(--brand); }
.btn-primary:hover, .btn-success:hover { background: var(--brand-600); border-color: var(--brand-600); }
/* KPI / Filtros / Tabla (igual que versión previa) */
.kpi-card{ border:1px solid #eef0f4; border-radius:16px; padding:18px 20px; background:#fff; box-shadow:0 1px 2px rgba(17,24,39,.05); }
.kpi-title{ font-size:.92rem; color:var(--muted); margin-bottom:6px; display:flex; gap:8px; align-items:center; }
.kpi-icon{ width:32px; height:32px; border-radius:10px; background:rgba(36,163,193,.12); display:flex; align-items:center; justify-content:center; color:var(--brand); }
.kpi-value{ font-size:1.9rem; font-weight:700; color:var(--ink); }
.kpi-delta{ font-weight:600; font-size:.9rem; }
.kpi-help{ font-size:.78rem; color:#9ca3af; }

.filter-card{ background:#fff; border:1px solid var(--line); border-radius:16px; padding:12px; box-shadow:0 1px 2px rgba(17,24,39,.04); }
.filters .form-select, .filters .form-control{ min-width:0; }
.input-icon{ position:relative; }
.input-icon i{ position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9ca3af; }
.input-icon input{ padding-left:34px; }

.table-card{ background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:0 1px 2px rgba(17,24,39,.04); }
.table thead th{ font-size:.9rem; color:#4b5563; border-bottom:1px solid var(--line); }
.table tbody td{ vertical-align:middle; padding: 5px; margin: 4px }
.table-hover tbody tr:hover{ background:#f9fbfd; }
.table-users .avatar{ width:35px; height:35px; border-radius:50%; object-fit:cover; }

.chip{ display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; font-size:.82rem; font-weight:600; border:1px solid transparent; }
.chip .dot{ width:8px; height:8px; border-radius:50%; display:inline-block; }
.active {background:#4BD272}
.chip-ok{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; } .chip-ok .dot{ background:var(--ok); }
.chip-warn{ background:#fffbeb; color:#92400e; border-color:#fde68a; } .chip-warn .dot{ background:var(--warn); }
.chip-muted{ background:#f3f4f6; color:#374151; border-color:#e5e7eb; } .chip-muted .dot{ background:var(--muted-badge); }

.btn-icon{ width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; }
.btn-light{ border:1px solid var(--line); }
.pagination .page-link{ border-radius:10px; }
/* ---------- Título ---------- */
  h2{
    font-weight:800; letter-spacing:.2px; color:var(--ink);
    margin-bottom:.75rem!important;
  }
  h2::after{
    content:""; display:block; width:78px; height:4px; border-radius:99px;
    margin-top:.5rem; background:linear-gradient(90deg,var(--brand),var(--brand-2));
  }
</style>

<div style="padding-left: 15px;">
  <h2 class="mb-4">Usuarios</h2>

  <!-- KPIs -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <div class="kpi-card">
        <div class="kpi-title"><span class="kpi-icon"><i class="fas fa-users"></i></span> Total usuarios</div>
        <div class="kpi-value"><?= number_format($totalUsuarios) ?></div>
        <div class="kpi-help">Base CESISS</div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="kpi-card">
        <div class="kpi-title"><span class="kpi-icon"><i class="fas fa-user-check"></i></span> Aprobados</div>
        <div class="kpi-value"><?= number_format($aprobados) ?></div>
        <div class="kpi-help">Con acceso activo</div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="kpi-card">
        <div class="kpi-title"><span class="kpi-icon"><i class="fas fa-user-clock"></i></span> Activos (7 días)</div>
        <div class="d-flex align-items-end justify-content-between">
          <div>
            <div class="kpi-value"><?= number_format($activosSemana) ?> <span class="kpi-delta <?= $deltaActivos>=0?'text-success':'text-danger' ?>">
              <?= $deltaActivos>=0?'+':''; ?><?= $deltaActivos ?>%</span></div>
           <!--  <div class="kpi-delta <?= $deltaActivos>=0?'text-success':'text-danger' ?>"> -->
        
          
          </div>
        </div>
        <div class="kpi-help">vs. semana previa</div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="kpi-card">
        <div class="kpi-title"><span class="kpi-icon"><i class="fas fa-user-plus"></i></span> Pendientes</div>
        <div class="kpi-value"><?= number_format($pendientes) ?></div>
        <div class="kpi-help">En espera de aprobación</div>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="filter-card mb-3">
    <form id="filterForm" method="get" class="filters row g-2 align-items-center">
      <div class="col-12 col-md-3">
        <select name="role" class="form-select" onchange="this.form.submit()">
          <option value="">Seleccionar Rol</option>
          <?php foreach($roles as $rol): ?>
            <option value="<?= h($rol) ?>" <?= $roleFilter===$rol?'selected':''; ?>><?= h($rol) ?></option>
          <?php endforeach; ?>
        </select>
      </div> 

      <div class="col-12 col-md-3">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">Seleccionar Estatus</option>
          <option value="aprobado"  <?= $statusFilter==='aprobado'?'selected':''; ?>>Aprobado</option>
          <option value="pendiente" <?= $statusFilter==='pendiente'?'selected':''; ?>>Pendiente</option>
          <option value="activo"    <?= $statusFilter==='activo'?'selected':''; ?>>Activo (≤30 días)</option>
          <option value="inactivo"  <?= $statusFilter==='inactivo'?'selected':''; ?>>Inactivo (&gt;30 días)</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <select name="company" class="form-select" onchange="this.form.submit()">
          <option value="">Seleccionar Empresa</option>
          <?php foreach($empresas as $emp): ?>
            <option value="<?= h($emp) ?>" <?= $companyFilter===$emp?'selected':''; ?>><?= h($emp) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <div class="input-icon">
          <i class="fas fa-search"></i>
          <input id="q" name="q" type="search" class="form-control" placeholder="Search user, email, tienda..." value="<?= h($q) ?>" />
        </div>
      </div>

      <div class="col-6 col-md-1">
        <select name="per_page" class="form-select">
          <?php foreach([10,25,50,100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage===$n?'selected':''; ?>><?= $n ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-2 d-grid">
        <button class="btn btn-primary"><i class="fas fa-filter me-1"></i> Aplicar</button>
      </div>

      <div class="col-12 col-lg d-flex gap-2 justify-content-start justify-content-lg-end">
        <a class="btn btn-outline-secondary"
           href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv','page'=>null])) ?>">
           <i class="fas fa-file-export me-1"></i> Export
        </a>
        <a href="registrar.php" class="btn btn-success">
          <i class="fas fa-user-plus me-1"></i> Añadir Nuevo Usuario
        </a>
      </div>
    </form>
  </div>

  <!-- Tabla (con nuevas columnas) -->
  <div class="table-card table-responsive">
    <table class="table table-hover align-middle table-users mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px;"></th>
          <th>Usuario</th>
          <th>Rol</th>
          <th>Correo</th>
          <th>Empresa</th>
          <th>Puesto</th>
          <th>Tienda</th>
          <th style="width:180px;">Status</th>
          <th style="width:180px;">Ultima conexión</th>
          <th style="width:180px;">Ultima Actividad</th>
          <th class="text-center" style="width:18 0px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        while($u = $listado->fetch_assoc()): ?> 
          <tr>
            <td class="text-center">
              <?php $fotoPath = __DIR__ . "/../../uploads/usuarios/" . ($u['foto'] ?? ''); ?>
              <?php if (!empty($u['foto']) && is_file($fotoPath)): ?>
                <img src="/sisec-ui/uploads/usuarios/<?= h($u['foto']) ?>" class="avatar" alt="avatar">
              <?php else: ?>          
                <i class="fas fa-user-circle fa-2x text-secondary"></i>
              <?php endif; ?>
            </td>
            <td class="fw-semibold"><?= h($u['nombre']) ?></td>
            <td><?= h($u['rol']) ?></td>
            <td><?= h($u['email']) ?></td>
            <td><?= h($u['empresa']) ?></td>  
            <td><?= h($u['cargo']) ?: '—' ?></td>
            <td><?= h($u['sucursal']) ?></td>
            <td class="text-center"><?= badgeStatus((int)$u['esta_aprobado'], $u['last_activity']?? null) ?></td> 
            <td><?= fmt_date_relativa($u['last_activity'] ?? null) ?></td>
            <td style="max-width: 200px;">
             <?php 
             $mensaje= limpiarMsg(h($u['ultimo_mensaje']));
             $modeloNombre = h($u['equipo_nombre']??'');
             if (!empty($mensaje)): ?>
               <span >
                 <?php
                 
                 $mensaje.= " " . $modeloNombre;
                  
                  echo $mensaje
                  ?>
               </span>
             <?php else: ?>
               <span class="text-muted italic small">Sin actividad</span>
             <?php endif; ?>
           </td>
            <td class="text-center">
              <a  href="ver.php?id=<?= (int)$u['id'] ?>" class="btn btn-light btn-icon" title="Ver"><i class="far fa-eye"></i></a>
              
              <a  href="editar.php?id=<?= (int)$u['id'] ?>" class="btn btn-warning btn-icon" title="Editar"><i class="fas fa-edit"></i></a>

              <button type="button" class="btn btn-danger btn-icon" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?= (int)$u['id'] ?>" title="Eliminar">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($totalFiltered === 0): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Sin resultados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    
  </div>

  <!-- Paginación -->
  <?php
    $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
    if ($totalPages > 1):
      $baseParams = $_GET; unset($baseParams['page']);
  ?>
    <nav aria-label="Usuarios pagination" class="mt-3">
      <ul class="pagination">
        <?php
          $mk = function($p, $label=null, $disabled=false, $active=false) use ($baseParams){
            $baseParams['page'] = $p; $href = '?'.http_build_query($baseParams);
            $cls = 'page-item'.($disabled?' disabled':'').($active?' active':''); $lbl = $label ?? (string)$p;
            return "<li class=\"$cls\"><a class=\"page-link\" href=\"$href\">$lbl</a></li>";
          };
          echo $mk(max(1,$page-1), '&laquo;', $page===1);
          $start = max(1, $page-2); $end = min($totalPages, $page+2);
          for($i=$start;$i<=$end;$i++) echo $mk($i, null, false, $i===$page);
          echo $mk(min($totalPages,$page+1), '&raquo;', $page===$totalPages);
        ?>
      </ul>
    </nav>
  <?php endif; ?>

</div>

<!-- Modal de confirmación -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar eliminación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">¿Seguro que quieres eliminar este usuario?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-danger" id="btnConfirmDelete">Eliminar</a>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Modal eliminar
  const confirmDeleteModal = document.getElementById('confirmDeleteModal');
  const btnConfirmDelete   = document.getElementById('btnConfirmDelete');
  confirmDeleteModal.addEventListener('show.bs.modal', (event) => {
    const button  = event.relatedTarget;
    const userId  = button.getAttribute('data-id');
    btnConfirmDelete.href = `/sisec-ui/controllers/UserController.php?accion=eliminar&id=${userId}`;
  });

  // Buscar con Enter
  const q = document.getElementById('q');
  q.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      document.getElementById('filterForm').submit();
    }
  });
});
</script>
<script>
  //funcion AJAX  
</script>