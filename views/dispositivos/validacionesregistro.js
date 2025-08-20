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
  "TP-LINK": { poe: ["TL-SG2210MP"], plano: ["TL-SG3428XF"] },
  PHYBRIDGE: { poe: ["NV-FLX-024-10G"] },
  "EXTREME NETWORKS": { poe: ["X435-24P-4S"] },
  GRANDSTREAM: { poe: ["GWN7802P"] },
  AXIS: { poe: ["T8524","T8516","T8508"] },
  ARUBA: { poe: ["R8Q67A ARUBA"] }
};

// CÁMARAS (sin duplicados; AmericanDynamics vacío por ahora)
const modelosPorMarcaCamara = {
  HANWHA: {
    ip: [
      "QNV-6012R","QND-6082R","PNO-A9081R","QNV-6082R1","QND-8010R",
      "QNO-6012R","QNO-6082R","XNF-8010RVM","XNF-9010RV","PNM-7082RVD",
      "QND-6012R","QND6022R1","XNF-8010RV","QND-8010R","XNF-9010RV"
    ],
    analogica: ["HCP-6320A"]
  },
  AXIS: {
    ip: [
      "F4105-LRE","M1135 Mk II","M1135-E Mk II","M1137-E Mk II","P1375-E",
      "P1377","P1455-LE","P1465-LE","P1467-LE","P1468-LE","P3265-LVE",
      "P3265V","P3267-LV","P3705-PLVE","P3735-PLE","P3818-PVE",
      "P4705-PLVE","P4708-PLVE","P5654-E Mk II","P5655-E","M2035-LE",
      "M2036-LE","M3007-PV","M3067-P","M3085-V","M3086-V","M4317-PLVE",
      "M4327-P","M4328-P","M1387-LE","P1387-LE"
    ],
    analogica: []
  },
  HIKVISION: {
    ip: ["DS-2CD2023G2-I(U)","DS-2CD2125G0-IMS"],
    analogica: ["DS-2CE12DF0T-F","DS-2CE16D0T-LFS","DS-2CE57D3T-VPITF"]
  },
  UNIVIEW: {
    ip: [
      "IPC322SB-DF28K-I0","IPC314SB-ADF28K-I0","IPC2122SB-ADF28KM-I0",
      "IPC2125LE-ADF28KM-G","IPC2325SB-DZK-I0","IPC3224SS-ADF28K-I1",
      "IPC324LE-DSF28K-G","IPC325SB-DF28K-I0","IPC3605SB-ADF16KM-l0",
      "IPC3612LB-ADF28K-H","IPC3612LB-SF28-A","IPC815SB-ADF14K-I0",
      "IPC86CEB-AF18KC-I0","IPC2K24SE-ADF40KMC-WL-I0","HC121@TS8C-Z"
    ],
    analogica: ["UAC-D122-AF28M-H"]
  },
  AVIGILON: {
    ip: [
      "2.0C-H6M-D1","2.0C-H6A-D1","2.0C-H6SL-D1","3.0C-H6SL-BO2-IR",
      "6.0C-H6ADH-DO1-IR","8.0C-H6A-BO1-IR","8.0C-H6A-FE-DO1",
      "8.0C-H6A-FE-360-DO1-IR","12C-H5A-4MH-30"
    ],
    analogica: []
  },
  DAHUA:   { ip: [], analogica: [] },
  MERIVA:  { ip: [], analogica: ["MSC-203","MSC-3214"] },
  WISENET: { ip: [], analogica: [] },
  SAMSUNG: { ip: ["SCO-2080R"], analogica: [] },
  AmericanDynamics: { ip: [], analogica: [] }
};

// NVR
const modelosPorMarcaNVR = {
  HIKVISION: { nvr: ["DS-7732NI","DS-7608NI","DS7732NIM4/16P"] },
  HANWHA:    { nvr: ["XRN-820S","XRN-1620SB1"] },
  DAHUA:     { nvr: ["NVR4216-4KS3","DHI-NVR5416-16P-EI"] },
  UNIVIEW:   { nvr: [
    "NVR302-16S2-P16","NVR304-16X","NVR301-16LS3-P8","NVR304-32S-P16",
    "NVR301-04S3-P4","NVR301-04X-P4","NVR301-08LX-P8","NVR301-08S3-P8",
    "NVR301-04LS3-P4","NVR516-128","NVR302-08S2-P8","NVR302-32E2-IQ",
    "NVR302-16E2-P16-IQ","NVR302-16B-P16-IQ","NVR302-08E2-P8-IQ",
    "NVR304-16B-P16-IQ","NV041UNV15"
  ] },
  MERIVA:    { nvr: [] },
  WISENET:   { nvr: [] },
  AVIGILON:  { nvr: ["AINVRPRM128TBNA"] },
  EPCOM:     { nvr: ["GABVID1R3"] }
};

