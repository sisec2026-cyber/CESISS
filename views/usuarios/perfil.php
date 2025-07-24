<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();

require_once __DIR__ . '/../../includes/db.php';

$usuarioId = $_SESSION['usuario_id'];

$stmt = $conn->prepare("SELECT nombre, rol, foto FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
?>

<?php
$pageTitle = "Perfil de usuario";
$pageHeader = "Mi perfil";
$activePage = "perfil";

ob_start();
?>

<div class="card mx-auto shadow-sm" style="max-width: 600px;">
  <div class="card-body">
    <h5 class="text-center mb-3"></h5>

    <form action="actualizar_perfil.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $usuarioId ?>">

      <div class="text-center mb-3">
        <img src="/sisec-ui/uploads/usuarios/<?= htmlspecialchars($usuario['foto']) ?>"
          alt="Foto de perfil"
          class="rounded-circle mb-3"
          style="width: 100px; height: 100px; object-fit: cover;">
      </div>

      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Foto de perfil (opcional)</label>
        <input type="file" name="foto" class="form-control">
      </div>

      <div class="mb-3">
        <label class="form-label">Nueva contraseña (opcional)</label>
        <input type="password" name="password" class="form-control" placeholder="Deja en blanco para no cambiarla">
      </div>

      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>