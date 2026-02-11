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

/* ========== Catálogos: Equipos ========== */
$equipos = $conn->query("SELECT id, nom_equipo FROM equipos ORDER BY nom_equipo ASC");
$equipoActual = (int)($device['equipo'] ?? 0);
$modeloActual = (int)($device['modelo'] ?? 0);
$marcaActual = (int)($device['marca_id'] ?? 0);

/* ========== Cargar MARCAS del equipo actual ========== */
$marcasRes = null;
if ($equipoActual > 0) {
  $sqlMarcas = "SELECT id_marcas, nom_marca FROM marcas WHERE equipo_id = ? ORDER BY nom_marca ASC";
  $stMarcas = $conn->prepare($sqlMarcas);
  $stMarcas->bind_param("i", $equipoActual);
  $stMarcas->execute();
  $marcasRes = $stMarcas->get_result();
  $stMarcas->close();
}

/* ========== Nombre de la marca actual (por si no aparece en la lista) ========== */
$marcaNombreActual = null;
if ($marcaActual > 0) {
  $qMarca = $conn->prepare("SELECT nom_marca FROM marcas WHERE id_marcas = ? LIMIT 1");
  $qMarca->bind_param("i", $marcaActual);
  $qMarca->execute();
  $resMarca = $qMarca->get_result()->fetch_assoc();
  $marcaNombreActual = $resMarca['nom_marca'] ?? null;
  $qMarca->close();
}

/* ========== Cargar MODELOS de la marca actual ========== */
$modelosRes = null;
if ($marcaActual > 0) {
  $sqlModelos = "SELECT id, num_modelos FROM modelos WHERE marca_id = ? ORDER BY num_modelos ASC";
  $stModelos = $conn->prepare($sqlModelos);
  $stModelos->bind_param("i", $marcaActual);
  $stModelos->execute();
  $modelosRes = $stModelos->get_result();
  $stModelos->close();
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
  $resModelo = $q->get_result()->fetch_assoc();
  $modeloNombreActual = $resModelo['num_modelos'] ?? null;
  $q->close();
}

/* ========== Analíticas existentes (para pre-check) ========== */
$tieneAnalitica  = (int)($device['tiene_analitica'] ?? 0);
$analiticasExist = array_filter(array_map('trim', explode(',', (string)($device['analiticas'] ?? ''))));

ob_start();
?>

