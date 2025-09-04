<?php
include __DIR__ . '/../../includes/conexion.php';
include __DIR__ . '/../../includes/footer.php';
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<lang="es">
<head>
<meta charset="UTF-8" />
<title>Registro de usuario</title>
<?php
$stmt = $conexion->query("SELECT COUNT(*) as total FROM usuarios");
$totalUsuarios = $stmt->fetch_assoc()['total'] ?? 0; // Contar usuarios existentes
$limiteAlcanzado = $totalUsuarios >= 1000; // Variable para controlar límite
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
  <?php if ($limiteAlcanzado): ?>
    <div class="alert alert-danger text-center mb-3 w-100">Se ha alcanzado el límite máximo de 1000 usuarios. No se pueden registrar más.</div>
  <?php endif; ?>
  
  <form action="/sisec-ui/controllers/UserController.php" method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm w-100" style="max-width: 700px;">
    <input type="hidden" name="accion" value="crear">
    <h4 class="mb-4 text-center">Registrar usuario</h4>
    <!-- Nombre completo -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="nombre" class="form-label">Nombre completo</label>
        <input type="text" class="form-control" id="nombre" name="nombre" required>
      </div>
      <!-- Correo -->
      <div class="col-md-6">
        <label for="email" class="form-label">Correo</label>
        <input type="text" class="form-control" id="email" name="email" required>
      </div>
    </div>
    <!-- Clave -->
    <div class="mb-3">
      <label for="clave" class="form-label">Contraseña</label>
      <input type="password" class="form-control" id="clave" name="clave" required>
      <div class="mt-2" id="passwordChecklist">
        <small>
          <span id="checkLength" class="text-danger">Al menos 8 caracteres</span><br>
          <span id="checkUpper" class="text-danger">Al menos una mayúscula</span><br>
          <span id="checkLower" class="text-danger">Al menos una minúscula</span><br>
          <span id="checkNumber" class="text-danger">Al menos un número</span><br>
          <span id="checkSpecial" class="text-danger">Al menos un carácter especial (!@#$%^&*)</span>
        </small>
      </div>
    </div>
  <!--Script para los carácteres de la contraseña-->
    <script>
    const claveInput = document.getElementById('clave');
    const checkLength = document.getElementById('checkLength');
    const checkUpper  = document.getElementById('checkUpper');
    const checkLower  = document.getElementById('checkLower');
    const checkNumber = document.getElementById('checkNumber');
    const checkSpecial= document.getElementById('checkSpecial');
    const btnGuardar  = document.getElementById('btnGuardar');
    const passwordMessage = document.getElementById('passwordMessage');

    claveInput.addEventListener('input', ()=>{
        const val = claveInput.value;

        const cumpleLength  = val.length >= 8;
        const cumpleUpper   = /[A-Z]/.test(val);
        const cumpleLower   = /[a-z]/.test(val);
        const cumpleNumber  = /\d/.test(val);
        const cumpleSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(val);

        actualizarRequisito(checkLength, cumpleLength, 'Al menos 8 caracteres');
        actualizarRequisito(checkUpper, cumpleUpper, 'Al menos una mayúscula');
        actualizarRequisito(checkLower, cumpleLower, 'Al menos una minúscula');
        actualizarRequisito(checkNumber, cumpleNumber, 'Al menos un número');
        actualizarRequisito(checkSpecial, cumpleSpecial, 'Al menos un carácter especial (!@#$%^&*)');

        // Mensaje de requisitos pendientes
        let pendientes = [];
        if (!cumpleLength) pendientes.push("mínimo 8 caracteres");
        if (!cumpleUpper) pendientes.push("una mayúscula");
        if (!cumpleLower) pendientes.push("una minúscula");
        if (!cumpleNumber) pendientes.push("un número");
        if (!cumpleSpecial) pendientes.push("un carácter especial (!@#$%^&*)");

        if (pendientes.length > 0) {
            passwordMessage.textContent = "Falta: " + pendientes.join(", ");
            passwordMessage.classList.replace("text-success", "text-danger");
            btnGuardar.setAttribute('disabled', true);
        } else {
            passwordMessage.textContent = "✔ Contraseña válida";
            passwordMessage.classList.replace("text-danger", "text-success");
            btnGuardar.removeAttribute('disabled');
        }
    });

    function actualizarRequisito(elemento, cumple, texto) {
        if (cumple) {
            elemento.classList.replace('text-danger','text-success');
            elemento.textContent = `✔ ${texto}`;
        } else {
            elemento.classList.replace('text-success','text-danger');
            elemento.textContent = texto;
        }
    }
    </script>

    <!-- Cargo -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="cargo" class="form-label">Cargo</label>
        <input type="text" class="form-control" id="cargo" name="cargo" required>
      </div>
      <!-- Empresa -->
      <div class="col-md-6">
        <label for="empresa" class="form-label">Empresa</label>
        <input type="text" class="form-control" id="empresa" name="empresa" required>
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
            if (in_array($valor, ['Superadmin', 'Administrador'])) continue;
            echo "<option value='{$valor}'>{$nombre}</option>";
          }?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="foto" class="form-label">Foto de perfil</label>
        <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
      </div>
    </div>
    <!-- Región -->
    <!-- Ubicación: Región → Ciudad → Municipio → Sucursal -->
    <div class="row mb-3">
      <div class="col-md-3" id="selectRegion" style="display:none;">
        <label>Región</label>
        <select id="region" name="region" class="form-select"><option value="">Seleccione región</option></select>
      </div>
      <div class="col-md-3" id="selectCiudad" style="display:none;">
        <label>Ciudad</label>
        <select id="ciudad" name="ciudad" class="form-select"><option value="">Seleccione ciudad</option></select>
      </div>
      <div class="col-md-3" id="selectMunicipio" style="display:none;">
        <label>Municipio</label>
        <select id="municipio" name="municipio" class="form-select"><option value="">Seleccione municipio</option></select>
      </div>
      <div class="col-md-3" id="selectSucursal" style="display:none;">
        <label>Sucursal</label>
        <select id="sucursal" name="sucursal" class="form-select"><option value="">Seleccione sucursal</option></select>
      </div>
    </div>
    <!-- Pregunta de seguridad -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="pregunta_seguridad" class="form-label">Pregunta de seguridad</label>
        <select class="form-select" id="pregunta_seguridad" name="pregunta_seguridad" required>
          <option value="">Seleccione una pregunta</option>
          <option value="¿Cuál es el nombre de tu primera mascota?">¿Cuál es el nombre de tu primera mascota?</option>
          <option value="¿Cuál es el segundo nombre de tu madre?">¿Cuál es el segundo nombre de tu madre?</option>
          <option value="¿En qué ciudad naciste?">¿En qué ciudad naciste?</option>
          <option value="¿Cuál fue tu primer colegio?">¿Cuál fue tu primer colegio?</option>
          <option value="¿Cómo se llama tu mejor amigo de la infancia?">¿Cómo se llama tu mejor amigo de la infancia?</option>
        </select>
      </div>
      <!-- Respuesta de seguridad -->
      <div class="col-md-6">
        <label for="respuesta_seguridad" class="form-label">Respuesta de seguridad</label>
        <input type="text" class="form-control" id="respuesta_seguridad" name="respuesta_seguridad" required>
      </div>
    </div>
    <!-- Botones -->
    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
      <button type="submit" id="btnGuardar" class="btn btn-primary flex-grow-1" <?= $limiteAlcanzado ? 'disabled' : '' ?> disabled>Guardar usuario</button>
      <div id="passwordMessage" class="text-danger mt-2" style="font-size: 0.9em;"></div>
      <a href="index.php" class="btn btn-danger flex-grow-1">Cancelar</a>
    </div>
  </form>
