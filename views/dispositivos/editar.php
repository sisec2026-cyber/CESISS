<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Técnico', 'Mantenimientos', 'Capturista']);

include __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido o no especificado.');
}

$id = (int)$_GET['id'];

/* ========== Dispositivo ========== */
$stmt = $conn->prepare("SELECT * FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$device) die('Dispositivo no encontrado.');

/* ========== Catálogos: Equipos/Modelos ========== */
$equipos = $conn->query("SELECT id, nom_equipo FROM equipos ORDER BY nom_equipo ASC");
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

/* ========== Catálogos: Sucursales / Tipos Alarma / Tipos CCTV ========== */
$sucursales  = $conn->query("SELECT id, nom_sucursal FROM sucursales ORDER BY nom_sucursal ASC");
$tiposAlarma = $conn->query("SELECT id, tipo_alarma FROM alarma ORDER BY id ASC");
$tiposCctv   = $conn->query("SELECT id, tipo_cctv FROM cctv ORDER BY id ASC");

/* ========== Nombre del modelo actual (por si no aparece en la lista) ========== */
$modeloNombreActual = null;
if ($modeloActual > 0) {
  $q = $conn->prepare("SELECT num_modelos FROM modelos WHERE id = ? LIMIT 1");
  $q->bind_param("i", $modeloActual);
  $q->execute();
  $modeloNombreActual = ($q->get_result()->fetch_assoc()['num_modelos'] ?? null);
  $q->close();
}

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

  <!-- EQUIPO -->
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

    <div class="mt-2">
      <button type="button" id="btnEquipoEditar" class="btn btn-sm btn-outline-secondary">
        Editar nombre
      </button>
    </div>

    <div id="equipoEditGroup" class="mt-2" style="display:none;">
      <input type="text" name="equipo_nombre_edit" id="equipo_nombre_edit" class="form-control" placeholder="Nuevo nombre de equipo">
      <input type="hidden" name="equipo_edit_mode" id="equipo_edit_mode" value="0">
      <small class="text-muted">Cambia solo el nombre para este dispositivo (crea/reutiliza un equipo).</small>
    </div>
  </div>

  <!-- MODELO -->
  <div class="col-md-6">
    <label class="form-label">Modelo</label>
    <?php
      $opciones = [];
      $tieneLista = ($modelosRes && $modelosRes->num_rows > 0);
      $modeloAparece = false;

      if ($tieneLista) {
        while ($mo = $modelosRes->fetch_assoc()) {
          $idMo  = (int)$mo['id'];
          $txtMo = (string)$mo['num_modelos'];
          if ($idMo === $modeloActual) $modeloAparece = true;
          $opciones[] = ['id' => $idMo, 'txt' => $txtMo];
        }
      }
    ?>
    <select
      name="modelo"
      id="modelo"
      class="form-select"
      required
      data-modelo-actual-id="<?= (int)$modeloActual ?>"
      data-modelo-actual-txt="<?= htmlspecialchars($modeloNombreActual ?? '') ?>"
    >
      <?php if (!$tieneLista): ?>
        <option value="" disabled selected>No hay modelos para este equipo</option>
      <?php endif; ?>

      <?php foreach ($opciones as $op): ?>
        <option value="<?= $op['id'] ?>" <?= ($op['id'] === $modeloActual) ? 'selected' : '' ?>>
          <?= htmlspecialchars($op['txt']) ?>
        </option>
      <?php endforeach; ?>

      <?php if ($modeloActual > 0 && !$modeloAparece && $modeloNombreActual): ?>
        <optgroup label="Modelo actual (fuera del equipo)">
          <option value="<?= (int)$modeloActual ?>" selected>
            (Actual) <?= htmlspecialchars($modeloNombreActual) ?>
          </option>
        </optgroup>
      <?php endif; ?>
    </select>

    <div class="mt-2">
      <button type="button" id="btnModeloEditar" class="btn btn-sm btn-outline-secondary">
        Editar nombre
      </button>
    </div>

    <div id="modeloEditGroup" class="mt-2" style="display:none;">
      <input type="text" name="modelo_nombre_edit" id="modelo_nombre_edit" class="form-control" placeholder="Nuevo nombre de modelo">
      <input type="hidden" name="modelo_edit_mode" id="modelo_edit_mode" value="0">
      <small class="text-muted">Cambia solo el nombre para este dispositivo (crea/reutiliza un modelo en la misma marca del modelo base).</small>
    </div>
  </div>

  <!-- SERIE (General) -->
  <div class="col-md-6">
    <label class="form-label">Serie</label>
    <input type="text" name="serie" class="form-control" value="<?= htmlspecialchars($device['serie'] ?? '') ?>">
  </div>

  <!-- MAC (CCTV/SWITCH) -->
  <div id="group-mac" class="col-md-6 grupo-cctv grupo-switch grupo-monitor-hide">
    <label class="form-label">Dirección MAC</label>
    <input type="text" name="mac" class="form-control" value="<?= htmlspecialchars($device['mac'] ?? '') ?>">
  </div>

  <!-- No. Servidor (CCTV) -->
  <div id="group-servidor" class="col-md-6 grupo-cctv grupo-switch-hide grupo-alarma-hide grupo-monitor-hide">
    <label class="form-label">No. de Servidor</label>
    <input type="text" name="servidor" class="form-control" value="<?= htmlspecialchars($device['servidor'] ?? '') ?>">
  </div>

  <!-- VMS (CCTV) -->
  <div id="group-vms" class="col-md-6 grupo-cctv grupo-switch-hide grupo-alarma-hide grupo-monitor-hide">
    <label class="form-label">VMS</label>
    <input type="text" name="vms" id="vms" class="form-control" value="<?= htmlspecialchars($device['vms'] ?? '') ?>" required>
  </div>

  <!-- Credenciales (NO en ALARMA/SWITCH/MONITOR) -->
  <div id="group-user" class="col-md-6 grupo-credenciales">
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
      <button type="button" class="btn btn-outline-secondary" id="toggleUsuario">Bloquear/Editar</button>
    </div>
    <small class="text-muted">Credencial del dispositivo.</small>
  </div>

  <div id="group-pass" class="col-md-6 grupo-credenciales">
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
      <button type="button" class="btn btn-outline-secondary" id="togglePassVis">Mostrar/Ocultar</button>
    </div>
    <small class="text-muted">Se guarda tal cual en BD.</small>
  </div>

  <!-- Switch / Puerto (CCTV / SWITCH) -->
  <div id="group-switch" class="col-md-6 grupo-cctv grupo-switch grupo-alarma-hide grupo-monitor-hide">
    <label class="form-label">Switch</label>
    <input type="text" name="switch" class="form-control" value="<?= htmlspecialchars($device['switch'] ?? '') ?>">
  </div>

  <div id="group-puerto" class="col-md-6 grupo-cctv grupo-switch grupo-alarma-hide grupo-monitor-hide">
    <label class="form-label">Puerto</label>
    <input type="text" name="puerto" class="form-control" value="<?= htmlspecialchars($device['puerto'] ?? '') ?>">
  </div>

  <!-- ========== BLOQUE CCTV ESPECÍFICO: tipo CCTV ========== -->
  <div id="group-cctv-tipo" class="col-md-6 grupo-cctv">
    <label class="form-label">Tipo de CCTV</label>
    <select name="cctv_id" id="cctv_id" class="form-select">
      <option value="" <?= empty($device['cctv_id']) ? 'selected' : '' ?>>-- Selecciona tipo --</option>
      <?php if ($tiposCctv && $tiposCctv->num_rows): ?>
        <?php while($tc = $tiposCctv->fetch_assoc()): ?>
          <?php $idTc = (int)$tc['id']; ?>
          <option value="<?= $idTc ?>" <?= ($idTc === (int)$device['cctv_id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($tc['tipo_cctv']) ?>
          </option>
        <?php endwhile; ?>
      <?php endif; ?>
    </select>
  </div>

  <!-- ========== BLOQUE ALARMA ESPECÍFICO ========== -->
  <div id="group-alarma-tipo" class="col-md-6 grupo-alarma">
    <label class="form-label">Conexión de alarma</label>
    <select name="alarma_id" id="alarma_id" class="form-select">
      <option value="" <?= empty($device['alarma_id']) ? 'selected' : '' ?>>-- Selecciona tipo --</option>
      <?php if ($tiposAlarma && $tiposAlarma->num_rows): ?>
        <?php while($ta = $tiposAlarma->fetch_assoc()): ?>
          <?php $idTa = (int)$ta['id']; ?>
          <option value="<?= $idTa ?>" <?= ($idTa === (int)$device['alarma_id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($ta['tipo_alarma']) ?>
          </option>
        <?php endwhile; ?>
      <?php endif; ?>
    </select>
  </div>

  <div id="group-zona-alarma" class="col-md-6 grupo-alarma">
    <label class="form-label">Zona de alarma</label>
    <input type="text" name="zona_alarma" class="form-control" value="<?= htmlspecialchars($device['zona_alarma'] ?? '') ?>">
  </div>

  <div id="group-tipo-sensor" class="col-md-6 grupo-alarma">
    <label class="form-label">Tipo de sensor</label>
    <input type="text" name="tipo_sensor" class="form-control" value="<?= htmlspecialchars($device['tipo_sensor'] ?? '') ?>">
  </div>

  <!-- Área (texto libre) -->
  <div class="col-md-6">
    <label class="form-label">Área</label>
    <input type="text" name="area" class="form-control" value="<?= htmlspecialchars($device['area'] ?? '') ?>">
  </div>

  <!-- Estado -->
  <div class="col-md-6">
    <label class="form-label">Estado</label>
    <select name="estado" class="form-select" required>
      <option value="1" <?= ((int)$device['estado'] === 1) ? 'selected' : '' ?>>Activo</option>
      <option value="2" <?= ((int)$device['estado'] === 2) ? 'selected' : '' ?>>En mantenimiento</option>
      <option value="3" <?= ((int)$device['estado'] === 3) ? 'selected' : '' ?>>Desactivado</option>
    </select>
  </div>

  <!-- Fecha -->
  <div class="col-md-6">
    <label class="form-label">Fecha</label>
    <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($device['fecha'] ?? '') ?>" required>
  </div>

  <!-- Observaciones -->
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

<!-- ================= ESTILOS/JS ================= -->
<style>
  /* utilitario */
  .d-none{ display:none !important; }
</style>

<script>
// util: texto del option seleccionado
function selectedText(sel) {
  const opt = sel.options[sel.selectedIndex];
  return opt ? opt.textContent.trim() : '';
}

/* ===== EQUIPO: toggle edición ===== */
const equipoSel = document.getElementById('equipo');
const btnEquipoEditar = document.getElementById('btnEquipoEditar');
const equipoEditGroup = document.getElementById('equipoEditGroup');
const equipoNombreEdit = document.getElementById('equipo_nombre_edit');
const equipoEditMode = document.getElementById('equipo_edit_mode');

btnEquipoEditar.addEventListener('click', () => {
  const isHidden = equipoEditGroup.style.display === 'none';
  if (isHidden) {
    equipoEditGroup.style.display = '';
    equipoEditMode.value = '1';
    equipoNombreEdit.value = selectedText(equipoSel);
    btnEquipoEditar.textContent = 'Cancelar edición';
  } else {
    equipoEditGroup.style.display = 'none';
    equipoEditMode.value = '0';
    equipoNombreEdit.value = '';
    btnEquipoEditar.textContent = 'Editar nombre';
  }
});

/* ===== MODELO: toggle edición ===== */
const modeloSel = document.getElementById('modelo');
const btnModeloEditar = document.getElementById('btnModeloEditar');
const modeloEditGroup = document.getElementById('modeloEditGroup');
const modeloNombreEdit = document.getElementById('modelo_nombre_edit');
const modeloEditMode = document.getElementById('modelo_edit_mode');

btnModeloEditar.addEventListener('click', () => {
  const isHidden = modeloEditGroup.style.display === 'none';
  if (isHidden) {
    modeloEditGroup.style.display = '';
    modeloEditMode.value = '1';
    modeloNombreEdit.value = selectedText(modeloSel);
    btnModeloEditar.textContent = 'Cancelar edición';
  } else {
    modeloEditGroup.style.display = 'none';
    modeloEditMode.value = '0';
    modeloNombreEdit.value = '';
    btnModeloEditar.textContent = 'Editar nombre';
  }
});

/* ===== Cambiar equipo preservando el modelo ===== */
(function() {
  const modeloActualId  = (modeloSel.dataset.modeloActualId || '').trim();
  const modeloActualTxt = (modeloSel.dataset.modeloActualTxt || '').trim();

  equipoSel.addEventListener('change', async function() {
    const equipoId = this.value;

    // selección previa (si el usuario no cambió modelo)
    const seleccionadoAntesId  = modeloSel.value || modeloActualId;
    const seleccionadoAntesTxt = (function() {
      if (modeloSel.value) {
        const opt = modeloSel.options[modeloSel.selectedIndex];
        return opt ? opt.textContent.trim() : '';
      }
      return modeloActualTxt;
    })();

    modeloSel.innerHTML = '<option value="" disabled selected>Cargando modelos...</option>';
    if (!equipoId) return;

    try {
      const resp = await fetch('obtener_modelos.php?equipo_id=' + encodeURIComponent(equipoId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const data = await resp.json(); // [{id, num_modelos}]

      // poblar
      modeloSel.innerHTML = '';
      const frag = document.createDocumentFragment();
      (Array.isArray(data) ? data : []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = String(m.id);
        opt.textContent = m.num_modelos;
        frag.appendChild(opt);
      });
      modeloSel.appendChild(frag);

      // ¿sigue el anterior?
      const existe = seleccionadoAntesId && Array.from(modeloSel.options).some(o => String(o.value) === String(seleccionadoAntesId));

      if (existe) {
        modeloSel.value = String(seleccionadoAntesId);
      } else if (seleccionadoAntesId) {
        // agregar opción temporal para no perder el modelo actual
        const og = document.createElement('optgroup');
        og.label = 'Modelo actual (fuera del equipo)';
        const opt = document.createElement('option');
        opt.value = String(seleccionadoAntesId);
        opt.textContent = '(Actual) ' + (seleccionadoAntesTxt || ('ID ' + String(seleccionadoAntesId)));
        og.appendChild(opt);
        modeloSel.insertBefore(og, modeloSel.firstChild);
        modeloSel.value = String(seleccionadoAntesId);
      } else {
        // sin previo: selecciona el primero si existe
        if (modeloSel.options.length) modeloSel.selectedIndex = 0;
      }

      // si estás editando nombre de modelo, sincroniza el input con lo visible
      if (modeloEditMode.value === '1') {
        modeloNombreEdit.value = selectedText(modeloSel);
      }

      // Actualiza visibilidad por si el cambio de equipo cambió la categoría
      updateVisibilityFromEquipo();

    } catch (e) {
      console.error(e);
      modeloSel.innerHTML = '<option value="" disabled selected>No hay modelos para este equipo</option>';
    }
  });
})();

/* ===== Usuario / Pass toggles ===== */
const usuarioInput = document.getElementById('usuario');
const toggleUsuarioBtn = document.getElementById('toggleUsuario');
if (toggleUsuarioBtn && usuarioInput) {
  toggleUsuarioBtn.addEventListener('click', () => {
    usuarioInput.disabled = !usuarioInput.disabled;
    toggleUsuarioBtn.textContent = usuarioInput.disabled ? 'Editar' : 'Bloquear';
  });
}
const passInput = document.getElementById('contrasena');
const togglePassBtn = document.getElementById('togglePassVis');
if (togglePassBtn && passInput) {
  togglePassBtn.addEventListener('click', () => {
    passInput.type = passInput.type === 'password' ? 'text' : 'password';
  });
}

/* ============================
   VISIBILIDAD POR CATEGORÍA
   ============================ */
function _normU(s){
  s = (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  s = s.toUpperCase().replace(/[_-]+/g,' ').replace(/\s+/g,' ').trim();
  return s;
}

function catDesdeEquipoText(txt){
  const v = _normU(txt);

  if (v.includes('CAMARA') || v.includes('CCTV')) return 'camara';
  if (v.includes('NVR')) return 'nvr';
  if (v.includes('DVR')) return 'dvr';
  if (v.includes('SERVIDOR') || v.includes('SERVER')) return 'servidor';
  if (v.includes('SWITCH')) return 'switch';
  if (v.includes('MONITOR') || v.includes('DISPLAY')) return 'monitor';
  if (v.includes('ESTACION TRABAJO') || v.includes('WORKSTATION') || v === 'PC' || v.includes('COMPUTADORA')) return 'estacion_trabajo';

  const alarmaKeys = [
    "ALARMA","TRANSMISOR","SENSOR","DETECTOR","HUMO","OVER HEAD","OVERHEAD","ZONA",
    "BOTON","PANICO","ESTACION","PULL STATION","PULL","PANEL","CABLEADO","SIRENA",
    "RECEPTOR","EMISOR","LLAVIN","TECLADO","ESTROBO","CRISTAL","RUPTURA","REPETIDOR",
    "REPETIDORA","DH","PIR","CM","BTN","OH","DRC","REP"
  ];
  for (const k of alarmaKeys){
    if (v.includes(_normU(k))) return 'alarma';
  }
  return 'otro';
}

function _showAll(list){ list.forEach(el=>el.classList.remove('d-none')); }
function _hideAll(list){ list.forEach(el=>el.classList.add('d-none')); }

function applyVisibilityByCategory(cat){
  const cctvEls        = document.querySelectorAll('.grupo-cctv');
  const switchEls      = document.querySelectorAll('.grupo-switch');
  const credEls        = document.querySelectorAll('.grupo-credenciales');
  const alarmaEls      = document.querySelectorAll('.grupo-alarma');

  const alarmaHideEls  = document.querySelectorAll('.grupo-alarma-hide');
  const switchHideEls  = document.querySelectorAll('.grupo-switch-hide');
  const monitorHideEls = document.querySelectorAll('.grupo-monitor-hide');

  // Estado base: oculta específicos
  _hideAll(cctvEls);
  _hideAll(switchEls);
  _hideAll(credEls);
  _hideAll(alarmaEls);

  // Limpia hides
  _hideAll(alarmaHideEls);
  _hideAll(switchHideEls);
  _hideAll(monitorHideEls);

  // Campo VMS requerido solo en CCTV-like
  const vms = document.getElementById('vms');
  if (vms) vms.removeAttribute('required');

  // Reglas por categoría
  if (cat === 'camara' || cat === 'nvr' || cat === 'dvr' || cat === 'servidor'){
    _showAll(cctvEls);
    _showAll(credEls);
    _showAll(switchHideEls);
    _showAll(monitorHideEls);
    if (vms) vms.setAttribute('required','required');
  }
  else if (cat === 'alarma'){
    _showAll(alarmaEls);
    // lo marcado como alarma-hide permanece oculto (ya hecho)
  }
  else if (cat === 'switch'){
    _showAll(switchEls);
    _showAll(alarmaHideEls);
    _hideAll(switchHideEls);
  }
  else if (cat === 'monitor'){
    _hideAll(monitorHideEls);
  }
  else {
    // Otros → deja credenciales visibles
    _showAll(credEls);
  }
}

function getEquipoText(){
  const sel = document.getElementById('equipo');
  if(!sel) return '';
  const opt = sel.options[sel.selectedIndex];
  return opt ? opt.textContent.trim() : '';
}

function updateVisibilityFromEquipo(){
  const cat = catDesdeEquipoText(getEquipoText());
  applyVisibilityByCategory(cat);
}

document.addEventListener('DOMContentLoaded', updateVisibilityFromEquipo);
if (equipoSel) {
  equipoSel.addEventListener('change', updateVisibilityFromEquipo);
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Editar dispositivo #$id";
$pageHeader = "Editar dispositivo";
$activePage = "";
include __DIR__ . '/../../layout.php';
