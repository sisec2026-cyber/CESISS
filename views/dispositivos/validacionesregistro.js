/* =======================================================================
   A) NÚCLEO “GRANDE” (Pulido + compatibilidad con reglas que ya te sirven)
   - Re-captura de elementos al cargar
   - Detección robusta de categoría (incluye Rotari/Ratonera/Expansora/Transformador/Módulos)
   - Ocultado por alias (user/pass/switch/puerto/mac/ip)
   - Marcas/Modelos por categoría (requiere catálogos ya cargados)
   - Validadores MAC/IP
   ======================================================================= */

/* ==== Helpers base ==== */
const norm  = s => (s ?? '').toString().trim();
const normU = s => norm(s).toUpperCase();
const quitarAcentos = s => norm(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'');

/* ==== Catálogos: derivados/fallbacks (requieren que existan los dicts) ==== */
function safeHas(obj, key){ return obj && Object.prototype.hasOwnProperty.call(obj, key); }
function marcaKey(dict, valorSeleccion) {
  const val = normU(valorSeleccion);
  return Object.keys(dict || {}).find(k => normU(k) === val) || null;
}
function flattenBySubkey(dict, subkey) {
  const out = [];
  Object.values(dict || {}).forEach(grupo => {
    if (grupo && Array.isArray(grupo[subkey])) out.push(...grupo[subkey].map(normU));
  });
  return out;
}
// Switch: sets globales si hay catálogo
const modelosPorTipo = {
  switch_poe:   new Set(safeHas(window,'modelosPorMarca') ? flattenBySubkey(window.modelosPorMarca, 'poe')   : []),
  switch_plano: new Set(safeHas(window,'modelosPorMarca') ? flattenBySubkey(window.modelosPorMarca, 'plano') : [])
};
// Merge de todos los diccionarios de alarma si existen
function mergeAlarmDicts() {
  const alarmDicts = [
    window.modelosPorMarcaDH, window.modelosPorMarcaPIR, window.modelosPorMarcaCM,
    window.modelosPorMarcaBTN, window.modelosPorMarcaOH, window.modelosPorMarcaEstrobo,
    window.modelosPorMarcaREP, window.modelosPorMarcaDRC, window.modelosPorMarcaEstacionManual
  ].filter(Boolean);

  const merged = {}; // { MARCA: { alambrico:[], inalambrico:[] } }
  const pushArr = (to, arr) => { if (Array.isArray(arr)) arr.forEach(m => { if (!to.includes(m)) to.push(m); }); };

  alarmDicts.forEach(dict => {
    Object.entries(dict || {}).forEach(([marca, grupos]) => {
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
const ALARMA_MERGED = mergeAlarmDicts();

/* ==== Detección de categoría ==== */
const CAT_MAP = [
  { cat:'switch', keys:[/^\s*switch\s*$/i,'SWITCH'] },
  { cat:'camara', keys:[/^\s*(camara|cctv)\s*$/i,'CAMARA','CCTV'] },
  { cat:'nvr',    keys:['NVR'] },
  { cat:'dvr',    keys:['DVR'] },
  { cat:'servidor', keys:['SERVIDOR','SERVER'] },
  { cat:'monitor', keys:['MONITOR','DISPLAY'] },
  { cat:'estacion_trabajo', keys:['ESTACION TRABAJO','ESTACION DE TRABAJO','WORKSTATION','PC','COMPUTADORA'] },
  // Refuerzo: términos que quieres tratar como alarma
  { cat:'alarma', keys:['ROTARI','RATONERA','EXPANSORA','TRANSFORMADOR','MODULO','MODULOS'] }
];

const ALARMA_KEYS = [
  "ALARMA","TRANSMISOR","SENSOR","DETECTOR","HUMO","OVER HEAD","OVERHEAD","ZONA",
  "BOTON","BOTON PANICO","PANICO","ESTACION","PULL STATION","PULL",
  "PANEL","CABLEADO","SIRENA","RECEPTOR","EMISOR","LLAVIN","TECLADO",
  "ESTROBO","CRISTAL","RUPTURA","REPETIDOR","REPETIDORA","DH","PIR","CM","BTN","OH","DRC","REP",
  "ROTARI","RATONERA","EXPANSORA","TRANSFORMADOR","MODULO","MODULOS"
].map(s => quitarAcentos(s.toUpperCase()));

function detectarCategoria(texto) {
  const raw = String(texto || '');
  const v = quitarAcentos(normU(raw)).replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();

  // salida corta para tus términos
  if (/(^|[\s\-])(rotari|ratonera|expansora|transformador|modulo|modulos)([\s\-]|$)/i.test(raw)) {
    return 'alarma';
  }
  for (const {cat, keys} of CAT_MAP) {
    if ((keys||[]).some(k => typeof k === 'string' ? v.includes(quitarAcentos(k)) : k.test(v))) return cat;
  }
  if (ALARMA_KEYS.some(k => v.includes(k))) return 'alarma';
  return 'otro';
}

/* ==== Cache/refs de elementos (re-captura en DOMContentLoaded) ==== */
let equipoInput, marcaSelect, modeloInput, datalist;
let grupoCCTV, grupoAlarma, campoRC, ubicacionRC, campoWin, campoVmsVer;
let tipoAlarmaContainer, tipoSwitchContainer, tipoCamaraContainer;
let inputTipoAlarma, inputTipoSwitch, inputTipoCCTV;
let marcaManualInput, toggleMarcaManualBtn;

/* ==== Helpers UI de visibilidad/required ==== */
const show = (el, on) => el?.classList.toggle('d-none', !on);
const setReq = (input, on) => { if (input) input.required = !!on; };

/* ==== Ocultado robusto por alias (name/id/label) ==== */
function normTxt(t){return (t??'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toUpperCase();}
const FIELD_ALIASES = {
  user:   { names:['user'],      ids:[],           labels:['IDE','USUARIO','USER'] },
  pass:   { names:['pass'],      ids:[],           labels:['IDE PASSWORD','CONTRASEÑA','PASSWORD'] },
  switch: { names:['switch'],    ids:[],           labels:['SWITCH'] },
  puerto: { names:['puerto'],    ids:[],           labels:['NO. PUERTO','PUERTO','PORT'] },
  mac:    { names:['mac'],       ids:['macInput'], labels:['DIRECCION MAC','MAC','MAC ADDRESS'] },
  ip:     { names:['ip','ipTag'],ids:['ipInput'],  labels:['DIRECCION IP','IP','IP ADDRESS'] },
};
function uniqEls(arr){ const s = new Set(); const out=[]; arr.forEach(el=>{ if(el && !s.has(el)){ s.add(el); out.push(el);} }); return out; }
function findWrapper(el){ return el?.closest('.col-md-3, .col-md-4, .col-md-6, .col-sm-6, .col-12, .form-group') || el?.parentElement || null; }
function wrappersForAlias(key){
  const conf = FIELD_ALIASES[key] || {};
  let found = [];
  (conf.names||[]).forEach(n=>{ document.querySelectorAll(`[name="${n}"]`).forEach(el=>found.push(findWrapper(el))); });
  (conf.ids||[]).forEach(id=>{ const el = document.getElementById(id); if (el) found.push(findWrapper(el)); });
  const wanted = new Set((conf.labels||[]).map(normTxt));
  document.querySelectorAll('label').forEach(lbl=>{ if (wanted.has(normTxt(lbl.textContent))) found.push(findWrapper(lbl)); });
  return uniqEls(found.filter(Boolean));
}
function hideAliases(keys){
  keys.forEach(k=>{
    wrappersForAlias(k).forEach(w=>{
      w.classList.add('d-none'); w.style.display='none'; w.setAttribute('aria-hidden','true');
      w.querySelectorAll('input,select,textarea').forEach(i=>i.required=false);
    });
  });
  // Oculta también “status” de MAC/IP si están fuera del wrapper
  if (keys.includes('mac')){
    const tag = document.getElementById('tag'); const wt = tag ? findWrapper(tag) : null;
    if (wt){ wt.classList.add('d-none'); wt.style.display='none'; wt.setAttribute('aria-hidden','true'); }
  }
  if (keys.includes('ip')){
    const ip = document.getElementById('ip'); const wi = ip ? findWrapper(ip) : null;
    if (wi){ wi.classList.add('d-none'); wi.style.display='none'; wi.setAttribute('aria-hidden','true'); }
  }
}
function showAliases(keys){
  keys.forEach(k=>{
    wrappersForAlias(k).forEach(w=>{
      w.classList.remove('d-none'); w.style.display=''; w.setAttribute('aria-hidden','false');
    });
  });
}
function setRequiredAliases(keys, on){
  keys.forEach(k=>{
    wrappersForAlias(k).forEach(w=>{
      w.querySelectorAll('input,select,textarea').forEach(i=>i.required=!!on);
    });
  });
}

/* ==== Llenado de marcas/modelos por categoría (depende de catálogos) ==== */
const diccionarioPorCategoria = {
  switch:           window.modelosPorMarca,
  camara:           window.modelosPorMarcaCamara,
  nvr:              window.modelosPorMarcaNVR,
  dvr:              window.modelosPorMarcaDVR,
  servidor:         window.modelosPorMarcaServidor,
  dh:               window.modelosPorMarcaDH,
  pir:              window.modelosPorMarcaPIR,
  cm:               window.modelosPorMarcaCM,
  btn:              window.modelosPorMarcaBTN,
  oh:               window.modelosPorMarcaOH,
  estrobo:          window.modelosPorMarcaEstrobo,
  rep:              window.modelosPorMarcaREP,
  drc:              window.modelosPorMarcaDRC,
  monitor:          window.modelosPorMarcaMonitor,
  estacionmanual:   window.modelosPorMarcaEstacionManual,
  estacion_trabajo: window.modelosPorMarcaEstacionTrabajo,
  alarma:           ALARMA_MERGED
};
function limpiarDatalist(){ if (datalist) datalist.innerHTML=''; }
function setDatalistModelos(modelos, {autocompletarUnico = true} = {}){
  if (!datalist) return;
  limpiarDatalist();
  const frag = document.createDocumentFragment();
  (modelos||[]).forEach(modelo => {
    const option = document.createElement('option');
    option.value = String(modelo);
    frag.appendChild(option);
  });
  datalist.appendChild(frag);
  if (modeloInput) {
    if (autocompletarUnico && modelos.length === 1) {
      modeloInput.value = modelos[0];
    } else if (modeloInput.value && !modelos.map(normU).includes(normU(modeloInput.value))) {
      modeloInput.value = '';
    }
  }
}
function llenarMarcasPorCategoria(cat){
  const dict = diccionarioPorCategoria[cat] || null;
  if (!marcaSelect) return;
  marcaSelect.innerHTML = '<option value="">-- Selecciona una marca --</option>';
  if (!dict) return;
  Object.keys(dict).sort().forEach(mk=>{
    const opt = document.createElement('option'); opt.value = mk; opt.textContent = mk; marcaSelect.appendChild(opt);
  });
}
function llenarModelos(cat, mk){
  limpiarDatalist();
  const dict = diccionarioPorCategoria[cat] || null; if (!dict) return;
  const key = marcaKey(dict, mk); if (!key) return;
  const modelos = Object.values(dict[key]).flat().map(String);
  setDatalistModelos(modelos, { autocompletarUnico: true });
}
function normalizaConexionLabel(label) {
  const v = quitarAcentos(String(label || '')).toLowerCase();
  if (v.includes('inalambr')) return 'inalambrico';
  if (v.includes('alambr'))   return 'alambrico';
  return null;
}
function isMarcaManualMode() {
  return marcaManualInput && !marcaManualInput.classList.contains('d-none');
}
function getMarcaActual() {
  const manual = norm(marcaManualInput?.value);
  if (isMarcaManualMode() && manual) return manual;
  return marcaSelect?.value || '';
}
function setMarcaValueForSubmit(value) {
  if (!marcaSelect || !value) return;
  const val = norm(value);
  let opt = [...marcaSelect.options].find(o => normU(o.value) === normU(val));
  if (!opt) { opt = new Option(val, val, true, true); marcaSelect.add(opt); }
  marcaSelect.value = opt.value;
}
function obtenerModelosPorConexion(cat, mk, conexion) {
  const dict = diccionarioPorCategoria[cat]; if (!dict) return [];
  const key = mk ? marcaKey(dict, mk) : null;
  const pushAll = (out, modelos) => { if (Array.isArray(modelos)) modelos.forEach(m => out.push(String(m))); };
  if (key) {
    const grupo = dict[key] || {};
    if (Array.isArray(grupo[conexion])) return grupo[conexion].map(String);
    const out = []; Object.values(grupo).forEach(v => pushAll(out, v)); return [...new Set(out)];
  }
  const out = []; Object.values(dict).forEach(grupo => {
    if (Array.isArray(grupo?.[conexion])) pushAll(out, grupo[conexion]);
    else Object.values(grupo || {}).forEach(v => pushAll(out, v));
  });
  return [...new Set(out)];
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
  const modelo = normU(modeloInput?.value || '');
  const dict = diccionarioPorCategoria[cat];
  if (!dict || !modelo) return;
  const key = marcaKey(dict, mk); if (!key) return;
  const alam = new Set((dict[key].alambrico || []).map(normU));
  const inal = new Set((dict[key].inalambrico || []).map(normU));
  if (alam.has(modelo)) {
    if (inputTipoAlarma) inputTipoAlarma.value = 'Alámbrico';
    activarBotones('#tipoAlarmaContainer .btn', 'Alámbrico');
  } else if (inal.has(modelo)) {
    if (inputTipoAlarma) inputTipoAlarma.value = 'Inalámbrico';
    activarBotones('#tipoAlarmaContainer .btn', 'Inalámbrico');
  }
}
function clasificarPorModelo(cat) {
  const modelo = normU(modeloInput?.value || '');
  const mk = getMarcaActual(); if (!modelo) return;

  if (cat === 'switch' && window.modelosPorMarca) {
    let esPoe = modelosPorTipo.switch_poe.has(modelo);
    let esPlano = modelosPorTipo.switch_plano.has(modelo);
    const key = marcaKey(window.modelosPorMarca, mk);
    if (key) {
      const poe   = new Set((window.modelosPorMarca[key].poe   || []).map(normU));
      const plano = new Set((window.modelosPorMarca[key].plano || []).map(normU));
      esPoe = poe.has(modelo) || esPoe; esPlano = plano.has(modelo) || esPlano;
    }
    if (esPoe)  { if (inputTipoSwitch) inputTipoSwitch.value = 'PoE';   activarBotones('.tipo-switch','PoE');   }
    else if (esPlano) { if (inputTipoSwitch) inputTipoSwitch.value = 'Plano'; activarBotones('.tipo-switch','Plano'); }
    else { if (inputTipoSwitch) inputTipoSwitch.value = ''; }
    return;
  }

  if (cat === 'camara' && window.modelosPorMarcaCamara) {
    const key = marcaKey(window.modelosPorMarcaCamara, mk);
    if (key) {
      const ipSet = new Set((window.modelosPorMarcaCamara[key].ip || []).map(normU));
      const anSet = new Set((window.modelosPorMarcaCamara[key].analogica || []).map(normU));
      if (ipSet.has(modelo)) { if (inputTipoCCTV) inputTipoCCTV.value = 'IP'; activarBotones('#tipoCamaraContainer .btn', 'IP'); }
      else if (anSet.has(modelo)) { if (inputTipoCCTV) inputTipoCCTV.value = 'Analógica'; activarBotones('#tipoCamaraContainer .btn', 'Analógica'); }
    }
    return;
  }

  if (new Set(['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual']).has(cat)) {
    clasificarAlarmaPorModelo(cat);
  }
}

/* ==== Reglas de visibilidad (combinadas con tus “que sí funcionan”) ==== */
function toggleGruposPorCategoria(cat) {
  const esCamaraLike = new Set(['camara','servidor','nvr','dvr']).has(cat);
  const esAlarmaLike = new Set(['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual']).has(cat);
  const esMonitor    = (cat === 'monitor');
  const esSwitch     = (cat === 'switch');

  // Bloques especiales
  show(grupoCCTV,   esCamaraLike);
  show(grupoAlarma, esAlarmaLike);

  // RC visible por defecto salvo 'otro'
// RC visible por defecto salvo 'otro' **y salvo alarma**
  const mostrarRC = (cat !== 'otro' && !esAlarmaLike);
  show(campoRC,  mostrarRC);


  // Campos específicos de servidor
  show(ubicacionRC, cat === 'servidor');
  show(campoWin,    cat === 'servidor');
  show(campoVmsVer, cat === 'servidor');

  // Controles de tipo
  show(tipoSwitchContainer, esSwitch);
  if (tipoCamaraContainer) tipoCamaraContainer.style.display = (cat === 'camara') ? 'block' : 'none';
  show(tipoAlarmaContainer, esAlarmaLike);

  // ===== RESET: mostrar todo lo común y limpiar required =====
  showAliases(['user','pass','switch','puerto','mac','ip']);
  setRequiredAliases(['user','pass','switch','puerto','mac','ip'], false);

  // ===== Reglas combinadas (las tuyas “que sí funcionan”) =====
  if (esMonitor) {
    // MONITOR/DISPLAY: ocultar switch, IP, MAC, puerto, user, pass
    hideAliases(['user','pass','switch','puerto','mac','ip']);
  } else if (esSwitch) {
    // SWITCH: ocultar MAC, IP y el propio campo “Switch”
    hideAliases(['mac','ip','switch']);
  } else if (esAlarmaLike) {
    // ALARMA: ocultar MAC e IP
    hideAliases(['mac','ip','user','pass','switch','puerto','rc','ubicacion_rc']);
    setRequiredAliases(['user','pass','switch','puerto','rc','ubicacion_rc'], false);
  }

  // Credenciales required solo cuando visibles (no monitores)
// Credenciales (IDE / IDE Password) solo required cuando NO es monitor NI alarma
const credVisibles = (!esMonitor && !esAlarmaLike);
setRequiredAliases(['user','pass'], credVisibles);


  // Sincroniza wrappers específicos si existen (clases opcionales)
// Refuerzo para wrappers con clases personalizadas (si existen)
const userWrap = document.querySelector('.campo-user');
const passWrap = document.querySelector('.campo-pass');

if (userWrap) userWrap.classList.toggle('d-none', esMonitor || esAlarmaLike);
if (passWrap) passWrap.classList.toggle('d-none', esMonitor || esAlarmaLike);

}

/* ==== Eventos principales ==== */
function actualizarModelosSegunConexion() {
  const cat = detectarCategoria(equipoInput?.value || '');
  if (!['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual'].includes(cat)) return;
  const conexion = normalizaConexionLabel(inputTipoAlarma?.value || ''); if (!conexion) return;
  const mk = getMarcaActual();
  const modelos = obtenerModelosPorConexion(cat, mk, conexion);
  setDatalistModelos(modelos);
}
function onEquipoChange() {
  const cat = detectarCategoria(equipoInput?.value || '');
  toggleGruposPorCategoria(cat);
  llenarMarcasPorCategoria(cat);
  limpiarDatalist();

  if (modeloInput) modeloInput.value = '';

  if (cat !== 'switch' && inputTipoSwitch)  { inputTipoSwitch.value = ''; activarBotones('.tipo-switch',''); }
  if (cat !== 'camara' && inputTipoCCTV)    { inputTipoCCTV.value   = ''; activarBotones('#tipoCamaraContainer .btn',''); }

  const esAlarmaLike = new Set(['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual']).has(cat);
  if (!esAlarmaLike && inputTipoAlarma) {
    inputTipoAlarma.value = ''; activarBotones('#tipoAlarmaContainer .btn','');
  } else {
    actualizarModelosSegunConexion();
  }
}
function onMarcaChange() {
  const cat = detectarCategoria(equipoInput?.value || '');
  if (modeloInput) modeloInput.value = '';
  const esAlarmaLike = new Set(['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual']).has(cat);
  if (esAlarmaLike && normalizaConexionLabel(inputTipoAlarma?.value || '')) {
    actualizarModelosSegunConexion();
  } else {
    llenarModelos(cat, getMarcaActual());
  }
  clasificarPorModelo(cat);
}
function onModeloInput() {
  const cat = detectarCategoria(equipoInput?.value || '');
  clasificarPorModelo(cat);
}
// Expuesto para oninput del HTML si lo usas
window.actualizarMarcaYBotones = function () { onEquipoChange(); };

/* ==== Validaciones (MAC / IP) ==== */
function setStatus(elId, ok, okMsg, badMsg) {
  const el = document.getElementById(elId);
  if (!el) return;
  const msg = ok ? okMsg : badMsg;
  if ('value' in el) el.value = msg; else el.textContent = msg;
  el.style.color = ok ? 'green' : 'red';
}
window.formatearYValidarMac = function(input) {
  let valor = input.value.replace(/[^A-Fa-f0-9]/g,'').toUpperCase().slice(0, 12);
  input.value = valor.match(/.{1,2}/g)?.join(':') ?? '';
  const ok = /^([0-9A-F]{2}:){5}[0-9A-F]{2}$/.test(input.value);
  setStatus('tag', ok, '✅ MAC válida', '❌ MAC inválida');
}
window.validarIP = function(input) {
  const ip = input.value.replace(/[^0-9.]/g, '');
  input.value = ip;
  const partes = ip.split('.');
  const ok = partes.length === 4 && partes.every(p => /^\d{1,3}$/.test(p) && +p >= 0 && +p <= 255);
  setStatus('ip', ok, '✅ IP válida', '❌ IP inválida');
}

/* ==== DOMContentLoaded: re-captura y wire-up ==== */
document.addEventListener('DOMContentLoaded', () => {
  // Re-captura (asegura no-null)
  equipoInput = document.getElementById('equipo');
  marcaSelect = document.getElementById('marca');
  modeloInput = document.getElementById('modelo');
  datalist    = document.getElementById('sugerencias-modelo');

  grupoCCTV   = document.querySelector('.grupo-cctv');
  grupoAlarma = document.querySelector('.grupo-alarma');
  campoRC     = document.querySelector('.campo-rc');
  ubicacionRC = document.querySelector('.campo-ubicacion-rc');
  campoWin    = document.querySelector('.campo-win');
  campoVmsVer = document.querySelector('.campo-vms-version');

  tipoAlarmaContainer = document.getElementById('tipoAlarmaContainer');
  tipoSwitchContainer = document.getElementById('tipoSwitchContainer');
  tipoCamaraContainer = document.getElementById('tipoCamaraContainer');

  inputTipoAlarma = document.getElementById('tipo_alarma');
  inputTipoSwitch = document.getElementById('tipo_switch');
  inputTipoCCTV   = document.getElementById('tipo_cctv');

  marcaManualInput     = document.getElementById('marcaManual');
  toggleMarcaManualBtn = document.getElementById('toggleMarcaManual');

  // Botones de tipo (alarma/switch/cámara)
  document.querySelectorAll('.tipo-alarma').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tipo-alarma').forEach(b => { b.classList.remove('activo'); b.setAttribute('aria-pressed', 'false'); });
      btn.classList.add('activo'); btn.setAttribute('aria-pressed', 'true');
      const conexion = normalizaConexionLabel(btn.textContent);
      if (inputTipoAlarma) inputTipoAlarma.value = conexion === 'inalambrico' ? 'Inalámbrico' : 'Alámbrico';
      actualizarModelosSegunConexion();
    });
  });
  document.querySelectorAll('.tipo-switch').forEach(btn => {
    btn.addEventListener('click', () => {
      activarBotones('.tipo-switch', btn.textContent);
      if (inputTipoSwitch) {
        if (/poe/i.test(btn.textContent))   inputTipoSwitch.value = 'PoE';
        if (/plano/i.test(btn.textContent)) inputTipoSwitch.value = 'Plano';
      }
    });
  });
  document.querySelectorAll('#tipoCamaraContainer .btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activarBotones('#tipoCamaraContainer .btn', btn.textContent);
      if (inputTipoCCTV) {
        if (/ip$/i.test(btn.textContent))            inputTipoCCTV.value = 'IP';
        if (/anal[oó]gica/i.test(btn.textContent))   inputTipoCCTV.value = 'Analógica';
      }
    });
  });

  // Marca manual
  function showManual(){ if (!marcaManualInput || !marcaSelect) return; marcaManualInput.classList.remove('d-none'); marcaSelect.disabled = true; setTimeout(() => marcaManualInput.focus(), 0); }
  function hideManual(){ if (!marcaManualInput || !marcaSelect) return; marcaManualInput.classList.add('d-none');   marcaSelect.disabled = false; }
  function syncSelectFromManual(){
    const val = (marcaManualInput?.value || '').trim(); if (!val) return;
    setMarcaValueForSubmit(val); marcaSelect?.dispatchEvent(new Event('change', { bubbles: true }));
  }
  toggleMarcaManualBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    if (!marcaManualInput) return;
    if (marcaManualInput.classList.contains('d-none')) { showManual(); } else { hideManual(); }
  });
  marcaManualInput?.addEventListener('input',   syncSelectFromManual);
  marcaManualInput?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); syncSelectFromManual(); }});
  marcaManualInput?.addEventListener('blur',    syncSelectFromManual);

  // Eventos principales
  equipoInput?.addEventListener('input', onEquipoChange);
  equipoInput?.addEventListener('change', onEquipoChange);
  marcaSelect?.addEventListener('change', onMarcaChange);
  modeloInput?.addEventListener('input', onModeloInput);

  // Primera evaluación
  onEquipoChange();

  // Efecto desvanecer sugerencia (si existe)
  setTimeout(() => {
    const sugerencia = document.getElementById('sugerencia');
    if (sugerencia) { sugerencia.style.opacity = '0'; setTimeout(() => sugerencia.remove(), 1000); }
  }, 3000);
});



