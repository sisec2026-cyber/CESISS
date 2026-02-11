<?php
// /sisec-ui/views/usuarios/ver.php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador']);

$pageTitle  = "Detalle de usuario";
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
  $ts = strtotime($dt);
  if ($ts === false) return '—';
  $diff = time() - $ts;
  if ($diff < 60)   return 'Hace segundos';
  if ($diff < 3600) return 'Hace '.floor($diff/60).' min';
  if ($diff < 86400)return 'Hace '.floor($diff/3600).' h';
  return date('d/M/Y H:i', $ts);
}
function chipStatus(int $aprobado, ?string $ultimoLogin): string {
  if ($aprobado !== 1) {
    return '<span class="chip chip-warn" title="Cuenta pendiente de aprobación"><span class="dot"></span>Pendiente</span>';
  }
  $activo = $ultimoLogin && (strtotime($ultimoLogin) >= strtotime('-30 days'));
  return $activo
    ? '<span class="chip chip-ok" title="Inició sesión en los últimos 30 días"><span class="dot"></span>Activo</span>'
    : '<span class="chip chip-muted" title="Sin inicio de sesión en 30+ días"><span class="dot"></span>Inactivo</span>';
}

/* =========================
   VALIDACIÓN ID + FETCH
========================= */
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  echo '<div class="alert alert-danger m-3">ID inválido.</div>';
  $content = ob_get_clean(); include __DIR__ . '/../../layout.php'; exit;
}
$id = (int)$_GET['id'];

$sql = "
  SELECT u.id, u.nombre, u.rol, u.foto, u.cargo, u.email, u.empresa, u.esta_aprobado, u.ultimo_login,
         COALESCE(s.nom_sucursal, 'SIN SUCURSAL') AS sucursal
  FROM usuarios u
  LEFT JOIN sucursales s ON u.sucursal = s.ID
  WHERE u.id = ?
  LIMIT 1
";
$st = $conexion->prepare($sql);
$st->bind_param('i', $id);
$st->execute();
$res = $st->get_result();
$u = $res->fetch_assoc();
$st->close();

if (!$u) {
  echo '<div class="alert alert-warning m-3">Usuario no encontrado.</div>';
  echo '<div class="m-3"><a class="btn btn-secondary" href="index.php"><i class="fas fa-arrow-left me-1"></i> Volver</a></div>';
  $content = ob_get_clean(); include __DIR__ . '/../../layout.php'; exit;
}

/* Path de foto */
$fotoFile = $u['foto'] ? (__DIR__ . "/../../uploads/usuarios/" . $u['foto']) : null;
$hasFoto  = $fotoFile && is_file($fotoFile);
$fotoUrl  = $hasFoto ? ("/sisec-ui/uploads/usuarios/" . rawurlencode($u['foto'])) : null;
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

/* margen seguro bajo topbar */
.main-content-users{ margin-top: calc(var(--topbar-h) + 8px); }

body { background: var(--bg); }
.btn-primary, .btn-success { background: var(--brand); border-color: var(--brand); }
.btn-primary:hover, .btn-success:hover { background: var(--brand-600); border-color: var(--brand-600); }

.card-soft{
  background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:0 1px 2px rgba(17,24,39,.05);
}
.card-title-sm{ font-size:.95rem; color:#4b5563; }

.profile-header{ padding:24px; }
.profile-avatar{
  width:96px; height:96px; border-radius:50%; object-fit:cover; border:3px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,.06);
}
.meta{ color: var(--muted); font-size:.92rem; }
.divider-h{ height:1px; background:var(--line); margin:12px 0; }

.kv{ display:grid; grid-template-columns: 180px 1fr; gap:10px 16px; }
.kv .k{ color:var(--muted); }
.kv .v strong{ color:var(--ink); }

/* Chips */
.chip{ display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; font-size:.82rem; font-weight:600; border:1px solid transparent; user-select:none; }
.chip .dot{ width:8px; height:8px; border-radius:50%; display:inline-block; }
.chip-ok{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; } .chip-ok .dot{ background:var(--ok); }
.chip-warn{ background:#fffbeb; color:#92400e; border-color:#fde68a; } .chip-warn .dot{ background:var(--warn); }
.chip-muted{ background:#f3f4f6; color:#374151; border-color:#e5e7eb; } .chip-muted .dot{ background:var(--muted-badge); }

.actions .btn{ min-width:42px; }
</style>

<div class="container-fluid px-0 main-content-users">

  <!-- Encabezado Perfil -->
  <div class="card-soft profile-header mb-3">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div>
        <?php if ($hasFoto): ?>
          <img src="<?= h($fotoUrl) ?>" class="profile-avatar" alt="avatar">
        <?php else: ?>
          <div class="profile-avatar d-flex align-items-center justify-content-center bg-light text-secondary">
            <i class="fas fa-user fa-2x"></i>
          </div>
        <?php endif; ?>
      </div>

      <div class="flex-grow-1">
        <h3 class="mb-1"><?= h($u['nombre']) ?></h3>
        <div class="meta mb-1"><i class="far fa-envelope me-1"></i><?= h($u['email']) ?></div>
        <?= chipStatus((int)$u['esta_aprobado'], $u['ultimo_login']) ?>
      </div>

      <div class="actions d-flex gap-2 ms-auto">
        <a href="index.php" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i> Volver
        </a>
        <a href="editar.php?id=<?= (int)$u['id'] ?>" class="btn btn-warning">
          <i class="fas fa-edit me-1"></i> Editar
        </a>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?= (int)$u['id'] ?>">
          <i class="fas fa-trash me-1"></i> Eliminar
        </button>
      </div>
    </div>
  </div>

  <!-- Información de cuenta -->
  <div class="card-soft p-3">
    <div class="row g-3">
      <div class="col-12 col-xl-6">
        <div class="card-title-sm mb-2">Información general</div>
        <div class="kv">
          <!-- <div class="k">ID</div><div class="v"><strong>#<?= (int)$u['id'] ?></strong></div> -->
          <div class="k">Rol</div><div class="v"><strong><?= h($u['rol']) ?></strong></div>
          <div class="k">Cargo</div><div class="v"><?= h($u['cargo']) ?: '—' ?></div>
          <div class="k">Empresa</div><div class="v"><?= h($u['empresa']) ?: '—' ?></div>
          <div class="k">Sucursal</div><div class="v"><?= h($u['sucursal']) ?></div>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="card-title-sm mb-2">Actividad</div>
        <div class="kv">
          <div class="k">Estatus</div>
          <div class="v"><?= chipStatus((int)$u['esta_aprobado'], $u['ultimo_login']) ?></div>

          <div class="k">Último inicio</div>
          <div class="v"><?= fmt_date_relativa($u['ultimo_login']) ?></div>
        </div>
      </div>
    </div>
  </div>

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
  const confirmDeleteModal = document.getElementById('confirmDeleteModal');
  const btnConfirmDelete   = document.getElementById('btnConfirmDelete');
  confirmDeleteModal.addEventListener('show.bs.modal', (event) => {
    const button  = event.relatedTarget;
    const userId  = button.getAttribute('data-id');
    btnConfirmDelete.href = `/sisec-ui/controllers/UserController.php?accion=eliminar&id=${userId}`;
  });
});
</script>