// DVR
const modelosPorMarcaDVR = {
  HIKVISION: { dvr: ["DS-7204HUHI"] },
  DAHUA:     { dvr: ["XVR5104"] },
  HANWHA:    { dvr: ["HRX-435"] },
  ZKTECO:    { dvr: ["Z8404XE"] },
  SAMSUNG:   { dvr: [] },
  MERIVA:    { dvr: [] }
};

// SERVIDORES
const modelosPorMarcaServidor = {
  DELL: { servidores: ["POWER EDGE R550 XEON GOLD","R250","R350","POWER EDGE T40","T40","POWEREDGE T360"] },
  SUPERMICRO: { servidores: ["SYS-520P-WTR","PWS-741P-1R","SYS-520P-WTR 2UR"] },
  AXIS: { servidores: ["S1296 96TB","S1264 64TB","S1264 24TB"] },
  AVIGILON_ALTA: { servidores: ["APP-500-8-DG A500 8TB","APP-750-32-DG A750 32TB"] },
  LIAS: { servidores: ["AWA-CLD-3Y"] }
};

// ------------------------------------------------------------
// DISPOSITIVOS DE ALARMA Y CONTROL (Alámbrico / Inalámbrico)
// ------------------------------------------------------------

// Detectores de humo (DH)
const modelosPorMarcaDH = {
  DMP: { alambrico: ["1046747","1164NS-W"], inalambrico: [] }
};

// PIRS (sensores de movimiento)
const modelosPorMarcaPIR = {
  HONEYWELL: { alambrico: ["IS335","DT7450"], inalambrico: ["5800PIR","5800PIR-RES"] },
  DSC:       { alambrico: ["LC-100-PI","LC-104-PIMW"], inalambrico: ["WS4904P"] },
  BOSCH:     { alambrico: ["ISC-BPR2-W12","ISC-BDL2-WP12"], inalambrico: [] }, 
  DMP:       { alambrico: ["1046747","1164NS-W"], inalambrico: [] },
  OPTEX:     { alambrico: [], inalambrico: [] }
};

// Contactos magnéticos (CM)
const modelosPorMarcaCM = {
  HONEYWELL: { alambrico: ["7939WG","943WG"], inalambrico: ["5816","5800MINI"] },
  DSC:       { alambrico: ["DC-1025","DC-1025T"], inalambrico: ["WS4945","PG9309"] },
  SFIRE:     { alambrico: [], inalambrico: ["2023"] },
  SECOLARM:  { alambrico: [], inalambrico: ["216Q/GY","226LQ","4601LQ"] },
  TANEALARM: { alambrico: [], inalambrico: ["GP23"] }
};

// Botón de pánico (BTN)
const modelosPorMarcaBTN = {
  DMP:       { alambrico: ["1142-W"], inalambrico: ["1144-2","1148-G"] },
  INOVONICS: { alambrico: ["EN1235-S","EN1236-D"], inalambrico: ["EN1235 SF"] },
  ACCESPRO:  { alambrico: ["APBSEMC","PRO800B","ACCESSK1"], inalambrico: [] },
  AXCEZE:    { alambrico: ["AXB70R"], inalambrico: [] },
  ENFORCER:  { alambrico: ["SD-927-PKCNSQ"], inalambrico: [] }
};

// Overhead (OH)
const modelosPorMarcaOH = {
  HONEYWELL: { alambrico: ["7939WG","943WG"], inalambrico: ["5816","5800MINI"] },
  DSC:       { alambrico: ["DC-1025","DC-1025T"], inalambrico: ["WS4945","PG9309"] },
  SFIRE:     { alambrico: [], inalambrico: ["2023"] },
  SECOLARM:  { alambrico: [], inalambrico: ["216Q/GY","226LQ","4601LQ"] },
  TANEALARM: { alambrico: [], inalambrico: ["GP23"] }
};

