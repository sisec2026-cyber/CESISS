<?php 
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // 1️⃣ Verifica si hay sesión iniciada
verificarRol(['Administrador', 'Mantenimientos', 'Invitado']);

include __DIR__ . '/../../includes/db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Buscar con filtro
if ($search !== '') {
    // Preparamos la consulta para buscar dispositivos por varios campos
$stmt = $conn->prepare("
    SELECT d.*, 
           s.nom_sucursal, 
           m.nom_municipio, 
           c.nom_ciudad,
           eq.nom_equipo,
           mo.num_modelos,
           es.status_equipo
    FROM dispositivos d
    LEFT JOIN sucursales s ON d.sucursal = s.ID
    LEFT JOIN municipios m ON s.municipio_id = m.ID
    LEFT JOIN ciudades c ON m.ciudad_id = c.ID
    LEFT JOIN equipos eq ON d.equipo = eq.ID
    LEFT JOIN modelos mo ON d.modelo = mo.ID
    LEFT JOIN status es ON d.status = es.ID
    WHERE 
        eq.nom_equipo LIKE ? OR 
        mo.num_modelos LIKE ? OR 
        s.nom_sucursal LIKE ? OR 
        es.status_equipo LIKE ? OR 
        d.fecha = ? OR 
        d.id = ?
    ORDER BY d.id ASC
");
    $likeSearch = "%$search%";
    $stmt->bind_param("sssssi", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
} elseif (isset($_GET['sucursal_id']) && is_numeric($_GET['sucursal_id'])) {
    $sucursalId = intval($_GET['sucursal_id']);
    $stmt = $conn->prepare("
        SELECT d.*, 
               s.nom_sucursal, 
               m.nom_municipio, 
               c.nom_ciudad,
               eq.nom_equipo,
               mo.num_modelos,
               es.status_equipo
        FROM dispositivos d
        LEFT JOIN sucursales s ON d.sucursal = s.ID
        LEFT JOIN municipios m ON s.municipio_id = m.ID
        LEFT JOIN ciudades c ON m.ciudad_id = c.ID
        LEFT JOIN equipos eq ON d.equipo = eq.ID
        LEFT JOIN modelos mo ON d.modelo = mo.ID
        LEFT JOIN status es ON d.estado = es.ID
        WHERE d.sucursal = ?
        ORDER BY d.id ASC
    ");
    $stmt->bind_param("i", $sucursalId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false; // no mostrar nada si no hay sucursal
}

// Verificamos si la consulta devolvió resultados

ob_start();
?>

<h2>Listado de dispositivos</h2>

<!-- Buscador y botón alineados -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
  <form method="GET" style="display: flex; gap: 10px;">
    <!--input type="text" name="search" class="form-control" placeholder="Buscar por ID, equipo, modelo, fecha..." value="<?= htmlspecialchars($search) ?>"-->
   <!--<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>-->
  </form>

  <button id="btnExportar" class="btn btn-danger">
  <i class="fas fa-file-pdf"></i> Exportar PDF
</button>

  <?php if (in_array($_SESSION['usuario_rol'], ['Administrador', 'Mantenimientos'])): ?>
    <a href="registro.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar nuevo dispositivo</a>
  <?php endif; ?>
</div>

<script>
  document.getElementById("btnExportar").addEventListener("click", function () {
    const ciudad = document.getElementById("ciudad").value;
    const municipio = document.getElementById("municipio").value;
    const sucursal = document.getElementById("sucursal").value;

    let url = `exportar_lista_pdf.php?`;

    if (ciudad) url += `ciudad=${ciudad}&`;
    if (municipio) url += `municipio=${municipio}&`;
    if (sucursal) url += `sucursal=${sucursal}`;

    window.open(url, '_blank');
  });
</script>

                                            <!-- Filtros Busqueda -->
<div class="row mb-3">
  <div class="col-md-4">
    <label for="ciudad" class="form-label">Ciudad</label>
    <select id="ciudad" class="form-select">
      <option value="">-- Selecciona una ciudad --</option>
      <?php
        $ciudades = $conn->query("SELECT ID, nom_ciudad FROM ciudades ORDER BY nom_ciudad");
        while ($row = $ciudades->fetch_assoc()):
      ?>
        <option value="<?= $row['ID'] ?>"><?= htmlspecialchars($row['nom_ciudad']) ?></option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="col-md-4">
    <label for="municipio" class="form-label">Municipio</label>
    <select id="municipio" class="form-select" disabled>
      <option value="">-- Selecciona un municipio --</option>
    </select>
  </div>

  <div class="col-md-4">
    <label for="sucursal" class="form-label">Sucursal</label>
    <select id="sucursal" class="form-select" disabled>
      <option value="">-- Selecciona una sucursal --</option>
    </select>
  </div>
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
        <th>Determinante</th>
        <th>Equipo</th>
        <th>Fecha de instalación</th>
        <th>Modelo</th>
        <th>Estado</th>
        <th>Sucursal</th>
        <th>Imagen</th>
        <th>Acciones</th>
      </tr>
    </thead>

    <tbody id="resultado-dispositivos">
      <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($device = $result->fetch_assoc()): ?>
      <tr>
        <!-- folio -->
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['id']) ?></td>
        <td><?= htmlspecialchars($device['nom_equipo']) ?></td>
        <td><?= htmlspecialchars($device['fecha']) ?></td>
        <td><?= htmlspecialchars($device['num_modelos']) ?></td>
        <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['status_equipo']) ?></td>
<td>
  <?= htmlspecialchars($device['nom_sucursal']) ?>
  <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($device['nom_municipio']) ?>, <?= htmlspecialchars($device['nom_ciudad']) ?>">
    <i class="fas fa-info-circle text-muted"></i>
  </span>
</td>
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
      <?php else: ?>
          <tr>
    <td colspan="8" class="text-center text-muted">Selecciona una sucursal para ver los dispositivos</td>
  </tr>
      <?php endif; ?>
    </tbody>
                                                              <!-- Fin de la tabla -->

  </table>
</div>
<?php if ($_SESSION['usuario_rol'] === 'Administrador' || $_SESSION['usuario_rol'] === 'Invitado'): ?>
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
                                                  <!-- Filtros -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  const ciudadSelect = document.getElementById('ciudad');
  const municipioSelect = document.getElementById('municipio');
  const sucursalSelect = document.getElementById('sucursal');
  const resultadoContenedor = document.getElementById('resultado-dispositivos');

  ciudadSelect.addEventListener('change', function () {
    const ciudadId = this.value;

    municipioSelect.innerHTML = '<option value="">-- Selecciona un municipio --</option>';
    sucursalSelect.innerHTML = '<option value="">-- Selecciona una sucursal --</option>';
    municipioSelect.disabled = true;
    sucursalSelect.disabled = true;

    if (ciudadId) {
      fetch(`obtener_municipios.php?ciudad_id=${ciudadId}`)
        .then(response => response.json())
        .then(data => {
          municipioSelect.disabled = false;
          data.forEach(municipio => {
            municipioSelect.innerHTML += `<option value="${municipio.ID}">${municipio.nom_municipio}</option>`;
          });
        });
    }
  });

  municipioSelect.addEventListener('change', function () {
    const municipioId = this.value;

    sucursalSelect.innerHTML = '<option value="">-- Selecciona una sucursal --</option>';
    sucursalSelect.disabled = true;

    if (municipioId) {
      fetch(`obtener_sucursales.php?municipio_id=${municipioId}`)
        .then(response => response.json())
        .then(data => {
          sucursalSelect.disabled = false;
          data.forEach(sucursal => {
            sucursalSelect.innerHTML += `<option value="${sucursal.ID}">${sucursal.nom_sucursal}</option>`;
          });
        });
    }
  });

  // ✅ Mueve esto fuera de la función
  sucursalSelect.addEventListener('change', actualizarTabla);

  function actualizarTabla() {
    const ciudadId = ciudadSelect.value;
    const municipioId = municipioSelect.value;
    const sucursalId = sucursalSelect.value;

    if (!sucursalId) {
      resultadoContenedor.innerHTML = `
        <tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
      return;
    }

    const params = new URLSearchParams();
    if (ciudadId) params.append('ciudad_id', ciudadId);
    if (municipioId) params.append('municipio_id', municipioId);
    if (sucursalId) params.append('sucursal_id', sucursalId);

    fetch(`buscar_dispositivos.php?${params.toString()}`)
      .then(response => response.text())
      .then(html => {
        resultadoContenedor.innerHTML = html;
      });
  }

  // Opcional: limpiar tabla si cambian ciudad o municipio
  ciudadSelect.addEventListener('change', () => {
    resultadoContenedor.innerHTML = `
      <tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
  });

  municipioSelect.addEventListener('change', () => {
    resultadoContenedor.innerHTML = `
      <tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
  });
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
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