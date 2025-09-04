<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin', 'Administrador']);
include __DIR__ . '/../../includes/conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
  die("ID de usuario inválido.");
}

$resultado = $conexion->query("SELECT * FROM usuarios WHERE id = $id");
$usuario = $resultado->fetch_assoc() ?: [];
if (!$usuario) {
  die("Usuario no encontrado.");
}

$pageTitle = "Editar usuario";
$pageHeader = "Editar usuario";
$activePage = "usuarios";
ob_start();
?>

<h2 class="mb-4">Editar usuario</h2>
<div class="container d-flex justify-content-center mt-4 mb-5">
  <form action="/sisec-ui/controllers/UserController.php" method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm w-100" style="max-width: 700px;">
    <input type="hidden" name="accion" value="actualizar">
    <input type="hidden" name="id" value="<?= htmlspecialchars($usuario['id'] ?? '') ?>">

    <!-- Nombre y correo -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="nombre" class="form-label">Nombre completo</label>
        <input type="text" class="form-control" id="nombre" name="nombre"
               value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" required>
      </div>
      <div class="col-md-6">
        <label for="email" class="form-label">Correo</label>
        <input type="email" class="form-control" id="email" name="email"
               value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required>
      </div>
    </div>

    <!-- Contraseña -->
    <div class="mb-3">
      <label for="clave" class="form-label">Nueva contraseña (opcional)</label>
      <input type="password" class="form-control" id="clave" name="clave">
      <small class="text-muted">Déjalo en blanco si no deseas cambiar la contraseña</small>
    </div>

    <!-- Cargo y empresa -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="cargo" class="form-label">Cargo</label>
        <input type="text" class="form-control" id="cargo" name="cargo"
               value="<?= htmlspecialchars($usuario['cargo'] ?? '') ?>" required>
      </div>
      <div class="col-md-6">
        <label for="empresa" class="form-label">Empresa</label>
        <input type="text" class="form-control" id="empresa" name="empresa"
               value="<?= htmlspecialchars($usuario['empresa'] ?? '') ?>" required>
      </div>
    </div>

    <!-- Rol -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="rol" class="form-label">Rol</label>
        <select class="form-select" id="rol" name="rol" required>
          <option value="">Seleccione un rol</option>
          <?php
          $roles = [
            'Superadmin' => 'Super administrador',
            'Administrador' => 'Administrador',
            'Capturista' => 'Capturista',
            'Técnico' => 'Técnico',
            'Distrital' => 'Distrital',
            'Prevencion' => 'Jefe de prevención',
            'Mantenimientos' => 'Mantenimientos',
            'Monitorista' => 'Monitorista'
          ];
          $ROL_SESION = $_SESSION['usuario_rol'] ?? '';
          foreach ($roles as $valor => $nombre) {
            if ($valor === 'Superadmin' && $ROL_SESION !== 'Superadmin') continue;
            $sel = (($usuario['rol'] ?? '') === $valor) ? 'selected' : '';
            echo "<option value='{$valor}' {$sel}>{$nombre}</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="foto" class="form-label">Foto de perfil</label><br>
        <?php if (!empty($usuario['foto']) && file_exists(__DIR__ . '/../../uploads/usuarios/' . $usuario['foto'])): ?>
          <img src="/sisec-ui/uploads/usuarios/<?= htmlspecialchars($usuario['foto']) ?>" alt="foto" width="60" class="mb-2 rounded-circle"><br>
        <?php endif; ?>
        <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
      </div>
    </div>

    <!-- Región, ciudad, municipio, sucursal -->
    <div class="row mb-3">
      <div class="col-md-3" id="selectRegion" style="display:none;">
        <label>Región</label>
        <select id="region" name="region" class="form-select">
          <option value="">Seleccione región</option>
          <?php
          $resRegiones = $conexion->query("SELECT id, nom_region FROM regiones WHERE id IN (1,3,6)");
          while($row = $resRegiones->fetch_assoc()){
            $sel = (($usuario['region'] ?? '') == $row['id']) ? 'selected' : '';
            echo "<option value='{$row['id']}' {$sel}>{$row['nom_region']}</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-md-3" id="selectCiudad" style="display:none;">
        <label>Ciudad</label>
        <select id="ciudad" name="ciudad" class="form-select">
          <option value="<?= htmlspecialchars($usuario['ciudad'] ?? '') ?>">
            <?= htmlspecialchars($usuario['ciudad'] ?? 'Seleccione ciudad') ?>
          </option>
        </select>
      </div>
      <div class="col-md-3" id="selectMunicipio" style="display:none;">
        <label>Municipio</label>
        <select id="municipio" name="municipio" class="form-select">
          <option value="<?= htmlspecialchars($usuario['municipio'] ?? '') ?>">
            <?= htmlspecialchars($usuario['municipio'] ?? 'Seleccione municipio') ?>
          </option>
        </select>
      </div>
      <div class="col-md-3" id="selectSucursal" style="display:none;">
        <label>Sucursal</label>
        <select id="sucursal" name="sucursal" class="form-select">
          <option value="<?= htmlspecialchars($usuario['sucursal'] ?? '') ?>">
            <?= htmlspecialchars($usuario['sucursal'] ?? 'Seleccione sucursal') ?>
          </option>
        </select>
      </div>
    </div>

    <!-- Pregunta y respuesta de seguridad -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="pregunta_seguridad" class="form-label">Pregunta de seguridad</label>
        <select class="form-select" id="pregunta_seguridad" name="pregunta_seguridad" required>
          <option value="">Seleccione una pregunta</option>
          <?php
          $preguntas = [
            "¿Cuál es el nombre de tu primera mascota?",
            "¿Cuál es el segundo nombre de tu madre?",
            "¿En qué ciudad naciste?",
            "¿Cuál fue tu primer colegio?",
            "¿Cómo se llama tu mejor amigo de la infancia?"
          ];
          foreach ($preguntas as $p) {
            $sel = (($usuario['pregunta_seguridad'] ?? '') === $p) ? 'selected' : '';
            echo "<option value=\"$p\" $sel>$p</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="respuesta_seguridad" class="form-label">Respuesta de seguridad</label>
        <input type="text" class="form-control" id="respuesta_seguridad" name="respuesta_seguridad"
               value="<?= htmlspecialchars($usuario['respuesta_seguridad'] ?? '') ?>">
      </div>
    </div>

    <!-- Botones -->
    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
      <button type="submit" class="btn btn-primary flex-grow-1">Guardar cambios</button>
      <a href="index.php" class="btn btn-danger flex-grow-1">Cancelar</a>
    </div>
  </form>
</div>

<script>
// Visibilidad según rol
document.getElementById('rol').addEventListener('change', function() {
  let rol = this.value;
  ['selectRegion','selectCiudad','selectMunicipio','selectSucursal'].forEach(id=>{
    document.getElementById(id).style.display='none';
  });
  if(['Superadmin','Administrador','Capturista','Técnico','Mantenimientos'].includes(rol)) return;
  if(rol==='Distrital'){
    document.getElementById('selectRegion').style.display='block';
    return;
  }
  if(['Prevencion','Monitorista'].includes(rol)){
    ['selectRegion','selectCiudad','selectMunicipio','selectSucursal'].forEach(id=>{
      document.getElementById(id).style.display='block';
    });
    return;
  }
});

// Región -> Ciudades
document.getElementById('region').addEventListener('change', function(){
  let regionId = this.value;
  let ciudadSelect = document.getElementById('ciudad');
  ciudadSelect.innerHTML = '<option>Cargando...</option>';
  fetch('/sisec-ui/controllers/UserController.php?accion=ciudades&region=' + regionId)
  .then(res=>res.json())
  .then(data=>{
    ciudadSelect.innerHTML = '<option value="">Seleccione ciudad</option>';
    data.forEach(c=>{ ciudadSelect.innerHTML += `<option value="${c.id}">${c.nombre}</option>` });
  });
  document.getElementById('municipio').innerHTML='<option value="">Seleccione municipio</option>';
  document.getElementById('sucursal').innerHTML='<option value="">Seleccione sucursal</option>';
});

// Ciudad -> Municipios
document.getElementById('ciudad').addEventListener('change', function(){
  if(!['Prevencion','Monitorista'].includes(document.getElementById('rol').value)) return;
  let ciudadId = this.value;
  let municipioSelect = document.getElementById('municipio');
  municipioSelect.innerHTML = '<option>Cargando...</option>';
  fetch('/sisec-ui/controllers/UserController.php?accion=municipios&ciudad=' + ciudadId)
  .then(res=>res.json())
  .then(data=>{
    municipioSelect.innerHTML = '<option value="">Seleccione municipio</option>';
    data.forEach(m=>{ municipioSelect.innerHTML += `<option value="${m.id}">${m.nombre}</option>` });
  });
  document.getElementById('sucursal').innerHTML='<option value="">Seleccione sucursal</option>';
});

// Municipio -> Sucursales
document.getElementById('municipio').addEventListener('change', function(){
  if(!['Prevencion','Monitorista'].includes(document.getElementById('rol').value)) return;
  let municipioId = this.value;
  let sucursalSelect = document.getElementById('sucursal');
  sucursalSelect.innerHTML = '<option>Cargando...</option>';
  fetch('/sisec-ui/controllers/UserController.php?accion=sucursales&municipio=' + municipioId)
  .then(res=>res.json())
  .then(data=>{
    sucursalSelect.innerHTML = '<option value="">Seleccione sucursal</option>';
    data.forEach(s=>{ sucursalSelect.innerHTML += `<option value="${s.id}">${s.nombre}</option>` });
  });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>