// Estrobos
const modelosPorMarcaEstrobo = {
  SYSTEM_SENSOR: { alambrico: ["SPSRK","SPSWK"], inalambrico: [] },
  GENTEX:        { alambrico: ["GXS","GX90"], inalambrico: [] }
};

// Repetidoras (REP)
const modelosPorMarcaREP = {
  DMP:       { alambrico: [], inalambrico: ["1100R-W"] },
  INOVONICS: { alambrico: ["EN5040T"], inalambrico: [] }
};

// Dispositivos de ruptura de cristal (DRC)
const modelosPorMarcaDRC = {
  DMP:       { alambrico: ["EN1247","1128-W"], inalambrico: [] },
  INOVONICS: { alambrico: ["EN1247"], inalambrico: [] },
  DSC:       { alambrico: ["DG50AU"], inalambrico: [] },
  HONEYWELL: { alambrico: ["FG1625T"], inalambrico: [] }
};

// Monitores
const modelosPorMarcaMonitor = {
  DELL:    { monitores: ["E2223HN","E2225HS"] },
  SAMSUNG: { monitores: ["LS24A310NHLXZX","LS19A330NHLXZX","LS24A600NWLXZX","LS49CG950SLXZX","LS22C310EALXZX","LS24A336NHLXZX","LS24D300GALXZX","S33A"] },
  BENQ:    { monitores: ["GW2480","GW2283","GW2780","GW2490","GW2790"] },
  HANWHA:  { monitores: ["SMT-1935","SMT-2233"] },
  HP:      { monitores: ["P22 G5","P24V G5","M22F"] },
  ZKTECO:  { monitores: ["ZD43-4K"] },
  UNIVIEW: { monitores: ["MW3232-V-K","MW3232-E","MW3232-V-K2","MW3222-V-DT"] },
  LG:      { monitores: ["32MR50C-B","32SR50F-W.AWM"] },
  STARTECH:{ monitores: ["FPWARTB1M"] }
};

// Estación manual (pull station)
const modelosPorMarcaEstacionManual = {
  DMP: { alambrico: ["850S"], inalambrico: [] }
};

// Estación de trabajo (PC)
const modelosPorMarcaEstacionTrabajo = {
  DELL:   { estacion: ["OptiPlex 3080","OptiPlex 7080","Precision 3460"] },
  HP:     { estacion: ["ProDesk 600 G6","EliteDesk 800 G6","Z2 G9"] },
  LENOVO: { estacion: ["ThinkCentre M70q","ThinkStation P330"] }
};

// ------------------------------------------------------------
// Helpers de normalización y búsqueda
// ------------------------------------------------------------
const norm  = s => (s ?? '').toString().trim();
const normU = s => norm(s).toUpperCase();
const quitarAcentos = s => norm(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'');

function marcaKey(dict, valorSeleccion) {
  const val = normU(valorSeleccion);
  return Object.keys(dict).find(k => normU(k) === val) || null;
}
function flattenBySubkey(dict, subkey) {
  const out = [];
  Object.values(dict).forEach(grupo => {
    if (grupo && Array.isArray(grupo[subkey])) out.push(...grupo[subkey].map(normU));
  });
  return out;
}

// Switches: fallback global
const modelosPorTipo = {
  switch_poe:   flattenBySubkey(modelosPorMarca, 'poe'),
  switch_plano: flattenBySubkey(modelosPorMarca, 'plano')
};

// --- Combinar todos los diccionarios de alarma en uno "ALARMA" ---
function mergeAlarmDicts() {
  const alarmDicts = [
    modelosPorMarcaDH, modelosPorMarcaPIR, modelosPorMarcaCM,
    modelosPorMarcaBTN, modelosPorMarcaOH, modelosPorMarcaEstrobo,
    modelosPorMarcaREP, modelosPorMarcaDRC, modelosPorMarcaEstacionManual
  ];
  const merged = {}; // { MARCA: { alambrico:[], inalambrico:[] } }

  const pushArr = (to, arr) => {
    if (!Array.isArray(arr)) return;
    arr.forEach(m => { if (!to.includes(m)) to.push(m); });
  };

  alarmDicts.forEach(dict => {
    Object.entries(dict).forEach(([marca, grupos]) => {
      if (!merged[marca]) merged[marca] = { alambrico: [], inalambrico: [] };
      const keys = Object.keys(grupos || {});
      if (keys.includes('alambrico') || keys.includes('inalambrico')) {
        pushArr(merged[marca].alambrico, grupos.alambrico || []);
        pushArr(merged[marca].inalambrico, grupos.inalambrico || []);
      } else {
        Object.values(grupos || {}).forEach(v => pushArr(merged[marca].alambrico, v));
      }
    });
  });

  return merged;
}

