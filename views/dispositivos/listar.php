listar
<?php 
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // Verifica si hay sesión iniciada
verificarRol(['Superadmin','Administrador', 'Mantenimientos', 'Invitado','Técnico','Capturista','Distrital','Prevencion','Monitorista']);
include __DIR__ . '/../../includes/db.php';

// Filtros del usuario logueado
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

// Helper para armar WHERE por alcance de usuario
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

// ====== Construcción de consulta según contexto (búsqueda / sucursal / alcance) ======
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
  // --- BÚSQUEDA con alcance de usuario ---
  $types = "sssssi";
  $params = [];
  $sql = "SELECT d.*, 
      det.nom_determinante AS determinantes,
      s.nom_sucursal, 
      m.nom_municipio, 
      c.nom_ciudad,
      eq.nom_equipo,
      mo.num_modelos,
      es.status_equipo
    FROM dispositivos d
    LEFT JOIN sucursales s ON d.sucursal = s.ID
    LEFT JOIN determinantes det ON s.id = det.sucursal_id
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
  // --- Listado por sucursal seleccionada ---
  $sucursalId = intval($_GET['sucursal_id']);
  $stmt = $conn->prepare("SELECT d.*, 
      det.nom_determinante AS determinantes,
      s.nom_sucursal, 
      m.nom_municipio, 
      c.nom_ciudad,
      eq.nom_equipo,
      mo.num_modelos,
      es.status_equipo
    FROM dispositivos d
    LEFT JOIN sucursales s ON d.sucursal = s.ID
    LEFT JOIN determinantes det ON s.id = det.sucursal_id
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
  // --- Precarga según alcance de usuario (si tiene filtros fijos) ---
  $types = "";
  $params = [];
  $extra = buildUserScopeWhere($types, $params, $filtroRegion, $filtroCiudad, $filtroMunicipio, $filtroSucursal);
  if ($extra) {
    $sql = "SELECT d.*, 
        det.nom_determinante AS determinantes,
        s.nom_sucursal, 
        m.nom_municipio, 
        c.nom_ciudad,
        eq.nom_equipo,
        mo.num_modelos,
        es.status_equipo
      FROM dispositivos d
      LEFT JOIN sucursales s ON d.sucursal = s.ID
      LEFT JOIN determinantes det ON s.id = det.sucursal_id
      LEFT JOIN municipios m ON s.municipio_id = m.ID
      LEFT JOIN ciudades c ON m.ciudad_id = c.ID
      LEFT JOIN equipos eq ON d.equipo = eq.ID
      LEFT JOIN modelos mo ON d.modelo = mo.ID
      LEFT JOIN status es ON d.estado = es.ID
      WHERE " . implode(" AND ", $extra) . "
      ORDER BY d.id ASC";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
  } else {
    // El usuario puede ver todo, pero mantiene la UX: no mostrar nada hasta elegir sucursal
    $result = false;
  }
}

ob_start();
?>

