// ------------------------------------------------------------
//  Datos (catálogos)
// ------------------------------------------------------------

// SWITCHES
const modelosPorMarca = {
  CISCO: {
    poe: [
      "CBS250-8FPE-2G-NA","CBS250-16P2GNA","CBS350-24P-4G-NA","CBS250-24P-4GNA",
      "CBS250-24PP-4G-NA","CBS350-48FP-4G-NA","CBS250-24FP-4X-NA","C1200-24FP-4G",
      "C1200-16P-2G","C1200-24P-4G","C1200-16T-2G","C1200-24FP-4X"
    ],
    plano: ["CBS250-16-2G-NA","CBS350-24S-4G-NA","CBS250-24T-4G","CBS350-8S-E-2G-NA"]
  },
  PLANET: {
    poe: ["GS-4210-8P2S","GSD-1008HP","GS421016P4C","FSD-1008HP","FGSW-2624HPS","FGSD-1022VHP","GS-6311-24HP4X"],
    plano: ["NSW2020-24T1GT1GC-POE-IN","MGBSX1","GSD-803","XGS-6350-24X4C"]
  },
  "TP-LINK": {
    poe: ["TL-SG2210MP"],
    plano: ["TL-SG3428XF"]
  },
  PHYBRIDGE: { poe: ["NV-FLX-024-10G"] },
  "EXTREME NETWORKS": { poe: ["X435-24P-4S"] },
  GRANDSTREAM: { poe: ["GWN7802P"] },
  AXIS: { poe: ["T8524","T8516","T8508"] },
  ARUBA: { poe: ["R8Q67A ARUBA"] }
};

// CÁMARAS
const modelosPorMarcaCamara = {
  HANWHA: { ip: ["QNV-6012R","QND-6082R","PNO-A9081R"], analogica: [] },
  AXIS: { ip: ["M3085-V","M3086-V","M2036-LE","P1467-LE"], analogica: [] },
  HIKVISION: { ip: ["DS-2CD2023G2-I(U)","DS-2CD2125G0-IMS"], analogica: [] },
  UNIVIEW: { ip: ["IPC322SB-DF28K-I0","IPC314SB-ADF28K-I0"], analogica: [] },
  AVIGILON: { ip: ["2.0C-H6M-D1"], analogica: [] }
};

// NVR
const modelosPorMarcaNVR = {
  HIKVISION: { nvr: ["DS-7732NI","DS-7608NI"] },
  HANWHA: { nvr: ["SRN-873S"] },
  DAHUA: { nvr: ["NVR608"] },
  UNIVIEW: { nvr: ["NVR302"] }
};

// DVR
const modelosPorMarcaDVR = {
  HIKVISION: { dvr: ["DS-7204HUHI"] },
  DAHUA: { dvr: ["XVR5104"] },
  HANWHA: { dvr: ["HRX-435"] },
  ZKTECO: { dvr: ["Z8404XE"] }
};

// SERVIDORES
const modelosPorMarcaServidor = {
  DELL: { servidor: ["R740"] },
  SUPERMICRO: { servidor: ["SYS-6029"] },
  AVIGILON: { servidor: ["HD NVR"] },
  HP: { servidor: ["PROLIANT DL380"] }
};

// ------------------------------------------------------------
// Helpers de normalización y búsqueda
// ------------------------------------------------------------
const norm = s => (s ?? '').toString().trim();
const normU = s => norm(s).toUpperCase();
const quitarAcentos = s => norm(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'');

function marcaKey(dict, valorSeleccion) {
  const val = normU(valorSeleccion);
  return Object.keys(dict).find(k => normU(k) === val) || null;
}
// Aplana modelos por subtipo (p.ej. poe/plano) en arrays globales
function flattenBySubkey(dict, subkey) {
  const out = [];
  Object.values(dict).forEach(grupo => {
    if (grupo && Array.isArray(grupo[subkey])) out.push(...grupo[subkey].map(normU));
  });
  return out;
}

// Construimos “modelosPorTipo” (globales) para switches (útil como fallback)
const modelosPorTipo = {
  switch_poe: flattenBySubkey(modelosPorMarca, 'poe'),
  switch_plano: flattenBySubkey(modelosPorMarca, 'plano')
};

// ------------------------------------------------------------
// Cache de elementos
// ------------------------------------------------------------
const equipoInput = document.getElementById('equipo');
const marcaSelect = document.getElementById('marca');
const modeloInput = document.getElementById('modelo');
const datalist = document.getElementById('sugerencias-modelo');