<style>
  :root{--brand:#3C92A6; --brand-2:#24A3C1; --ink:#10343b; --muted:#486973; --bg:#F7FBFD;--surface:#FFFFFF; --border:#DDEEF3; --border-strong:#BFE2EB;--chip:#EAF7FB; --ring:0 0 0 .22rem rgba(36,163,193,.25); --ring-strong:0 0 0 .28rem rgba(36,163,193,.33);--shadow-sm:0 6px 18px rgba(20,78,90,.08);--radius-xl:1rem; --radius-2xl:1.25rem;}
  h2{ font-weight:800; letter-spacing:.2px; color:var(--ink); margin-bottom:.75rem!important; }
  h2::after{ content:""; display:block; width:78px; height:4px; border-radius:99px; margin-top:.5rem;background:linear-gradient(90deg,var(--brand),var(--brand-2)); }
</style>
<?php
$back = !empty($_GET['return_url'])
  ? $_GET['return_url']
  : '/sisec-ui/views/dispositivos/listar.php';
?>

<div style="padding-left: 25px;">
  <h2>Editar dispositivo</h2>
  <form action="actualizar.php" method="post" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="id" value="<?= (int)$device['id'] ?>">
    <input type="hidden" name="modelo_reasignar" id="modelo_reasignar" value="0">
  <!-- EQUIPO -->
  <div class="col-md-6">
    <label class="form-label">Equipo</label>
    <select name="equipo" id="equipo" class="form-select" required>
      <option value="" disabled>-- Selecciona equipo --</option>
      <?php 
      $equipos->data_seek(0); // Reset pointer
      while ($eq = $equipos->fetch_assoc()): 
      ?>
        <option value="<?= (int)$eq['id'] ?>" <?= ((int)$eq['id'] === $equipoActual) ? 'selected' : '' ?>>
          <?= htmlspecialchars($eq['nom_equipo']) ?>
        </option>
      <?php endwhile; ?>
    </select>
    <div class="mt-2">
      <button type="button" id="btnEquipoEditar" class="btn btn-sm btn-outline-secondary">Editar nombre</button>
    </div>
    <div id="equipoEditGroup" class="mt-2" style="display:none;">
      <input type="text" name="equipo_nombre_edit" id="equipo_nombre_edit" class="form-control" placeholder="Nuevo nombre de equipo">
      <input type="hidden" name="equipo_edit_mode" id="equipo_edit_mode" value="0">
      <small class="text-muted">Cambia solo el nombre para este dispositivo (crea/reutiliza un equipo).</small>
    </div>
  </div>
  <!-- MARCA -->
  <div class="col-md-6">
    <label class="form-label">Marca</label>
    <select name="marca_id" id="marca" class="form-select" required data-marca-actual-id="<?= (int)$marcaActual ?>" data-marca-actual-txt="<?= htmlspecialchars($marcaNombreActual ?? '') ?>">
      <option value="" disabled>-- Selecciona marca --</option>
      <?php if ($marcasRes && $marcasRes->num_rows > 0): ?>
        <?php 
        $marcaAparece = false;
        while ($ma = $marcasRes->fetch_assoc()): 
          $idMarca = (int)$ma['id_marcas'];
          if ($idMarca === $marcaActual) $marcaAparece = true;
        ?>
        <option value="<?= $idMarca ?>" <?= ($idMarca === $marcaActual) ? 'selected' : '' ?>>
          <?= htmlspecialchars($ma['nom_marca']) ?>
        </option>
        <?php endwhile; ?>
        <!-- Si la marca actual no está en la lista (de otro equipo), mostrarla -->
        <?php if ($marcaActual > 0 && !$marcaAparece && $marcaNombreActual): ?>
          <optgroup label="Marca actual (de otro equipo)">
            <option value="<?= (int)$marcaActual ?>" selected>
              (Actual) <?= htmlspecialchars($marcaNombreActual) ?>
            </option>
          </optgroup>
        <?php endif; ?>
      <?php else: ?>
        <!-- Si no hay marcas pero existe una marca actual, mostrarla -->
        <?php if ($marcaActual > 0 && $marcaNombreActual): ?>
          <option value="<?= (int)$marcaActual ?>" selected>
            <?= htmlspecialchars($marcaNombreActual) ?>
          </option>
        <?php else: ?>
          <option value="" disabled selected>Selecciona primero un equipo</option>
        <?php endif; ?>
      <?php endif; ?>
    </select>
    <div class="mt-2">
      <button type="button" id="btnMarcaEditar" class="btn btn-sm btn-outline-secondary">Editar nombre</button>
    </div>
    <div id="marcaEditGroup" class="mt-2" style="display:none;">
      <input type="text" name="marca_nombre_edit" id="marca_nombre_edit" class="form-control" placeholder="Nuevo nombre de marca">
      <input type="hidden" name="marca_edit_mode" id="marca_edit_mode" value="0">
      <small class="text-muted">Crea o reutiliza una marca dentro del equipo seleccionado.</small>
    </div>
  </div>
  <!-- MODELO -->
  <div class="col-md-6">
    <label class="form-label">Modelo</label>
    <select name="modelo" id="modelo" class="form-select" required data-modelo-actual-id="<?= (int)$modeloActual ?>" data-modelo-actual-txt="<?= htmlspecialchars($modeloNombreActual ?? '') ?>">
      <option value="" disabled>-- Selecciona modelo --</option>
      <?php if ($modelosRes && $modelosRes->num_rows > 0): ?>
        <?php 
        $modeloAparece = false;
        while ($mo = $modelosRes->fetch_assoc()): 
          $idMo = (int)$mo['id'];
          if ($idMo === $modeloActual) $modeloAparece = true;
        ?>
          <option value="<?= $idMo ?>" <?= ($idMo === $modeloActual) ? 'selected' : '' ?>>
            <?= htmlspecialchars($mo['num_modelos']) ?>
          </option>
        <?php endwhile; ?>
        <!-- Si el modelo actual no está en la lista (de otra marca), mostrarlo -->
        <?php if ($modeloActual > 0 && !$modeloAparece && $modeloNombreActual): ?>
          <optgroup label="Modelo actual (de otra marca)">
            <option value="<?= (int)$modeloActual ?>" selected>(Actual) <?= htmlspecialchars($modeloNombreActual) ?></option>
          </optgroup>
        <?php endif; ?>
      <?php else: ?>
        <!-- Si no hay modelos pero existe un modelo actual, mostrarlo -->
        <?php if ($modeloActual > 0 && $modeloNombreActual): ?>
          <option value="<?= (int)$modeloActual ?>" selected>
            <?= htmlspecialchars($modeloNombreActual) ?>
          </option>
        <?php else: ?>
          <option value="" disabled selected>Selecciona primero una marca</option>
        <?php endif; ?>
      <?php endif; ?>
    </select>
    <div class="mt-2">
      <button type="button" id="btnModeloEditar" class="btn btn-sm btn-outline-secondary">Editar nombre</button>
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
    <!-- IP  -->
  <div id="group-ip" class="col-md-6 grupo-cctv grupo-switch grupo-servidor grupo-alarma-hide grupo-monitor-hide">
    <label class="form-label">Dirección IP</label>
    <input type="text" name="ip" class="form-control" 
           value="<?= htmlspecialchars($device['ip'] ?? '') ?>" 
           placeholder="Ej. 192.168.1.100">
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

  <!-- ===== Analíticas (solo CCTV/cámaras) ===== -->
  <div class="col-12 grupo-cctv">
    <label class="form-label">Analíticas</label>

    <!-- Hidden 0 + checkbox 1 para tener siempre un valor definido -->
    <input type="hidden" name="tiene_analitica" value="0">
    <div class="form-check mb-2">
      <input
        class="form-check-input"
        type="checkbox"
        id="tiene_analitica"
        name="tiene_analitica"
        value="1"
        <?= $tieneAnalitica ? 'checked' : '' ?>
      >
      <label class="form-check-label" for="tiene_analitica">Habilitar analítica</label>
    </div>

    <?php
      $catalogo = ['Merodeo','Cruce de líneas','Intrusión','Conteo de personas','Detección de rostro'];
      // Separar las analíticas que NO están en el catálogo para mostrarlas en "Otras"
      $otrasAnaliticas = array_diff($analiticasExist, $catalogo);
    ?>

    <div id="wrapAnaliticas" class="card border-0 p-3" style="<?= $tieneAnalitica ? '' : 'opacity:.6;' ?>">
      <div class="mb-2">
        <?php foreach ($catalogo as $opt):
          $chk = in_array($opt, $analiticasExist, true) ? 'checked' : '';
          $idc = 'a_' . substr(md5($opt), 0, 8);
        ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="<?= $idc ?>" name="analiticas[]" value="<?= htmlspecialchars($opt) ?>" <?= $chk ?>>
            <label class="form-check-label" for="<?= $idc ?>"><?= htmlspecialchars($opt) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label">Otras (separadas por coma)</label>
          <input type="text" name="analiticas_otras" class="form-control" 
                 placeholder="Ej. Cruces múltiples, Aforo" 
                 value="<?= htmlspecialchars(implode(', ', $otrasAnaliticas)) ?>">
        </div>
      </div>
      <small class="text-muted">Si no marcas "Habilitar analítica", estas opciones no se guardarán.</small>
    </div>
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

  <!-- Fechas -->
  <div class="col-md-6">
    <label class="form-label">Fecha de instalación</label>
    <input type="date" name="fecha_instalacion" class="form-control" value="<?= htmlspecialchars($device['fecha_instalacion'] ?? '') ?>">
    <small class="text-muted">Déjala vacía si está pendiente.</small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Fecha de mantenimiento</label>
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
</div>

<!-- ================= ESTILOS/JS ================= -->
<style>.x-hide{display:none!important;}</style>

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

/* ===== MARCA: toggle edición ===== */
const marcaSel = document.getElementById('marca');
const btnMarcaEditar = document.getElementById('btnMarcaEditar');
const marcaEditGroup = document.getElementById('marcaEditGroup');
const marcaNombreEdit = document.getElementById('marca_nombre_edit');
const marcaEditMode = document.getElementById('marca_edit_mode');

btnMarcaEditar.addEventListener('click', () => {
  const isHidden = marcaEditGroup.style.display === 'none';
  if (isHidden) {
    marcaEditGroup.style.display = '';
    marcaEditMode.value = '1';
    marcaNombreEdit.value = selectedText(marcaSel);
    btnMarcaEditar.textContent = 'Cancelar edición';
  } else {
    marcaEditGroup.style.display = 'none';
    marcaEditMode.value = '0';
    marcaNombreEdit.value = '';
    btnMarcaEditar.textContent = 'Editar nombre';
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

/* ===== Cambiar EQUIPO → cargar marcas preservando selección ===== */
(function() {
  const marcaActualId = (marcaSel.dataset.marcaActualId || '').trim();
  const marcaActualTxt = (marcaSel.dataset.marcaActualTxt || '').trim();

  equipoSel.addEventListener('change', async function() {
    const equipoId = this.value;
    
    // Guardar la marca seleccionada actualmente
    const seleccionadaAntesId = marcaSel.value || marcaActualId;
    const seleccionadaAntesTxt = (function() {
      if (marcaSel.value) {
        const opt = marcaSel.options[marcaSel.selectedIndex];
        return opt ? opt.textContent.trim() : '';
      }
      return marcaActualTxt;
    })();

    marcaSel.innerHTML = '<option value="" disabled selected>Cargando marcas...</option>';
    if (!equipoId) return;

    try {
      const resp = await fetch('obtener_marcas.php?equipo_id=' + encodeURIComponent(equipoId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const data = await resp.json();

      marcaSel.innerHTML = '';
      const frag = document.createDocumentFragment();
      const optDefault = document.createElement('option');
      optDefault.value = '';
      optDefault.disabled = true;
      optDefault.textContent = '-- Selecciona marca --';
      frag.appendChild(optDefault);

      (Array.isArray(data) ? data : []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = String(m.id_marcas);
        opt.textContent = m.nom_marca;
        frag.appendChild(opt);
      });
      marcaSel.appendChild(frag);

      // Verificar si la marca anterior existe en las nuevas opciones
      const existe = seleccionadaAntesId && Array.from(marcaSel.options).some(o => String(o.value) === String(seleccionadaAntesId));

      if (existe) {
        marcaSel.value = String(seleccionadaAntesId);
      } else if (seleccionadaAntesId) {
        // Si no existe, agregarla como "marca actual (de otro equipo)"
        const og = document.createElement('optgroup');
        og.label = 'Marca actual (de otro equipo)';
        const opt = document.createElement('option');
        opt.value = String(seleccionadaAntesId);
        opt.textContent = '(Actual) ' + (seleccionadaAntesTxt || ('ID ' + String(seleccionadaAntesId)));
        og.appendChild(opt);
        marcaSel.appendChild(og);
        marcaSel.value = String(seleccionadaAntesId);
      } else {
        if (marcaSel.options.length > 1) marcaSel.selectedIndex = 0;
      }

      // Limpiar modelos al cambiar equipo
      modeloSel.innerHTML = '<option value="" disabled selected>Selecciona primero una marca</option>';
      updateVisibilityFromEquipo();
    } catch (e) {
      console.error(e);
      marcaSel.innerHTML = '<option value="" disabled selected>Error al cargar marcas</option>';
    }
  });
})();

/* ===== Cambiar MARCA → cargar modelos preservando selección ===== */
(function() {
  const modeloActualId = (modeloSel.dataset.modeloActualId || '').trim();
  const modeloActualTxt = (modeloSel.dataset.modeloActualTxt || '').trim();

  marcaSel.addEventListener('change', async function() {
    const marcaId = this.value;

    // Guardar el modelo seleccionado actualmente
    const seleccionadoAntesId = modeloSel.value || modeloActualId;
    const seleccionadoAntesTxt = (function() {
      if (modeloSel.value) {
        const opt = modeloSel.options[modeloSel.selectedIndex];
        return opt ? opt.textContent.trim() : '';
      }
      return modeloActualTxt;
    })();

    modeloSel.innerHTML = '<option value="" disabled selected>Cargando modelos...</option>';
    if (!marcaId) return;

    try {
      const resp = await fetch('obtener_modelos.php?marca_id=' + encodeURIComponent(marcaId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const data = await resp.json();

      modeloSel.innerHTML = '';
      const frag = document.createDocumentFragment();
      const optDefault = document.createElement('option');
      optDefault.value = '';
      optDefault.disabled = true;
      optDefault.textContent = '-- Selecciona modelo --';
      frag.appendChild(optDefault);

      (Array.isArray(data) ? data : []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = String(m.id);
        opt.textContent = m.num_modelos;
        frag.appendChild(opt);
      });
      modeloSel.appendChild(frag);

      // Verificar si el modelo anterior existe en las nuevas opciones
      const existe = seleccionadoAntesId && Array.from(modeloSel.options).some(o => String(o.value) === String(seleccionadoAntesId));

if (existe) {
  // Modelo pertenece a la marca → normal
  modeloSel.value = String(seleccionadoAntesId);
  document.getElementById('modelo_reasignar').value = '0';

} else if (seleccionadoAntesId) {
  // Modelo de otra marca → el usuario decide conservarlo
  const og = document.createElement('optgroup');
  og.label = 'Modelo actual (de otra marca)';
  const opt = document.createElement('option');
  opt.value = String(seleccionadoAntesId);
  opt.textContent = '(Actual) ' + (seleccionadoAntesTxt || ('ID ' + String(seleccionadoAntesId)));
  og.appendChild(opt);
  modeloSel.appendChild(og);
  modeloSel.value = String(seleccionadoAntesId);

  // 🔴 ESTA ES LA LÍNEA CLAVE
  document.getElementById('modelo_reasignar').value = '1';

} else {
  document.getElementById('modelo_reasignar').value = '0';
}

    } catch (e) {
      console.error(e);
      modeloSel.innerHTML = '<option value="" disabled selected>Error al cargar modelos</option>';
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

/* ============================ VISIBILIDAD POR CATEGORÍA ============================ */
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
  const alarmaKeys = ['sensor', 'dh', 'pir', 'cm', 'oh', 'estrobo', 'estorbo', 'estrobos', 'rep', 'drc', 'teclado', 'sirena', 'boton', 'botón', 'sensor', 'movimiento', 'magnetico', 'magnético', 'contacto', puerta', 'ventana', 'tarjeta de comunicación', 'tarjeta de comunicacion', 'keypad', 'sirena', 'panel', 'pane', 'control', 'expansora', 'modulo', 'módulo', 'panico', 'pánico', 'expansor', 'estación manual', 'estación manual', 'estación manuak', 'em', 'receptora', 'receptor', 'relevador', 'relevadora', 'weigand', 'fuente de poder', 'gp23', 'electro iman', 'electro imán', 'electroiman', 'electroimamn', 'liberador', 'bateria', 'batería', 'transformador', 'trasformador', 'tamper', 'rondin', 'rondín', 'impacto', 'ratonera', transmisor', 'trasmisor', 'pir 360', 'pir360', 'alarma', 'detector', 'humo', 'overhead', 'over head', 'zona', 'pull station', 'pull', 'cableado', 'sirena', 'receptor', 'emisor', 'llavin', 'cristal', 'ruptura', 'repetidor', 'repetidora', 'btn', 'rep', 'em'];
  for (const k of alarmaKeys){ if (v.includes(_normU(k))) return 'alarma'; }
  return 'otro';
}

function _showAll(list){ list.forEach(el=>el.classList.remove('x-hide')); }
function _hideAll(list){ list.forEach(el=>el.classList.add('x-hide')); }
function applyVisibilityByCategory(cat){
  const cctvEls = document.querySelectorAll('.grupo-cctv');
  const switchEls = document.querySelectorAll('.grupo-switch');
  const credEls = document.querySelectorAll('.grupo-credenciales');
  const alarmaEls = document.querySelectorAll('.grupo-alarma');
  const alarmaHideEls = document.querySelectorAll('.grupo-alarma-hide');
  const switchHideEls = document.querySelectorAll('.grupo-switch-hide');
  const monitorHideEls = document.querySelectorAll('.grupo-monitor-hide');

  _hideAll(cctvEls); _hideAll(switchEls); _hideAll(credEls); _hideAll(alarmaEls);
  _hideAll(alarmaHideEls); _hideAll(switchHideEls); _hideAll(monitorHideEls);

  const vms = document.getElementById('vms');
  if (vms) vms.removeAttribute('required');

  if (cat === 'camara' || cat === 'nvr' || cat === 'dvr' || cat === 'servidor'){
    _showAll(cctvEls); _showAll(credEls); _showAll(switchHideEls); _showAll(monitorHideEls);
    if (vms) vms.setAttribute('required','required');
  } else if (cat === 'alarma'){
    _showAll(alarmaEls);
  } else if (cat === 'switch'){
    _showAll(switchEls); _showAll(alarmaHideEls); _hideAll(switchHideEls);
  } else if (cat === 'monitor'){
    _hideAll(monitorHideEls);
  } else {
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
if (equipoSel) equipoSel.addEventListener('change', updateVisibilityFromEquipo);

/* ===== Habilitar/Deshabilitar área analíticas por switch ===== */
const swAnal = document.getElementById('tiene_analitica');
const wrapAnal = document.getElementById('wrapAnaliticas');
if (swAnal && wrapAnal) {
  function syncAnal() {
    wrapAnal.style.opacity = swAnal.checked ? '1' : '.6';
    const inputs = wrapAnal.querySelectorAll('input[type="checkbox"], input[type="text"]');
    inputs.forEach(i => i.disabled = !swAnal.checked);
  }
  swAnal.addEventListener('change', syncAnal);
  syncAnal();
}

// Si el usuario cambia manualmente el modelo, cancelar reasignación
modeloSel.addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  if (opt && opt.parentElement.tagName !== 'OPTGROUP') {
    document.getElementById('modelo_reasignar').value = '0';
  }
});

</script>

<?php
$content = ob_get_clean();
$pageTitle = "Editar dispositivo #$id";
$pageHeader = "Editar dispositivo";
$activePage = "";
include __DIR__ . '/../../layout.php';