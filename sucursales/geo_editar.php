<?php
// /sisec-ui/views/sucursales/geo_editar.php
require_once __DIR__ . '/../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Técnico','Mantenimientos']);
require_once __DIR__ . '/../includes/db.php';

$sucursalId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($sucursalId <= 0) {
  http_response_code(400);
  die('Falta parámetro id');
}

/* === Cargar datos de sucursal === */
$stmt = $conn->prepare("
  SELECT s.id, s.nom_sucursal, s.lat, s.lng,
         m.nom_municipio AS municipio,
         c.nom_ciudad    AS ciudad,
         r.nom_region    AS estado
  FROM sucursales s
  INNER JOIN municipios m ON s.municipio_id = m.id
  INNER JOIN ciudades   c ON m.ciudad_id    = c.id
  INNER JOIN regiones   r ON c.region_id    = r.id
  WHERE s.id = ?
");

$stmt->bind_param('i', $sucursalId);
$stmt->execute();
$suc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$suc) {
  http_response_code(404);
  die('Sucursal no encontrada');
}

/* === Guardar (POST) === */
$mensaje = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $lat = isset($_POST['lat']) ? trim($_POST['lat']) : '';
  $lng = isset($_POST['lng']) ? trim($_POST['lng']) : '';

  if ($lat === '' || $lng === '') {
    $error = 'Lat y Lng son obligatorios';
  } else if (!is_numeric($lat) || !is_numeric($lng)) {
    $error = 'Lat/Lng deben ser numéricos';
  } else {
    $lat = (float)$lat;
    $lng = (float)$lng;
    $up = $conn->prepare("UPDATE sucursales SET lat=?, lng=? WHERE id=?");
    $up->bind_param('ddi', $lat, $lng, $sucursalId);
    if ($up->execute()) {
      $mensaje = 'Coordenadas actualizadas correctamente.';
      // refrescar datos
      $suc['lat'] = $lat; $suc['lng'] = $lng;
    } else {
      $error = 'No se pudo guardar. Revisa permisos/DB.';
    }
    $up->close();
  }
}

ob_start();
?>
<h2 class="mb-3">Ubicación de sucursal</h2>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <h5 class="mb-1"><?= htmlspecialchars($suc['nom_sucursal']) ?></h5>
        <div class="text-muted">
          <?= htmlspecialchars($suc['municipio']) ?>, <?= htmlspecialchars($suc['ciudad']) ?> · <?= htmlspecialchars($suc['estado']) ?>
        </div>
        <!-- <?php if (!empty($suc['direccion'])): ?>
          <div class="small mt-1">Dirección: <?= htmlspecialchars($suc['direccion']) ?></div>
        <?php endif; ?>
        <?php if (!empty($suc['cp'])): ?>
          <div class="small">CP: <?= htmlspecialchars($suc['cp']) ?></div>
        <?php endif; ?> -->
      </div>
      <div class="col-md-6 text-md-end">
        <a class="btn btn-sm btn-outline-secondary" href="/sisec-ui/views/dispositivos/listar.php?SucursalID=<?= urlencode($suc['id']) ?>">Ver dispositivos</a>
        <a class="btn btn-sm btn-outline-dark" href="javascript:history.back()">← Regresar</a>
      </div>
    </div>
  </div>
</div>

