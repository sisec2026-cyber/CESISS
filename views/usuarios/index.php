<?php

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // 1️⃣ Verifica si hay sesión iniciada
verificarRol(['Superadmin', 'Administrador']);

?>

<?php
$pageTitle = "Usuarios";
$pageHeader = "Gestión de usuarios";
$activePage = "usuarios";

include __DIR__ . '/../../includes/conexion.php';
ob_start();

// Obtener lista de usuarios
$usuarios = $conexion->query("SELECT id, nombre, rol, foto FROM usuarios");
?>

<h2 class="mb-4">Usuarios registrados</h2>

<div class="mb-3 text-end">
  <a href="registrar.php" class="btn btn-success">
    <i class="fas fa-user-plus me-1"></i> Nuevo usuario
  </a>
</div>

<div class="table-responsive">
  <table class="table table-hover table-bordered text-center align-middle">
    <thead class="table-primary">
      <tr>
        <th style="width: 50px;"><i class="fas fa-user"></i></th>
        <th>Nombre</th>
        <th>Rol</th>
        <th style="width: 150px;" class="text-center">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($u = $usuarios->fetch_assoc()): ?>
        <tr>
          <td class="text-center">
            <?php if (!empty($u['foto']) && file_exists(__DIR__ . "/../../uploads/usuarios/" . $u['foto'])): ?>
              <img src="/sisec-ui/uploads/usuarios/<?= htmlspecialchars($u['foto']) ?>" alt="foto" width="40" height="40" class="rounded-circle">
            <?php else: ?>
              <i class="fas fa-user-circle fa-2x text-secondary"></i>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($u['nombre']) ?></td>
          <td><?= htmlspecialchars($u['rol']) ?></td>
          <td class="text-center">
            <a href="editar.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-warning me-1">
              <i class="fas fa-edit"></i>
            </a>

            <!-- Botón eliminar que abre el modal -->
            <button type="button" 
                    class="btn btn-sm btn-danger btn-eliminar" 
                    data-bs-toggle="modal" 
                    data-bs-target="#confirmDeleteModal" 
                    data-id="<?= $u['id'] ?>">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  </div>

<!-- Modal de confirmación -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar eliminación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        ¿Seguro que quieres eliminar este usuario?
      </div>
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

<!-- Script para actualizar el enlace de eliminar cuando se abra el modal -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var confirmDeleteModal = document.getElementById('confirmDeleteModal');
    var btnConfirmDelete = document.getElementById('btnConfirmDelete');

    confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      var userId = button.getAttribute('data-id');
      var urlDelete = `/sisec-ui/controllers/UserController.php?accion=eliminar&id=${userId}`;
      btnConfirmDelete.href = urlDelete;
    });
  });
</script>