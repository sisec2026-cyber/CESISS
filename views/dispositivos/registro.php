<?php
require_once __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/db.php';
$ciudades = $conn->query("SELECT ID, nom_ciudad FROM ciudades ORDER BY nom_ciudad ASC");
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos']);
ob_start();
$equipo = $_GET['equipo'] ?? 'camara'; // Valor por defecto
?>

<style>
.dropzone {
  cursor: pointer;
  transition: border 0.3s ease, background-color 0.3s ease;
}
.dropzone:hover {
  border: 2px dashed #007bff;
  background-color: #f8f9fa;
}
.preview {
  max-width: 100%;
  max-height: 200px;
  object-fit: contain;
}
</style>

<style>
#sugerencia {
  margin-bottom: 1rem;
  background-color: #f0f8ff;
  padding: 10px;
  border-left: 5px solid #2196F3;
  transition: opacity 1s ease;
}
</style>

<h2 class="mb-4">Registrar dispositivo</h2>
<div id="sugerencia">
  <small>Nota:</small> Si los datos aplican tanto para CCTV como para Alarma. El sistema los organizará correctamente.
</div>

<form action="guardar.php" method="post" enctype="multipart/form-data" class="p-4" style="max-width: 1100px; margin: auto;">
  <div class="row g-4">
    <!-- CAMPOS QUE SIEMPRE SERAN VISIBLES -->
    <!-- Equipo -->
    <div class="col-md-3">
      <label class="form-label">Equipo</label>
      <input type="text" name= "equipo" placeholder="Ej. CCTV, DVR, NVR..." class="form-control" required>
    </div>
    <!-- Fecha de instalacion -->
    <div class="col-md-3">
      <label class="form-label">Fecha instalación/mantenimiento</label>
      <input type="date" name="fecha" class="form-control" required>
    </div>

    <!-- Modelo -->
    <div class="col-md-3">
      <label class="form-label">Modelo del equipo</label>
      <input type="text" name="modelo" placeholder="Escribe el modelo" class="form-control" required>
    </div>

    <!-- Estado -->
    <div class="col-md-3">
      <label class="form-label">Estado</label>
      <input type="text" name="estado" class="form-control" placeholder="Escribe el status" required>
    </div>
    
    <!-- CAMPOS ADICIONALES DE USUARIO Y UBICACIÓN -->
    <div class="row g-4 mt-4">
    <!-- Usuario -->
    <div class="col-md-4">
      <label class="form-label">Usuario</label>
      <input type="text" name="user" placeholder="Nombre de usuario" class="form-control" required>
    </div>

    <!-- Contraseña -->
    <div class="col-md-4">
      <label class="form-label">Contraseña</label>
      <input type="password" name="pass" placeholder="Contraseña de usuario" class="form-control" required>
    </div>

    <!-- Ciudad -->
    <div class="col-md-4">
      <label class="form-label">Ciudad</label>
      <select name="ciudad" id="ciudad" class="form-select" required>
        <option value="">-- Selecciona una ciudad --</option>
        <?php while ($row = $ciudades->fetch_assoc()): ?>
        <option value="<?= $row['ID'] ?>"><?= htmlspecialchars($row['nom_ciudad']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    
    <!-- Municipio -->
    <div class="col-md-6">
      <label class="form-label">Municipio</label>
      <select name="municipio" id="municipio" class="form-select" required>
        <option value="">-- Selecciona un municipio --</option>
      </select>
    </div>
    
    <!-- Sucursal -->
    <div class="col-md-6">
      <label class="form-label">Sucursal</label>
      <input type="text" name="sucursal" placeholder="Ej. SB Plaza las antenas" class="form-control">
    </div>
  </div>
  
  <!-- Observaciones -->
   <div class="col-md-6">
    <label class="form-label">Observaciones</label>
    <input type="text" name="observaciones" placeholder="Escribe alguna observación" class="form-control">
  </div>
  
  <!-- Serie -->
   <div class="col-md-3">
    <label class="form-label">Número de serie</label>
    <input type="text" name="serie" placeholder="Escribe el número de serie" class="form-control">
  </div>

  <!-- Área de la tienda -->
    <div class="col-md-3">
      <label class="form-label">Ubicacion en tienda</label>
      <input type="text" name="area" placeholder= "Área de tienda" class="form-control" value="<?= htmlspecialchars($device['area'] ?? '') ?>">
    </div>
    
    <!-- CAMPOS ESPECIFICOS PARA CCTV -->
    <!-- No. de Servidor -->
    <div class="grupo-cctv d-none">
      <div class="row g-4">
        <div class="col-md-3">
          <label class="form-label">No. de Servidor</label>
          <input type="text" name="servidor" placeholder= "Escribe el numero de servidor" class="form-control" value="<?= htmlspecialchars($device['servidor'] ?? '') ?>">
        </div>
        
    <!-- VMS -->
    <div class="col-md-3">
      <label class="form-label">VMS</label>
      <input type="text" name="vms" placeholder= "Escribe el vms" class="form-control" value="<?= htmlspecialchars($device['servidor'] ?? '') ?>">
    </div>

    <!-- Switch -->
    <div class="col-md-3">
      <label class="form-label">Switch</label>
      <input type="text" name="switch" placeholder= "Escribe el switch" class="form-control" value="<?= htmlspecialchars($device['switch'] ?? '') ?>">
    </div>

    <!-- puerto -->
    <div class="col-md-3">
      <label class="form-label">No. Puerto</label>
      <input type="text" name="puerto" placeholder= "Escribe el número de puerto del switch" class="form-control" value="<?= htmlspecialchars($device['puerto'] ?? '') ?>">
    </div>
    </div>

    <!-- Dirección MAC -->
    <div class="col-md-3">
      <label class="form-label">Dirección MAC</label>
      <input type="text" name="mac" id="macInput" placeholder="00:11:22:33:44:55" class="form-control" oninput="formatearYValidarMac(this)">
      <input type="text" id="tag" class="form-control mt-2" disabled>
    </div>

    <!-- Dirección IP -->
    <div class="col-md-3">
      <label class="form-label">Dirección IP</label>
      <input type="text" name="ip" placeholder="192.168.1.1" class="form-control" oninput="formatearYValidarIP(this)">
    </div>
    </div>
    <!-- Fin de campos específicos para CCTV -->


    <!-- CAMPOS ESPECÍFICOS PARA ALARMA -->
    <div class="grupo-alarma d-none">
      <div class="row">
        <div class="col-md-4">
          <label class="form-label">Zona del sistema de alarma</label>
          <input type="text" name="zona_alarma" placeholder="Ej: Zona 1" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo de sensor</label>
          <input type="text" name="tipo_sensor" placeholder="Ej: PIR, Humo, etc." class="form-control">
        </div>
      </div>
    </div>
    
    <!-- IMAGENES -->
    <div class="row align-items-stretch mt-3">
      <!-- Imagen 1 -->
      <div class="col-md-4 mb-3">
        <div class="border rounded shadow-sm h-100 p-3"> <!-- Contorno externo -->
          <label class="form-label">Imagen</label>
          <div class="dropzone border rounded text-center p-4 position-relative" id="drop-imagen" data-input="imagen">
            <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
            <p class="text-muted mensaje">Arrastra una imagen aquí o haz clic</p>
            <input type="file" name="imagen" id="imagen" class="d-none" accept="image/*" required>
            <img id="preview-imagen" class="preview d-none mt-2" src="#" alt="Preview Imagen 1">
            <button type="button" class="btn btn-danger btn-sm d-none remove-btn mt-2" data-target="imagen">Eliminar</button>
          </div>
        </div>
      </div>
      
    <!-- Imagen 2 -->
    <div class="col-md-4 mb-3">
      <div class="border rounded shadow-sm h-100 p-3"> <!-- Contorno externo -->
        <label class="form-label">Imagen antes</label>
        <div class="dropzone border rounded text-center p-4 position-relative" id="drop-imagen2" data-input="imagen2">
          <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
          <p class="text-muted mensaje">Arrastra una imagen aquí o haz clic</p>
          <input type="file" name="imagen2" id="imagen2" class="d-none" accept="image/*" required>
          <img id="preview-imagen2" class="preview d-none mt-2" src="#" alt="Preview Imagen 2">
          <button type="button" class="btn btn-danger btn-sm d-none remove-btn mt-2" data-target="imagen2">Eliminar</button>
        </div>
      </div>
    </div>
    
    <!-- Imagen 3 -->
    <div class="col-md-4 mb-3">
      <div class="border rounded shadow-sm h-100 p-3"> <!-- Contorno externo -->
        <label class="form-label">Imagen después</label>
        <div class="dropzone border rounded text-center p-4 position-relative" id="drop-imagen3" data-input="imagen3">
          <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
          <p class="text-muted mensaje">Arrastra una imagen aquí o haz clic</p>
          <input type="file" name="imagen3" id="imagen3" class="d-none" accept="image/*" required>
          <img id="preview-imagen3" class="preview d-none mt-2" src="#" alt="Preview Imagen 3">
          <button type="button" class="btn btn-danger btn-sm d-none remove-btn mt-2" data-target="imagen3">Eliminar</button>
        </div>
      </div>
    </div>
  </div>
    <!-- Fin de imágenes -->
    
    <!-- Botón -->
    <div class="col-12 text-center">
      <button type="submit" class="btn btn-secondary px-5 py-2 rounded-pill shadow">
        <i class="fas fa-qrcode me-2"></i> Guardar y generar QR
      </button>
    </div>
  </div>
</form>

<script>
document.querySelectorAll('.dropzone').forEach(dropzone => {
  const inputId = dropzone.dataset.input;
  const fileInput = document.getElementById(inputId);
  const preview = document.getElementById(`preview-${inputId}`);
  const removeBtn = dropzone.querySelector('.remove-btn');
  const icono = dropzone.querySelector('.icono');
  const mensaje = dropzone.querySelector('.mensaje');
  const resetImage = () => {
    fileInput.value = '';
    preview.src = '#';
    preview.classList.add('d-none');
    removeBtn.classList.add('d-none');
    if (icono) icono.classList.remove('d-none');
    if (mensaje) mensaje.classList.remove('d-none');
  };
  
  dropzone.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
      preview.src = URL.createObjectURL(fileInput.files[0]);
      preview.classList.remove('d-none');
      removeBtn.classList.remove('d-none');
      if (icono) icono.classList.add('d-none');
      if (mensaje) mensaje.classList.add('d-none');
    }
  });
  
  dropzone.addEventListener('dragover', e => {
    e.preventDefault();
    dropzone.classList.add('bg-light');
  });
  
  dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('bg-light');
  });
  
  dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('bg-light');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
      const dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files = dt.files;
      preview.src = URL.createObjectURL(file);
      preview.classList.remove('d-none');
      removeBtn.classList.remove('d-none');
      if (icono) icono.classList.add('d-none');
      if (mensaje) mensaje.classList.add('d-none');
    }
  });
  
  removeBtn.addEventListener('click', e => {
    e.stopPropagation();
    resetImage();
  });
});

