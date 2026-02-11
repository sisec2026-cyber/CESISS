<?php
// /sisec-ui/views/ubicacion/sucursales_crear.php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Técnico','Mantenimientos']);
require_once __DIR__ . '/../../includes/db.php';

// Carga catálogo de ciudades
$ciudades = $conn->query("SELECT id, nom_ciudad FROM ciudades ORDER BY nom_ciudad ASC");
$mensaje = $_GET['ok'] ?? null;
$error   = $_GET['err'] ?? null;

ob_start();
?>
<style>
  :root{--brand:#3C92A6; --brand-2:#24A3C1; --ink:#10343b; --muted:#486973; --bg:#F7FBFD;--surface:#FFFFFF; --border:#DDEEF3; --border-strong:#BFE2EB;--chip:#EAF7FB; --ring:0 0 0 .22rem rgba(36,163,193,.25); --ring-strong:0 0 0 .28rem rgba(36,163,193,.33);--shadow-sm:0 6px 18px rgba(20,78,90,.08);--radius-xl:1rem; --radius-2xl:1.25rem;}
  h2{ font-weight:800; letter-spacing:.2px; color:var(--ink); margin-bottom:.75rem!important; }
  h2::after{ content:""; display:block; width:78px; height:4px; border-radius:99px; margin-top:.5rem;background:linear-gradient(90deg,var(--brand),var(--brand-2)); }
</style>
<div style="padding-left: 25px;">
  <h2 class="mb-3">Registrar nueva sucursal</h2>
  <p class="text-muted mb-4">Selecciona <strong>Ciudad</strong> y <strong>Municipio</strong>, escribe el <strong>nombre de la sucursal</strong>, su <strong>determinante</strong>, y captura <strong>sus cordenadas</strong> con el mapa (o búsqueda).</p>
  <?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="sucursales_guardar.php" class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">
        <!-- Ciudad -->
        <div class="col-md-3">
          <label class="form-label">Ciudad</label>
          <select name="ciudad_id" id="ciudad" class="form-select" required>
            <option value="">-- Selecciona una ciudad --</option>
            <?php while ($c = $ciudades->fetch_assoc()): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nom_ciudad']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <!-- Municipio -->
        <div class="col-md-3">
          <label class="form-label">Municipio</label>
          <select name="municipio_id" id="municipio" class="form-select" required>
            <option value="">-- Selecciona un municipio --</option>
          </select>
        </div>
        <!-- Nombre sucursal -->
        <div class="col-md-3">
          <label class="form-label">Nombre de sucursal</label>
          <input type="text" name="nom_sucursal" id="nom_sucursal" class="form-control" placeholder="Ej: SUBURBIA TOREO" required>
        </div>
        <!-- Determinante -->
        <div class="col-md-3">
          <label class="form-label">Determinante</label>
          <input type="text" name="nom_determinante" id="nom_determinante" class="form-control" placeholder="Ej: 1234" required>
        </div>
        <!-- Búsqueda / geocodificación -->
        <div class="col-md-6">
          <label class="form-label">Buscar dirección (Nominatim)</label>
          <div class="input-group">
            <input type="text" id="busqueda" class="form-control" placeholder="Ej: Centro Comercial Toreo, Naucalpan">
            <button type="button" id="btnBuscar" class="btn btn-outline-primary">Buscar</button>
          </div>
          <div class="form-text">Consejo: agrega municipio y estado. Se consulta tu <code>nominatim_proxy.php</code>.</div>
        </div>
        <!-- Lat/Lng -->
        <div class="col-md-3">
          <label class="form-label">Latitud</label>
          <input type="text" class="form-control" name="lat" id="lat" inputmode="decimal" pattern="-?[0-9]*[.,]?[0-9]*" autocomplete="off" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Longitud</label>
          <input type="text" class="form-control" name="lng" id="lng" inputmode="decimal" pattern="-?[0-9]*[.,]?[0-9]*" autocomplete="off" required>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="button" id="btnMiUbicacion" class="btn btn-sm btn-outline-secondary">Usar mi ubicación</button>
          <button type="button" id="btnCentrarMunicipio" class="btn btn-sm btn-outline-secondary">Centrar en municipio</button>
        </div>
        <div class="col-12">
          <div id="map" style="height: 460px; border-radius: 10px; overflow: hidden;"></div>
          <div class="form-text mt-2">Tip: haz clic en el mapa para colocar/mover el marcador. Guardar creará la sucursal y su determinante.</div>
        </div>
        <div class="col-12 text-end">
          <a class="btn btn-light" href="javascript:history.back()">← Cancelar</a>
          <button class="btn btn-primary" type="submit">Guardar sucursal</button>
        </div>
      </div>
    </div>
  </form>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const defaultCenter = [19.4326, -99.1332]; // CDMX
const map = L.map('map').setView(defaultCenter, 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 20, attribution: '&copy; OpenStreetMap'}).addTo(map);
let marker = null;
function setPoint(lat, lng, zoom=17) {
  if (marker) marker.setLatLng([lat, lng]);
  else marker = L.marker([lat, lng], {draggable:true}).addTo(map);
  map.setView([lat, lng], zoom);
  document.getElementById('lat').value = (+lat).toFixed(6);
  document.getElementById('lng').value = (+lng).toFixed(6);
  marker.off('dragend').on('dragend', e => {
    const p = e.target.getLatLng();
    document.getElementById('lat').value = p.lat.toFixed(6);
    document.getElementById('lng').value = p.lng.toFixed(6);
  });
}