/* =======================================================================
   B) IMÁGENES (Drag&Drop, Ctrl+V, N/A) + Menú Acciones
   - Consolidado tal cual lo tienes, pero aislado en IIFE
   ======================================================================= */
(() => {
  const MAX_SIZE_MB = 10;
  let SUPPRESS_UNDERLAY_CLICK_UNTIL = 0;

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
    };
    reader.readAsDataURL(file);
  }

  window.__handleUpload = handleFile;

  function generateNAPngDataURL() {
    const canvas = document.createElement('canvas');
    canvas.width = 640; canvas.height = 360;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f3f4f6'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#d1d5db'; ctx.lineWidth = 4; ctx.strokeRect(8, 8, canvas.width - 16, canvas.height - 16);
    ctx.fillStyle = '#6b7280';
    ctx.font = 'bold 120px system-ui, -apple-system, Segoe UI, Roboto, Arial';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('N/A', canvas.width/2, canvas.height/2);
    return canvas.toDataURL('image/png');
  }
  function dataURLToFile(dataURL, filename) {
    const arr = dataURL.split(','), mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]); let n = bstr.length; const u8 = new Uint8Array(n);
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
    chk.addEventListener('change', () => { if (chk.checked) applyNAtoSlot(ctx1); else clearSlot(ctx1); });
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
    activeDropzone = ctx.dz; manualPasteTarget = !!manual;
    dropzones.forEach(d => {
      d.dz.classList.remove('border-primary', 'border-2');
      if (d.tip) d.tip.classList.add('d-none');
    });
    ctx.dz.classList.add('border-primary', 'border-2');
    if (ctx.tip) ctx.tip.classList.remove('d-none');
  }
  dropzones.forEach(buildPasteTargetUI);
  if (dropzones[0]) setActivePasteTarget(dropzones[0], false);

  dropzones.forEach(({ i, dz, input, preview, removeBtn }) => {
    ['dragenter','dragover'].forEach(ev => {
      dz.addEventListener(ev, (e) => {
        e.preventDefault(); e.stopPropagation(); dz.classList.add('bg-light');
        if (!manualPasteTarget) { const ctx = dropzones.find(d => d.dz === dz); if (ctx) setActivePasteTarget(ctx, false); }
      });
    });
    ['dragleave','dragend','drop'].forEach(ev => {
      dz.addEventListener(ev, (e) => { if (ev !== 'drop') dz.classList.remove('bg-light'); });
    });
    dz.addEventListener('drop', (e) => {
      e.preventDefault(); e.stopPropagation(); dz.classList.remove('bg-light');
      const file = e.dataTransfer?.files?.[0] || null;
      if (file) handleFile(file, { i, dz, input, preview, removeBtn });
      if (i === 1) { const chk = document.getElementById('chkNAImg1'); if (chk) chk.checked = false; }
    });

    input?.addEventListener('change', () => {
      if (input.dataset.ignoreNextChange === '1') { input.dataset.ignoreNextChange = '0'; return; }
      const file = input.files?.[0]; if (file) handleFile(file, { i, dz, input, preview, removeBtn });
      if (i === 1) { const chk = document.getElementById('chkNAImg1'); if (chk) chk.checked = false; }
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', (e) => {
        e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
        SUPPRESS_UNDERLAY_CLICK_UNTIL = Date.now() + 700;
        input.dataset.ignoreNextChange = '1';
        input.value = '';
        const emptyDT = new DataTransfer(); input.files = emptyDT.files;
        preview.src = '#'; preview.classList.add('d-none');
        removeBtn.classList.add('d-none'); dz.classList.remove('bg-light');
        const msg = dz.querySelector('.mensaje'); if (msg) msg.textContent = 'Arrastra una imagen aquí o haz clic';
        if (i === 1) { const chk = document.getElementById('chkNAImg1'); if (chk) chk.checked = false; }
        setTimeout(() => { delete input.dataset.ignoreNextChange; }, 100);
      });
    }

    dz.tabIndex = 0;
    dz.addEventListener('focusin', () => {
      if (!manualPasteTarget) { const ctx = dropzones.find(d => d.dz === dz); if (ctx) setActivePasteTarget(ctx, false); }
    });
  });

  let pickSlot = null;
  window.openPickMenu = function(slot){
    pickSlot = slot;
    const modalEl = document.getElementById('pickImageModal');
    if (!modalEl) return;
    new bootstrap.Modal(modalEl).show();
  };
  dropzones.forEach(({ i, dz }) => {
    dz.addEventListener('click', (e) => {
      const now = Date.now();
      if (now < SUPPRESS_UNDERLAY_CLICK_UNTIL) return;
      if (document.querySelector('.modal.show')) return;
      if (e.target.closest('.form-check, label, input[type="checkbox"], button, .paste-pin')) return;
      e.preventDefault(); e.stopPropagation(); openPickMenu(i);
    }, true);
  });

  document.addEventListener('paste', (e) => {
    const dt = e.clipboardData || window.clipboardData; if (!dt) return;
    let file = null;
    if (dt.items && dt.items.length) {
      for (const item of dt.items) {
        if (item.type && item.type.startsWith('image/')) { const blob = item.getAsFile(); if (blob) { file = new File([blob], `pasted-${Date.now()}.png`, { type: blob.type || 'image/png' }); break; } }
      }
    }
    if (!file && dt.files && dt.files.length) { const f = dt.files[0]; if (f.type.startsWith('image/')) file = f; }
    if (!file) return;
    const ctx = dropzones.find(d => d.dz === activeDropzone) || dropzones[0];
    if (ctx) {
      handleFile(file, ctx);
      if (ctx.i === 1) { const chk = document.getElementById('chkNAImg1'); if (chk) chk.checked = false; }
    }
  });

  const pmEl    = document.getElementById('pickImageModal');
  const btnTake = document.getElementById('pmTakePhoto');
  const btnPick = document.getElementById('pmPickFile');
  const btnPaste= document.getElementById('pmPaste');

  btnTake?.addEventListener('click', () => {
    if (typeof window.openCamera === 'function') openCamera(pickSlot);
    SUPPRESS_UNDERLAY_CLICK_UNTIL = Date.now() + 700;
    const inst = bootstrap.Modal.getInstance(pmEl); setTimeout(() => { inst && inst.hide(); }, 0);
  });
  btnPick?.addEventListener('click', () => {
    const input = document.getElementById('imagen' + pickSlot); if (!input) return;
    try { input.setAttribute('capture','environment'); } catch(_) {}
    input.click();
    SUPPRESS_UNDERLAY_CLICK_UNTIL = Date.now() + 700;
    const inst = bootstrap.Modal.getInstance(pmEl); setTimeout(() => { inst && inst.hide(); }, 0);
  });
  btnPaste?.addEventListener('click', async () => {
    try {
      if (!navigator.clipboard?.read) throw new Error('No soportado');
      const items = await navigator.clipboard.read();
      let blob = null, type = null;
      for (const it of items) { for (const t of it.types) { if (t.startsWith('image/')) { blob = await it.getType(t); type = t; break; } } if (blob) break; }
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
      const inst = bootstrap.Modal.getInstance(pmEl); setTimeout(() => { inst && inst.hide(); }, 0);
    } catch (e) {
      alert('Tu navegador no permite leer imágenes del portapapeles aquí. Usa Ctrl+V / ⌘+V sobre la página.');
    }
  });
})();


