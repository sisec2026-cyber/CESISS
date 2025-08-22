<?php 
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // Verifica si hay sesión iniciada
verificarRol(['Superadmin','Administrador', 'Mantenimientos', 'Invitado','Técnico','Capturista','Distrital','Prevencion','Monitorista']);
include __DIR__ . '/../../includes/db.php';

//Filtros del usuario logueado ===
  $userId = $_SESSION['usuario_id'] ?? null;
  $filtroRegion = $filtroCiudad = $filtroMunicipio = $filtroSucursal = null;
  if ($userId) {
    $qUser = $conn->prepare("SELECT region, ciudad, municipio, sucursal FROM usuarios WHERE id = ?");
    $qUser->bind_param("i", $userId);
    $qUser->execute();
    $userFilter = $qUser->get_result()->fetch_assoc() ?: [];
    $filtroRegion    = !empty($userFilter['region'])    ? (int)$userFilter['region']    : null;
    $filtroCiudad    = !empty($userFilter['ciudad'])    ? (int)$userFilter['ciudad']    : null;
    $filtroMunicipio = !empty($userFilter['municipio']) ? (int)$userFilter['municipio'] : null;
    $filtroSucursal  = !empty($userFilter['sucursal'])  ? (int)$userFilter['sucursal']  : null;
  }

  //Helper para armar WHERE por alcance de usuario
  function buildUserScopeWhere(&$types, &$params, $fRegion, $fCiudad, $fMunicipio, $fSucursal) {
    $extra = [];
    if ($fSucursal) {
      $extra[] = "d.sucursal = ?";
      $types  .= "i";
      $params[] = $fSucursal;
    } elseif ($fMunicipio) {
      $extra[] = "m.ID = ?";
      $types  .= "i";
      $params[] = $fMunicipio;
    } elseif ($fCiudad) {
      $extra[] = "c.ID = ?";
      $types  .= "i";
      $params[] = $fCiudad;
    } elseif ($fRegion) {
      $extra[] = "c.region_id = ?";
      $types  .= "i";
      $params[] = $fRegion;
    }
    return $extra;
  }
  $search = isset($_GET['search']) ? trim($_GET['search']) : '';
  //Construcción de consulta según contexto (búsqueda / sucursal / alcance)
  if ($search !== '') {
    //BÚSQUEDA con alcance de usuario
    $types = "sssssi";
    $params = [];
    $sql = "SELECT d.*, 
      det.numero_tienda AS determinante,
      s.nom_sucursal, 
      m.nom_municipio, 
      c.nom_ciudad,
      eq.nom_equipo,
      mo.num_modelos,
      es.status_equipo
    FROM dispositivos d
    LEFT JOIN sucursales s ON d.sucursal = s.ID
    LEFT JOIN determinante det ON s.id = det.sucursal_id
    LEFT JOIN municipios m ON s.municipio_id = m.ID
    LEFT JOIN ciudades c ON m.ciudad_id = c.ID
    LEFT JOIN equipos eq ON d.equipo = eq.ID
    LEFT JOIN modelos mo ON d.modelo = mo.ID
    LEFT JOIN status es ON d.estado = es.ID
    WHERE (
      eq.nom_equipo LIKE ? OR 
      mo.num_modelos LIKE ? OR 
      s.nom_sucursal LIKE ? OR 
      es.status_equipo LIKE ? OR 
      d.fecha = ? OR 
      d.id = ?)";
  //Filtro por alcance de usuario
  $extra  = buildUserScopeWhere($types, $params, $filtroRegion, $filtroCiudad, $filtroMunicipio, $filtroSucursal);
    if ($extra) $sql .= " AND " . implode(" AND ", $extra);
    $sql .= " ORDER BY d.id ASC";
    $stmt = $conn->prepare($sql);
    $likeSearch = "%$search%";
    $bindValues = [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $search, $search];
    $paramsFull = array_merge($bindValues, $params);
    $stmt->bind_param($types, ...$paramsFull);
    $stmt->execute();
    $result = $stmt->get_result();
  } elseif (isset($_GET['sucursal_id']) && is_numeric($_GET['sucursal_id'])) {
    //Listado por sucursal seleccionada
    $sucursalId = intval($_GET['sucursal_id']);
    $stmt = $conn->prepare("SELECT d.*, 
      det.numero_tienda AS determinante,
      s.nom_sucursal, 
      m.nom_municipio, 
      c.nom_ciudad,
      eq.nom_equipo,
      mo.num_modelos,
      es.status_equipo
    FROM dispositivos d
    LEFT JOIN sucursales s ON d.sucursal = s.ID
    LEFT JOIN determinante det ON s.id = det.sucursal_id
    LEFT JOIN municipios m ON s.municipio_id = m.ID
    LEFT JOIN ciudades c ON m.ciudad_id = c.ID
    LEFT JOIN equipos eq ON d.equipo = eq.ID
    LEFT JOIN modelos mo ON d.modelo = mo.ID
    LEFT JOIN status es ON d.estado = es.ID
    WHERE d.sucursal = ?
    ORDER BY d.id ASC");
    $stmt->bind_param("i", $sucursalId);
    $stmt->execute();
    $result = $stmt->get_result();
  } else {
    //Precarga según alcance de usuario (si tiene filtros fijos)
    $types = "";
    $params = [];
    $extra = buildUserScopeWhere($types, $params, $filtroRegion, $filtroCiudad, $filtroMunicipio, $filtroSucursal);
    if ($extra) {
      $sql = "SELECT d.*, 
        det.numero_tienda AS determinante,
        s.nom_sucursal, 
        m.nom_municipio, 
        c.nom_ciudad,
        eq.nom_equipo,
        mo.num_modelos,
        es.status_equipo
      FROM dispositivos d
      LEFT JOIN sucursales s ON d.sucursal = s.ID
      LEFT JOIN determinante det ON s.id = det.sucursal_id
      LEFT JOIN municipios m ON s.municipio_id = m.ID
      LEFT JOIN ciudades c ON m.ciudad_id = c.ID
      LEFT JOIN equipos eq ON d.equipo = eq.ID
      LEFT JOIN modelos mo ON d.modelo = mo.ID
      LEFT JOIN status es ON d.estado = es.ID
      WHERE " . implode(" AND ", $extra) . "
      ORDER BY d.id ASC";
      $stmt = $conn->prepare($sql);
      if ($types) {
        $stmt->bind_param($types, ...$params);
      }
      $stmt->execute();
      $result = $stmt->get_result();
    } else {
      //El usuario puede ver todo, pero mantiene la UX: no mostrar nada hasta elegir sucursal
      $result = false;
    }
}
ob_start();
?>

  <h2>Listado de dispositivos</h2>
  <!-- Buscador y botón alineados -->
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
    <form id="formBusqueda" method="GET" style="display: flex; gap: 10px;">
      <input type="text" id="search" name="search" class="form-control" style="width:300px" placeholder="Busca equipo, modelo, fecha..." disabled>
      <button type="submit" class="btn btn-primary" disabled><i class="fas fa-search"></i></button>
    </form>
    <button id="btnExportar" class="btn btn-danger" style="display: none;"><i class="fas fa-file-pdf"></i> Exportar Listado</button>
      <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador', 'Mantenimientos','Capturista','Técnico'])): ?>
      <a href="registro.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar nuevo dispositivo</a>
      <?php endif; ?>
  </div>

  <script>
    document.getElementById("btnExportar").addEventListener("click", function() {
      const ciudad = document.getElementById("ciudad").value;
      const municipio = document.getElementById("municipio").value;
      const sucursal = document.getElementById("sucursal").value;
      let url = `exportar_lista_pdf.php?`;
      if(ciudad) url += `ciudad = ${ciudad}&`;
      if(municipio) url += `municipio = ${municipio}&`;
      if(sucursal) url += `sucursal = ${sucursal}`;
      window.open(url, '_blank');
    });
  </script>

  <!-- Filtros de búsqueda -->
  <div class="row mb-3">
    <div class="col-md-4">
      <label for="ciudad" class="form-label">Ciudad</label>
      <select id="ciudad" class="form-select" <?= $filtroCiudad ? 'disabled' : '' ?>>
        <option value="">-- Selecciona una ciudad --</option>
        <?php
        // Cargar ciudades según alcance
        $qCiudades = "SELECT ID, nom_ciudad FROM ciudades";
        $w = [];
        if ($filtroRegion) { $w[] = "region_id = " . (int)$filtroRegion; } // (ajusta campo si difiere)
        if ($filtroCiudad) { $w[] = "ID = " . (int)$filtroCiudad; }
        if ($w) $qCiudades .= " WHERE " . implode(" AND ", $w);
        $qCiudades .= " ORDER BY nom_ciudad";
        $ciudades = $conn->query($qCiudades);
        while ($row = $ciudades->fetch_assoc()): ?>
        <option value="<?= $row['ID'] ?>" <?= ($filtroCiudad == $row['ID']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($row['nom_ciudad']) ?>
        </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label for="municipio" class="form-label">Municipio</label>
      <select id="municipio" class="form-select" <?= $filtroMunicipio ? 'disabled' : '' ?>>
        <option value=""><?= $filtroCiudad ? '-- Selecciona un municipio --' : '-- Selecciona un municipio --' ?></option>
      </select>
    </div>
    <div class="col-md-4">
      <label for="sucursal" class="form-label">Sucursal</label>
      <select id="sucursal" class="form-select" <?= $filtroSucursal ? 'disabled' : '' ?>>
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

  <!-- Contenedor responsivo de la tabla (solo de escritorio) -->
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
          <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['determinante'] ?? '') ?></td>
          <td><?= htmlspecialchars($device['nom_equipo']) ?></td>
          <td><?= htmlspecialchars($device['fecha']) ?></td>
          <td><?= htmlspecialchars($device['num_modelos']) ?></td>
          <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['status_equipo']) ?></td>
          <td><?= htmlspecialchars($device['nom_sucursal']) ?>
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
            <a href="device.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-primary btn-ver" data-id="<?= $device['id'] ?>"><i class="fas fa-eye"></i>
            </a>
            <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Capturista','Técnico'])): ?>
              <a href="editar.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-secondary">
                <i class="fa-regular fa-pen-to-square"></i>
              </a>
            <?php endif; ?>
            <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Capturista','Técnico'])): ?>
              <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?= $device['id'] ?>">
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
    </table>
  </div>

  <!-- Modal de Confirmación -->
  <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador', 'Mantenimientos', 'Invitado','Técnico','Capturista','Distrital','Prevencion','Monitorista'])): ?>
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
    
  <!-- Restricciones de usuario -->
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    const ciudadSelect = document.getElementById('ciudad');
    const municipioSelect = document.getElementById('municipio');
    const sucursalSelect = document.getElementById('sucursal');
    const resultadoContenedor = document.getElementById('resultado-dispositivos');
    const botonPDF = document.getElementById('btnExportar');
    //Búsqueda dinámica
    const searchInput  = document.getElementById('search');
    const searchButton = document.querySelector('#formBusqueda button');
    //Valores fijos del usuario
    const FIXED_REGION    = <?= $filtroRegion    ? (int)$filtroRegion    : 'null' ?>;
    const FIXED_CIUDAD    = <?= $filtroCiudad    ? (int)$filtroCiudad    : 'null' ?>;
    const FIXED_MUNICIPIO = <?= $filtroMunicipio ? (int)$filtroMunicipio : 'null' ?>;
    const FIXED_SUCURSAL  = <?= $filtroSucursal  ? (int)$filtroSucursal  : 'null' ?>;
    //Carga municipios al cambiar ciudad
    ciudadSelect.addEventListener('change', function () {
      const ciudadId = this.value;
      municipioSelect.innerHTML = '<option value="">-- Selecciona un municipio --</option>';
      sucursalSelect.innerHTML = '<option value="">-- Selecciona una sucursal --</option>';
      municipioSelect.disabled = !ciudadId || !!FIXED_MUNICIPIO;
      sucursalSelect.disabled = true && !FIXED_SUCURSAL;
      if (ciudadId) {
        fetch(`obtener_municipios.php?ciudad_id=${ciudadId}`)
          .then(response => response.json())
          .then(data => {
            municipioSelect.disabled = false || !!FIXED_MUNICIPIO;
            data.forEach(m => {
              municipioSelect.innerHTML += `<option value="${m.ID}">${m.nom_municipio}</option>`;
            });
            // Si el usuario tiene municipio fijo, selecciónalo
            if (FIXED_MUNICIPIO) {
              municipioSelect.value = String(FIXED_MUNICIPIO);
              municipioSelect.dispatchEvent(new Event('change'));
              municipioSelect.disabled = true;
            }
          });
      }
    });
    //Carga sucursales al cambiar municipio
    municipioSelect.addEventListener('change', function () {
      const municipioId = this.value;
      sucursalSelect.innerHTML = '<option value="">-- Selecciona una sucursal --</option>';
      sucursalSelect.disabled = !municipioId || !!FIXED_SUCURSAL;
      if (municipioId) {
        fetch(`obtener_sucursales.php?municipio_id=${municipioId}`)
          .then(response => response.json())
          .then(data => {
            sucursalSelect.disabled = false || !!FIXED_SUCURSAL;
            data.forEach(s => {
              sucursalSelect.innerHTML += `<option value="${s.ID}">${s.nom_sucursal}</option>`;
            });
            // Si el usuario tiene sucursal fija, se selecciona
            if (FIXED_SUCURSAL) {
              sucursalSelect.value = String(FIXED_SUCURSAL);
              sucursalSelect.disabled = true;
              actualizarTabla();
            }
          });
      }
    });
    // Cambio de sucursal → actualizar tabla
    sucursalSelect.addEventListener('change', actualizarTabla);
    function actualizarTabla() {
      const ciudadId = ciudadSelect.value;
      const municipioId = municipioSelect.value;
      const sucursalId = sucursalSelect.value;
      //Mostrar botón PDF solo con los 3 niveles
      if (ciudadId && municipioId && sucursalId) {
        botonPDF.style.display = 'inline-block';
      } else {
        botonPDF.style.display = 'none';
      }
      //Control de búsqueda: habilitar solo si hay sucursal
      if (!sucursalId) {
        if (searchInput && searchButton) {
          searchInput.disabled  = true;
          searchButton.disabled = true;
          searchInput.value = '';
        }
        resultadoContenedor.innerHTML = `<tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
        return;
      } else {
        if (searchInput && searchButton) {
          searchInput.disabled  = false;
          searchButton.disabled = false;
        }
      }
      const params = new URLSearchParams();
      if (ciudadId) params.append('ciudad_id', ciudadId);
      if (municipioId) params.append('municipio_id', municipioId);
      if (sucursalId) params.append('sucursal_id', sucursalId);
      if (searchInput && searchInput.value.trim()) {
        params.append('search', searchInput.value.trim());
      }
      fetch(`buscar_dispositivos.php?${params.toString()}`)
        .then(response => response.text())
        .then(html => { resultadoContenedor.innerHTML = html; });
    }
    //Búsqueda en vivo
    if (searchInput) {
      searchInput.addEventListener('keyup', function() {
        const query = this.value.trim();
        const ciudadId = ciudadSelect.value;
        const municipioId = municipioSelect.value;
        const sucursalId = sucursalSelect.value;
        const params = new URLSearchParams();
        if (ciudadId) params.append('ciudad_id', ciudadId);
        if (municipioId) params.append('municipio_id', municipioId);
        if (sucursalId) params.append('sucursal_id', sucursalId);
        if (query) params.append('search', query);
        fetch(`buscar_dispositivos.php?${params.toString()}`)
          .then(response => response.text())
          .then(html => { resultadoContenedor.innerHTML = html; });
      });
    }
    //Precarga automática según filtros fijos del usuario
    (function initByUserScope() {
      if(FIXED_CIUDAD) {
        ciudadSelect.value = String(FIXED_CIUDAD);
        ciudadSelect.disabled = true;
        ciudadSelect.dispatchEvent(new Event('change'));
      }
      if(FIXED_SUCURSAL && FIXED_MUNICIPIO && FIXED_CIUDAD) {
        setTimeout(() => {
          if(!municipioSelect.value && FIXED_MUNICIPIO) municipioSelect.value = String(FIXED_MUNICIPIO);
          if(!sucursalSelect.value && FIXED_SUCURSAL)   sucursalSelect.value   = String(FIXED_SUCURSAL);
          actualizarTabla();
        }, 300);
      }
    })();
    //Limpia tabla al cambiar ciudad/municipio
    ciudadSelect.addEventListener('change', () => {
      resultadoContenedor.innerHTML = `<tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
    });
    municipioSelect.addEventListener('change', () => {
      resultadoContenedor.innerHTML = `<tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
    });
  });
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", function () {
    const tbody            = document.getElementById('resultado-dispositivos');
    const ciudadSelect     = document.getElementById('ciudad');
    const municipioSelect  = document.getElementById('municipio');
    const sucursalSelect   = document.getElementById('sucursal');
    const searchInput      = document.getElementById('search');

    // Delegación de eventos para que funcione con contenido AJAX
    tbody.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-ver');
      if (!btn) return;

      e.preventDefault();

      // 1) Obtenemos el ID del dispositivo
      const id = btn.dataset.id || new URL(btn.href, window.location.origin).searchParams.get('id');

      // 2) Leemos los filtros actuales de la UI
      const params = new URLSearchParams();
      const ciudadId    = ciudadSelect?.value;
      const municipioId = municipioSelect?.value;
      const sucursalId  = sucursalSelect?.value;
      const q           = (searchInput?.value || '').trim();

      if (ciudadId)    params.set('ciudad_id', ciudadId);
      if (municipioId) params.set('municipio_id', municipioId);
      if (sucursalId)  params.set('sucursal_id', sucursalId);
      if (q)           params.set('search', q);

      // 3) Armamos la URL actual del listado con esos filtros
      const baseList = window.location.pathname; // p. ej. /sisec/privado/dispositivos/listar.php
      const currentListUrl = params.toString() ? `${baseList}?${params.toString()}` : baseList;

      // 4) Redirigimos a device.php con el return_url correcto
      const destino = `device.php?id=${encodeURIComponent(id)}&return_url=${encodeURIComponent(currentListUrl)}`;
      window.location.href = destino;
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