map.on('click', e => setPoint(e.latlng.lat, e.latlng.lng, map.getZoom() < 16 ? 16 : map.getZoom()));
async function jget(url) {
  const r = await fetch(url, {credentials:'same-origin'});
  if (!r.ok) throw new Error('HTTP '+r.status);
  return r.json();
}

// Cascada: Ciudad → Municipios
const selCiudad = document.getElementById('ciudad');
const selMpio   = document.getElementById('municipio');
function clearSelect(sel, ph) {
  sel.innerHTML = '';
  const o = document.createElement('option');
  o.value = '';
  o.textContent = ph;
  sel.appendChild(o);
}
selCiudad.addEventListener('change', async () => {
  clearSelect(selMpio, '-- Selecciona un municipio --');
  const id = selCiudad.value;
  if (!id) return;
  try {
    const data = await jget(`../ubicacion/api_municipios.php?ciudad_id=${encodeURIComponent(id)}`);
    data.forEach(m => {
      const o = document.createElement('option');
      o.value = m.id;
      o.textContent = m.nom_municipio ?? m.nombre ?? '';
      if (o.textContent) selMpio.appendChild(o);
    });
  } catch(e) { alert('No se pudieron cargar los municipios'); }
});
document.getElementById('btnMiUbicacion').addEventListener('click', () => {
  if (!navigator.geolocation) return alert('Tu navegador no soporta geolocalización.');
  navigator.geolocation.getCurrentPosition(
    pos => setPoint(pos.coords.latitude, pos.coords.longitude),
    _ => alert('No se pudo obtener tu ubicación.')
  );
});
// Centrar en municipio (geocodifica municipio + ciudad + México)
document.getElementById('btnCentrarMunicipio').addEventListener('click', async () => {
  const ciudadTxt = selCiudad.options[selCiudad.selectedIndex]?.text || '';
  const mpioTxt   = selMpio.options[selMpio.selectedIndex]?.text || '';
  const q = [mpioTxt, ciudadTxt, 'México'].filter(Boolean).join(', ');
  if (!q.trim()) return alert('Selecciona ciudad y municipio.');
  await buscarDireccion(q);
});
// Búsqueda con tu proxy Nominatim
async function buscarDireccion(texto) {
  if (!texto) return;
  try {
    const url = '/sisec-ui/api/nominatim_proxy.php?q=' + encodeURIComponent(texto + ' México');
    const j = await (await fetch(url)).json();
    if (Array.isArray(j) && j[0]) {
      setPoint(parseFloat(j[0].lat), parseFloat(j[0].lon));
    } else {
      alert('No se encontró la dirección. Agrega municipio/estado.');
    }
  } catch (e) {
    console.error(e);
    alert('Error al buscar. Ver consola.');
  }
}
document.getElementById('btnBuscar').addEventListener('click', () => {
  const v = document.getElementById('busqueda').value.trim();
  buscarDireccion(v);
});
// UX: mayúsculas suaves
['nom_sucursal','nom_determinante'].forEach(id=>{
  const el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('input', ()=>{
    const s = el.selectionStart, e = el.selectionEnd;
    el.value = (el.value||'').toLocaleUpperCase('es-MX');
    if (typeof s === 'number') el.setSelectionRange(s,e);
  });
});
</script>
<script>
// ===== Pin flotante mientras escribes (inputs ↔ mapa) =====
const latInp = document.getElementById('lat');
const lngInp = document.getElementById('lng');
let ghost = null; // circleMarker temporal (pre-visualización)
let tDebounce = null;
function debounce(fn, ms=200) {
  return (...args) => {
    clearTimeout(tDebounce);
    tDebounce = setTimeout(() => fn(...args), ms);
  };
}
// Crea/actualiza el pin flotante
function showGhost(lat, lng) {
  if (!ghost) {
    ghost = L.circleMarker([lat, lng], {
      radius: 8,
      weight: 2,
      opacity: 0.9,
      fillOpacity: 0.15
    }).addTo(map);
  } else {
    ghost.setLatLng([lat, lng]);
    ghost.setStyle({opacity: 0.9, fillOpacity: 0.15});
  }
}
// Oculta y destruye el pin flotante
function hideGhost() {
  if (ghost) {
    map.removeLayer(ghost);
    ghost = null;
  }
}
// Parseo flexible (acepta coma como decimal y "lat, lng")
function parseNumPair(latStr, lngStr) {
  function toNum(v) {
    if (v == null) return NaN;
    return Number(String(v).trim().replace(',', '.'));
  }
  // Soporta pegar "LAT, LNG" en un solo input (normalmente lat)
  const s = String(latStr || '').trim();
  const m = s.match(/^\s*(-?\d+(?:[.,]\d+)?)\s*[, ]+\s*(-?\d+(?:[.,]\d+)?)\s*$/);
  if (m && (!lngStr || !String(lngStr).trim())) {
    return [Number(m[1].replace(',', '.')), Number(m[2].replace(',', '.')), true];
  }
  return [toNum(latStr), toNum(lngStr), false];
}
function validLatLng(lat, lng) {
  return Number.isFinite(lat) && Number.isFinite(lng) &&
         lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
}
// Previsualiza mientras escribes (sin fijar marcador real)
function livePreviewFromInputs() {
  let [lat, lng, combined] = parseNumPair(latInp.value, lngInp.value);
  if (combined) {
    // Si pegaron "lat, lng" en LAT, reparte a los inputs y volvemos a leer
    latInp.value = lat.toFixed(6);
    lngInp.value = lng.toFixed(6);
  }
  // Relee por si acabamos de repartir
  lat = Number(String(latInp.value).replace(',', '.'));
  lng = Number(String(lngInp.value).replace(',', '.'));
  if (validLatLng(lat, lng)) {
    showGhost(lat, lng);
    // Mueve la vista suavemente si estás lejos
    const center = map.getCenter();
    const dist = Math.hypot(center.lat - lat, center.lng - lng);
    if (dist > 0.1) map.setView([lat, lng], Math.max(map.getZoom(), 16));
  } else {
    hideGhost();
  }
}
// Fija marcador real a partir de inputs (y limpia ghost)
function finalizeFromInputs(pan = true) {
  let [lat, lng, combined] = parseNumPair(latInp.value, lngInp.value);
  if (combined) {
    latInp.value = lat.toFixed(6);
    lngInp.value = lng.toFixed(6);
  } else {
    lat = Number(String(latInp.value).replace(',', '.'));
    lng = Number(String(lngInp.value).replace(',', '.'));
  }
  if (!validLatLng(lat, lng)) return;
  latInp.value = lat.toFixed(6);
  lngInp.value = lng.toFixed(6);
  setPoint(lat, lng, pan ? 17 : map.getZoom());
  hideGhost();
}
// Eventos de escritura: solo muestran ghost (debounced)
latInp.addEventListener('input', debounce(livePreviewFromInputs, 150));
lngInp.addEventListener('input', debounce(livePreviewFromInputs, 150));
// Blur: fija marcador sin forzar zoom brusco
latInp.addEventListener('blur', () => finalizeFromInputs(false));
lngInp.addEventListener('blur', () => finalizeFromInputs(false));
// Enter: fija marcador y centra
[latInp, lngInp].forEach(el => {
  el.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      finalizeFromInputs(true);
    }
  });
});
// Si clicas el mapa o arrastras el marcador, ya decidiste con el mapa → ocultamos ghost
map.on('click', () => hideGhost());
// Asegúrate de ocultar el ghost también cuando tomes la posición del municipio
// (solo si tu buscarDireccion hace setPoint inmediato)
const _buscarDireccion = buscarDireccion;
buscarDireccion = async function(texto) {
  hideGhost();
  return _buscarDireccion(texto);
};
// Antes de enviar, por si no hiciste blur/enter
const formSuc = document.querySelector('form[action="sucursales_guardar.php"]');
if (formSuc) {
  formSuc.addEventListener('submit', () => finalizeFromInputs(false));
}
</script>
<?php
$content = ob_get_clean();
$activePage = 'sucursales';
$pageTitle = "Registrar Sucursal";
include __DIR__ . '/../../layout.php';