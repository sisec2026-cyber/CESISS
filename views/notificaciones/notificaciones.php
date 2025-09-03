<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin', 'Administrador']);
include __DIR__ . '/../../includes/db.php';

// ========= Parámetros y utilidades =========
$page    = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$filtro  = $_GET['filtro'] ?? 'todos'; // 'todos' | 'novistas'
$q       = trim($_GET['q'] ?? '');     // búsqueda por nombre de usuario

/** Formato de fecha amigable */
function formatFecha($ts) {
  if (!$ts) return '-';
  $dt = new DateTime($ts);
  return $dt->format('d/m/Y H:i');
}

/** Limpia “ID #123” incrustado en el mensaje */
function limpiarMensajeId(string $msg): string {
  $msg = preg_replace('/\b(?:con\s+)?ID\s*#\s*\d+\b/i', '', $msg);   // quita ID #123
  $msg = preg_replace('/\s{2,}/', ' ', $msg);                        // colapsa espacios
  $msg = preg_replace('/\s+([,.;:!?)])/u', '$1', $msg);              // espacios antes de puntuación
  return trim($msg);
}

/** Helper para bind dinámico */
function bindAll(mysqli_stmt $stmt, string $types, array $params) {
  if ($types && $params) {
    $stmt->bind_param($types, ...$params);
  }
}

// ========= Construcción dinámica de filtros (WHERE) =========
$conds  = [];
$types  = '';
$params = [];

// filtro "no vistas"
if ($filtro === 'novistas') {
  $conds[] = 'n.visto = 0';
}

// búsqueda por nombre de usuario (tabla usuarios.nombre)
if ($q !== '') {
  $conds[] = 'u.nombre LIKE ?';
  $types  .= 's';
  $params[] = "%{$q}%";
}

$whereSql = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

// ========= Contadores para pills (respetando la búsqueda) =========
// Total con búsqueda aplicada (todas)
$sqlCountAll = "SELECT COUNT(*)
                FROM notificaciones n
                LEFT JOIN usuarios u ON u.id = n.usuario_id
                $whereSql";
$stmtAll = $conn->prepare($sqlCountAll);
bindAll($stmtAll, $types, $params);
$stmtAll->execute();
$stmtAll->bind_result($totalTodas);
$stmtAll->fetch();
$stmtAll->close();

// Total "no vistas" con la misma búsqueda (forzando visto=0)
$condsNV = $conds;
if (!in_array('n.visto = 0', $condsNV, true)) $condsNV[] = 'n.visto = 0';
$whereNV = $condsNV ? ('WHERE ' . implode(' AND ', $condsNV)) : '';

$sqlCountNV = "SELECT COUNT(*)
               FROM notificaciones n
               LEFT JOIN usuarios u ON u.id = n.usuario_id
               $whereNV";
$stmtNV = $conn->prepare($sqlCountNV);
// Para NV, si ya traía n.visto=0 no cambiamos los params; si lo agregamos aquí tampoco requiere nuevos params
bindAll($stmtNV, $types, $params);
$stmtNV->execute();
$stmtNV->bind_result($totalNoVistas);
$stmtNV->fetch();
$stmtNV->close();

// ========= Total para paginación (según filtro actual y búsqueda) =========
$sqlTotal = "SELECT COUNT(*)
             FROM notificaciones n
             LEFT JOIN usuarios u ON u.id = n.usuario_id
             $whereSql";
$stmtTotal = $conn->prepare($sqlTotal);
bindAll($stmtTotal, $types, $params);
$stmtTotal->execute();
$stmtTotal->bind_result($totalNotifications);
$stmtTotal->fetch();
$stmtTotal->close();

// ========= Traer datos paginados =========
// n: notificaciones (n.usuario_id, n.dispositivo_id)
// u: usuarios (u.nombre)
// d: dispositivos
// m: modelos (m.num_modelos)
$sql = "SELECT 
          n.id,
          n.usuario_id,
          u.nombre          AS usuario_nombre,
          n.mensaje,
          n.fecha,
          n.visto,
          n.dispositivo_id,
          m.num_modelos     AS modelo_nombre
        FROM notificaciones n
        LEFT JOIN usuarios u  ON u.id = n.usuario_id
        LEFT JOIN dispositivos d ON d.id = n.dispositivo_id
        LEFT JOIN modelos m      ON m.id = d.modelo
        $whereSql
        ORDER BY n.fecha DESC
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);

// types/params + paginación
$typesMain  = $types . 'ii';
$paramsMain = $params;
$paramsMain[] = $offset;
$paramsMain[] = $perPage;

bindAll($stmt, $typesMain, $paramsMain);
$stmt->execute();
$result = $stmt->get_result();

// ========= Render =========
ob_start();
?>