// ------------------------------------------------------------
// Cache de elementos
// ------------------------------------------------------------
const equipoInput = document.getElementById('equipo');
const marcaSelect = document.getElementById('marca');
const modeloInput = document.getElementById('modelo');
const datalist    = document.getElementById('sugerencias-modelo');

const grupoCCTV   = document.querySelector('.grupo-cctv');
const grupoAlarma = document.querySelector('.grupo-alarma');
const campoRC     = document.querySelector('.campo-rc');
const ubicacionRC = document.querySelector('.campo-ubicacion-rc');
const campoWin    = document.querySelector('.campo-win');
const campoVmsVer = document.querySelector('.campo-vms-version');

const tipoAlarmaContainer = document.getElementById('tipoAlarmaContainer'); // "Alámbrico / Inalámbrico"
const tipoSwitchContainer = document.getElementById('tipoSwitchContainer'); // "PoE / Plano"
const tipoCamaraContainer = document.getElementById('tipoCamaraContainer'); // "IP / Analógica"

const inputTipoAlarma = document.getElementById('tipo_alarma');  // hidden
const inputTipoSwitch = document.getElementById('tipo_switch');  // hidden
const inputTipoCCTV   = document.getElementById('tipo_cctv');    // hidden

// --- Elementos para Marca manual ---
const marcaManualInput     = document.getElementById('marcaManual');
const toggleMarcaManualBtn = document.getElementById('toggleMarcaManual');

// ------------------------------------------------------------
// 1) Gestión dropzones
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
function detectarCategoria(texto) {
  const v = quitarAcentos(normU(String(texto))).replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();

  if (['SWITCH'].includes(v)) return 'switch';
  if (['CAMARA','CCTV'].includes(v)) return 'camara';
  if (['NVR'].includes(v)) return 'nvr';
  if (['DVR'].includes(v)) return 'dvr';
  if (['SERVIDOR','SERVER'].includes(v)) return 'servidor';
  if (['MONITOR','DISPLAY'].includes(v)) return 'monitor';
  if (['ESTACION TRABAJO','ESTACION DE TRABAJO','WORKSTATION','PC','COMPUTADORA'].includes(v)) return 'estacion_trabajo';
  if (['ALARMA'].includes(v)) return 'alarma';

  if (v.includes('SWITCH')) return 'switch';
  if (v.includes('CAMARA') || v.includes('CCTV')) return 'camara';
  if (v.includes('NVR')) return 'nvr';
  if (v.includes('DVR')) return 'dvr';
  if (v.includes('SERVIDOR') || v.includes('SERVER')) return 'servidor';
  if (v.includes('MONITOR') || v.includes('DISPLAY')) return 'monitor';
  if (v.includes('ESTACION TRABAJO') || v.includes('ESTACION DE TRABAJO') || v.includes('WORKSTATION') || v.includes('COMPUTADORA') || v.includes('PC')) return 'estacion_trabajo';

  const palabrasClaveAlarmaGenerales = [
    "ALARMA","TRANSMISOR","SENSOR","DETECTOR","HUMO","OVER HEAD","OVERHEAD","ZONA",
    "BOTON","BOTON PANICO","PANICO","ESTACION","PULL STATION","PULL",
    "PANEL","CABLEADO","SIRENA","RECEPTOR","EMISOR","LLAVIN","TECLADO",
    "ESTROBO","CRISTAL","RUPTURA","REPETIDOR","REPETIDORA","DH","PIR","CM","BTN","OH","DRC","REP"
  ];
  if (palabrasClaveAlarmaGenerales.some(p => v.includes(quitarAcentos(normU(p))))) {
    return 'alarma';
  }

  return 'otro';
}

