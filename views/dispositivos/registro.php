  <?php
  require_once __DIR__ . '/../../includes/auth.php';
  include __DIR__ . '/../../includes/db.php';

  verificarAutenticacion();
  verificarRol(['Superadmin','Administrador', 'Técnico', 'Capturista']);

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
  <div class="input-group">
    <select name="marca" id="marca" class="form-control">
      <option value="">Selecciona una marca</option>
    </select>
    <!-- IMPORTANTE: type="button" -->
    <button type="button" id="toggleMarcaManual" class="btn btn-outline-secondary" title="Escribir marca">✎</button>
  </div>
  <input type="text" id="marcaManual" class="form-control mt-2 d-none" placeholder="Escribe la marca">
  <div class="form-text">Elige una marca o escribe una nueva.</div>
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

<!-- Script para manejo de imágenes, permite CTRL + V, ARRASTRAR Y PEGAR, Y CON OPCION DE N/A EN LA PRIMER IMAGEN -->
<script>
(() => {
  const MAX_SIZE_MB = 10; // límite opcional por archivo

  const dropzones = [1,2,3].map(i => ({
    i,
    dz: document.getElementById(`drop-imagen${i}`),
    input: document.getElementById(`imagen${i}`),
    preview: document.getElementById(`preview-imagen${i}`),
    removeBtn: document.querySelector(`button.remove-btn[data-target="imagen${i}"]`),
    pinBtn: null, // botón "Pegar aquí"
    tip: null     // badge "Destino de pegar"
  }));

  let activeDropzone = null;        // dropzone destino para pegar (Ctrl+V)
  let manualPasteTarget = false;    // true si el usuario seleccionó manualmente el destino

  // ===== Helpers de imagen / input =====
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
    removeBtn.classList.add('d-none');

    const msg = dz.querySelector('.mensaje');
    if (msg) msg.textContent = 'Arrastra una imagen aquí o haz clic';
  }

  function toast(text) { alert(text); }

  function handleFile(file, ctx) {
    if (!file.type.startsWith('image/')) return toast('Solo se permiten imágenes.');
    const sizeMB = file.size / (1024*1024);
    if (sizeMB > MAX_SIZE_MB) return toast(`La imagen supera ${MAX_SIZE_MB} MB.`);

    setFileToInput(ctx.input, file);

    const reader = new FileReader();
    reader.onload = () => {
      ctx.preview.src = reader.result;
      ctx.preview.classList.remove('d-none');
      ctx.removeBtn.classList.remove('d-none');
      const msg = ctx.dz.querySelector('.mensaje');
      if (msg) msg.textContent = `Imagen lista (${file.name})`;
    };
    reader.readAsDataURL(file);
  }

  // Genera PNG con “N/A” (para preview y enviar al backend)
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
    ctx.removeBtn.classList.remove('d-none');

    const msg = ctx.dz.querySelector('.mensaje');
    if (msg) msg.textContent = 'Marcado como N/A';
  }

  // ===== Crea checkbox “Marcar como N/A” SOLO para Imagen 1 =====
  const dz1 = document.getElementById('drop-imagen1');
  const input1 = document.getElementById('imagen1');
  const ctx1 = dropzones[0]; // referencia útil
  let chkNAImg1 = null;

  if (dz1) {
    const naWrap = document.createElement('div');
    naWrap.className = 'form-check text-start mt-2';
    naWrap.innerHTML = `
      <input class="form-check-input" type="checkbox" id="chkNAImg1">
      <label class="form-check-label" for="chkNAImg1">Marcar como N/A</label>
    `;
    dz1.appendChild(naWrap);

    // Evita que el click del checkbox/label burbujee al dropzone y abra el file picker
    ['click','mousedown','touchstart'].forEach(evt => {
      naWrap.addEventListener(evt, (e) => e.stopPropagation(), { capture: true });
    });

    chkNAImg1 = naWrap.querySelector('#chkNAImg1');

    // Si se marca el checkbox, aplicar N/A; si se desmarca, limpiar
    chkNAImg1.addEventListener('change', () => {
      if (chkNAImg1.checked) {
        applyNAtoSlot(ctx1);
      } else {
        clearSlot(ctx1);
      }
    });

    // En el submit, si no hay imagen 1 o está marcado N/A, aplicar N/A automáticamente
    const form = dz1.closest('form');
    if (form) {
      form.addEventListener('submit', (e) => {
        const noHayImg1 = (input1.files?.length || 0) === 0;
        if (noHayImg1 || chkNAImg1.checked) {
          e.preventDefault();
          applyNAtoSlot(ctx1).then(() => form.submit());
        }
      });
    }
  }

  // ===== UI para seleccionar destino de pegado (botón y badge) =====
  function buildPasteTargetUI(ctx) {
    // Botón "Pegar aquí"
    const pin = document.createElement('button');
    pin.type = 'button';
    pin.className = 'btn btn-outline-primary btn-sm position-absolute top-0 end-0 m-2 paste-pin';
    pin.title = 'Establecer este cuadro como destino para pegar (Ctrl+V)';
    pin.innerHTML = '<i class="fas fa-thumbtack"></i> <span class="d-none d-md-inline">Pegar aquí</span>';

    // Badge "Destino de pegar"
    const tip = document.createElement('span');
    tip.className = 'badge bg-primary position-absolute top-0 start-0 m-2 d-none';
    tip.textContent = 'Destino de pegar';

    // Evita que al hacer clic sobre el botón se abra el selector de archivos
    ['click','mousedown','touchstart'].forEach(evt => {
      pin.addEventListener(evt, (e) => e.stopPropagation(), { capture: true });
    });

    pin.addEventListener('click', () => {
      setActivePasteTarget(ctx, true); // selección manual
    });

    ctx.dz.style.position = ctx.dz.style.position || 'relative';
    ctx.dz.appendChild(pin);
    ctx.dz.appendChild(tip);
    ctx.pinBtn = pin;
    ctx.tip = tip;
  }

  function setActivePasteTarget(ctx, manual=false) {
    activeDropzone = ctx.dz;
    manualPasteTarget = !!manual;

    // Resalta visualmente el destino y muestra la badge
    dropzones.forEach(d => {
      d.dz.classList.remove('border-primary', 'border-2');
      if (d.tip) d.tip.classList.add('d-none');
    });
    ctx.dz.classList.add('border-primary', 'border-2');
    if (ctx.tip) ctx.tip.classList.remove('d-none');
  }

  // Construye UI en cada dropzone
  dropzones.forEach(buildPasteTargetUI);
  // Selección por defecto: cuadro 1 (no manual)
  setActivePasteTarget(dropzones[0], false);

  // ===== Listeners por dropzone =====
  dropzones.forEach(({ i, dz, input, preview, removeBtn }) => {
    // Click abre el selector de archivos, excepto si fue en checkbox/label/botón
    dz.addEventListener('click', (e) => {
      if (e.target.closest('.form-check, label, input[type="checkbox"], button')) return;
      input.click();
    });

    // Drag & Drop
    ['dragenter','dragover'].forEach(ev => {
      dz.addEventListener(ev, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.add('bg-light');
        // Si NO hay destino manual, el hover define el destino de pegar
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
      if (i === 1 && chkNAImg1) chkNAImg1.checked = false; // si soltó algo en 1, desmarca N/A
    });

    // Cambio por selector de archivos
    input.addEventListener('change', () => {
      const file = input.files?.[0];
      if (file) handleFile(file, { i, dz, input, preview, removeBtn });
      if (i === 1 && chkNAImg1) chkNAImg1.checked = false;
    });

    // Botón Eliminar
    removeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      clearSlot({ input, preview, removeBtn, dz });
      if (i === 1 && chkNAImg1) chkNAImg1.checked = false;
    });

    // Foco por teclado: si no hay selección manual, el foco define destino
    dz.tabIndex = 0;
    dz.addEventListener('focusin', () => {
      if (!manualPasteTarget) {
        const ctx = dropzones.find(d => d.dz === dz);
        if (ctx) setActivePasteTarget(ctx, false);
      }
    });
  });

  // ===== Atajos de teclado para seleccionar destino: Alt+1, Alt+2, Alt+3 =====
  document.addEventListener('keydown', (e) => {
    if (!e.altKey) return;
    const map = { 'Digit1': 1, 'Digit2': 2, 'Digit3': 3 };
    const idx = map[e.code];
    if (!idx) return;
    const ctx = dropzones[idx - 1];
    if (ctx) {
      e.preventDefault();
      setActivePasteTarget(ctx, true); // selección manual
    }
  });

  // ===== Pegar (Ctrl+V) en toda la página =====
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

    // Pega en el destino activo
    const ctx = dropzones.find(d => d.dz === activeDropzone) || dropzones[0];
    if (ctx) {
      handleFile(file, ctx);
      if (ctx.i === 1 && chkNAImg1) chkNAImg1.checked = false;
    }
  });
})();
</script>
<!-- TERMINA SCRIPT DE MANEJO DE IMÁGENES -->



  <?php
  $content = ob_get_clean();
  $pageTitle = "Registrar dispositivo";
  $pageHeader = "Registro de dispositivo";
  $activePage = "registro";

  include __DIR__ . '/../../layout.php';
  ?>
