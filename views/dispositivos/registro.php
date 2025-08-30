<?php
  require_once __DIR__ . '/../../includes/auth.php';
  include __DIR__ . '/../../includes/db.php';

  verificarAutenticacion();
  verificarRol(['Superadmin','Administrador', 'Técnico', 'Capturista']);

  // Detecta rol para mostrar modo Técnico
  $rol = $_SESSION['usuario_rol'] ?? '';
  $isTecnico = ($rol === 'Técnico');

  // Catálogo de ciudades
  $ciudades = $conn->query("SELECT ID, nom_ciudad FROM ciudades ORDER BY nom_ciudad ASC");

  $equipo = $_GET['equipo'] ?? 'camara'; // Valor por defecto

  ob_start();
?>

<!-- ESTILOS COMUNES -->
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

  /* Cámara / UI */
  .camera-live { width: 100%; background: #000; aspect-ratio: 16/9; border-radius: .5rem; }

  /* Alineación/altura igual de tarjetas de imagen */
  .dz-card { height: 100%; display: flex; flex-direction: column; }
  .dz-body { flex: 1 1 auto; display: flex; flex-direction: column; justify-content: center; }

  /* Input file "fantasma": oculto sin usar display:none (iOS friendly) */
  .input-ghost {
    position: fixed !important;
    left: -100vw !important;
    width: 1px !important;
    height: 1px !important;
    opacity: 0 !important;
    pointer-events: none !important;
  }

  /* ===== Responsivo: ajustes suaves para móvil y tablet ===== */

  /* Reduce paddings de dropzones en pantallas chicas */
  @media (max-width: 575.98px) {
    .dz-body { padding: 1rem !important; }
    .preview { max-height: 160px; }
    .camera-live { aspect-ratio: 4/3; } /* más alto en móvil */
  }

  /* De sm a md: 2 columnas en rejillas de imágenes */
  @media (min-width: 576px) and (max-width: 991.98px) {
    .preview { max-height: 180px; }
  }

  /* Botoneras “Tipo …” apiladas en móvil */
  @media (max-width: 575.98px) {
    #tipoAlarmaContainer .btn,
    #tipoSwitchContainer .btn,
    #tipoCamaraContainer .btn {
      width: 100%;
      margin-bottom: .5rem;
    }
  }

  /* Input-group de Marca: permitir “salto de línea” sin romper layout */
  .input-group.flex-wrap { flex-wrap: wrap; gap: .5rem; }
  .input-group.flex-wrap > .form-control { min-width: 12rem; }
  .input-group.flex-wrap > .btn { white-space: nowrap; }

  /* Texto “Pegar aquí” del pin: ocúltalo en xs para no saturar */
  @media (max-width: 575.98px) {
    .paste-pin span { display: none !important; }
  }

  /* Aviso/sugerencia más compacto en móvil */
  @media (max-width: 575.98px) {
    #sugerencia { font-size: .9rem; padding: .5rem .75rem; }
  }
</style>

<h2 class="mb-4">Registrar dispositivo</h2>

