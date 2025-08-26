editar
<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Técnico', 'Mantenimientos', 'Capturista']);

include __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido o no especificado.');
}

$id = (int)$_GET['id'];

// Traer dispositivo
$stmt = $conn->prepare("SELECT * FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$device) die('Dispositivo no encontrado.');

// Catálogo de equipos
$equipos = $conn->query("SELECT id, nom_equipo FROM equipos ORDER BY nom_equipo ASC");

// Modelos del equipo actual (filtrados por la relación marcas.equipo_id)
$equipoActual = (int)($device['equipo'] ?? 0);
$modeloActual = (int)($device['modelo'] ?? 0);

$modelosRes = null;
if ($equipoActual > 0) {
  $sqlModelos = "
    SELECT m.id, m.num_modelos
    FROM modelos m
    INNER JOIN marcas ma ON ma.id_marcas = m.marca_id
    WHERE ma.equipo_id = ?
    ORDER BY m.num_modelos ASC
  ";
  $st = $conn->prepare($sqlModelos);
  $st->bind_param("i", $equipoActual);
  $st->execute();
  $modelosRes = $st->get_result();
  $st->close();
}

// Sucursales
$sucursales = $conn->query("SELECT id, nom_sucursal FROM sucursales ORDER BY nom_sucursal ASC");

ob_start();
?>
<?php
$back = !empty($_GET['return_url'])
  ? $_GET['return_url']
  : '/sisec-ui/views/dispositivos/listar.php';
?>
<a href="<?= htmlspecialchars($back) ?>" class="btn btn-outline-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Volver al listado
</a>


<h2>Editar dispositivo</h2>

<form action="actualizar.php" method="post" enctype="multipart/form-data" class="row g-3">
  <input type="hidden" name="id" value="<?= (int)$device['id'] ?>">

<!-- EQUIPO (muestra nombre, envía ID) -->
<div class="col-md-6">
  <label class="form-label">Equipo</label>
  <select name="equipo" id="equipo" class="form-select" required>
    <option value="" disabled <?= $equipoActual ? '' : 'selected' ?>>-- Selecciona equipo --</option>
    <?php while ($eq = $equipos->fetch_assoc()): ?>
      <option value="<?= (int)$eq['id'] ?>" <?= ((int)$eq['id'] === $equipoActual) ? 'selected' : '' ?>>
        <?= htmlspecialchars($eq['nom_equipo']) ?>
      </option>
    <?php endwhile; ?>
  </select>

  <!-- Botón para activar edición del nombre -->
  <div class="mt-2">
    <button type="button" id="btnEquipoEditar" class="btn btn-sm btn-outline-secondary">
      Editar nombre
    </button>
  </div>

  <!-- Área de edición de nombre de equipo -->
  <div id="equipoEditGroup" class="mt-2" style="display:none;">
    <input type="text" name="equipo_nombre_edit" id="equipo_nombre_edit" class="form-control" placeholder="Nuevo nombre de equipo">
    <input type="hidden" name="equipo_edit_mode" id="equipo_edit_mode" value="0">
    <input type="hidden" name="equipo_edit_id" id="equipo_edit_id" value="<?= (int)$equipoActual ?>">
    <small class="text-muted">Este cambio renombrará el equipo para todos los dispositivos que lo usen.</small>
  </div>
</div>

<!-- MODELO (muestra nombre, envía ID) -->
<div class="col-md-6">
  <label class="form-label">Modelo</label>
  <select name="modelo" id="modelo" class="form-select" required>
    <?php if (!$modelosRes || $modelosRes->num_rows === 0): ?>
      <option value="" disabled selected>Selecciona primero un equipo</option>
    <?php else: ?>
      <?php while ($mo = $modelosRes->fetch_assoc()): ?>
        <option value="<?= (int)$mo['id'] ?>" <?= ((int)$mo['id'] === $modeloActual) ? 'selected' : '' ?>>
          <?= htmlspecialchars($mo['num_modelos']) ?>
        </option>
      <?php endwhile; ?>
    <?php endif; ?>
  </select>

  <!-- Botón para activar edición del nombre -->
  <div class="mt-2">
    <button type="button" id="btnModeloEditar" class="btn btn-sm btn-outline-secondary">
      Editar nombre
    </button>
  </div>

  <!-- Área de edición de nombre de modelo -->
  <div id="modeloEditGroup" class="mt-2" style="display:none;">
    <input type="text" name="modelo_nombre_edit" id="modelo_nombre_edit" class="form-control" placeholder="Nuevo nombre de modelo">
    <input type="hidden" name="modelo_edit_mode" id="modelo_edit_mode" value="0">
    <input type="hidden" name="modelo_edit_id" id="modelo_edit_id" value="<?= (int)$modeloActual ?>">
    <small class="text-muted">Este cambio renombrará el modelo para todos los dispositivos que lo usen.</small>
  </div>
</div>

  <div class="col-md-6">
    <label class="form-label">Serie</label>
    <input type="text" name="serie" class="form-control" value="<?= htmlspecialchars($device['serie'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Dirección MAC</label>
    <input type="text" name="mac" class="form-control" value="<?= htmlspecialchars($device['mac'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">No. de Servidor</label>
    <input type="text" name="servidor" class="form-control" value="<?= htmlspecialchars($device['servidor'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">VMS</label>
    <input type="text" name="vms" class="form-control" value="<?= htmlspecialchars($device['vms'] ?? '') ?>" required>
  </div>

  <!-- Usuario del dispositivo -->
<div class="col-md-6">
  <label class="form-label">Usuario</label>
  <div class="input-group">
    <input
      type="text"
      name="usuario"
      id="usuario"
      class="form-control"
      value="<?= htmlspecialchars($device['user'] ?? '') ?>"
      autocomplete="username"
      placeholder="Usuario de acceso"
    >
    <!-- Botón opcional para habilitar/inhabilitar edición -->
    <button type="button" class="btn btn-outline-secondary" id="toggleUsuario">
      Bloquear/Editar
    </button>
  </div>
  <small class="text-muted">Credencial de acceso del equipo/modelo (no de la app).</small>
</div>

<!-- Contraseña del dispositivo -->
<div class="col-md-6">
  <label class="form-label">Contraseña</label>
  <div class="input-group">
    <input
      type="password"
      name="contrasena"
      id="contrasena"
      class="form-control"
      value="<?= htmlspecialchars($device['pass'] ?? '') ?>"
      autocomplete="current-password"
      placeholder="Contraseña de acceso"
    >
    <button type="button" class="btn btn-outline-secondary" id="togglePassVis">
      Mostrar/Ocultar
    </button>
  </div>
  <small class="text-muted">Se guarda tal cual en BD (credencial del dispositivo).</small>
</div>


  <div class="col-md-6">
    <label class="form-label">Switch</label>
    <input type="text" name="switch" class="form-control" value="<?= htmlspecialchars($device['switch'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Puerto</label>
    <input type="text" name="puerto" class="form-control" value="<?= htmlspecialchars($device['puerto'] ?? '') ?>">
  </div>

  <!-- Sucursal (muestra nombre como ya lo tienes) -->
  <div class="col-md-6">
    <label class="form-label">Sucursal</label>
    <select name="sucursal" class="form-select" required>
      <option value="" disabled <?= empty($device['sucursal']) ? 'selected' : '' ?>>-- Selecciona sucursal --</option>
      <?php while ($s = $sucursales->fetch_assoc()): ?>
        <?php $nombreSuc = (string)$s['nom_sucursal']; $idSuc = (int)$s['id']; ?>
        <option value="<?= htmlspecialchars($idSuc) ?>" <?= ($idSuc === (int)$device['sucursal']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($nombreSuc) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <!-- Área (texto, no hay tabla areas) -->
  <div class="col-md-6">
    <label class="form-label">Área</label>
    <input type="text" name="area" class="form-control" value="<?= htmlspecialchars($device['area'] ?? '') ?>">
  </div>

  <!-- Estado (tu columna es int; ajusta valores si quieres 1=Activo, etc.) -->
  <div class="col-md-6">
    <label class="form-label">Estado</label>
    <select name="estado" class="form-select" required>
      <option value="1" <?= ((int)$device['estado'] === 1) ? 'selected' : '' ?>>Activo</option>
      <option value="2" <?= ((int)$device['estado'] === 2) ? 'selected' : '' ?>>En mantenimiento</option>
      <option value="3" <?= ((int)$device['estado'] === 3) ? 'selected' : '' ?>>Desactivado</option>
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Fecha</label>
    <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($device['fecha'] ?? '') ?>" required>
  </div>

  <div class="col-12">
    <label class="form-label">Observaciones</label>
    <textarea name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($device['observaciones'] ?? '') ?></textarea>
  </div>

  <!-- Imágenes -->
  <div class="col-md-6">
    <label class="form-label">Imagen actual principal:</label><br>
    <?php if (!empty($device['imagen'])): ?>
      <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen']) ?>" width="200" alt="Imagen principal">
    <?php else: ?><em class="text-muted">Sin imagen</em><?php endif; ?>
    <br><label class="form-label mt-2">Cambiar imagen principal</label>
    <input type="file" name="imagen" class="form-control" accept="image/*">
  </div>

  <div class="col-md-6">
    <label class="form-label">Imagen actual 2:</label><br>
    <?php if (!empty($device['imagen2'])): ?>
      <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen2']) ?>" width="200" alt="Imagen 2">
    <?php else: ?><em class="text-muted">Sin imagen</em><?php endif; ?>
    <br><label class="form-label mt-2">Cambiar imagen 2</label>
    <input type="file" name="imagen2" class="form-control" accept="image/*">
  </div>

  <div class="col-md-6">
    <label class="form-label">Imagen actual 3:</label><br>
    <?php if (!empty($device['imagen3'])): ?>
      <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen3']) ?>" width="200" alt="Imagen 3">
    <?php else: ?><em class="text-muted">Sin imagen</em><?php endif; ?>
    <br><label class="form-label mt-2">Cambiar imagen 3</label>
    <input type="file" name="imagen3" class="form-control" accept="image/*">
  </div>

  <!-- QR -->
  <div class="col-md-6">
    <label class="form-label">Código QR:</label><br>
    <?php if (!empty($device['qr'])): ?>
      <img src="/sisec-ui/public/qrcodes/<?= htmlspecialchars($device['qr']) ?>" width="150" alt="Código QR">
    <?php else: ?><em class="text-muted">Sin QR</em><?php endif; ?>
  </div>

  <div class="col-12">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
    <a href="device.php?id=<?= (int)$id ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</form>

<script>
// Al cambiar equipo, cargar modelos de ese equipo
document.getElementById('equipo').addEventListener('change', async function() {
  const equipoId = this.value;
  const modelo   = document.getElementById('modelo');
  modelo.innerHTML = '<option value="" disabled selected>Cargando modelos...</option>';
  if (!equipoId) return;

  try {
    const resp = await fetch('obtener_modelos.php?equipo_id=' + encodeURIComponent(equipoId), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const data = await resp.json(); // [{id, num_modelos}]
    if (!Array.isArray(data) || data.length === 0) {
      modelo.innerHTML = '<option value="" disabled selected>No hay modelos para este equipo</option>';
      return;
    }
    modelo.innerHTML = '';
    for (const m of data) {
      const opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = m.num_modelos;
      modelo.appendChild(opt);
    }
  } catch (e) {
    console.error(e);
    modelo.innerHTML = '<option value="" disabled selected>Error al cargar modelos</option>';
  }
});
</script>

<script>
// Utilidad: obtener texto del option seleccionado
function selectedText(sel) {
  const opt = sel.options[sel.selectedIndex];
  return opt ? opt.textContent.trim() : '';
}

/* ====== EQUIPO: toggle edición ====== */
const equipoSel = document.getElementById('equipo');
const btnEquipoEditar = document.getElementById('btnEquipoEditar');
const equipoEditGroup = document.getElementById('equipoEditGroup');
const equipoNombreEdit = document.getElementById('equipo_nombre_edit');
const equipoEditMode = document.getElementById('equipo_edit_mode');
const equipoEditId = document.getElementById('equipo_edit_id');

btnEquipoEditar.addEventListener('click', () => {
  const isHidden = equipoEditGroup.style.display === 'none';
  if (isHidden) {
    // Activar edición
    equipoEditGroup.style.display = '';
    equipoEditMode.value = '1';
    equipoEditId.value = equipoSel.value || '';
    equipoNombreEdit.value = selectedText(equipoSel); // nombre actual
    btnEquipoEditar.textContent = 'Cancelar edición';
  } else {
    // Cancelar edición
    equipoEditGroup.style.display = 'none';
    equipoEditMode.value = '0';
    equipoNombreEdit.value = '';
    btnEquipoEditar.textContent = 'Editar nombre';
  }
});

// Si cambian de equipo y está activo el modo edición, actualiza el campo
equipoSel.addEventListener('change', () => {
  if (equipoEditMode.value === '1') {
    equipoEditId.value = equipoSel.value || '';
    equipoNombreEdit.value = selectedText(equipoSel);
  }
});

/* ====== MODELO: toggle edición ====== */
const modeloSel = document.getElementById('modelo');
const btnModeloEditar = document.getElementById('btnModeloEditar');
const modeloEditGroup = document.getElementById('modeloEditGroup');
const modeloNombreEdit = document.getElementById('modelo_nombre_edit');
const modeloEditMode = document.getElementById('modelo_edit_mode');
const modeloEditId = document.getElementById('modelo_edit_id');

btnModeloEditar.addEventListener('click', () => {
  const isHidden = modeloEditGroup.style.display === 'none';
  if (isHidden) {
    // Activar edición
    modeloEditGroup.style.display = '';
    modeloEditMode.value = '1';
    modeloEditId.value = modeloSel.value || '';
    modeloNombreEdit.value = selectedText(modeloSel); // nombre actual
    btnModeloEditar.textContent = 'Cancelar edición';
  } else {
    // Cancelar edición
    modeloEditGroup.style.display = 'none';
    modeloEditMode.value = '0';
    modeloNombreEdit.value = '';
    btnModeloEditar.textContent = 'Editar nombre';
  }
});

// Si cambian de modelo y está activo el modo edición, actualiza el campo
modeloSel.addEventListener('change', () => {
  if (modeloEditMode.value === '1') {
    modeloEditId.value = modeloSel.value || '';
    modeloNombreEdit.value = selectedText(modeloSel);
  }
});

/* ====== (Ya lo tenías) Cargar modelos cuando cambia equipo ====== */
document.getElementById('equipo').addEventListener('change', async function() {
  const equipoId = this.value;
  const modelo   = document.getElementById('modelo');
  modelo.innerHTML = '<option value="" disabled selected>Cargando modelos...</option>';
  if (!equipoId) return;

  try {
    const resp = await fetch('obtener_modelos.php?equipo_id=' + encodeURIComponent(equipoId), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const data = await resp.json(); // [{id, num_modelos}]
    if (!Array.isArray(data) || data.length === 0) {
      modelo.innerHTML = '<option value="" disabled selected>No hay modelos para este equipo</option>';
      return;
    }
    modelo.innerHTML = '';
    for (const m of data) {
      const opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = m.num_modelos;
      modelo.appendChild(opt);
    }
    // Si está activo modo edición de modelo, sincroniza nombre/ID
    if (modeloEditMode.value === '1') {
      modeloEditId.value = modelo.value || '';
      modeloNombreEdit.value = selectedText(modelo);
    }
  } catch (e) {
    console.error(e);
    modelo.innerHTML = '<option value="" disabled selected>Error al cargar modelos</option>';
  }
});
</script>

<script>
// Bloquear/editar "Usuario"
const usuarioInput = document.getElementById('usuario');
const toggleUsuarioBtn = document.getElementById('toggleUsuario');
if (toggleUsuarioBtn && usuarioInput) {
  toggleUsuarioBtn.addEventListener('click', () => {
    usuarioInput.disabled = !usuarioInput.disabled;
    toggleUsuarioBtn.textContent = usuarioInput.disabled ? 'Editar' : 'Bloquear';
  });
}

// Mostrar/Ocultar contraseña
const passInput = document.getElementById('contrasena');
const togglePassBtn = document.getElementById('togglePassVis');
if (togglePassBtn && passInput) {
  togglePassBtn.addEventListener('click', () => {
    passInput.type = passInput.type === 'password' ? 'text' : 'password';
  });
}
</script>



<?php
$content = ob_get_clean();
$pageTitle = "Editar dispositivo #$id";
$pageHeader = "Editar dispositivo";
$activePage = "";

include __DIR__ . '/../../layout.php';