function toggleGruposPorCategoria(cat) {
  const esCamaraLike = ['camara','servidor','nvr','dvr'].includes(cat);
  const esAlarmaLike = ['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual'].includes(cat);

  grupoCCTV?.classList.toggle('d-none', !esCamaraLike);
  grupoAlarma?.classList.toggle('d-none', !esAlarmaLike);

  const mostrarRC = ['camara','switch','servidor','nvr','dvr'].includes(cat);
  campoRC?.classList.toggle('d-none', !mostrarRC);
  ubicacionRC?.classList.toggle('d-none', cat !== 'servidor');
  campoWin?.classList.toggle('d-none', cat !== 'servidor');
  campoVmsVer?.classList.toggle('d-none', cat !== 'servidor');

  tipoSwitchContainer?.classList.toggle('d-none', cat !== 'switch');
  tipoCamaraContainer.style.display = (cat === 'camara') ? 'block' : 'none';
  tipoAlarmaContainer?.classList.toggle('d-none', !esAlarmaLike);

  // --- credenciales: ocultar y gestionar required ---
  const ocultarCred = esAlarmaLike || cat === 'switch';
  const userWrapper = document.querySelector('.campo-user');
  const passWrapper = document.querySelector('.campo-pass');
  const userInput = document.querySelector('[name="user"]');
  const passInput = document.querySelector('[name="pass"]');

  userWrapper?.classList.toggle('d-none', ocultarCred);
  passWrapper?.classList.toggle('d-none', ocultarCred);

  if (userInput) userInput.required = !ocultarCred;
  if (passInput) passInput.required = !ocultarCred;

  // Ocultar campos innecesarios cuando es alarma
  ['vms','switch','puerto'].forEach(name => {
    const campo = document.querySelector(`[name="${name}"]`)?.closest('.col-md-3');
    campo?.classList.toggle('d-none', esAlarmaLike);
  });
}


// ------------------------------------------------------------
// 3) Marcas y modelos según categoría
// ------------------------------------------------------------
const diccionarioPorCategoria = {
  switch:           modelosPorMarca,
  camara:           modelosPorMarcaCamara,
  nvr:              modelosPorMarcaNVR,
  dvr:              modelosPorMarcaDVR,
  servidor:         modelosPorMarcaServidor,
  dh:               modelosPorMarcaDH,
  pir:              modelosPorMarcaPIR,
  cm:               modelosPorMarcaCM,
  btn:              modelosPorMarcaBTN,
  oh:               modelosPorMarcaOH,
  estrobo:          modelosPorMarcaEstrobo,
  rep:              modelosPorMarcaREP,
  drc:              modelosPorMarcaDRC,
  monitor:          modelosPorMarcaMonitor,
  estacionmanual:   modelosPorMarcaEstacionManual,
  estacion_trabajo: modelosPorMarcaEstacionTrabajo,
  alarma:           mergeAlarmDicts()
};

function llenarMarcasPorCategoria(cat) {
  const dict = diccionarioPorCategoria[cat] || null;
  marcaSelect.innerHTML = '<option value="">-- Selecciona una marca --</option>';
  if (!dict) return;

  Object.keys(dict).sort().forEach(mk => {
    const opt = document.createElement('option');
    opt.value = mk;
    opt.textContent = mk;
    marcaSelect.appendChild(opt);
  });
}

function limpiarDatalist() { datalist.innerHTML = ''; }

function llenarModelos(cat, mk) {
  limpiarDatalist();
  const dict = diccionarioPorCategoria[cat] || null;
  if (!dict) return;

  const key = marcaKey(dict, mk);
  if (!key) return;

  const modelos = Object.values(dict[key]).flat().map(m => m.toString());
  modelos.forEach(modelo => {
    const option = document.createElement('option');
    option.value = modelo;
    datalist.appendChild(option);
  });
}

// ------------------------------------------------------------
// 4) Filtrado por conexión + resaltado y clasificación
// ------------------------------------------------------------
function normalizaConexionLabel(label) {
  const v = quitarAcentos(String(label || '')).toLowerCase();
  if (v.includes('inalambr')) return 'inalambrico';
  if (v.includes('alambr'))   return 'alambrico';
  return null;
}

// === Marca manual: helpers ===
function isMarcaManualMode() {
  return marcaManualInput && !marcaManualInput.classList.contains('d-none');
}
function getMarcaActual() {
  const manual = norm(marcaManualInput?.value);
  if (isMarcaManualMode() && manual) return manual;
  return marcaSelect.value;
}
function setMarcaValueForSubmit(value) {
  if (!value) return;
  const val = norm(value);
  let opt = [...marcaSelect.options].find(o => normU(o.value) === normU(val));
  if (!opt) {
    opt = new Option(val, val, true, true);
    marcaSelect.add(opt);
  }
  marcaSelect.value = opt.value;
}

