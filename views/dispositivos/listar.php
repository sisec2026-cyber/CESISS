<?php 
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // 1️⃣ Verifica si hay sesión iniciada
verificarRol(['Administrador', 'Mantenimientos', 'Invitado']);

include __DIR__ . '/../../includes/db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Buscar con filtro
if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT * FROM dispositivos 
        WHERE 
            equipo LIKE ? OR 
            modelo LIKE ? OR 
            sucursal LIKE ? OR 
            estado LIKE ? OR 
            fecha = ? OR 
            id = ?
        ORDER BY id ASC
    ");

    $likeSearch = "%$search%";
    $stmt->bind_param("sssssi", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM dispositivos ORDER BY id ASC");
}

ob_start();
?>

<h2>Listado de dispositivos</h2>

<!-- Buscador y botón alineados -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
  <form method="GET" style="display: flex; gap: 10px;">
    <input type="text" name="search" class="form-control" placeholder="Buscar por ID, equipo, modelo, fecha..." value="<?= htmlspecialchars($search) ?>">
   <!--<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>-->
  </form>

  <a href="exportar_lista_pdf.php?search=<?= urlencode($search) ?>" class="btn btn-danger" target="_blank">
  <i class="fas fa-file-pdf"></i> Exportar PDF
</a>

  <?php if (in_array($_SESSION['usuario_rol'], ['Administrador', 'Mantenimientos'])): ?>
    <a href="registro.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar nuevo dispositivo</a>
  <?php endif; ?>
</div>



<style>
  .table td, .table th {
    white-space: nowrap;
  }

  .table td img {
    max-width: 100%;
    height: auto;
  }

  @media (max-width: 768px) {
    .btn, .form-control {
      font-size: 0.9rem;
      padding: 6px 10px;
    }

    form {
      flex-wrap: wrap;
    }

    .table td, .table th {
      font-size: 0.8rem;
    }
  }
</style>

<!-- Contenedor responsivo de la tabla -->
<div class="table-responsive">
  <table class="table table-hover table-bordered text-center align-middle">
    <thead class="table-primary">
      <tr>
        <th class="d-none d-md-table-cell">Folio</th>
        <th>Equipo</th>
        <th>Fecha de instalación</th>
        <th>Modelo</th>
        <th class="d-none d-md-table-cell">Estado</th>
        <th>Sucursal</th>
        <th class="d-none d-md-table-cell">Observaciones</th>
        <th>Serie</th>
        <th class="d-none d-md-table-cell">MAC</th>
        <th class="d-none d-md-table-cell">VMS</th>
        <th class="d-none d-md-table-cell">Servidor</th>
        <th class="d-none d-md-table-cell">Switch</th>
        <th class="d-none d-md-table-cell">Puerto</th>
        <th class="d-none d-md-table-cell">Área</th>
        <th>Imagen</th>

        <th>Acciones</th>
      </tr>
    </thead>

    <tbody id="resultado-dispositivos">
      <?php while ($device = $result->fetch_assoc()): ?>
      <tr>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['id']) ?></td>
        <td><?= htmlspecialchars($device['equipo']) ?></td>
        <td><?= htmlspecialchars($device['fecha']) ?></td>
        <td><?= htmlspecialchars($device['modelo']) ?></td>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['estado']) ?></td>
        <td><?= htmlspecialchars($device['sucursal']) ?></td>
        <td class="d-none d-md-table-cell" style="max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($device['observaciones']) ?></td>
        <td><?= htmlspecialchars($device['serie']) ?></td>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['mac']) ?></td>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['vms']) ?></td>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['servidor']) ?></td>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['switch']) ?></td>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['puerto']) ?></td>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['area']) ?></td>
        <td>
          <?php if (!empty($device['imagen'])): ?>
            <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen']) ?>" alt="Imagen" style="max-height:50px; object-fit: contain;">
          <?php endif; ?>
        </td>
       
        <td>
          <a href="device.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
          <?php if (in_array($_SESSION['usuario_rol'], ['Administrador', 'Mantenimientos'])): ?>
            <a href="editar.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-regular fa-pen-to-square"></i></a>
          <?php endif; ?>
          <?php if ($_SESSION['usuario_rol'] === 'Administrador'): ?>
            <button 
              class="btn btn-sm btn-danger" 
              data-bs-toggle="modal" 
              data-bs-target="#confirmDeleteModal"
              data-id="<?= $device['id'] ?>"
            >
              <i class="fas fa-trash-alt"></i>
            </button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>


<?php if ($_SESSION['usuario_rol'] === 'Administrador'): ?>
  <!-- Modal de Confirmación -->
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        
        <div class="modal-header">
          <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar eliminación</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          ¿Estás segura(o) de que deseas eliminar este dispositivo?
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <a href="#" id="deleteLink" class="btn btn-danger">Eliminar</a>
        </div>

      </div>
    </div>
  </div>

  <script>
    var deleteModal = document.getElementById('confirmDeleteModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      var deviceId = button.getAttribute('data-id');

      var deleteLink = deleteModal.querySelector('#deleteLink');
      deleteLink.href = 'eliminar.php?id=' + deviceId;
    });
  </script>
  <script>
document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.querySelector("input[name='search']");
  const resultadoContenedor = document.getElementById("resultado-dispositivos");

  searchInput.addEventListener("keyup", function () {
    const query = searchInput.value;

    fetch(`buscar_dispositivos.php?search=${encodeURIComponent(query)}`)
      .then(response => response.text())
      .then(html => {
        resultadoContenedor.innerHTML = html;
      })
      .catch(error => console.error("Error en la búsqueda:", error));
  });
});
</script>

<?php endif; ?>

<?php
$content = ob_get_clean();
$pageTitle = "Listado de dispositivos";
$pageHeader = "Dispositivos";
$activePage = "dispositivos";

include __DIR__ . '/../../layout.php';
?>