<style>
  .card-notifs { border: 1px solid #16323a; box-shadow: 0 10px 20px rgba(0,0,0,.08); }
  .table-sticky thead th { position: sticky; top: 0; background: #0a2128; color: #e6f2f4; z-index: 1; }
  .table thead th { border-bottom: 1px solid #16323a; }
  .table td, .table th { vertical-align: middle; }
  .row-unseen { background: rgba(255, 193, 7, .12); }
  .pill-link { text-decoration: none; }
  .pill-link.active { background: #3C92A6; color: #fff; }
  .pill-link:not(.active):hover { background: rgba(60,146,166,.15); }
  .empty-state { padding: 3rem 1rem; text-align: center; color: #6c757d; }
  .empty-state .icon { font-size: 3rem; line-height: 1; opacity: .6; }
  .text-muted-xxs { font-size: .82rem; color: #8aa1a9; }
  .searchbar { gap: .5rem; }
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h2 class="m-0">Notificaciones</h2>

  <!-- Buscador por nombre de usuario -->
  <form method="get" class="d-flex searchbar">
    <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro) ?>">
    <input type="text"
           name="q"
           class="form-control"
           placeholder="Buscar por nombre de usuario…"
           value="<?= htmlspecialchars($q) ?>">
    <button class="btn btn-outline-primary" type="submit">
      <i class="fas fa-search"></i>
    </button>
    <?php if ($q !== ''): ?>
      <a class="btn btn-outline-secondary" href="?filtro=<?= urlencode($filtro) ?>">
        <i class="fas fa-times"></i>
      </a>
    <?php endif; ?>
  </form>
</div>

<!-- Pills con contadores (preservan búsqueda) -->
<div class="nav nav-pills gap-2 mb-3">
  <a class="pill-link nav-link <?= $filtro === 'todos' ? 'active' : '' ?>"
     href="?filtro=todos&q=<?= urlencode($q) ?>">
    Todas
    <span class="badge bg-light text-dark ms-2"><?= number_format((int)$totalTodas) ?></span>
  </a>
  <a class="pill-link nav-link <?= $filtro === 'novistas' ? 'active' : '' ?>"
     href="?filtro=novistas&q=<?= urlencode($q) ?>">
    No vistas
    <span class="badge bg-warning text-dark ms-2"><?= number_format((int)$totalNoVistas) ?></span>
  </a>
</div>

<div class="card card-notifs">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="fw-semibold"><i class="fas fa-bell me-2"></i>Listado</span>
    <small class="text-muted">Página <?= $page ?> de <?= max(1, ceil($totalNotifications / $perPage)) ?></small>
  </div>

  <?php if ($result->num_rows === 0): ?>
    <div class="empty-state">
      <div class="icon mb-3"><i class="far fa-bell-slash"></i></div>
      <h5 class="mb-1">No hay notificaciones</h5>
      <p class="mb-0">Ajusta tu búsqueda y filtros.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover table-sticky mb-0">
        <thead>
          <tr>
            <th style="width:50%">Mensaje</th>
            <th style="width:20%">Usuario</th>
            <th style="width:15%">Fecha</th>
            <th style="width:15%" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()):
            $modeloNombre  = $row['modelo_nombre'] ?? null; // modelos.num_modelos
            $usuarioNombre = $row['usuario_nombre'] ?? '—';
            $isUnseen      = ((int)$row['visto'] === 0);
            $dispId        = (int)($row['dispositivo_id'] ?? 0);
            $verHref       = $dispId ? "/sisec-ui/views/dispositivos/device.php?id={$dispId}" : "#";
            $btnTitle      = $dispId
                              ? ('Ver dispositivo' . ($modeloNombre ? (': ' . $modeloNombre) : ''))
                              : 'Sin dispositivo asociado';
            // Limpia “ID #xxx” del mensaje
            $mensaje = limpiarMensajeId($row['mensaje']);
            // Acompaña con modelo si no está
            if ($modeloNombre && stripos($mensaje, $modeloNombre) === false) {
              $mensaje .= " — Modelo: " . $modeloNombre;
            }
          ?>
            <tr class="<?= $isUnseen ? 'row-unseen' : '' ?>">
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($mensaje) ?></div>
                <?php if ($modeloNombre): ?>
                  <div class="text-muted-xxs">
                    Modelo: <span class="fw-semibold"><?= htmlspecialchars($modeloNombre) ?></span>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($usuarioNombre) ?></td>
              <td><span class="badge bg-secondary"><?= formatFecha($row['fecha']) ?></span></td>
              <td class="text-end">
                <a href="<?= htmlspecialchars($verHref) ?>"
                   class="btn btn-info btn-sm <?= $dispId ? '' : 'disabled' ?>"
                   title="<?= htmlspecialchars($btnTitle) ?>"
                   target="<?= $dispId ? '_blank' : '' ?>" rel="noopener">
                  <i class="fas fa-eye me-1"></i> Ver
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
// ===== Paginación =====
$totalPages = (int)ceil($totalNotifications / $perPage);
if ($totalPages > 1):
  // Conserva filtro y búsqueda en los enlaces de paginación
  $qstrBase = ['filtro' => $filtro];
  if ($q !== '') $qstrBase['q'] = $q;
  $qbase = http_build_query($qstrBase);

  $prev = max(1, $page - 1);
  $next = min($totalPages, $page + 1);
?>
<nav class="mt-3" aria-label="Paginación de notificaciones">
  <ul class="pagination mb-0">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="?page=1&<?= $qbase ?>" aria-label="Primera">&laquo;</a>
    </li>
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="?page=<?= $prev ?>&<?= $qbase ?>" aria-label="Anterior">&lsaquo;</a>
    </li>
    <?php
      $start = max(1, $page - 2);
      $end   = min($totalPages, $page + 2);
      if ($end - $start < 4) {
        $start = max(1, $end - 4);
        $end   = min($totalPages, $start + 4);
      }
      for ($i = $start; $i <= $end; $i++):
    ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&<?= $qbase ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <a class="page-link" href="?page=<?= $next ?>&<?= $qbase ?>" aria-label="Siguiente">&rsaquo;</a>
    </li>
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <a class="page-link" href="?page=<?= $totalPages ?>&<?= $qbase ?>" aria-label="Última">&raquo;</a>
    </li>
  </ul>
</nav>
<?php
endif;

$stmt->close();

$content    = ob_get_clean();
$pageTitle  = "Notificaciones";
$pageHeader = "Lista de notificaciones";
$activePage = "notificaciones";

include __DIR__ . '/../../layout.php';
