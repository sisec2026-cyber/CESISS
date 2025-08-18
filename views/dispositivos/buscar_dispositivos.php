<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Capturista','Técnico', 'Distrital','Prevencion','Mantenimientos', 'Monitorista']);

include __DIR__ . '/../../includes/db.php';

// Filtros desde el formulario o AJAX
$ciudad = $_GET['ciudad_id'] ?? '';
$municipio = $_GET['municipio_id'] ?? '';
$sucursal = $_GET['sucursal_id'] ?? '';
$search = $_GET['search'] ?? '';
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';

// Construcción dinámica del WHERE
$condiciones = [];
$params = [];
$tipos = '';

// Búsqueda general
if (!empty($search)) {
    $condiciones[] = "(
        eq.nom_equipo LIKE ? OR 
        mo.num_modelos LIKE ? OR 
        s.nom_sucursal LIKE ? OR 
        es.status_equipo LIKE ? OR 
        d.fecha = ? OR 
        d.id = ?
    )";
    $likeSearch = "%$search%";
    $params[] = &$likeSearch;
    $params[] = &$likeSearch;
    $params[] = &$likeSearch;
    $params[] = &$likeSearch;
    $params[] = &$search;
    $params[] = &$search;
    $tipos .= 'sssssi';
}


// Filtro por ciudad
if (!empty($ciudad)) {
    $condiciones[] = "c.ID = ?";
    $params[] = &$ciudad;
    $tipos .= 'i';
}

// Filtro por municipio
if (!empty($municipio)) {
    $condiciones[] = "m.ID = ?";
    $params[] = &$municipio;
    $tipos .= 'i';
}

// Filtro por sucursal
if (!empty($sucursal)) {
    $condiciones[] = "s.ID = ?";
    $params[] = &$sucursal;
    $tipos .= 'i';
}

// Filtro por rango de fechas
if (!empty($fechaInicio) && !empty($fechaFin)) {
    $condiciones[] = "d.fecha BETWEEN ? AND ?";
    $params[] = &$fechaInicio;
    $params[] = &$fechaFin;
    $tipos .= 'ss';
}

// Construir consulta
$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
$sql = "
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
    $where
    ORDER BY d.id ASC
";

$stmt = $conn->prepare($sql);
if ($params) {
    array_unshift($params, $tipos);
    call_user_func_array([$stmt, 'bind_param'], $params);
}
$stmt->execute();
$result = $stmt->get_result();

// Mostrar resultados
while ($device = $result->fetch_assoc()):
?>
<tr>
  <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['id']) ?></td>
  <td><?= htmlspecialchars($device['nom_equipo']) ?></td>
  <td><?= htmlspecialchars($device['fecha']) ?></td>
  <td><?= htmlspecialchars($device['num_modelos']) ?></td>
  <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['status_equipo']) ?></td>
  <td>
    <?= htmlspecialchars($device['nom_sucursal'] ?? '-') ?><br>
    <small><?= htmlspecialchars($device['nom_municipio'] ?? '-') ?>, <?= htmlspecialchars($device['nom_ciudad'] ?? '-') ?></small>
  </td>
  
  <td>
    <?php if (!empty($device['imagen'])): ?>
      <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen']) ?>" alt="Imagen" style="max-height:50px; object-fit: contain;">
    <?php endif; ?>
  </td>
  <td>
    <a href="device.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
    <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador', 'Mantenimientos','Capturista','Técnico'])): ?>
      <a href="editar.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-regular fa-pen-to-square"></i></a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador'])): ?>
      <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?= $device['id'] ?>"><i class="fas fa-trash-alt"></i></button>
    <?php endif; ?>
  </td>
</tr>
<?php endwhile; ?>

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

  // Filtrar al cambiar ciudad, municipio o sucursal
function actualizarTabla() {
  const ciudadId = document.getElementById('ciudad').value;
  const municipioId = document.getElementById('municipio').value;
  const sucursalId = document.getElementById('sucursal').value;

  const params = new URLSearchParams();
  if (ciudadId) params.append('ciudad_id', ciudadId);
  if (municipioId) params.append('municipio_id', municipioId);
  if (sucursalId) params.append('sucursal_id', sucursalId);

  fetch(`buscar_dispositivos.php?${params.toString()}`)
    .then(response => response.text())
    .then(html => {
      document.getElementById('resultado-dispositivos').innerHTML = html;
    });
}

document.getElementById('ciudad').addEventListener('change', actualizarTabla);
document.getElementById('municipio').addEventListener('change', actualizarTabla);
document.getElementById('sucursal').addEventListener('change', actualizarTabla);

});
</script>
