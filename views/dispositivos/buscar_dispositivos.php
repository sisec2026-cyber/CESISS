<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Capturista','Técnico', 'Distrital','Prevencion','Monitorista','Mantenimientos']);
include __DIR__ . '/../../includes/db.php';

// Filtros fijos del usuario según su rol/permisos
$filtroRegion    = $_SESSION['usuario_region'] ?? null;
$filtroCiudad    = $_SESSION['usuario_ciudad'] ?? null;
$filtroMunicipio = $_SESSION['usuario_municipio'] ?? null;
$filtroSucursal  = $_SESSION['usuario_sucursal'] ?? null;

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
    d.id = ?)";
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
  $sql = "SELECT d.*, 
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
  ORDER BY d.id ASC";

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
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['determinante']) ?></td>
    <td><?= htmlspecialchars($device['nom_equipo']) ?></td>
    <td><?= htmlspecialchars($device['fecha']) ?></td>
    <td><?= htmlspecialchars($device['num_modelos']) ?></td>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['status_equipo']) ?></td>
    <td><?= htmlspecialchars($device['nom_sucursal'] ?? '-') ?>
      <br><small><?= htmlspecialchars($device['nom_municipio'] ?? '-') ?>, <?= htmlspecialchars($device['nom_ciudad'] ?? '-') ?></small></td>
    <td><?php if (!empty($device['imagen'])): ?><img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen']) ?>" alt="Imagen" style="max-height:50px; object-fit: contain;"><?php endif; ?></td>
    <td><a href="device.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
        <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador', 'Mantenimientos','Capturista','Técnico'])): ?>
        <a href="editar.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-regular fa-pen-to-square"></i></a>
        <?php endif; ?>
        <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador'])): ?>
        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?= $device['id'] ?>"><i class="fas fa-trash-alt"></i></button>
        <?php endif; ?></td>
  </tr><?php endwhile; ?>

<!-- Filtros -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  const ciudadSelect = document.getElementById('ciudad');
  const municipioSelect = document.getElementById('municipio');
  const sucursalSelect = document.getElementById('sucursal');
  const resultadoContenedor = document.getElementById('resultado-dispositivos');
  const botonPDF = document.getElementById('btnExportar');

  // Valores fijos del usuario (inyectados desde PHP)
  const FIXED_REGION    = <?= $filtroRegion    ? (int)$filtroRegion    : 'null' ?>;
  const FIXED_CIUDAD    = <?= $filtroCiudad    ? (int)$filtroCiudad    : 'null' ?>;
  const FIXED_MUNICIPIO = <?= $filtroMunicipio ? (int)$filtroMunicipio : 'null' ?>;
  const FIXED_SUCURSAL  = <?= $filtroSucursal  ? (int)$filtroSucursal  : 'null' ?>;

  // Carga municipios al cambiar ciudad
  ciudadSelect.addEventListener('change', function() {
    const ciudadId= this.value;
    municipioSelect.innerHTML= '<option value="">-- Selecciona un municipio --</option>';
    sucursalSelect.innerHTML= '<option value="">-- Selecciona una sucursal --</option>';
    municipioSelect.disabled = !ciudadId || !!FIXED_MUNICIPIO;
    sucursalSelect.disabled = true && !FIXED_SUCURSAL;
      if (ciudadId) {
      fetch(`obtener_municipios.php?ciudad_id=${ciudadId}`)
        .then(r => r.json())
        .then(data => {
          municipioSelect.disabled = false || !!FIXED_MUNICIPIO;
          data.forEach(m => {
            municipioSelect.innerHTML += `<option value="${m.ID}">${m.nom_municipio}</option>`;
          });
            if (FIXED_MUNICIPIO) {
              municipioSelect.value = String(FIXED_MUNICIPIO);
              municipioSelect.dispatchEvent(new Event('change'));
              municipioSelect.disabled = true;
            }
        });
      }
  });

  // Carga sucursales al cambiar municipio
  municipioSelect.addEventListener('change', function () {
    const municipioId = this.value;
    sucursalSelect.innerHTML = '<option value="">-- Selecciona una sucursal --</option>';
    sucursalSelect.disabled = !municipioId || !!FIXED_SUCURSAL;
      if (municipioId) {
        fetch(`obtener_sucursales.php?municipio_id=${municipioId}`)
          .then(r => r.json())
          .then(data => {
            sucursalSelect.disabled = false || !!FIXED_SUCURSAL;
            data.forEach(s => {
              sucursalSelect.innerHTML += `<option value="${s.ID}">${s.nom_sucursal}</option>`;
            });
            if (FIXED_SUCURSAL) {
              sucursalSelect.value = String(FIXED_SUCURSAL);
              sucursalSelect.disabled = true;
              actualizarTabla();
            }
          });
      }
  });

  sucursalSelect.addEventListener('change', actualizarTabla);
  // Filtrar al cambiar ciudad, municipio o sucursal
  sucursalSelect.addEventListener('change', actualizarTabla);
  function actualizarTabla() {
    const ciudadId = ciudadSelect.value;
    const municipioId = municipioSelect.value;
    const sucursalId = sucursalSelect.value;
      if (ciudadId && municipioId && sucursalId) {
        botonPDF.style.display = 'inline-block';
      } else {
        botonPDF.style.display = 'none';
      }
      if (!sucursalId) {
        resultadoContenedor.innerHTML = `<tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
        return;
      }
      const params = new URLSearchParams();
      if (ciudadId) params.append('ciudad_id', ciudadId);
      if (municipioId) params.append('municipio_id', municipioId);
      if (sucursalId) params.append('sucursal_id', sucursalId);
      fetch(`buscar_dispositivos.php?${params.toString()}`)
      .then(r => r.text())
      .then(html => { resultadoContenedor.innerHTML = html; });
  }

  //Precarga automática según filtros fijos del usuario
  (function initByUserScope() {
    if (FIXED_CIUDAD) {
      ciudadSelect.value = String(FIXED_CIUDAD);
      ciudadSelect.disabled = true;
      ciudadSelect.dispatchEvent(new Event('change'));
    }
    if (FIXED_SUCURSAL && FIXED_MUNICIPIO && FIXED_CIUDAD) {
      setTimeout(() => {
        if (!municipioSelect.value && FIXED_MUNICIPIO) municipioSelect.value = String(FIXED_MUNICIPIO);
        if (!sucursalSelect.value && FIXED_SUCURSAL)   sucursalSelect.value   = String(FIXED_SUCURSAL);
        actualizarTabla();
      }, 300);
    }
  })();
  // Limpia tabla al cambiar ciudad/municipio
  ciudadSelect.addEventListener('change', () => {
    resultadoContenedor.innerHTML = `<tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
  });
  municipioSelect.addEventListener('change', () => {
    resultadoContenedor.innerHTML = `<tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
  });
});
</script>