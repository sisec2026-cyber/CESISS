<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Técnico', 'Mantenimientos', 'Capturista']);

include __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido o no especificado.');
}

$id = (int)$_GET['id'];

// Dispositivo
$stmt = $conn->prepare("SELECT * FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$device) die('Dispositivo no encontrado.');

// Equipos
$equipos = $conn->query("SELECT id, nom_equipo FROM equipos ORDER BY nom_equipo ASC");

// Equipo/Modelo actuales
$equipoActual = (int)($device['equipo'] ?? 0);
$modeloActual = (int)($device['modelo'] ?? 0);

// Modelos del equipo actual
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

// Nombre del modelo actual (por si no aparece en la lista)
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

  <!-- Usuario / Contraseña -->
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
      <button type="button" class="btn btn-outline-secondary" id="toggleUsuario">Bloquear/Editar</button>
    </div>
    <small class="text-muted">Credencial del dispositivo.</small>
  </div>

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
      <button type="button" class="btn btn-outline-secondary" id="togglePassVis">Mostrar/Ocultar</button>
    </div>
    <small class="text-muted">Se guarda tal cual en BD.</small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Switch</label>
    <input type="text" name="switch" class="form-control" value="<?= htmlspecialchars($device['switch'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Puerto</label>
    <input type="text" name="puerto" class="form-control" value="<?= htmlspecialchars($device['puerto'] ?? '') ?>">
  </div>

  <!-- Sucursal (ID) -->
  <div class="col-md-6">
    <label class="form-label">Sucursal</label>
    <select name="sucursal" class="form-select" required>
      <option value="" disabled <?= empty($device['sucursal']) ? 'selected' : '' ?>>-- Selecciona sucursal --</option>
      <?php while ($s = $sucursales->fetch_assoc()): ?>
        <?php $idSuc = (int)$s['id']; ?>
        <option value="<?= $idSuc ?>" <?= ($idSuc === (int)$device['sucursal']) ? 'selected' : '' ?>>
          <?= htmlspecialchars((string)$s['nom_sucursal']) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <!-- Área (texto libre) -->
  <div class="col-md-6">
    <label class="form-label">Área</label>
    <input type="text" name="area" class="form-control" value="<?= htmlspecialchars($device['area'] ?? '') ?>">
  </div>

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

<!-- ================= JS ================= -->
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
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Editar dispositivo #$id";
$pageHeader = "Editar dispositivo";
$activePage = "";
include __DIR__ . '/../../layout.php';
