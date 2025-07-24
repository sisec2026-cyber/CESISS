<?php

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // 1️⃣ Verifica si hay sesión iniciada
verificarRol(['Administrador']);

$pageTitle = "Registrar usuario";
$pageHeader = "Nuevo usuario";
$activePage = "registrar";

ob_start();
?>

<h2 class="mb-4">Registrar nuevo usuario</h2>

<div class="container d-flex justify-content-center min-vh-100">
  <form action="/sisec-ui/controllers/UserController.php" method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm w-100" style="max-width: 500px;">
    <input type="hidden" name="accion" value="crear">

    <h4 class="mb-4 text-center">Registrar usuario</h4>

    

    <!-- Nombre completo -->
    <div class="mb-3">
      <label for="nombre" class="form-label">Nombre completo</label>
      <input type="text" class="form-control" id="nombre" name="nombre" required>
    </div>


    <!-- Clave -->
    <div class="mb-3">
      <label for="clave" class="form-label">Contraseña</label>
      <input type="password" class="form-control" id="clave" name="clave" required>
    </div>

    <!-- Rol -->
    <div class="mb-3">
      <label for="rol" class="form-label">Rol</label>
      <select class="form-select" id="rol" name="rol" required>
        <option value="">Seleccione un rol</option>
        <option value="Administrador">Administrador</option>
        <option value="Mantenimientos">Mantenimientos</option>
        <option value="Invitado">Invitado</option>
      </select>
    </div>

    <!-- Foto -->
    <div class="mb-3">
      <label for="foto" class="form-label">Foto de perfil</label>
      <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
    </div>

    <br>

    <!-- Botones -->
    <div class="d-flex justify-content-between">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Guardar usuario
      </button>
      <a href="?view=usuarios" class="btn btn-danger">Cancelar</a>
    </div>
  </form>
</div>



<?php
$content = ob_get_clean();

include __DIR__ . '/../../layout.php';

?>