<?php if ($mensaje): ?>
  <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" class="card shadow-sm">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Buscar dirección (Nominatim)</label>
        <div class="input-group">
          <input type="text" id="busqueda" class="form-control" placeholder="Ej: Centro Comercial Toreo, Naucalpan" />
          <button type="button" id="btnBuscar" class="btn btn-outline-primary">Buscar</button>
        </div>
        <div class="form-text">Usa términos claros; agrega municipio y estado.</div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Latitud</label>
        <input type="text" class="form-control" name="lat" id="lat" value="<?= $suc['lat'] !== null ? htmlspecialchars($suc['lat']) : '' ?>" required />
      </div>
      <div class="col-md-3">
        <label class="form-label">Longitud</label>
        <input type="text" class="form-control" name="lng" id="lng" value="<?= $suc['lng'] !== null ? htmlspecialchars($suc['lng']) : '' ?>" required />
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">Guardar</button>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button type="button" id="btnMiUbicacion" class="btn btn-sm btn-outline-secondary">Usar mi ubicación</button>
      <button type="button" id="btnCentrarEstado" class="btn btn-sm btn-outline-secondary">Centrar en estado</button>
    </div>

    <div id="map" class="mt-3" style="height: 460px; border-radius: 10px; overflow: hidden;"></div>
    <div class="form-text mt-2">Tip: Haz clic en el mapa para colocar/mover el marcador. El botón “Guardar” almacena lat/lng en la BD.</div>
  </div>
</form>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Datos PHP → JS
const suc = <?= json_encode($suc, JSON_UNESCAPED_UNICODE) ?>;

// Mapa
const defaultCenter = [19.4326, -99.1332]; // CDMX por defecto
const startLatLng = (suc.lat !== null && suc.lng !== null) ? [parseFloat(suc.lat), parseFloat(suc.lng)] : defaultCenter;
const map = L.map('map').setView(startLatLng, suc.lat ? 16 : 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20, attribution: '&copy; OpenStreetMap' }).addTo(map);

let marker = null;
if (suc.lat && suc.lng) {
  marker = L.marker([suc.lat, suc.lng], { draggable: true }).addTo(map);
  marker.on('dragend', e => {
    const p = e.target.getLatLng();
    document.getElementById('lat').value = p.lat.toFixed(6);
    document.getElementById('lng').value = p.lng.toFixed(6);
  });
}

function setPoint(lat, lng, zoom=17) {
  if (marker) { marker.setLatLng([lat, lng]); }
  else {
    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    marker.on('dragend', e => {
      const p = e.target.getLatLng();
      document.getElementById('lat').value = p.lat.toFixed(6);
      document.getElementById('lng').value = p.lng.toFixed(6);
    });
  }
  map.setView([lat, lng], zoom);
  document.getElementById('lat').value = (+lat).toFixed(6);
  document.getElementById('lng').value = (+lng).toFixed(6);
}

// Clic en el mapa → coloca punto
map.on('click', e => setPoint(e.latlng.lat, e.latlng.lng, map.getZoom() < 16 ? 16 : map.getZoom()));

// Buscar con Nominatim (usa tu proxy para respetar TOS)
async function buscarDireccion(texto) {
  if (!texto) return;
  try {
    const url = '/sisec-ui/api/nominatim_proxy.php?q=' + encodeURIComponent(texto + ' México');
    const r = await fetch(url);
    const j = await r.json();
    if (Array.isArray(j) && j[0]) {
      setPoint(parseFloat(j[0].lat), parseFloat(j[0].lon));
    } else {
      alert('No se encontró la dirección. Intenta con municipio/estado.');
    }
  } catch (e) {
    alert('Error al buscar. Ver consola.');
    console.error(e);
  }
}
document.getElementById('btnBuscar').addEventListener('click', () => {
  const v = document.getElementById('busqueda').value.trim();
  buscarDireccion(v);
});

// Botón: usar mi ubicación
document.getElementById('btnMiUbicacion').addEventListener('click', () => {
  if (!navigator.geolocation) return alert('Tu navegador no soporta geolocalización.');
  navigator.geolocation.getCurrentPosition(
    pos => setPoint(pos.coords.latitude, pos.coords.longitude),
    err => alert('No se pudo obtener ubicación.')
  );
});

// Centrar en estado (aprox: intenta geocodificar el estado/municipio)
document.getElementById('btnCentrarEstado').addEventListener('click', async () => {
  const texto = (suc.municipio ? (suc.municipio + ', ') : '') + suc.estado + ', México';
  await buscarDireccion(texto);
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
