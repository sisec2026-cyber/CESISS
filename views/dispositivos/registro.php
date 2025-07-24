<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos']);

ob_start();

$equipo = $_GET['equipo'] ?? 'camara'; // Valor por defecto

?>

<h2 class="mb-4">Registrar dispositivo</h2>

<form action="guardar.php" method="post" enctype="multipart/form-data" class="p-4" style="max-width: 950px; margin: auto;">
  <div class="row g-4">

    <!-- Equipo y Fecha -->
    <div class="col-md-3">
      <label class="form-label">Equipo</label>
      <input type="text" name= "equipo" placeholder="Escribe el tipo de dispositivo" class="form-control" required>
    </div>


    <div class="col-md-3">
      <label class="form-label">Fecha de instalación o mantenimiento</label>
      <input type="date" name="fecha" class="form-control" required>
    </div>

    <!-- Modelo y Estado -->
    <div class="col-md-3">
      <label class="form-label">Modelo</label>
      <input type="text" name="modelo" placeholder="Escribe el modelo" class="form-control" required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Estado del equipo</label>
      <select name="estado" class="form-select" required>
        <option value="">Selecciona</option>
        <option value="Activo">Activo</option>
        <option value="En mantenimiento">En mantenimiento</option>
        <option value="Desactivado">Desactivado</option>
      </select>
    </div>

    <!-- Sucursal y Observaciones -->
    <div class="col-md-6">
      <label class="form-label">Ubicación del equipo (Sucursal)</label>
      <input type="text" name="sucursal" placeholder="Escribe el lugar de la instalación" class="form-control" required>
    </div>

    <div class="col-md-6">
      <label class="form-label">Observaciones</label>
      <input type="text" name="observaciones" placeholder="Escribe alguna observación" class="form-control">
    </div>

    <!-- Serie y MAC -->
    <div class="col-md-3">
      <label class="form-label">Número de serie</label>
      <input type="text" name="serie" placeholder="Escribe el número de serie" class="form-control">
    </div>

    <div class="col-md-3">
      <label class="form-label">Dirección MAC</label>
      <input type="text" name="mac" placeholder="00:11:22:33:44:55" class="form-control">
    </div>

    <div class="col-md-3">
      <label class="form-label">No. de Servidor</label>
      <input type="text" name="servidor" placeholder= "Escribe el numero de servidor" class="form-control" value="<?= htmlspecialchars($device['servidor'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label">VMS</label>
      <input type="text" name="vms" placeholder= "Escribe el vms" class="form-control" value="<?= htmlspecialchars($device['servidor'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label">Switch</label>
      <input type="text" name="switch" placeholder= "Escribe el switch" class="form-control" value="<?= htmlspecialchars($device['switch'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label">No. Puerto</label>
      <input type="text" name="puerto" placeholder= "Escribe el número de puerto del switch" class="form-control" value="<?= htmlspecialchars($device['puerto'] ?? '') ?>">
    </div>

    <!-- Área de la tienda -->
    <div class="col-md-4">
      <label class="form-label">Área de la tienda</label>
      <input type="text" name="area" placeholder= "Escribe el area de la tienda" class="form-control" value="<?= htmlspecialchars($device['puerto'] ?? '') ?>">
    </div>

            <!-- Imagen 1 -->
    <div class="col-md-4">
      <label class="form-label">Imagen</label>
      <div class="dropzone border rounded text-center p-4 position-relative" id="drop-imagen" data-input="imagen">
        <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
        <p class="text-muted mensaje">Arrastra una imagen aquí o haz clic</p>
        <input type="file" name="imagen" id="imagen" class="d-none" accept="image/*" required>
        <img id="preview-imagen" class="preview d-none mt-2" src="#" style="max-height: 150px;">
        <button type="button" class="btn btn-danger btn-sm d-none remove-btn mt-2" data-target="imagen">Eliminar</button>
      </div>
    </div>

    <!-- Imagen 2 -->
    <div class="col-md-4">
      <label class="form-label">Imagen antes</label>
      <div class="dropzone border rounded text-center p-4 position-relative" id="drop-imagen2" data-input="imagen2">
        <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
        <p class="text-muted mensaje">Arrastra una imagen aquí o haz clic</p>
        <input type="file" name="imagen2" id="imagen2" class="d-none" accept="image/*" required>
        <img id="preview-imagen2" class="preview d-none mt-2" src="#" style="max-height: 150px;">
        <button type="button" class="btn btn-danger btn-sm d-none remove-btn mt-2" data-target="imagen2">Eliminar</button>
      </div>
    </div>

    <!-- Imagen 3 -->
    <div class="col-md-4">
      <label class="form-label">Imagen después</label>
      <div class="dropzone border rounded text-center p-4 position-relative" id="drop-imagen3" data-input="imagen3">
        <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
        <p class="text-muted mensaje">Arrastra una imagen aquí o haz clic</p>
        <input type="file" name="imagen3" id="imagen3" class="d-none" accept="image/*" required>
        <img id="preview-imagen3" class="preview d-none mt-2" src="#" style="max-height: 150px;">
        <button type="button" class="btn btn-danger btn-sm d-none remove-btn mt-2" data-target="imagen3">Eliminar</button>
      </div>
    </div>



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




<?php
$content = ob_get_clean();
$pageTitle = "Registrar dispositivo";
$pageHeader = "Registro de dispositivo";
$activePage = "registro";

include __DIR__ . '/../../layout.php';
?>