const grupoCCTV = document.querySelector('.grupo-cctv');
const grupoAlarma = document.querySelector('.grupo-alarma');
const campoRC = document.querySelector('.campo-rc');
const ubicacionRC = document.querySelector('.campo-ubicacion-rc');
const campoWin = document.querySelector('.campo-win');
const campoVmsVer = document.querySelector('.campo-vms-version');

const tipoAlarmaContainer = document.getElementById('tipoAlarmaContainer');
const tipoSwitchContainer = document.getElementById('tipoSwitchContainer');
const tipoCamaraContainer = document.getElementById('tipoCamaraContainer');

const inputTipoAlarma = document.getElementById('tipo_alarma');
const inputTipoSwitch = document.getElementById('tipo_switch');
const inputTipoCCTV   = document.getElementById('tipo_cctv');

// ------------------------------------------------------------
// 1) Gestión dropzones (idéntico a tu lógica, solo compactado)
// ------------------------------------------------------------
document.querySelectorAll('.dropzone').forEach(dropzone => {
  const inputId = dropzone.dataset.input;
  const fileInput = document.getElementById(inputId);
  const preview = document.getElementById(`preview-${inputId}`);
  const removeBtn = dropzone.querySelector('.remove-btn');
  const icono = dropzone.querySelector('.icono');
  const mensaje = dropzone.querySelector('.mensaje');

  const resetImage = () => {
    if (!fileInput) return;
    fileInput.value = '';
    if (preview) { preview.src = '#'; preview.classList.add('d-none'); }
    if (removeBtn) removeBtn.classList.add('d-none');
    if (icono) icono.classList.remove('d-none');
    if (mensaje) mensaje.classList.remove('d-none');
  };

  dropzone.addEventListener('click', () => fileInput?.click());
  fileInput?.addEventListener('change', () => {
    if (fileInput.files.length) {
      if (preview) { preview.src = URL.createObjectURL(fileInput.files[0]); preview.classList.remove('d-none'); }
      if (removeBtn) removeBtn.classList.remove('d-none');
      if (icono) icono.classList.add('d-none');
      if (mensaje) mensaje.classList.add('d-none');
    }
  });
  dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('bg-light'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('bg-light'));
  dropzone.addEventListener('drop', e => {
    e.preventDefault(); dropzone.classList.remove('bg-light');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
      const dt = new DataTransfer(); dt.items.add(file);
      if (fileInput) fileInput.files = dt.files;
      if (preview) { preview.src = URL.createObjectURL(file); preview.classList.remove('d-none'); }
      if (removeBtn) removeBtn.classList.remove('d-none');
      if (icono) icono.classList.add('d-none');
      if (mensaje) mensaje.classList.add('d-none');
    }
  });
  removeBtn?.addEventListener('click', e => { e.stopPropagation(); resetImage(); });
});

// ------------------------------------------------------------
// 2) Detección de categoría y visibilidad de bloques
// ------------------------------------------------------------
const palabrasClaveAlarma = [
  "alarma","transmisor","sensor","detector","humo","over head","zona",
  "boton","estacion","panel","cableado","sirena","receptor","emisor",
  "pir","llavin","contacto","repetidor","teclado","estrobo"
];

function detectarCategoria(texto) {
  const v = quitarAcentos(normU(texto));
  if (v.includes('SWITCH')) return 'switch';
  if (v.includes('SERVIDOR') || v.includes('SERVER')) return 'servidor';
  if (v.includes('NVR')) return 'nvr';
  if (v.includes('DVR')) return 'dvr';
  if (v.includes('CAMARA') || v.includes('CCTV')) return 'camara';
  if (palabrasClaveAlarma.some(p => quitarAcentos(v).includes(quitarAcentos(normU(p))))) return 'alarma';
  return 'otro';
}

function toggleGruposPorCategoria(cat) {
  const esCamaraLike = (cat === 'camara' || cat === 'servidor' || cat === 'nvr' || cat === 'dvr');
  grupoCCTV?.classList.toggle('d-none', !esCamaraLike);
  grupoAlarma?.classList.toggle('d-none', cat !== 'alarma');

  // Campos RC/Ubicación RC/VMS/Windows:
  campoRC?.classList.toggle('d-none', !(cat === 'camara' || cat === 'switch' || cat === 'servidor' || cat === 'nvr' || cat === 'dvr'));
  ubicacionRC?.classList.toggle('d-none', !(cat === 'servidor')); // como pediste
  campoWin?.classList.toggle('d-none', cat !== 'servidor');
  campoVmsVer?.classList.toggle('d-none', cat !== 'servidor');

  // Mostrar/ocultar botones contenedores
  tipoAlarmaContainer?.classList.toggle('d-none', cat !== 'alarma');
  tipoSwitchContainer?.classList.toggle('d-none', cat !== 'switch');
  tipoCamaraContainer.style.display = (cat === 'camara') ? 'block' : 'none';

  // Ocultar credenciales si es alarma o switch
  document.querySelector('.campo-user')?.classList.toggle('d-none', cat === 'alarma' || cat === 'switch');
  document.querySelector('.campo-pass')?.classList.toggle('d-none', cat === 'alarma' || cat === 'switch');

  // Ocultar campos innecesarios cuando es switch
  ['vms','switch','puerto'].forEach(name => {
    const campo = document.querySelector(`[name="${name}"]`)?.closest('.col-md-3');
    campo?.classList.toggle('d-none', cat === 'switch' || cat === 'alarma');
  });
}