// Devuelve modelos para {categoria, marca, conexion}
function obtenerModelosPorConexion(cat, mk, conexion /* 'alambrico'|'inalambrico' */) {
  const dict = diccionarioPorCategoria[cat];
  if (!dict) return [];

  const key = mk ? marcaKey(dict, mk) : null;

  const pushAll = (out, modelos) => {
    if (Array.isArray(modelos)) modelos.forEach(m => out.push(String(m)));
  };

  if (key) {
    const grupo = dict[key] || {};
    if (Array.isArray(grupo[conexion])) {
      return grupo[conexion].map(m => String(m));
    }
    const out = [];
    Object.values(grupo).forEach(v => pushAll(out, v));
    return [...new Set(out)];
  }

  const out = [];
  Object.values(dict).forEach(grupo => {
    if (Array.isArray(grupo?.[conexion])) {
      pushAll(out, grupo[conexion]);
    } else {
      Object.values(grupo || {}).forEach(v => pushAll(out, v));
    }
  });
  return [...new Set(out)];
}

function setDatalistModelos(modelos, {autocompletarUnico = true} = {}) {
  limpiarDatalist();
  modelos.forEach(modelo => {
    const option = document.createElement('option');
    option.value = modelo;
    datalist.appendChild(option);
  });
  if (autocompletarUnico && modelos.length === 1) {
    modeloInput.value = modelos[0];
  } else if (modeloInput.value && !modelos.map(normU).includes(normU(modeloInput.value))) {
    modeloInput.value = '';
  }
}

// Actualiza el datalist según la conexión seleccionada (solo alarmas)
function actualizarModelosSegunConexion() {
  const cat = detectarCategoria(equipoInput.value);
  if (!['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual'].includes(cat)) return;

  const conexion = normalizaConexionLabel(inputTipoAlarma.value);
  if (!conexion) return;

  const mk = getMarcaActual();
  const modelos = obtenerModelosPorConexion(cat, mk, conexion);
  setDatalistModelos(modelos);
}

function activarBotones(selector, textoObjetivo) {
  const botones = document.querySelectorAll(selector);
  botones.forEach(btn => btn.classList.remove('activo'));
  if (!textoObjetivo || !textoObjetivo.trim()) return;
  botones.forEach(btn => {
    if (btn.textContent.toLowerCase().includes(textoObjetivo.toLowerCase())) {
      btn.classList.add('activo');
    }
  });
}

function clasificarAlarmaPorModelo(cat) {
  const mk = getMarcaActual();
  const modelo = normU(modeloInput.value);
  const dict = diccionarioPorCategoria[cat];
  if (!dict || !modelo) return;

  const key = marcaKey(dict, mk);
  if (!key) return;

  const alam = (dict[key].alambrico || []).map(normU);
  const inal = (dict[key].inalambrico || []).map(normU);

  if (alam.includes(modelo)) {
    inputTipoAlarma.value = 'Alámbrico';
    activarBotones('#tipoAlarmaContainer .btn', 'Alámbrico');
  } else if (inal.includes(modelo)) {
    inputTipoAlarma.value = 'Inalámbrico';
    activarBotones('#tipoAlarmaContainer .btn', 'Inalámbrico');
  }
}

function clasificarPorModelo(cat) {
  const modelo = normU(modeloInput.value);
  const mk = getMarcaActual();
  if (!modelo) return;

  if (cat === 'switch') {
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
    return;
  }

  if (cat === 'camara') {
    const key = marcaKey(modelosPorMarcaCamara, mk);
    if (key) {
      const ipList = (modelosPorMarcaCamara[key].ip || []).map(normU);
      const anList = (modelosPorMarcaCamara[key].analogica || []).map(normU);
      if (ipList.includes(modelo)) {
        inputTipoCCTV.value = 'IP';
        activarBotones('#tipoCamaraContainer .btn', 'IP');
      } else if (anList.includes(modelo)) {
        inputTipoCCTV.value = 'Analógica';
        activarBotones('#tipoCamaraContainer .btn', 'Analógica');
      }
    }
    return;
  }

  if (['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual'].includes(cat)) {
    clasificarAlarmaPorModelo(cat);
  }
}