</script>

<script>
const equipoInput = document.querySelector('input[name="equipo"]');
const grupoCCTV = document.querySelector('.grupo-cctv');
const grupoAlarma = document.querySelector('.grupo-alarma');
const palabrasClaveCCTV = ["camara", 'cámara', "vms", "servidor", "cctv", "switch", "dvr", "nvr", "servidor","videoportero", "monitor","joystick","rack", "fuentes de camaras"];
const palabrasClaveAlarma = ["alarma", "transmisor", "sensor", "detector", "humo", "over head", "zona", "boton", "estacion", "panel", "cableado", "sirena", "receptor", "emisor", "pir", "llavin", "contacto", "repetidor", "teclado", "estrobo"] ;
equipoInput.addEventListener('input', () => {
  const valor = equipoInput.value.toLowerCase();
  const contieneCCTV = palabrasClaveCCTV.some(palabra => valor.includes(palabra));
  const contieneAlarma = palabrasClaveAlarma.some(palabra => valor.includes(palabra));
  
  // Mostrar u ocultar grupos
  grupoCCTV.classList.toggle('d-none', !contieneCCTV);
  grupoAlarma.classList.toggle('d-none', !contieneAlarma);
  });
  </script>
  
  <!-- Validar MAC  -->
  <script>
  function formatearYValidarMac(input) {
    let valor = input.value;
    
    // Eliminar todo lo que no sea hexadecimal
  valor = valor.replace(/[^A-Fa-f0-9]/g, '');

  // Dividir en pares de dos
  let partes = valor.match(/.{1,2}/g) || [];

  // Limitar a 6 pares (MAC Address)
  partes = partes.slice(0, 6);

  // Unir con ":"
  let macFormateada = partes.join(':');

  // Actualizar el valor del input
  input.value = macFormateada;

  // Validar con expresión regular
  let regex = /^([0-9A-Fa-f]{2}:){5}([0-9A-Fa-f]{2})$/;
  let tag = document.getElementById('tag');

  if (regex.test(macFormateada)) {
    tag.style.color = 'green';
    tag.value = '✅ MAC válida';
  } else {
    tag.style.color = 'red';
    tag.value = '❌ MAC inválida';
  }
}
</script>
  
                                                           <!-- Validar IP  -->             