// ------------------------------------------------------------
// 3) Marcas y modelos según categoría
// ------------------------------------------------------------
function llenarMarcasPorCategoria(cat) {
  const dict = (cat === 'switch') ? modelosPorMarca
              : (cat === 'camara') ? modelosPorMarcaCamara
              : (cat === 'nvr') ? modelosPorMarcaNVR
              : (cat === 'dvr') ? modelosPorMarcaDVR
              : (cat === 'servidor') ? modelosPorMarcaServidor
              : null;

  marcaSelect.innerHTML = '<option value="">-- Selecciona una marca --</option>';
  if (!dict) return;

  Object.keys(dict).sort().forEach(mk => {
    const opt = document.createElement('option');
    opt.value = mk; // guardo key tal cual (en mayúsculas)
    opt.textContent = mk; // visible
    marcaSelect.appendChild(opt);
  });
}

function limpiarDatalist() { datalist.innerHTML = ''; }

function llenarModelos(cat, mk) {
  limpiarDatalist();
  const dict = (cat === 'switch') ? modelosPorMarca
              : (cat === 'camara') ? modelosPorMarcaCamara
              : (cat === 'nvr') ? modelosPorMarcaNVR
              : (cat === 'dvr') ? modelosPorMarcaDVR
              : (cat === 'servidor') ? modelosPorMarcaServidor
              : null;
  if (!dict) return;

  const key = marcaKey(dict, mk);
  if (!key) return;

  // Aplana todos los subtipos
  const modelos = Object.values(dict[key]).flat().map(m => m.toString());
  modelos.forEach(modelo => {
    const option = document.createElement('option');
    option.value = modelo;
    datalist.appendChild(option);
  });
}

// ------------------------------------------------------------
// 4) Clasificación automática por modelo (switch PoE/plano, cámara IP/Analógica)
// ------------------------------------------------------------
function activarBotones(selector, textoObjetivo) {
  document.querySelectorAll(selector).forEach(btn => {
    btn.classList.remove('activo');
    if (btn.textContent.toLowerCase().includes(textoObjetivo.toLowerCase())) {
      btn.classList.add('activo');
    }
  });
}

function clasificarPorModelo(cat) {
  const modelo = normU(modeloInput.value);
  const mk = marcaSelect.value; // ya viene en mayúsculas si se eligió desde el select
  if (!modelo) return;

  if (cat === 'switch') {
    // Primero: buscar en la marca elegida; si no, fallback global
    const key = marcaKey(modelosPorMarca, mk);
    let esPoe = false, esPlano = false;
    if (key) {
      const poe = (modelosPorMarca[key].poe || []).map(normU);
      const plano = (modelosPorMarca[key].plano || []).map(normU);
      esPoe = poe.includes(modelo);
      esPlano = plano.includes(modelo);
    } else {
      esPoe = modelosPorTipo.switch_poe.includes(modelo);
      esPlano = modelosPorTipo.switch_plano.includes(modelo);
    }

    if (esPoe) { inputTipoSwitch.value = 'PoE'; activarBotones('.tipo-switch','PoE'); }
    else if (esPlano) { inputTipoSwitch.value = 'Plano'; activarBotones('.tipo-switch','Plano'); }
    else { inputTipoSwitch.value = ''; }
  }

  if (cat === 'camara') {
    const key = marcaKey(modelosPorMarcaCamara, mk);
    let esIP = false, esAnalog = false;
    if (key) {
      const ipList = (modelosPorMarcaCamara[key].ip || []).map(normU);
      const anList = (modelosPorMarcaCamara[key].analogica || []).map(normU);
      esIP = ipList.includes(modelo);
      esAnalog = anList.includes(modelo);
    }
    if (esIP) { inputTipoCCTV.value = 'IP'; activarBotones('#tipoCamaraContainer .btn','IP'); }
    else if (esAnalog) { inputTipoCCTV.value = 'Analógico'; activarBotones('#tipoCamaraContainer .btn','Analógica'); }
    else { /* no inferido; se mantiene lo que haya elegido el usuario */ }
  }
}