</div>

<script>
function cargarRegiones() {
    let regionSelect = document.getElementById('region');
    regionSelect.innerHTML = '<option>Cargando...</option>';
    fetch('/sisec-ui/controllers/UserController.php?accion=regiones')
    .then(res => res.json())
    .then(data => {
        regionSelect.innerHTML = '<option value="">Seleccione región</option>';
        data.forEach(r => {
            regionSelect.innerHTML += `<option value="${r.id}">${r.nombre}</option>`;
        });
    });
}

// Cada vez que cambie el rol
document.getElementById('rol').addEventListener('change', function() {
    let rol = this.value;
    ['selectRegion','selectCiudad','selectMunicipio','selectSucursal'].forEach(id=>{
        document.getElementById(id).style.display='none';
    });

    if(['Superadmin','Administrador','Capturista','Técnico','Mantenimientos'].includes(rol)) return;

    if(rol==='Distrital') { 
        document.getElementById('selectRegion').style.display='block'; 
        cargarRegiones();
        return; 
    }

    if(['Prevencion','Monitorista'].includes(rol)){
        ['selectRegion','selectCiudad','selectMunicipio','selectSucursal'].forEach(id=>{
            document.getElementById(id).style.display='block';
        });
        cargarRegiones();
    }
});


// JS de visibilidad y select dinámico (igual que tu versión)
document.getElementById('rol').addEventListener('change', function() {
  let rol = this.value;
  ['selectRegion','selectCiudad','selectMunicipio','selectSucursal'].forEach(id=>{
    document.getElementById(id).style.display='none';
  });
  if(['Superadmin','Administrador','Capturista','Técnico','Mantenimientos'].includes(rol)) return;
  if(rol==='Distrital') { document.getElementById('selectRegion').style.display='block'; return; }
  if(['Prevencion','Monitorista'].includes(rol)){
    ['selectRegion','selectCiudad','selectMunicipio','selectSucursal'].forEach(id=>{ document.getElementById(id).style.display='block'; });
  }
});

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
</body>
</html>