<?php if ($isTecnico): ?>
  <!-- ===================== MODO TÉCNICO ===================== -->
  <div class="alert alert-primary">
    Modo técnico: captura <strong>Equipo</strong>, <strong>Ubicación</strong> e <strong>Imágenes</strong> (puedes tomar foto con la cámara).
  </div>

  <form action="guardar.php" method="post" enctype="multipart/form-data"
        class="p-4 container-fluid px-2 px-md-4" style="max-width: 900px; margin: auto;">
    <div class="row g-4">
      <!-- Equipo -->
      <div class="col-md-6">
        <label class="form-label">Equipo</label>
        <input
          type="text"
          name="equipo"
          id="equipo"
          class="form-control"
          placeholder="Ej. Cámara, DVR, Switch, NVR, Alarma…"
          list="equipos-sugeridos"
          required
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

      <!-- Ubicación (selects en cascada) -->
      <div class="col-md-6">
        <label class="form-label">Ciudad</label>
        <select name="ciudad" id="ciudad" class="form-select" required>
          <option value="">-- Selecciona una ciudad --</option>
          <?php
            $ciudadesTech = $conn->query("SELECT ID, nom_ciudad FROM ciudades ORDER BY nom_ciudad ASC");
            while ($row = $ciudadesTech->fetch_assoc()):
          ?>
            <option value="<?= $row['ID'] ?>"><?= htmlspecialchars($row['nom_ciudad']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Municipio</label>
        <select name="municipio" id="municipio" class="form-select" required>
          <option value="">-- Selecciona un municipio --</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Sucursal</label>
        <select name="sucursal" id="sucursal" class="form-select" required>
          <option value="">-- Selecciona una sucursal --</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Determinante</label>
        <select name="determinante" id="determinante" class="form-select" required>
          <option value="">-- Selecciona determinante --</option>
        </select>
      </div>

      <!-- Imágenes con cámara -->
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mt-3 align-items-stretch">
        <?php for ($i=1; $i<=3; $i++): ?>
          <div class="col">
            <div class="border rounded p-3 dz-card">
              <label class="form-label">
                <?= $i === 1 ? 'Imagen' : ($i === 2 ? 'Imagen antes' : 'Imagen después') ?>
              </label>

              <div class="dropzone border rounded text-center p-4 position-relative dz-body" id="drop-imagen<?= $i ?>" data-input="imagen<?= $i ?>">
                <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
                <p class="text-muted mensaje m-0">Haz clic para <strong>Tomar foto / Elegir archivo / Pegar</strong></p>

                <input type="file" name="imagen<?= $i ?>" id="imagen<?= $i ?>" class="input-ghost" accept="image/*" capture="environment">
                <img id="preview-imagen<?= $i ?>" class="preview d-none mt-2" src="#" alt="Preview Imagen <?= $i ?>">

                <div class="mt-3 d-flex gap-2 justify-content-center">
                  <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCamera(<?= $i ?>)">
                    <i class="fas fa-camera me-1"></i> Tomar foto
                  </button>
                  <button type="button" class="btn btn-danger btn-sm d-none remove-btn" data-target="imagen<?= $i ?>">Eliminar</button>
                </div>
              </div>
            </div>
          </div>
        <?php endfor; ?>
      </div>

      <div class="col-12 text-center">
        <button type="submit" class="btn btn-secondary px-5 py-2 rounded-pill shadow">
          <i class="fas fa-qrcode me-2"></i> Guardar y generar QR
        </button>
      </div>
    </div>

    <!-- Defaults mínimos (si tu backend los requiere) -->
    <input type="hidden" name="fecha" value="<?= date('Y-m-d') ?>">
    <input type="hidden" name="estado" value="Instalado">
  </form>

  <!-- Modal de cámara -->
  <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-camera me-2"></i>Tomar foto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" onclick="stopCamera()"></button>
        </div>
        <div class="modal-body">
          <video id="cameraVideo" class="camera-live" autoplay playsinline></video>
          <canvas id="cameraCanvas" class="d-none"></canvas>
          <div class="d-flex gap-2 mt-3">
            <button type="button" id="switchBtn" class="btn btn-outline-secondary" onclick="switchCamera()">Cambiar cámara</button>
            <button type="button" class="btn btn-primary ms-auto" onclick="takePhoto()">
              <i class="fas fa-circle me-1"></i> Capturar
            </button>
          </div>
          <div class="form-text mt-2">Permite el acceso a la cámara del dispositivo.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal" onclick="stopCamera()">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Elegir acción para imagen -->
  <div class="modal fade" id="pickImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title">Agregar imagen</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body d-grid gap-2">
          <button type="button" class="btn btn-primary" id="pmTakePhoto">
            <i class="fas fa-camera me-1"></i> Tomar foto
          </button>
          <button type="button" class="btn btn-outline-secondary" id="pmPickFile">
            <i class="fas fa-file-image me-1"></i> Elegir archivo
          </button>
          <button type="button" class="btn btn-outline-secondary" id="pmPaste">
            <i class="fas fa-clipboard me-1"></i> Pegar del portapapeles
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- JS de cascada (técnico) -->
  <script>
  (function () {
    const ciudadSel = document.getElementById('ciudad');
    const muniSel   = document.getElementById('municipio');
    const sucSel    = document.getElementById('sucursal');
    const detSel    = document.getElementById('determinante');

    if (!ciudadSel || !muniSel || !sucSel || !detSel) return;

    const clearAndPH = (sel, ph) => {
      sel.innerHTML = '';
      const o = document.createElement('option');
      o.value = '';
      o.textContent = ph;
      sel.appendChild(o);
    };
    async function jget(url) {
      const r = await fetch(url, { credentials:'same-origin' });
      if (!r.ok) throw new Error('HTTP '+r.status);
      return r.json();
    }

    ciudadSel.addEventListener('change', async () => {
      clearAndPH(muniSel, '-- Selecciona un municipio --');
      clearAndPH(sucSel,  '-- Selecciona una sucursal --');
      clearAndPH(detSel,  '-- Selecciona determinante --');
      if (!ciudadSel.value) return;
      try {
        const data = await jget(`../ubicacion/api_municipios.php?ciudad_id=${encodeURIComponent(ciudadSel.value)}`);
        data.forEach(m => {
          const o = document.createElement('option');
          o.value = m.id;
          o.textContent = m.nom_municipio ?? m.nombre ?? '';
          if (o.textContent) muniSel.appendChild(o);
        });
      } catch(e){ alert('No se pudieron cargar los municipios'); }
    });

    muniSel.addEventListener('change', async () => {
      clearAndPH(sucSel, '-- Selecciona una sucursal --');
      clearAndPH(detSel, '-- Selecciona determinante --');
      if (!muniSel.value) return;
      try {
        const data = await jget(`../ubicacion/api_sucursales.php?municipio_id=${encodeURIComponent(muniSel.value)}`);
        data.forEach(s => {
          const o = document.createElement('option');
          o.value = s.id;
          o.textContent = s.nom_sucursal ?? s.nombre ?? '';
          if (o.textContent) sucSel.appendChild(o);
        });
      } catch(e){ alert('No se pudieron cargar las sucursales'); }
    });

    sucSel.addEventListener('change', async () => {
      clearAndPH(detSel, '-- Selecciona determinante --');
      if (!sucSel.value) return;
      try {
        const data = await jget(`../ubicacion/api_determinantes.php?sucursal_id=${encodeURIComponent(sucSel.value)}`);
        data.forEach(d => {
          const o = document.createElement('option');
          o.value = d.id;
          o.textContent = (d.nom_determinante ?? d.determinante ?? '').trim();
          if (o.textContent) detSel.appendChild(o);
        });
      } catch(e){ alert('No se pudieron cargar las determinantes'); }
    });
  })();
  </script>

<?php else: ?>

  <!-- ===================== MODO COMPLETO (TU FORMULARIO ORIGINAL) ===================== -->
  <div id="sugerencia">
    <small>Nota:</small> Si los datos aplican tanto para CCTV como para Alarma, el sistema los organizará correctamente.
  </div>

  <!-- FORMULARIO ORIGINAL (NO MODIFICADO) -->
  <form action="guardar.php" method="post" enctype="multipart/form-data"
        class="p-4 container-fluid px-2 px-md-4" style="max-width: 1100px; margin: auto;">
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
        <div id="tipoAlarmaContainer" class="mt-2 d-none">
          <button type="button" class="btn btn-outline-primary me-2 tipo-alarma" onclick="document.getElementById('tipo_alarma').value = 'Alámbrico'">Alámbrico</button>
          <button type="button" class="btn btn-outline-primary tipo-alarma" onclick="document.getElementById('tipo_alarma').value = 'Inalámbrico'">Inalámbrico</button>
        </div>

        <div id="tipoSwitchContainer" class="mt-2 d-none">
          <button type="button" class="btn btn-outline-primary me-2 tipo-switch" onclick="document.getElementById('tipo_switch').value = 'Plano'">Plano</button>
          <button type="button" class="btn btn-outline-primary tipo-switch" onclick="document.getElementById('tipo_switch').value = 'PoE'">PoE</button>
        </div>

        <div id="tipoCamaraContainer" style="display: none; margin-top: 10px;">
          <button type="button" id="camaraIP" class="btn btn-outline-primary" onclick="document.getElementById('tipo_cctv').value = 'IP'">IP</button>
          <button type="button" id="camaraAnalogica" class="btn btn-outline-primary" onclick="document.getElementById('tipo_cctv').value = 'Analógico'">Analógica</button>
        </div>
        <!-- FIN BOTONES -->

        <!-- Campos ocultos -->
        <input type="hidden" name="tipo_alarma" id="tipo_alarma">
        <input type="hidden" name="tipo_switch" id="tipo_switch">
        <input type="hidden" name="tipo_cctv" id="tipo_cctv">

        <div class="col-md-3 mt-2">
          <label class="form-label">Marca</label>
          <div class="input-group flex-wrap">
            <select name="marca" id="marca" class="form-control">
              <option value="">Selecciona una marca</option>
            </select>
            <button type="button" id="toggleMarcaManual" class="btn btn-outline-secondary" title="Escribir marca">✎</button>
          </div>
          <input type="text" id="marcaManual" class="form-control mt-2 d-none" placeholder="Escribe la marca">
          <div class="form-text">Elige una marca o escribe una nueva.</div>
        </div>

        <div id="tipo-dispositivo-wrapper" style="display: none;"></div>

        <div class="col-md-3">
          <label class="form-label">Número de serie</label>
          <input type="text" name="serie" class="form-control" placeholder="Escribe el número de serie" required>
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
          <input type="text" name="user" class="form-control" placeholder="Nombre de usuario" required>
        </div>

        <div class="col-md-3 campo-pass">
          <label class="form-label">Contraseña</label>
          <input type="password" name="pass" class="form-control" placeholder="Contraseña de usuario" required>
        </div>
      </div>

      <!-- BLOQUE 2: Ubicación -->
      <div class="row g-4 mt-3">
        <!-- Ciudad -->
        <div class="col-md-3">
          <label class="form-label">Ciudad</label>
          <select name="ciudad" id="ciudad" class="form-select" required>
            <option value="">-- Selecciona una ciudad --</option>
            <?php while ($row = $ciudades->fetch_assoc()): ?>
              <option value="<?= $row['ID'] ?>"><?= htmlspecialchars($row['nom_ciudad']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Municipio -->
        <div class="col-md-3">
          <label class="form-label">Municipio</label>
          <select name="municipio" id="municipio" class="form-select" required>
            <option value="">-- Selecciona un municipio --</option>
          </select>
        </div>

        <!-- Sucursal (select + opción de nueva) -->
        <div class="col-md-3">
          <label class="form-label">Sucursal</label>
          <select name="sucursal_id" id="sucursal" class="form-select" required>
            <option value="">-- Selecciona primero ciudad y municipio --</option>
          </select>

          <!-- Campo para NUEVA sucursal -->
          <input type="text" name="sucursal_nueva" id="sucursal_nueva" class="form-control mt-2 d-none" placeholder="Escribe el nombre de la nueva sucursal">
          <div class="form-text">Elige una sucursal existente o crea una nueva.</div>
        </div>

        <!-- Determinante (select dependiente o input si aplica) -->
        <div class="col-md-3">
          <label class="form-label">Determinante</label>

          <!-- Select determinante (para sucursal existente) -->
          <select name="determinante_id" id="determinante" class="form-select">
            <option value="">-- Selecciona una sucursal --</option>
          </select>

          <!-- Campo para NUEVA determinante -->
          <input type="text" name="determinante_nueva" id="determinante_nueva" class="form-control mt-2 d-none" placeholder="Escribe la nueva determinante">
          <div class="form-text" id="ayudaDeterminante">Si creas una sucursal nueva, escribe aquí la determinante.</div>
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
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mt-3 align-items-stretch">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <div class="col">
            <div class="border rounded shadow-sm h-100 p-3 dz-card">
              <label class="form-label">
                <?= $i === 1 ? 'Imagen' : ($i === 2 ? 'Imagen antes' : 'Imagen después') ?>
              </label>

              <div class="dropzone border rounded text-center p-4 position-relative dz-body" id="drop-imagen<?= $i ?>" data-input="imagen<?= $i ?>">
                <i class="fas fa-image fa-2x mb-2 text-muted icono"></i>
                <p class="text-muted mensaje m-0">Haz clic para <strong>Tomar foto / Elegir archivo / Pegar</strong></p>

                <input type="file" name="imagen<?= $i ?>" id="imagen<?= $i ?>" class="input-ghost" accept="image/*">
                <img id="preview-imagen<?= $i ?>" class="preview d-none mt-2" src="#" alt="Preview Imagen <?= $i ?>">

                <button type="button" class="btn btn-danger btn-sm d-none remove-btn mt-3" data-target="imagen<?= $i ?>">Eliminar</button>
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

<?php endif; ?>

<!-- SCRIPTS -->
<script src="validacionesregistro.js"></script>

<script>
(() => {
  const selCiudad   = document.getElementById('ciudad');
  const selMpio     = document.getElementById('municipio');
  const selSucursal = document.getElementById('sucursal');
  const inpSucNueva = document.getElementById('sucursal_nueva');

  const selDet      = document.getElementById('determinante');
  const inpDetNueva = document.getElementById('determinante_nueva');
  const ayudaDet    = document.getElementById('ayudaDeterminante');

  const OPTION_NUEVA_SUCURSAL     = '__NEW_SUCURSAL__';
  const OPTION_NUEVA_DETERMINANTE = '__NEW_DETERMINANTE__';

  function clearSelect(select, placeholder) {
    if (!select) return;
    select.innerHTML = '';
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = placeholder || '-- Selecciona --';
    select.appendChild(opt);
  }

  function toggle(el, show) {
    if (!el) return;
    el.classList.toggle('d-none', !show);
  }

  async function fetchJSON(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) {
      const txt = await res.text();
      console.error('HTTP error:', res.status, txt);
      throw new Error('HTTP error ' + res.status);
    }
    return res.json();
  }

  // ===== CIUDAD -> MUNICIPIOS =====
  selCiudad?.addEventListener('change', async () => {
    const ciudad_id = selCiudad.value;

    clearSelect(selMpio,     '-- Selecciona un municipio --');
    clearSelect(selSucursal, '-- Selecciona primero ciudad y municipio --');
    clearSelect(selDet,      '-- Selecciona una sucursal --');
    toggle(inpSucNueva, false);
    toggle(inpDetNueva, false);

    if (!ciudad_id) return;

    try {
      const url  = `../ubicacion/api_municipios.php?ciudad_id=${encodeURIComponent(ciudad_id)}`;
      const data = await fetchJSON(url);

      data.forEach(m => {
        const o = document.createElement('option');
        o.value = m.id;
        o.textContent = m.nom_municipio;
        selMpio.appendChild(o);
      });
    } catch (e) {
      console.error('[Municipios] Error', e);
      alert('No se pudieron cargar los municipios.');
    }
  });

  // ===== MUNICIPIO -> SUCURSALES =====
  selMpio?.addEventListener('change', async () => {
    const ciudad_id    = selCiudad.value;
    const municipio_id = selMpio.value;

    clearSelect(selSucursal, '-- Selecciona una sucursal --');
    clearSelect(selDet,      '-- Selecciona una sucursal --');
    toggle(inpSucNueva, false);
    toggle(inpDetNueva, false);

    if (!ciudad_id || !municipio_id) return;

    try {
      const url  = `../ubicacion/api_sucursales.php?ciudad_id=${encodeURIComponent(ciudad_id)}&municipio_id=${encodeURIComponent(municipio_id)}`;
      const data = await fetchJSON(url);

      data.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id;
        o.textContent = s.nom_sucursal;
        selSucursal.appendChild(o);
      });

      // Opción NUEVA sucursal
      const on = document.createElement('option');
      on.value = OPTION_NUEVA_SUCURSAL;
      on.textContent = '➕ Nueva sucursal…';
      selSucursal.appendChild(on);
    } catch (e) {
      console.error('[Sucursales] Error', e);
      alert('No se pudieron cargar las sucursales.');
    }
  });

  // ===== SUCURSAL -> DETERMINANTES o INPUT =====
  selSucursal?.addEventListener('change', async () => {
    const val = selSucursal.value;

    clearSelect(selDet, '-- Selecciona una determinante --');
    toggle(inpDetNueva, false);

    if (val === OPTION_NUEVA_SUCURSAL) {
      toggle(inpSucNueva, true);
      clearSelect(selDet, '-- Determinante por nueva sucursal --');
      toggle(inpDetNueva, true);

      if (ayudaDet) ayudaDet.textContent = 'Nueva sucursal: escribe aquí la determinante.';
      selDet.required        = false;
      if (inpDetNueva) inpDetNueva.required   = true;
      return;
    }

    if (!val) {
      toggle(inpSucNueva, false);
      toggle(inpDetNueva, false);
      clearSelect(selDet, '-- Selecciona una sucursal --');
      return;
    }

    // Sucursal existente → determinantes
    toggle(inpSucNueva, false);
    if (ayudaDet) ayudaDet.textContent = 'Elige una determinante o crea una nueva.';
    selDet.required      = true;
    if (inpDetNueva) inpDetNueva.required = false;

    try {
      const url  = `../ubicacion/api_determinantes.php?sucursal_id=${encodeURIComponent(val)}`;
      const data = await fetchJSON(url);

      clearSelect(selDet, '-- Selecciona una determinante --');

      let count = 0;
      data.forEach(d => {
        const name = (d.determinante ?? d.nom_determinante ?? '').trim();
        if (!name) return;
        const o = document.createElement('option');
        o.value = d.id;
        o.textContent = name;
        selDet.appendChild(o);
        count++;
      });

      // Opción NUEVA determinante
      const on = document.createElement('option');
      on.value = OPTION_NUEVA_DETERMINANTE;
      on.textContent = '➕ Nueva determinante…';
      selDet.appendChild(on);

      if (count === 0) {
        console.warn('[Determinantes] Vacío para sucursal', val);
      }
    } catch (e) {
      console.error('[Determinantes] Error', e);
      alert('No se pudieron cargar las determinantes.');
    }
  });

  // ===== DETERMINANTE: mostrar input si eligen "Nueva" =====
  selDet?.addEventListener('change', () => {
    if (!selDet) return;
    if (selDet.value === OPTION_NUEVA_DETERMINANTE) {
      toggle(inpDetNueva, true);
      if (inpDetNueva) inpDetNueva.required = true;
    } else {
      toggle(inpDetNueva, false);
      if (inpDetNueva) inpDetNueva.required = false;
    }
  });

  // ===== Validación al enviar =====
  const form = document.querySelector('form[action="guardar.php"]');
  if (form) {
    form.addEventListener('submit', (e) => {
      if (!selSucursal) return;
      if (selSucursal.value === OPTION_NUEVA_SUCURSAL) {
        if (inpSucNueva && !inpSucNueva.value.trim()) {
          e.preventDefault();
          alert('Escribe el nombre de la nueva sucursal.');
          inpSucNueva.focus();
          return;
        }
        if (inpDetNueva && !inpDetNueva.value.trim()) {
          e.preventDefault();
          alert('Escribe la determinante para la nueva sucursal.');
          inpDetNueva.focus();
          return;
        }
      }

      if (selSucursal.value && selSucursal.value !== OPTION_NUEVA_SUCURSAL &&
          selDet && selDet.value === OPTION_NUEVA_DETERMINANTE && inpDetNueva && !inpDetNueva.value.trim()) {
        e.preventDefault();
        alert('Escribe la nueva determinante.');
        inpDetNueva.focus();
      }
    });
  }
})();
</script>