<h2>Listado de dispositivos</h2>
<?php if (!empty($_SESSION['flash_success']) || !empty($_SESSION['flash_error']) || !empty($_SESSION['flash_warning'])): ?>
  <div class="mb-3">
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert" data-autohide="true">
        <i class="fas fa-check-circle me-1"></i>
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_warning'])): ?>
      <div class="alert alert-warning alert-dismissible fade show" role="alert" data-autohide="true">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <?= htmlspecialchars($_SESSION['flash_warning']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert" data-autohide="true">
        <i class="fas fa-times-circle me-1"></i>
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
    <?php endif; ?>
  </div>
  <?php 
    // Limpiar flashes para que no reaparezcan tras refresh
    unset($_SESSION['flash_success'], $_SESSION['flash_warning'], $_SESSION['flash_error']); 
  ?>
<?php endif; ?>





<!-- Buscador y botón alineados -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
  <form id="formBusqueda" method="GET" style="display: none; gap: 10px;">
    <input type="text" id="search" name="search" class="form-control" style="width:300px" placeholder="Busca equipo, modelo, fecha...">
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
  </form>

  <button id="btnExportar" class="btn btn-danger" style="display: none;">
    <i class="fas fa-file-pdf"></i> Exportar Listado
  </button>

  <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador', 'Mantenimientos','Capturista','Técnico'])): ?>
    <a href="registro.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar nuevo dispositivo</a>
  <?php endif; ?>
</div>

<script>
  // Exportar respetando filtros actuales
  document.getElementById("btnExportar").addEventListener("click", function() {
    const params = new URLSearchParams();
    const ciudad    = document.getElementById("ciudad").value;
    const municipio = document.getElementById("municipio").value;
    const sucursal  = document.getElementById("sucursal").value;
    const searchEl  = document.getElementById("search");
    const q         = searchEl ? searchEl.value.trim() : "";

    if (ciudad)    params.set('ciudad_id', ciudad);
    if (municipio) params.set('municipio_id', municipio);
    if (sucursal)  params.set('sucursal_id', sucursal);
    if (q)         params.set('search', q);

    window.open(`exportar_lista_pdf.php?${params.toString()}`, '_blank');
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
      if ($filtroRegion) { $w[] = "region_id = " . (int)$filtroRegion; } // ajusta si tu campo difiere
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
      <option value="">-- Selecciona un municipio --</option>
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
  .table td, .table th { white-space: nowrap; }
  .table td img { max-width: 100%; height: auto; }
  @media (max-width: 768px) {
    .btn, .form-control { font-size: 0.9rem; padding: 6px 10px; }
    form { flex-wrap: wrap; }
    .table td, .table th { font-size: 0.8rem; }
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
            <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['determinantes'] ?? '') ?></td>
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
              <a href="device.php?id=<?= $device['id'] ?>" 
                 class="btn btn-sm btn-primary btn-ver" 
                 data-id="<?= $device['id'] ?>">
                <i class="fas fa-eye"></i>
              </a>
              <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Capturista','Técnico'])): ?>
                <a href="editar.php?id=<?= $device['id'] ?>" 
                   class="btn btn-sm btn-secondary btn-editar"
                   data-id="<?= $device['id'] ?>">
                  <i class="fa-regular fa-pen-to-square"></i>
                </a>
              <?php endif; ?>
              <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Capturista','Técnico'])): ?>
                <button class="btn btn-sm btn-danger" 
                        data-bs-toggle="modal" 
                        data-bs-target="#confirmDeleteModal" 
                        data-id="<?= $device['id'] ?>">
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

<?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador', 'Mantenimientos', 'Invitado','Técnico','Capturista','Distrital','Prevencion','Monitorista'])): ?>
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
<?php endif; ?>

<!-- ====== JS: Persistencia de filtros, return_url, y carga encadenada ====== -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  const formBusqueda      = document.getElementById('formBusqueda');
  const searchInput       = document.getElementById('search');

  const ciudadSelect      = document.getElementById('ciudad');
  const municipioSelect   = document.getElementById('municipio');
  const sucursalSelect    = document.getElementById('sucursal');
  const tbody             = document.getElementById('resultado-dispositivos');
  const botonPDF          = document.getElementById('btnExportar');

  // Valores fijos por alcance de usuario (inyectados desde PHP)
  const FIXED_REGION      = <?= $filtroRegion    ? (int)$filtroRegion    : 'null' ?>;
  const FIXED_CIUDAD      = <?= $filtroCiudad    ? (int)$filtroCiudad    : 'null' ?>;
  const FIXED_MUNICIPIO   = <?= $filtroMunicipio ? (int)$filtroMunicipio : 'null' ?>;
  const FIXED_SUCURSAL    = <?= $filtroSucursal  ? (int)$filtroSucursal  : 'null' ?>;

  const STORAGE_KEY = 'dispositivos:listar:filtros';

  // ---------- Helpers ----------
  function saveFilters() {
    const data = {
      ciudad:    ciudadSelect.value || '',
      municipio: municipioSelect.value || '',
      sucursal:  sucursalSelect.value || '',
      search:    (searchInput?.value || '').trim()
    };
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  }

  function loadPersisted() {
    try { return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}'); }
    catch(e) { return {}; }
  }

  function buildParams() {
    const params = new URLSearchParams();
    if (ciudadSelect.value)    params.set('ciudad_id', ciudadSelect.value);
    if (municipioSelect.value) params.set('municipio_id', municipioSelect.value);
    if (sucursalSelect.value)  params.set('sucursal_id', sucursalSelect.value);
    if (searchInput && searchInput.value.trim())
                                params.set('search', searchInput.value.trim());
    return params;
  }

  function getCurrentListUrl() {
    const base = window.location.pathname; // e.g. /sisec-ui/views/dispositivos/listar.php
    const qs = buildParams().toString();
    return qs ? `${base}?${qs}` : base;
  }

  function toggleUIBySelection() {
    // Mostrar Exportar y buscador solo cuando hay 3 niveles
    if (ciudadSelect.value && municipioSelect.value && sucursalSelect.value) {
      botonPDF.style.display = 'inline-block';
      formBusqueda.style.display = 'flex';
      if (searchInput) {
        searchInput.disabled = false;
        const btn = formBusqueda.querySelector('button'); if (btn) btn.disabled = false;
      }
    } else {
      botonPDF.style.display = 'none';
      formBusqueda.style.display = 'none';
      if (searchInput) {
        searchInput.disabled = true;
        const btn = formBusqueda.querySelector('button'); if (btn) btn.disabled = true;
        searchInput.value = '';
      }
    }
  }

  function renderEmptyMsg() {
    tbody.innerHTML = `<tr><td colspan="8" class="text-muted text-center">Selecciona una sucursal para ver los dispositivos</td></tr>`;
  }

  // Cargas encadenadas (PROMESAS) para evitar “bloqueos”
  function loadMunicipios(ciudadId) {
    municipioSelect.innerHTML = '<option value="">-- Selecciona un municipio --</option>';
    sucursalSelect.innerHTML  = '<option value="">-- Selecciona una sucursal --</option>';
    sucursalSelect.disabled   = true && !FIXED_SUCURSAL;

    if (!ciudadId) {
      municipioSelect.disabled = true;
      return Promise.resolve();
    }

    municipioSelect.disabled = false || !!FIXED_MUNICIPIO;

    return fetch(`obtener_municipios.php?ciudad_id=${encodeURIComponent(ciudadId)}`)
      .then(r => r.json())
      .then(data => {
        data.forEach(m => {
          municipioSelect.innerHTML += `<option value="${m.ID}">${m.nom_municipio}</option>`;
        });
        if (FIXED_MUNICIPIO) {
          municipioSelect.value = String(FIXED_MUNICIPIO);
          municipioSelect.disabled = true;
        }
      });
  }

  function loadSucursales(municipioId) {
    sucursalSelect.innerHTML = '<option value="">-- Selecciona una sucursal --</option>';

    if (!municipioId) {
      sucursalSelect.disabled = true;
      return Promise.resolve();
    }

    sucursalSelect.disabled = false || !!FIXED_SUCURSAL;

    return fetch(`obtener_sucursales.php?municipio_id=${encodeURIComponent(municipioId)}`)
      .then(r => r.json())
      .then(data => {
        data.forEach(s => {
          sucursalSelect.innerHTML += `<option value="${s.ID}">${s.nom_sucursal}</option>`;
        });
        if (FIXED_SUCURSAL) {
          sucursalSelect.value = String(FIXED_SUCURSAL);
          sucursalSelect.disabled = true;
        }
      });
  }

  function actualizarTabla() {
    toggleUIBySelection();
    saveFilters();

    const sucursalId = sucursalSelect.value;
    if (!sucursalId) {
      renderEmptyMsg();
      return;
    }

    const params = buildParams();
    fetch(`buscar_dispositivos.php?${params.toString()}`)
      .then(response => response.text())
      .then(html => { tbody.innerHTML = html; });
  }

  // -------- Inicialización: URL > sessionStorage > FIXED_* --------
  (function initFromURLorStorage() {
    const url = new URLSearchParams(window.location.search);
    const hasURL = url.has('ciudad_id') || url.has('municipio_id') || url.has('sucursal_id') || url.has('search');

    const persisted = hasURL ? null : loadPersisted();

    const targetCiudad    = hasURL ? (url.get('ciudad_id') || '')    : (persisted.ciudad    ?? (FIXED_CIUDAD    ? String(FIXED_CIUDAD)    : ''));
    const targetMunicipio = hasURL ? (url.get('municipio_id') || '') : (persisted.municipio ?? (FIXED_MUNICIPIO ? String(FIXED_MUNICIPIO) : ''));
    const targetSucursal  = hasURL ? (url.get('sucursal_id') || '')  : (persisted.sucursal  ?? (FIXED_SUCURSAL  ? String(FIXED_SUCURSAL)  : ''));
    const targetSearch    = hasURL ? (url.get('search') || '')       : (persisted.search    ?? '');

    if (targetCiudad) ciudadSelect.value = String(targetCiudad);
    if (searchInput)  searchInput.value  = targetSearch;

    Promise.resolve()
      .then(() => targetCiudad    ? loadMunicipios(targetCiudad) : null)
      .then(() => { if (targetMunicipio) municipioSelect.value = String(targetMunicipio); return targetMunicipio ? loadSucursales(targetMunicipio) : null; })
      .then(() => { if (targetSucursal)  sucursalSelect.value   = String(targetSucursal); })
      .then(() => { actualizarTabla(); });
  })();

  // -------- Eventos de UI --------
  ciudadSelect.addEventListener('change', function () {
    const ciudadId = this.value;
    loadMunicipios(ciudadId).then(() => {
      saveFilters();
      renderEmptyMsg();
      toggleUIBySelection();
    });
  });

  municipioSelect.addEventListener('change', function () {
    const municipioId = this.value;
    loadSucursales(municipioId).then(() => {
      saveFilters();
      renderEmptyMsg();
      toggleUIBySelection();
    });
  });

  sucursalSelect.addEventListener('change', actualizarTabla);

  if (searchInput) {
    searchInput.addEventListener('keyup', function() {
      saveFilters();
      const suc = sucursalSelect.value;
      if (!suc) { renderEmptyMsg(); return; }
      const params = buildParams();
      fetch(`buscar_dispositivos.php?${params.toString()}`)
        .then(r => r.text())
        .then(html => { tbody.innerHTML = html; });
    });
  }

  // Delegación: añadir return_url a VER/EDITAR
  tbody.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-ver, .btn-editar');
    if (!btn) return;

    e.preventDefault();
    const href = btn.getAttribute('href');
    const url  = new URL(href, window.location.origin);
    url.searchParams.set('return_url', getCurrentListUrl());
    window.location.href = url.toString();
  });

  // Añadir return_url también al ELIMINAR (link del modal)
  const deleteModal = document.getElementById('confirmDeleteModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', function (event) {
      const button     = event.relatedTarget;
      const deviceId   = button.getAttribute('data-id');
      const deleteLink = deleteModal.querySelector('#deleteLink');

      const returnUrl  = getCurrentListUrl();
      deleteLink.href  = `eliminar.php?id=${encodeURIComponent(deviceId)}&return_url=${encodeURIComponent(returnUrl)}`;
    });
  }

  // Si el navegador usa BFCache, restaurar visibilidad de UI sin recargar
  window.addEventListener('pageshow', () => toggleUIBySelection());
});
</script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert[data-autohide="true"]');
    alerts.forEach(function(el) {
      setTimeout(function() {
        // Bootstrap 5: cerrar programáticamente
        if (window.bootstrap && bootstrap.Alert) {
          const instance = bootstrap.Alert.getOrCreateInstance(el);
          instance.close();
        } else {
          el.remove();
        }
      }, 4500);
    });
  });
</script>


<?php
$content = ob_get_clean();
$pageTitle = "Listado de dispositivos";
$pageHeader = "Dispositivos";
$activePage = "dispositivos";
include __DIR__ . '/../../layout.php';