// ------------------------------------------------------------
// 5) Validaciones (MAC / IP) — tus funciones originales
// ------------------------------------------------------------
function formatearYValidarMac(input) {
  let valor = input.value.replace(/[^A-Fa-f0-9]/g,'');
  let partes = valor.match(/.{1,2}/g) || [];
  partes = partes.slice(0, 6);
  input.value = partes.join(':');
  const regex = /^([0-9A-Fa-f]{2}:){5}([0-9A-Fa-f]{2})$/;
  const tag = document.getElementById('tag');
  if (tag) {
    tag.style.color = regex.test(input.value) ? 'green' : 'red';
    tag.value = regex.test(input.value) ? '✅ MAC válida' : '❌ MAC inválida';
  }
}
function validarIP(input) {
  const ip = input.value.replace(/[^0-9.]/g, '');
  input.value = ip;
  const partes = ip.split('.');
  const esValida = partes.length === 4 && partes.every(p => {
    const num = parseInt(p, 10);
    return !isNaN(num) && num >= 0 && num <= 255;
  });
  const tag = document.getElementById('ip');
  if (tag) {
    tag.style.color = esValida ? 'green' : 'red';
    tag.value = esValida ? '✅ IP válida' : '❌ IP inválida';
  }
}

// ------------------------------------------------------------
// 6) Municipios dinámicos (igual al tuyo)
// ------------------------------------------------------------
document.getElementById('ciudad')?.addEventListener('change', function () {
  const ciudadID = this.value;
  const municipioSelect = document.getElementById('municipio');
  municipioSelect.innerHTML = '<option value="">Cargando municipios...</option>';
  fetch(`obtener_municipios.php?ciudad_id=${encodeURIComponent(ciudadID)}`)
    .then(r => r.json())
    .then(data => {
      municipioSelect.innerHTML = '<option value="">-- Selecciona un municipio --</option>';
      data.forEach(m => {
        const o = document.createElement('option');
        o.value = m.ID;
        o.textContent = m.nom_municipio;
        municipioSelect.appendChild(o);
      });
    })
    .catch(() => { municipioSelect.innerHTML = '<option value="">Error al cargar</option>'; });
});

// ------------------------------------------------------------
// 7) Wiring de eventos (una sola vez, sin duplicados)
// ------------------------------------------------------------
function onEquipoChange() {
  const cat = detectarCategoria(equipoInput.value);
  toggleGruposPorCategoria(cat);
  llenarMarcasPorCategoria(cat);
  limpiarDatalist();
  // Reset de clasificaciones cuando cambia la categoría
  if (cat !== 'switch') { inputTipoSwitch.value = ''; activarBotones('.tipo-switch',''); }
  if (cat !== 'camara') { inputTipoCCTV.value = ''; activarBotones('#tipoCamaraContainer .btn',''); }
}

function onMarcaChange() {
  const cat = detectarCategoria(equipoInput.value);
  llenarModelos(cat, marcaSelect.value);
  // Reintentar clasificar por si ya hay modelo escrito
  clasificarPorModelo(cat);
}

function onModeloInput() {
  const cat = detectarCategoria(equipoInput.value);
  clasificarPorModelo(cat);
}

// Expuesto para el oninput del HTML
window.actualizarMarcaYBotones = function () {
  onEquipoChange();
};

document.addEventListener('DOMContentLoaded', () => {
  // Botones toggle: ya tienes onclick en HTML para setear inputs hidden;
  // aquí solo marcamos visualmente cuando el usuario hace click:
  document.querySelectorAll('.tipo-alarma').forEach(btn => {
    btn.addEventListener('click', () => activarBotones('.tipo-alarma', btn.textContent));
  });
  document.querySelectorAll('.tipo-switch').forEach(btn => {
    btn.addEventListener('click', () => activarBotones('.tipo-switch', btn.textContent));
  });

  equipoInput?.addEventListener('input', onEquipoChange);
  marcaSelect?.addEventListener('change', onMarcaChange);
  modeloInput?.addEventListener('input', onModeloInput);

  // Primera evaluación al cargar (por si viene algo pre-llenado)
  onEquipoChange();
});

// ------------------------------------------------------------
// 8) Efecto desvanecer sugerencia (igual que el tuyo)
// ------------------------------------------------------------
setTimeout(() => {
  const sugerencia = document.getElementById('sugerencia');
  if (sugerencia) {
    sugerencia.style.opacity = '0';
    setTimeout(() => sugerencia.remove(), 1000);
  }
}, 3000);
