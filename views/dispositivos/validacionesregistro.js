// ------------------------------------------------------------
//  Validaciones y lógica de UI
// ------------------------------------------------------------

// ========== 1. GESTIÓN DE DROPZONES (IMÁGENES) ==========
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


// ========== 2. CAMPOS DINÁMICOS POR TIPO DE EQUIPO ==========
const equipoInput = document.querySelector('input[name="equipo"]');
const grupoCCTV = document.querySelector('.grupo-cctv');
const grupoAlarma = document.querySelector('.grupo-alarma');
const campoRC = document.querySelector('.campo-rc');
const ubicacionRC = document.querySelector('.campo-ubicacion-rc');
const campoWin = document.querySelector('.campo-win');
const campoVmsVer = document.querySelector('.campo-vms-version');

const palabrasClaveCCTV = [
  "camara", "cámara", "vms", "servidor", "cctv", "switch", "dvr", "nvr",
  "videoportero", "monitor", "joystick", "rack", "fuentes de camaras"
];

const palabrasClaveAlarma = [
  "alarma", "transmisor", "sensor", "detector", "humo", "over head", "zona",
  "boton", "estacion", "panel", "cableado", "sirena", "receptor", "emisor",
  "pir", "llavin", "contacto", "repetidor", "teclado", "estrobo"
];

function actualizarCamposPorEquipo(valor) {
  valor = valor.toLowerCase();

  const esCamara = palabrasClaveCCTV.some(p => valor.includes(p));
  const esSwitch = valor.includes('switch');
  const esServidor = valor.includes('servidor');
  const esUbicacionRC = valor.includes('ubicacion rc');
  const esAlarma = palabrasClaveAlarma.some(p => valor.includes(p));

  grupoCCTV.classList.toggle('d-none', !esCamara && !esServidor);
  campoRC.classList.toggle('d-none', !(esCamara || esSwitch || esServidor));
  ubicacionRC.classList.toggle('d-none', !esUbicacionRC && !esServidor);
  campoWin.classList.toggle('d-none', !esServidor);
  campoVmsVer.classList.toggle('d-none', !esServidor);

  const campoSwitch = document.querySelector('[name="switch"]')?.closest('.col-md-3');
  const campoPuerto = document.querySelector('[name="puerto"]')?.closest('.col-md-3');
  if (campoSwitch) campoSwitch.classList.toggle('d-none', esAlarma);
  if (campoPuerto) campoPuerto.classList.toggle('d-none', esAlarma);

  // Mostrar botones de alarma si aplica
  const tipoAlarmaContainer = document.getElementById('tipoAlarmaContainer');
  if (tipoAlarmaContainer) tipoAlarmaContainer.classList.toggle('d-none', !esAlarma);

  // Mostrar botones de tipo de switch si es switch
  const tipoSwitchContainer = document.getElementById('tipoSwitchContainer');
  if (tipoSwitchContainer) tipoSwitchContainer.classList.toggle('d-none', !esSwitch);

  // Ocultar campos específicos cuando es switch
  const camposOcultarEnSwitch = ['vms', 'switch', 'puerto'];
  camposOcultarEnSwitch.forEach(id => {
    const campo = document.querySelector(`[name="${id}"]`)?.closest('.col-md-3');
    if (campo) campo.classList.toggle('d-none', esSwitch || esAlarma); // también se ocultan si es alarma
  });
}

// Activar botones tipo toggle (alarma)
document.querySelectorAll('.tipo-alarma').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tipo-alarma').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
  });
});

// Activar botones tipo toggle (switch)
document.querySelectorAll('.tipo-switch').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tipo-switch').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
  });
});


const tipoAlarmaContainer = document.getElementById('tipoAlarmaContainer');
const tipoSwitchContainer = document.getElementById('tipoSwitchContainer');

equipoInput.addEventListener('input', () => {
  const valor = equipoInput.value.toLowerCase();

  const contieneCCTV = palabrasClaveCCTV.some(palabra => valor.includes(palabra));
  const contieneAlarma = palabrasClaveAlarma.some(palabra => valor.includes(palabra));
  const esSwitch = valor.includes('switch');

  // Mostrar u ocultar bloques existentes
  grupoCCTV.classList.toggle('d-none', !contieneCCTV);
  grupoAlarma.classList.toggle('d-none', !contieneAlarma);
  document.querySelector('.campo-user')?.classList.toggle('d-none', contieneAlarma);
  document.querySelector('.campo-pass')?.classList.toggle('d-none', contieneAlarma);

  // Mostrar botones de tipo de alarma
  tipoAlarmaContainer.classList.toggle('d-none', !contieneAlarma);

  // Mostrar botones de tipo de switch
  tipoSwitchContainer.classList.toggle('d-none', !esSwitch);

  actualizarCamposPorEquipo(valor);
});