<!-- Script imágenes (Drag&Drop, Ctrl+V, N/A) + Menú Acciones -->
<script>
(() => {
  const MAX_SIZE_MB = 10;
  let SUPPRESS_UNDERLAY_CLICK_UNTIL = 0; // evita reabrir menú por rebote

  const dropzones = [1,2,3].map(i => ({
    i,
    dz: document.getElementById(`drop-imagen${i}`),
    input: document.getElementById(`imagen${i}`),
    preview: document.getElementById(`preview-imagen${i}`),
    removeBtn: document.querySelector(`button.remove-btn[data-target="imagen${i}"]`),
    pinBtn: null,
    tip: null
  })).filter(z => z.dz);

  let activeDropzone = dropzones[0]?.dz || null;
  let manualPasteTarget = false;

  function setFileToInput(input, file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
  }

  function clearSlot({ input, preview, removeBtn, dz }) {
    const dt = new DataTransfer();
    input.files = dt.files;

    preview.classList.add('d-none');
    preview.src = '#';
    if (removeBtn) removeBtn.classList.add('d-none');

    const msg = dz.querySelector('.mensaje');
    if (msg) msg.textContent = 'Arrastra una imagen aquí o haz clic';
  }

  function toast(text) { alert(text); }

  function handleFile(file, ctx) {
    if (!file || !file.type || !file.type.startsWith('image/')) return toast('Solo se permiten imágenes.');
    const sizeMB = file.size / (1024*1024);
    if (sizeMB > MAX_SIZE_MB) return toast(`La imagen supera ${MAX_SIZE_MB} MB.`);

    setFileToInput(ctx.input, file);

    const reader = new FileReader();
    reader.onload = () => {
      ctx.preview.src = reader.result;
      ctx.preview.classList.remove('d-none');
      if (ctx.removeBtn) ctx.removeBtn.classList.remove('d-none');
      const msg = ctx.dz.querySelector('.mensaje');
      if (msg) msg.textContent = `Imagen lista (${file.name})`;
      // <- IMPORTANTE: ya NO disparamos otro change aquí
    };
    reader.readAsDataURL(file);
  }

  window.__handleUpload = handleFile;

  function generateNAPngDataURL() {
    const canvas = document.createElement('canvas');
    canvas.width = 640; canvas.height = 360;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#f3f4f6';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.strokeStyle = '#d1d5db';
    ctx.lineWidth = 4;
    ctx.strokeRect(8, 8, canvas.width - 16, canvas.height - 16);

    ctx.fillStyle = '#6b7280';
    ctx.font = 'bold 120px system-ui, -apple-system, Segoe UI, Roboto, Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('N/A', canvas.width/2, canvas.height/2);

    return canvas.toDataURL('image/png');
  }

  function dataURLToFile(dataURL, filename) {
    const arr = dataURL.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8 = new Uint8Array(n);
    while (n--) u8[n] = bstr.charCodeAt(n);
    return new File([u8], filename, { type: mime });
  }

  async function applyNAtoSlot(ctx) {
    const naDataURL = generateNAPngDataURL();
    const file = dataURLToFile(naDataURL, 'NA.png');
    setFileToInput(ctx.input, file);

    ctx.preview.src = naDataURL;
    ctx.preview.classList.remove('d-none');
    if (ctx.removeBtn) ctx.removeBtn.classList.remove('d-none');

    const msg = ctx.dz.querySelector('.mensaje');
    if (msg) msg.textContent = 'Marcado como N/A';
  }

  // Checkbox N/A en imagen 1 (si existe)
  const ctx1 = dropzones.find(z => z.i === 1);
  if (ctx1) {
    const naWrap = document.createElement('div');
    naWrap.className = 'form-check text-start mt-2';
    naWrap.innerHTML = `
      <input class="form-check-input" type="checkbox" id="chkNAImg1">
      <label class="form-check-label" for="chkNAImg1">Marcar como N/A</label>
    `;
    ctx1.dz.appendChild(naWrap);
    ['click','mousedown','touchstart'].forEach(evt => {
      naWrap.addEventListener(evt, (e) => e.stopPropagation(), { capture: true });
    });
    const chk = naWrap.querySelector('#chkNAImg1');
    chk.addEventListener('change', () => {
      if (chk.checked) applyNAtoSlot(ctx1);
      else clearSlot(ctx1);
    });
    const form = ctx1.dz.closest('form');
    if (form) {
      form.addEventListener('submit', (e) => {
        const noHayImg1 = (ctx1.input.files?.length || 0) === 0;
        if (noHayImg1 || chk.checked) {
          e.preventDefault();
          applyNAtoSlot(ctx1).then(() => form.submit());
        }
      });
    }
  }

  // Pegado destino
  function buildPasteTargetUI(ctx) {
    const pin = document.createElement('button');
    pin.type = 'button';
    pin.className = 'btn btn-outline-primary btn-sm position-absolute top-0 end-0 m-2 paste-pin';
    pin.title = 'Establecer este cuadro como destino para pegar (Ctrl+V)';
    pin.innerHTML = '<i class="fas fa-thumbtack"></i> <span class="d-none d-md-inline">Pegar aquí</span>';

    const tip = document.createElement('span');
    tip.className = 'badge bg-primary position-absolute top-0 start-0 m-2 d-none';
    tip.textContent = 'Destino de pegar';

    ['click','mousedown','touchstart'].forEach(evt => {
      pin.addEventListener(evt, (e) => e.stopPropagation(), { capture: true });
    });

    pin.addEventListener('click', () => setActivePasteTarget(ctx, true));

    ctx.dz.style.position = ctx.dz.style.position || 'relative';
    ctx.dz.appendChild(pin);
    ctx.dz.appendChild(tip);
    ctx.pinBtn = pin;
    ctx.tip = tip;
  }

  function setActivePasteTarget(ctx, manual=false) {
    activeDropzone = ctx.dz;
    manualPasteTarget = !!manual;
    dropzones.forEach(d => {
      d.dz.classList.remove('border-primary', 'border-2');
      if (d.tip) d.tip.classList.add('d-none');
    });
    ctx.dz.classList.add('border-primary', 'border-2');
    if (ctx.tip) ctx.tip.classList.remove('d-none');
  }

  dropzones.forEach(buildPasteTargetUI);
  if (dropzones[0]) setActivePasteTarget(dropzones[0], false);

  // Drag & Drop + change + eliminar + foco
  dropzones.forEach(({ i, dz, input, preview, removeBtn }) => {
    ['dragenter','dragover'].forEach(ev => {
      dz.addEventListener(ev, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.add('bg-light');
        if (!manualPasteTarget) {
          const ctx = dropzones.find(d => d.dz === dz);
          if (ctx) setActivePasteTarget(ctx, false);
        }
      });
    });
    ['dragleave','dragend','drop'].forEach(ev => {
      dz.addEventListener(ev, (e) => {
        if (ev !== 'drop') dz.classList.remove('bg-light');
      });
    });
    dz.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      dz.classList.remove('bg-light');
      const file = e.dataTransfer?.files?.[0] || null;
      if (file) handleFile(file, { i, dz, input, preview, removeBtn });
      if (i === 1) {
        const chk = document.getElementById('chkNAImg1');
        if (chk) chk.checked = false;
      }
    });

    input.addEventListener('change', () => {
      // Evita re-procesar si venimos de un borrado programático
      if (input.dataset.ignoreNextChange === '1') {
        input.dataset.ignoreNextChange = '0';
        return;
      }

      const file = input.files?.[0];
      if (file) handleFile(file, { i, dz, input, preview, removeBtn });

      if (i === 1) {
        const chk = document.getElementById('chkNAImg1');
        if (chk) chk.checked = false;
      }
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', (e) => {
        // Bloquea cualquier acción secundaria/rebote
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        // Evita que el click alcance la dropzone por ~700ms
        SUPPRESS_UNDERLAY_CLICK_UNTIL = Date.now() + 700;

        // Marca para ignorar el próximo "change" del input
        input.dataset.ignoreNextChange = '1';

        // Limpieza completa del input
        input.value = '';
        const emptyDT = new DataTransfer();
        input.files = emptyDT.files;

        // Limpieza de UI
        preview.src = '#';
        preview.classList.add('d-none');
        if (removeBtn) removeBtn.classList.add('d-none');
        dz.classList.remove('bg-light');
        const msg = dz.querySelector('.mensaje');
        if (msg) msg.textContent = 'Arrastra una imagen aquí o haz clic';

        // (Opcional) desactivar checkbox N/A de la 1
        if (i === 1) {
          const chk = document.getElementById('chkNAImg1');
          if (chk) chk.checked = false;
        }

        // Quita el flag tras un tick para no interferir con futuras cargas
        setTimeout(() => { delete input.dataset.ignoreNextChange; }, 100);
      });
    }

    dz.tabIndex = 0;
    dz.addEventListener('focusin', () => {
      if (!manualPasteTarget) {
        const ctx = dropzones.find(d => d.dz === dz);
        if (ctx) setActivePasteTarget(ctx, false);
      }
    });
  });

  // ===== Menú de acciones: evitar reabrir por rebote
  let pickSlot = null;
  window.openPickMenu = function(slot){
    pickSlot = slot;
    const modalEl = document.getElementById('pickImageModal');
    if (!modalEl) return;
    new bootstrap.Modal(modalEl).show();
  };

  // Intercepta click en dropzone para abrir menú (si no hay otro modal visible)
  dropzones.forEach(({ i, dz }) => {
    dz.addEventListener('click', (e) => {
      const now = Date.now();
      if (now < SUPPRESS_UNDERLAY_CLICK_UNTIL) return;
      if (document.querySelector('.modal.show')) return;

      // Evita abrir menú si click fue en controles internos (botones, checkbox, etc.)
      if (e.target.closest('.form-check, label, input[type="checkbox"], button, .paste-pin')) return;

      e.preventDefault();
      e.stopPropagation();
      openPickMenu(i);
    }, true);
  });

  // ===== Pegar (Ctrl+V) global
  document.addEventListener('paste', (e) => {
    const dt = e.clipboardData || window.clipboardData;
    if (!dt) return;

    let file = null;
    if (dt.items && dt.items.length) {
      for (const item of dt.items) {
        if (item.type && item.type.startsWith('image/')) {
          const blob = item.getAsFile();
          if (blob) {
            file = new File([blob], `pasted-${Date.now()}.png`, { type: blob.type || 'image/png' });
            break;
          }
        }
      }
    }
    if (!file && dt.files && dt.files.length) {
      const f = dt.files[0];
      if (f.type.startsWith('image/')) file = f;
    }
    if (!file) return;

    const ctx = dropzones.find(d => d.dz === activeDropzone) || dropzones[0];
    if (ctx) {
      handleFile(file, ctx);
      if (ctx.i === 1) {
        const chk = document.getElementById('chkNAImg1');
        if (chk) chk.checked = false;
      }
    }
  });

  // ===== Botones del modal de acciones
  const pmEl    = document.getElementById('pickImageModal');
  const btnTake = document.getElementById('pmTakePhoto');
  const btnPick = document.getElementById('pmPickFile');
  const btnPaste= document.getElementById('pmPaste');

  btnTake?.addEventListener('click', () => {
    if (typeof window.openCamera === 'function') openCamera(pickSlot);
    SUPPRESS_UNDERLAY_CLICK_UNTIL = Date.now() + 700;
    const inst = bootstrap.Modal.getInstance(pmEl);
    setTimeout(() => { inst && inst.hide(); }, 0);
  });

  btnPick?.addEventListener('click', () => {
    const input = document.getElementById('imagen' + pickSlot);
    if (!input) return;
    try { input.setAttribute('capture','environment'); } catch(_) {}
    input.click();
    SUPPRESS_UNDERLAY_CLICK_UNTIL = Date.now() + 700;
    const inst = bootstrap.Modal.getInstance(pmEl);
    setTimeout(() => { inst && inst.hide(); }, 0);
  });

  btnPaste?.addEventListener('click', async () => {
    try {
      if (!navigator.clipboard?.read) throw new Error('No soportado');
      const items = await navigator.clipboard.read();
      let blob = null, type = null;
      for (const it of items) {
        for (const t of it.types) {
          if (t.startsWith('image/')) { blob = await it.getType(t); type = t; break; }
        }
        if (blob) break;
      }
      if (!blob) throw new Error('No hay imagen en el portapapeles');
      const ext = type === 'image/png' ? 'png' : (type === 'image/jpeg' ? 'jpg' : 'bin');
      const file = new File([blob], `clipboard-${Date.now()}.${ext}`, { type: type || 'application/octet-stream' });

      const input   = document.getElementById('imagen' + pickSlot);
      const preview = document.getElementById('preview-imagen' + pickSlot);
      const dz      = document.getElementById('drop-imagen' + pickSlot);
      const removeBtn = dz?.querySelector('.remove-btn') || null;

      if (typeof window.__handleUpload === 'function') {
        window.__handleUpload(file, { i: pickSlot, dz, input, preview, removeBtn });
      } else {
        setFileToInput(input, file);
        input.dispatchEvent(new Event('change', { bubbles: true })); 
      }

      SUPPRESS_UNDERLAY_CLICK_UNTIL = Date.now() + 700;

      const inst = bootstrap.Modal.getInstance(pmEl);
      setTimeout(() => { inst && inst.hide(); }, 0);
    } catch (e) {
      alert('Tu navegador no permite leer imágenes del portapapeles aquí. Usa Ctrl+V / ⌘+V sobre la página.');
    }
  });

})();
</script>

