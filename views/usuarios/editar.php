<?php

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // 1️⃣ Verifica si hay sesión iniciada
verificarRol(['Administrador']);

session_start();


include __DIR__ . '/../../includes/conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
  die("ID de usuario inválido.");
}

$resultado = $conexion->query("SELECT * FROM usuarios WHERE id = $id");
$usuario = $resultado->fetch_assoc();

if (!$usuario) {
  die("Usuario no encontrado.");
}

$pageTitle = "Editar usuario";
$pageHeader = "Editar usuario";
$activePage = "usuarios";

ob_start();
?>

<h2 class="mb-4">Editar usuario</h2>

<div class="container d-flex justify-content-center">
  <form action="/sisec-ui/controllers/UserController.php" method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm w-100" style="max-width: 500px;">
    <input type="hidden" name="accion" value="actualizar">
    <input type="hidden" name="id" value="<?= $usuario['id'] ?>">

    <!-- Nombre -->
    <div class="mb-3">
      <label for="nombre" class="form-label">Nombre completo</label>
      <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
    </div>

    <!-- Rol -->
    <div class="mb-3">
      <label for="rol" class="form-label">Rol</label>
      <select class="form-select" id="rol" name="rol" required>
        <option value="">Seleccione un rol</option>
        <option value="Administrador"<?= $usuario['rol'] == 'Administrador' ? 'selected' : '' ?>>Administrador</option>
        <option value="Mantenimientos" <?= $usuario['rol'] == 'Mantenimientos' ? 'selected' : '' ?>>Mantenimientos</option>
        <option value="Invitado" <?= $usuario['rol'] == 'Invitado' ? 'selected' : '' ?>>Invitado</option>
      </select>
    </div>

    <!-- Foto -->
    <div class="mb-3">
      <?php if (!empty($usuario['foto']) && file_exists(__DIR__ . '/../../uploads/usuarios/' . $usuario['foto'])): ?>
        <label class="form-label">Foto actual</label><br>
        <img src="/sisec-ui/uploads/usuarios/<?= htmlspecialchars($usuario['foto']) ?>" alt="foto" width="80" class="mb-2 rounded-circle">
      <?php endif; ?>
      <label for="foto" class="form-label">Cambiar foto de perfil</label>
      <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
    </div>

    <div class="d-flex justify-content-between">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar cambios</button>
      <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
