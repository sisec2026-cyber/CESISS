  <?php
  require_once __DIR__ . '/../../includes/auth.php';
  include __DIR__ . '/../../includes/db.php';

  verificarAutenticacion();
  verificarRol(['Superadmin','Administrador', 'Mantenimientos']);

  $ciudades = $conn->query("SELECT ID, nom_ciudad FROM ciudades ORDER BY nom_ciudad ASC");
  $equipo = $_GET['equipo'] ?? 'camara'; // Valor por defecto

  ob_start();
  ?>


  <!-- ESTILOS -->
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

    #sugerencia {
      margin-bottom: 1rem;
      background-color: #f0f8ff;
      padding: 10px;
      border-left: 5px solid #2196F3;
      transition: opacity 1s ease;
    }

    #camaraIP.activo,
    #camaraAnalogica.activo {
      background-color: #007bff;
      color: white;
    }
    #tipoAlarmaContainer .activo,
  #tipoSwitchContainer .activo {
    background-color: #007bff;
    color: white;
  }


  </style>

  <!-- ENCABEZADO -->
  <h2 class="mb-4">Registrar dispositivo</h2>
  <div id="sugerencia">
    <small>Nota:</small> Si los datos aplican tanto para CCTV como para Alarma, el sistema los organizará correctamente.
  </div>

  <!-- FORMULARIO -->
  <form action="guardar.php" method="post" enctype="multipart/form-data" class="p-4" style="max-width: 1100px; margin: auto;">
    <div class="row g-4">

      <!-- BLOQUE 1: Datos básicos -->
      <div class="row g-4 mt-3">
        <div class="col-md-3">
  <label class="form-label">Equipo</label>
  <input
    type="text"
    name="equipo"
    id="equipo"
    class="form-control"
    placeholder="Ej. Cámara, DVR, NVR, Switch, Servidor, Monitor, Alarma…"
    list="equipos-sugeridos"
    oninput="actualizarMarcaYBotones()"
  />
  <datalist id="equipos-sugeridos">
    <option value="Cámara"></option>
    <option value="CCTV"></option>
    <option value="Switch"></option>
    <option value="NVR"></option>
    <option value="DVR"></option>
    <option value="Servidor"></option>
    <option value="Monitor"></option>
    <option value="Estación de trabajo"></option>
    <option value="Alarma"></option>
    <option value="Pir"></option>
  </datalist>

        </div>
                                                                      <!-- INICIO BOTONES -->
  <!-- Botones para tipo de alarma -->
  <div id="tipoAlarmaContainer" class="mt-2 d-none">
    <button type="button" class="btn btn-outline-primary me-2 tipo-alarma" onclick="document.getElementById('tipo_alarma').value = 'Alámbrico'">Alámbrico</button>
    <button type="button" class="btn btn-outline-primary tipo-alarma" onclick="document.getElementById('tipo_alarma').value = 'Inalámbrico'">Inalámbrico</button>
  </div>

  <!-- Botones para tipo de switch -->
  <div id="tipoSwitchContainer" class="mt-2 d-none">
    <button type="button" class="btn btn-outline-primary me-2 tipo-switch" onclick="document.getElementById('tipo_switch').value = 'Plano'">Plano</button>
    <button type="button" class="btn btn-outline-primary tipo-switch" onclick="document.getElementById('tipo_switch').value = 'PoE'">PoE</button>
  </div>

  <!-- Botones para tipo de cámara -->
  <div id="tipoCamaraContainer" style="display: none; margin-top: 10px;">
    <button type="button" id="camaraIP" class="btn btn-outline-primary" onclick="document.getElementById('tipo_cctv').value = 'IP'">IP</button>
    <button type="button" id="camaraAnalogica" class="btn btn-outline-primary" onclick="document.getElementById('tipo_cctv').value = 'Analógico'">Analógica</button>
  </div>
                                                                        <!-- FIN BOTONES -->
    <!-- Campos ocultos que guardan el tipo seleccionado -->
  <input type="hidden" name="tipo_alarma" id="tipo_alarma">
  <input type="hidden" name="tipo_switch" id="tipo_switch">
  <input type="hidden" name="tipo_cctv" id="tipo_cctv">
                                                                  
        <div class="col-md-3 mt-2">
          <label class="form-label">Marca</label>
          <select name="marca" id="marca" class="form-control">
            <option value="">Selecciona una marca</option>
          </select>
        </div>

        <div id="tipo-dispositivo-wrapper" style="display: none;"></div>

        <div class="col-md-3">
          <label class="form-label">Número de serie</label>
          <input type="text" name="serie" class="form-control" placeholder="Escribe el número de serie">
        </div>

        <div class="col-md-3">
          <label class="form-label">Fecha instalación/mantenimiento</label>
          <input type="date" name="fecha" class="form-control" required>
        </div>

  <div class="col-md-3">
    <label class="form-label">Modelo del equipo</label>
    <input type="text" name="modelo" id="modelo" class="form-control" list="sugerencias-modelo" placeholder="Escribe el modelo" required>
    <datalist id="sugerencias-modelo"></datalist>
  </div>


        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <input type="text" name="estado" class="form-control" placeholder="Escribe el status" required>
        </div>

      <div class="col-md-3 campo-user">
        <label class="form-label">Usuario</label>
        <input type="text" name="user" class="form-control" placeholder="Nombre de usuario">
      </div>

      <div class="col-md-3 campo-pass">
        <label class="form-label">Contraseña</label>
        <input type="password" name="pass" class="form-control" placeholder="Contraseña de usuario">
      </div>
    </div>

      <!-- BLOQUE 2: Ubicación -->
      <div class="row g-4 mt-3">
        <div class="col-md-3">
          <label class="form-label">Ciudad</label>
          <select name="ciudad" id="ciudad" class="form-select" required>
            <option value="">-- Selecciona una ciudad --</option>
            <?php while ($row = $ciudades->fetch_assoc()): ?>
              <option value="<?= $row['ID'] ?>"><?= htmlspecialchars($row['nom_ciudad']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Municipio</label>
          <select name="municipio" id="municipio" class="form-select" required>
            <option value="">-- Selecciona un municipio --</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Sucursal</label>
          <input type="text" name="sucursal" class="form-control" placeholder="Ej. SB Plaza las antenas">
        </div>

        <div class="col-md-3">
          <label class="form-label">Determinante</label>
          <input type="text" name="determinante" class="form-control" placeholder="Num determinante">
        </div>
      </div>

      <!-- BLOQUE 3: Red y configuración -->
      <div class="row g-4 mt-3">
        <div class="col-md-3">
          <label class="form-label">Ubicación en tienda</label>
          <input type="text" name="area" class="form-control" placeholder="Área de tienda" value="<?= htmlspecialchars($device['area'] ?? '') ?>">
        </div>

        <div class="col-md-3 campo-rc d-none">
          <label class="form-label">RC</label>
          <input type="text" name="rc" class="form-control" placeholder="Ej. RC1, RC2 o N/A">
        </div>

        <div class="col-md-3 campo-ubicacion-rc d-none">
          <label class="form-label">Ubi. RC tienda</label>
          <input type="text" name="Ubicacion_rc" class="form-control" placeholder="Ej. Ubicación de RC">
        </div>

        <div class="col-md-3">
          <label class="form-label">Switch</label>
          <input type="text" name="switch" class="form-control" placeholder="¿A qué switch está conectado?" value="<?= htmlspecialchars($device['switch'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">No. Puerto</label>
          <input type="text" name="puerto" class="form-control" placeholder="Número de puerto" value="<?= htmlspecialchars($device['puerto'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Dirección MAC</label>
          <input type="text" name="mac" id="macInput" class="form-control" placeholder="00:11:22:33:44:55" oninput="formatearYValidarMac(this)">
          <input type="text" id="tag" class="form-control mt-2" disabled>
        </div>

        <div class="col-md-3">
          <label class="form-label">Dirección IP</label>
        <input type="text" name="ipTag" id="ipInput" class="form-control" placeholder="192.168.1.1" oninput="validarIP(this)">
        <input type="text" id="ip" class="form-control mt-2" disabled>

        </div>

        <div class="col-md-3">
          <label class="form-label">Observaciones</label>
          <input type="text" name="observaciones" class="form-control" placeholder="Escribe alguna observación">
        </div>
      </div>

      <!-- BLOQUE 4: Campos específicos -->
      <div class="row g-4 mt-3 grupo-cctv d-none">
        <div class="col-md-3">
          <label class="form-label">No. de Servidor</label>
          <input type="text" name="servidor" class="form-control" placeholder="Número de servidor" value="<?= htmlspecialchars($device['servidor'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">VMS</label>
          <input type="text" name="vms" class="form-control" placeholder="VMS" value="<?= htmlspecialchars($device['servidor'] ?? '') ?>">
        </div>

        <div class="col-md-3 campo-vms-version d-none">
          <label class="form-label">Versión de VMS</label>
          <input type="text" name="version_vms" class="form-control" placeholder="Ej. v2.3.1">
        </div>

        <div class="col-md-3 campo-win d-none">
          <label class="form-label">Versión de Windows</label>
          <input type="text" name="version_windows" class="form-control" placeholder="Ej. Windows Server 2019">
        </div>
      </div>

      <div class="row g-4 mt-3 grupo-alarma d-none">
        <div class="col-md-3">
          <label class="form-label">Zona del sistema de alarma</label>
          <input type="text" name="zona_alarma" class="form-control" placeholder="Ej: Zona 1">
        </div>

        <div class="col-md-4">
          <label class="form-label">Tipo de sensor</label>
          <input type="text" name="tipo_sensor" class="form-control" placeholder="Ej: PIR, Humo, etc.">
        </div>
      </div>

<!-- BLOQUE 5: Imágenes -->
<div class="row g-4 mt-3">
  <?php for ($i = 1; $i <= 3; $i++): ?>
    <div class="col-md-4 mb-3">
      <div class="border rounded shadow-sm h-100 p-3">
        <label class="form-label"><?= $i === 1 ? 'Imagen' : ($i === 2 ? 'Imagen antes' : 'Imagen después') ?></label>
        <div class="dropzone border rounded text-center p-4 position-relative" id="drop-imagen<?= $i ?>" data-input="imagen<?= $i ?>">
          <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
          <p class="text-muted mensaje">Arrastra una imagen aquí o haz clic</p>
          <!-- ya no tiene required -->
          <input type="file" name="imagen<?= $i ?>" id="imagen<?= $i ?>" class="d-none" accept="image/*">
          <img id="preview-imagen<?= $i ?>" class="preview d-none mt-2" src="#" alt="Preview Imagen <?= $i ?>">
          <button type="button" class="btn btn-danger btn-sm d-none remove-btn mt-2" data-target="imagen<?= $i ?>">Eliminar</button>
        </div>
      </div>
    </div>
  <?php endfor; ?>
</div>


      <!-- BOTÓN GUARDAR -->
      <div class="row g-4 mt-3">
        <div class="col-12 text-center">
          <button type="submit" class="btn btn-secondary px-5 py-2 rounded-pill shadow">
            <i class="fas fa-qrcode me-2"></i> Guardar y generar QR
          </button>
        </div>
      </div>

    </div>
  </form>

  <!-- SCRIPTS -->
  <script src="validacionesregistro.js"></script>



  <?php
  $content = ob_get_clean();
  $pageTitle = "Registrar dispositivo";
  $pageHeader = "Registro de dispositivo";
  $activePage = "registro";

  include __DIR__ . '/../../layout.php';
  ?>
