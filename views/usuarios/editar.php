<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion(); // 1️⃣ Verifica si hay sesión iniciada
verificarRol(['Superadmin', 'Administrador']);
//session_start();
include __DIR__ . '/../../includes/conexion.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
  die("ID de usuario inválido.");
}

$resultado = $conexion->query("SELECT * FROM usuarios WHERE id = $id");
$usuario = $resultado->fetch_assoc();
if (!$usuario) {
  die("Usuario no encontrado.");
}

$pageTitle = "Editar usuario";
$pageHeader = "Editar usuario";
$activePage = "usuarios";
ob_start();
?>

<h2 class="mb-4">Editar usuario</h2>
<div class="container d-flex justify-content-center">
  <form action="/sisec-ui/controllers/UserController.php" method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm w-100" style="max-width: 500px;">
    <input type="hidden" name="accion" value="actualizar">
    <input type="hidden" name="id" value="<?= $usuario['id'] ?>">

    <!-- Nombre -->
    <div class="mb-3">
      <label for="nombre" class="form-label">Nombre completo</label>
      <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
    </div>

    <!-- Cargo -->
    <div class="mb-3">
      <label for="cargo" class="form-label">Cargo</label>
      <input type="text" class="form-control" id="cargo" name="cargo" required>
    </div>

    <!-- Empresa -->
    <div class="mb-3">
      <label for="empresa" class="form-label">Empresa</label>
      <input type="text" class="form-control" id="empresa" name="empresa" required>
    </div>

    <!-- Rol -->
    <div class="mb-3">
      <label for="rol" class="form-label">Rol</label>
      <select class="form-select" id="rol" name="rol" required>
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
          // Solo Superadmin puede ver la opción Superadmin
          if ($valor === 'Superadmin' && $ROL_SESION !== 'Superadmin') {
            continue;
          }
          echo "<option value='{$valor}'>{$nombre}</option>";}?>
      </select>
    </div>

    <!-- Región -->
    <div id="selectRegion" style="display:none;">
      <label>Región</label>
      <select id="region" name="region" class="form-select">
        <option value="">Seleccione región</option>
        <?php
        $resRegiones = $conexion->query("SELECT id, nom_region FROM regiones WHERE id IN (1,3,6)");
        while($row = $resRegiones->fetch_assoc()){
          echo "<option value='{$row['id']}'>{$row['nom_region']}</option>";
        }
        ?>
      </select>
    </div>

    <!-- Ciudad -->
    <div id="selectCiudad" style="display:none;">
      <label>Ciudad</label>
      <select id="ciudad" name="ciudad" class="form-select">
        <option value="">Seleccione ciudad</option>
      </select>
    </div>

    <!-- Municipio -->
    <div id="selectMunicipio" style="display:none;">
      <label>Municipio</label>
      <select id="municipio" name="municipio" class="form-select">
        <option value="">Seleccione municipio</option>
      </select>
    </div>

    <!-- Sucursal -->
    <div id="selectSucursal" style="display:none;"><label>Sucursal</label>
    <select id="sucursal" name="sucursal" class="form-select">
      <option value="">Seleccione sucursal</option>
    </select>
    </div>

    <!-- Foto -->
    <div class="mb-3">
      <?php if (!empty($usuario['foto']) && file_exists(__DIR__ . '/../../uploads/usuarios/' . $usuario['foto'])): ?>
        <label class="form-label">Foto actual</label><br>
        <img src="/sisec-ui/uploads/usuarios/<?= htmlspecialchars($usuario['foto']) ?>" alt="foto" width="80" class="mb-2 rounded-circle">
      <?php endif; ?>
      <label for="foto" class="form-label">Cambiar foto de perfil</label>
      <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
    </div>

    <div class="d-flex justify-content-between">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar cambios</button>
      <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</div>

<script>
// Manejo de visibilidad según rol
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
  // Limpiar municipio y sucursal
  document.getElementById('municipio').innerHTML='<option value="">Seleccione municipio</option>';
  document.getElementById('sucursal').innerHTML='<option value="">Seleccione sucursal</option>';
});

// Ciudad -> Municipios (solo Prevención/Monitorista)
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

// Municipio -> Sucursales (solo Prevención/Monitorista)
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