<!-- Cámara: openCamera / switchCamera / takePhoto / stopCamera -->
<script>
let __cam = { slot:null, stream:null, devices:[], index:0, video:null, canvas:null };

function setFileToInput(input, file) {
  const dt = new DataTransfer();
  dt.items.add(file);
  input.files = dt.files;
  input.dispatchEvent(new Event('change', { bubbles: true }));
}

async function enumerateCameras() {
  try {
    const all = await navigator.mediaDevices.enumerateDevices();
    return all.filter(d => d.kind === 'videoinput');
  } catch (e) { return []; }
}

async function startCamera(deviceId) {
  stopCamera();
  const hasDeviceId = !!deviceId;
  const constraints = {
    video: hasDeviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } },
    audio: false,
  };
  __cam.stream = await navigator.mediaDevices.getUserMedia(constraints);
  __cam.video.srcObject = __cam.stream;
  await __cam.video.play();
}

window.openCamera = async function(slot) {
  const modalEl = document.getElementById('cameraModal');
  if (!modalEl) return alert('Falta el modal de cámara en el HTML.');

  __cam.slot   = slot;
  __cam.video  = document.getElementById('cameraVideo');
  __cam.canvas = document.getElementById('cameraCanvas');

  const modal = new bootstrap.Modal(modalEl);
  modal.show();

  try {
    await startCamera(null);
    __cam.devices = await enumerateCameras();
    __cam.index   = 0;

    const switchBtn = document.getElementById('switchBtn');
    if (switchBtn) switchBtn.style.display = (__cam.devices.length > 1) ? '' : 'none';

    modalEl.addEventListener('hidden.bs.modal', stopCamera, { once: true });

  } catch (err) {
    console.error('getUserMedia error:', err);
    alert('No se pudo acceder a la cámara. Se abrirá el selector de archivos del dispositivo.');
    const input = document.getElementById('imagen' + slot);
    if (input) {
      try { input.setAttribute('capture','environment'); } catch(_) {}
      input.click();
    }
    const instance = bootstrap.Modal.getInstance(modalEl);
    instance && instance.hide();
  }
};