<script>
function validarIP(input) {
  const ip = input.value;

  // Elimina caracteres inválidos
  input.value = ip.replace(/[^0-9.]/g, '');

  const partes = ip.split('.');
  const esValida = partes.length === 4 && partes.every(p => {
    const num = parseInt(p);
    return !isNaN(num) && num >= 0 && num <= 255;
  });

  document.getElementById('ipTag').value = esValida ? '✅ IP válida' : '❌ IP inválida';
}
</script>

                                                      <!-- Tiempo de la sugerencia -->
<script>
      setTimeout(() => {
      const sugerencia = document.getElementById('sugerencia');
      sugerencia.style.opacity = '0';
      setTimeout(() => sugerencia.remove(), 1000); // lo remueve del DOM tras el desvanecimiento
    }, 3000); // 1 segundo
</script>

<script>
document.getElementById('ciudad').addEventListener('change', function () {
  const ciudadID = this.value;
  const municipioSelect = document.getElementById('municipio');

  // Mensaje de carga temporal
  municipioSelect.innerHTML = '<option value="">Cargando municipios...</option>';

  fetch(`obtener_municipios.php?ciudad_id=${ciudadID}`)
    .then(response => response.json())
    .then(data => {
      municipioSelect.innerHTML = '<option value="">-- Selecciona un municipio --</option>';
      data.forEach(municipio => {
        const option = document.createElement('option');
        option.value = municipio.ID;
        option.textContent = municipio.nom_municipio;
        municipioSelect.appendChild(option);
      });
    })
    .catch(error => {
      municipioSelect.innerHTML = '<option value="">Error al cargar</option>';
      console.error('Error cargando municipios:', error);
    });
});
</script>


<?php
$content = ob_get_clean();
$pageTitle = "Registrar dispositivo";
$pageHeader = "Registro de dispositivo";
$activePage = "registro";

include __DIR__ . '/../../layout.php';
?>