// ========== 3. SELECCIÓN DE MARCAS POR TIPO DE CÁMARA ==========
document.addEventListener('DOMContentLoaded', function () {
  const equipoInput = document.getElementById('equipo');
  const marcaSelect = document.getElementById('marca');
  const tipoCamaraContainer = document.getElementById('tipoCamaraContainer');
  const tipoDispositivoWrapper = document.getElementById('tipo-dispositivo-wrapper');
  const camaraIPBtn = document.getElementById('camaraIP');
  const camaraAnalogicaBtn = document.getElementById('camaraAnalogica');

  const MARCAS = {
    ip: ["Hanwha", "Axis", "Hikvision", "UNIVIEW", "AVIGILON"],
    analogica: ["Hanwha", "Hikvision"],
    ambas: ["Hanwha", "Hikvision"],
    servidor: ["Server Avigilon", "SuperMicro", "Dell"],
    dvr: ["Hanwha Techwin (Samsung)", "Hikvision", "Dahua", "Bosch Security", "Panasonic", "UNV", "ZKTeco"],
    nvr: ["Hanwha Techwin (Samsung)", "Hikvision", "Dahua", "Bosch Security", "Panasonic", "Axis Communications", "UNV"]
  };

  function actualizarMarcas(marcas = []) {
    marcaSelect.innerHTML = '<option value="">-- Selecciona una marca --</option>';
    marcas.forEach(marca => {
      const option = document.createElement('option');
      option.value = marca.toLowerCase();
      option.textContent = marca;
      marcaSelect.appendChild(option);
    });
  }

  function mostrarTipoCamara(mostrar = true) {
    tipoCamaraContainer.style.display = mostrar ? 'block' : 'none';
    tipoDispositivoWrapper.style.display = mostrar ? 'block' : 'none';
  }

  function actualizarMarcasSegunBotones() {
    const ipActivo = camaraIPBtn.classList.contains('activo');
    const analogicoActivo = camaraAnalogicaBtn.classList.contains('activo');

    if (ipActivo && analogicoActivo) {
      actualizarMarcas(MARCAS.ambas);
    } else if (ipActivo) {
      actualizarMarcas(MARCAS.ip);
    } else if (analogicoActivo) {
      actualizarMarcas(MARCAS.analogica);
    } else {
      actualizarMarcas([]);
    }
  }

  function manejarCambioEquipo() {
    const valor = equipoInput.value.trim().toLowerCase();

    if (valor === "servidor") {
      mostrarTipoCamara(false);
      actualizarMarcas(MARCAS.servidor);
    } else if (valor === "dvr") {
      mostrarTipoCamara(false);
      actualizarMarcas(MARCAS.dvr);
    } else if (valor === "nvr") {
      mostrarTipoCamara(false);
      actualizarMarcas(MARCAS.nvr);
    } else if (valor.includes("camara") || valor.includes("cámara")) {
      mostrarTipoCamara(true);
      actualizarMarcasSegunBotones();
    } else {
      mostrarTipoCamara(false);
      actualizarMarcas([]);
    }
  }

  camaraIPBtn.addEventListener('click', function () {
    this.classList.toggle('activo');
    actualizarMarcasSegunBotones();
  });

  camaraAnalogicaBtn.addEventListener('click', function () {
    this.classList.toggle('activo');
    actualizarMarcasSegunBotones();
  });

  equipoInput.addEventListener('input', manejarCambioEquipo);
});


// ========== 4. VALIDACIONES (MAC e IP) ==========
function formatearYValidarMac(input) {
  let valor = input.value.replace(/[^A-Fa-f0-9]/g, '');
  let partes = valor.match(/.{1,2}/g) || [];
  partes = partes.slice(0, 6);
  input.value = partes.join(':');

  const regex = /^([0-9A-Fa-f]{2}:){5}([0-9A-Fa-f]{2})$/;
  const tag = document.getElementById('tag');
  tag.style.color = regex.test(input.value) ? 'green' : 'red';
  tag.value = regex.test(input.value) ? '✅ MAC válida' : '❌ MAC inválida';
}

function validarIP(input) {
  const ip = input.value.replace(/[^0-9.]/g, '');
  input.value = ip;

  const partes = ip.split('.');
  const esValida = partes.length === 4 && partes.every(p => {
    const num = parseInt(p);
    return !isNaN(num) && num >= 0 && num <= 255;
  });

  const tag = document.getElementById('ip');
  if (tag) {
    tag.style.color = esValida ? 'green' : 'red';
    tag.value = esValida ? '✅ IP válida' : '❌ IP inválida';
  }
}



// ========== 5. EFECTO DE DESVANECIMIENTO DE MENSAJE DE SUGERENCIA ==========
setTimeout(() => {
  const sugerencia = document.getElementById('sugerencia');
  if (sugerencia) {
    sugerencia.style.opacity = '0';
    setTimeout(() => sugerencia.remove(), 1000);
  }
}, 3000);


// ========== 6. MUNICIPIOS DINÁMICOS ==========
document.getElementById('ciudad').addEventListener('change', function () {
  const ciudadID = this.value;
  const municipioSelect = document.getElementById('municipio');
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