window.switchCamera = async function() {
  if (!__cam.devices.length) return;
  __cam.index = (__cam.index + 1) % __cam.devices.length;
  try { await startCamera(__cam.devices[__cam.index].deviceId); }
  catch (e) { console.error('switchCamera error:', e); }
};

window.stopCamera = function() {
  if (__cam.stream) {
    __cam.stream.getTracks().forEach(t => t.stop());
    __cam.stream = null;
  }
};

function dataURLToFile(dataURL, filename) {
  const arr = dataURL.split(',');
  const mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]);
  let n = bstr.length;
  const u8 = new Uint8Array(n);
  while (n--) u8[n] = bstr.charCodeAt(n);
  return new File([u8], filename, { type: mime });
}

window.takePhoto = function() {
  if (!__cam.video) return;
  const w = __cam.video.videoWidth || 1280;
  const h = __cam.video.videoHeight || 720;
  __cam.canvas.width = w;
  __cam.canvas.height = h;

  const ctx = __cam.canvas.getContext('2d');
  ctx.drawImage(__cam.video, 0, 0, w, h);

  const dataUrl = __cam.canvas.toDataURL('image/jpeg', 0.92);
  const file = dataURLToFile(dataUrl, `captura-${Date.now()}.jpg`);

  const input   = document.getElementById('imagen' + __cam.slot);
  const preview = document.getElementById('preview-imagen' + __cam.slot);

  if (!input) { alert('No se encontró el input de imagen para el slot ' + __cam.slot); return; }

  setFileToInput(input, file);

  const modalEl = document.getElementById('cameraModal');
  const instance = bootstrap.Modal.getInstance(modalEl);
  instance && instance.hide();
  stopCamera();
};
</script>

<?php
  $content = ob_get_clean();
  $pageTitle = "Registrar dispositivo";
  $pageHeader = "Registro de dispositivo";
  $activePage = "registro";

  include __DIR__ . '/../../layout.php';
?>