/* =======================================================================
   C) CÁMARA (openCamera / switchCamera / takePhoto / stopCamera)
   ======================================================================= */
let __cam = { slot:null, stream:null, devices:[], index:0, video:null, canvas:null };

function setFileToInput(input, file) {
  const dt = new DataTransfer();
  dt.items.add(file);
  input.files = dt.files;
  input.dispatchEvent(new Event('change', { bubbles: true }));
}
async function enumerateCameras() {
  try { const all = await navigator.mediaDevices.enumerateDevices(); return all.filter(d => d.kind === 'videoinput'); }
  catch (e) { return []; }
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
    if (input) { try { input.setAttribute('capture','environment'); } catch(_) {} input.click(); }
    const instance = bootstrap.Modal.getInstance(modalEl); instance && instance.hide();
  }
};
window.switchCamera = async function() {
  if (!__cam.devices.length) return;
  __cam.index = (__cam.index + 1) % __cam.devices.length;
  try { await startCamera(__cam.devices[__cam.index].deviceId); }
  catch (e) { console.error('switchCamera error:', e); }
};
window.stopCamera = function() {
  if (__cam.stream) { __cam.stream.getTracks().forEach(t => t.stop()); __cam.stream = null; }
};
function dataURLToFile(dataURL, filename) {
  const arr = dataURL.split(','), mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]); let n = bstr.length; const u8 = new Uint8Array(n);
  while (n--) u8[n] = bstr.charCodeAt(n);
  return new File([u8], filename, { type: mime });
}
window.takePhoto = function() {
  if (!__cam.video) return;
  const w = __cam.video.videoWidth || 1280;
  const h = __cam.video.videoHeight || 720;
  __cam.canvas.width = w; __cam.canvas.height = h;

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