// ------------------------------------------------------------
// 5) Validaciones (MAC / IP)
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
// 6) Municipios dinámicos
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
// 7) Wiring de eventos
// ------------------------------------------------------------
function onEquipoChange() {
  const cat = detectarCategoria(equipoInput.value);
  toggleGruposPorCategoria(cat);
  llenarMarcasPorCategoria(cat);
  limpiarDatalist();

  modeloInput.value = '';

  if (cat !== 'switch')  { inputTipoSwitch.value = ''; activarBotones('.tipo-switch',''); }
  if (cat !== 'camara')  { inputTipoCCTV.value   = ''; activarBotones('#tipoCamaraContainer .btn',''); }
  if (!['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual'].includes(cat)) {
    inputTipoAlarma.value = ''; activarBotones('#tipoAlarmaContainer .btn','');
  } else {
    actualizarModelosSegunConexion();
  }
}

function onMarcaChange() {
  const cat = detectarCategoria(equipoInput.value);

  modeloInput.value = '';

  if (
    ['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual'].includes(cat) &&
    normalizaConexionLabel(inputTipoAlarma.value)
  ) {
    actualizarModelosSegunConexion();
  } else {
    llenarModelos(cat, getMarcaActual());
  }

  clasificarPorModelo(cat);
}

function onModeloInput() {
  const cat = detectarCategoria(equipoInput.value);
  clasificarPorModelo(cat);
}

// Expuesto para oninput del HTML si lo usas
window.actualizarMarcaYBotones = function () {
  onEquipoChange();
};

document.addEventListener('DOMContentLoaded', () => {
  // ---- Botones Alámbrico / Inalámbrico ----
  document.querySelectorAll('.tipo-alarma').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tipo-alarma').forEach(b => {
        b.classList.remove('activo');
        b.setAttribute('aria-pressed', 'false');
      });
      btn.classList.add('activo');
      btn.setAttribute('aria-pressed', 'true');

      const conexion = normalizaConexionLabel(btn.textContent);
      inputTipoAlarma.value = conexion === 'inalambrico' ? 'Inalámbrico' : 'Alámbrico';
      actualizarModelosSegunConexion();
    });
  });

  // ---- Botones PoE / Plano ----
  document.querySelectorAll('.tipo-switch').forEach(btn => {
    btn.addEventListener('click', () => {
      activarBotones('.tipo-switch', btn.textContent);
      if (/poe/i.test(btn.textContent))   inputTipoSwitch.value = 'PoE';
      if (/plano/i.test(btn.textContent)) inputTipoSwitch.value = 'Plano';
    });
  });

  // ---- Botones IP / Analógica ----
  document.querySelectorAll('#tipoCamaraContainer .btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activarBotones('#tipoCamaraContainer .btn', btn.textContent);
      if (/ip$/i.test(btn.textContent))            inputTipoCCTV.value = 'IP';
      if (/anal[oó]gica/i.test(btn.textContent))   inputTipoCCTV.value = 'Analógica';
    });
  });

  // ---- Marca manual: toggle + sincronización ----
  function showManual() {
    marcaManualInput.classList.remove('d-none');
    marcaSelect.disabled = true;
    setTimeout(() => marcaManualInput.focus(), 0);
  }
  function hideManual() {
    marcaManualInput.classList.add('d-none');
    marcaSelect.disabled = false;
  }
  function syncSelectFromManual() {
    const val = (marcaManualInput.value || '').trim();
    if (!val) return;
    setMarcaValueForSubmit(val);
    marcaSelect.dispatchEvent(new Event('change', { bubbles: true }));
  }

  toggleMarcaManualBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    if (marcaManualInput.classList.contains('d-none')) { showManual(); } else { hideManual(); }
  });
  marcaManualInput?.addEventListener('input',   syncSelectFromManual);
  marcaManualInput?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); syncSelectFromManual(); }});
  marcaManualInput?.addEventListener('blur',    syncSelectFromManual);

  // Eventos principales
  equipoInput?.addEventListener('input', onEquipoChange);
  marcaSelect?.addEventListener('change', onMarcaChange);
  modeloInput?.addEventListener('input', onModeloInput);

  // Primera evaluación al cargar
  onEquipoChange();
});

// ------------------------------------------------------------
// 8) Efecto desvanecer sugerencia
// ------------------------------------------------------------
setTimeout(() => {
  const sugerencia = document.getElementById('sugerencia');
  if (sugerencia) {
    sugerencia.style.opacity = '0';
    setTimeout(() => sugerencia.remove(), 1000);
  }
}, 3000);

