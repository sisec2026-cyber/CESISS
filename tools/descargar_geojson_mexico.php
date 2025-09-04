<?php
/**
 * /sisec-ui/tools/descargar_geojson_mexico.php
 * Descarga un GeoJSON de ESTADOS de México y lo guarda como:
 *   /sisec-ui/assets/geo/mexico_estados.geojson
 *
 * Ejecútalo con sesión de Admin.
 */
session_start();
if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador'])) {
  http_response_code(403);
  die('Acceso no autorizado');
}

$destDir = realpath(__DIR__ . '/../assets/geo');
if ($destDir === false) {
  @mkdir(__DIR__ . '/../assets/geo', 0775, true);
  $destDir = realpath(__DIR__ . '/../assets/geo');
}
if ($destDir === false) {
  http_response_code(500);
  die('No pude crear la carpeta /assets/geo');
}

$destFile = $destDir . DIRECTORY_SEPARATOR . 'mexico_estados.geojson';

/** Fuentes (intenta en orden hasta que una funcione) */
$sources = [
  // 1) Estados (32) — ligero
  'https://raw.githubusercontent.com/angelnmara/geojson/master/mexicoHigh.json', // :contentReference[oaicite:3]{index=3}
  // 2) Estados (32) — ligero
  'https://raw.githubusercontent.com/strotgen/mexico-leaflet/master/states.geojson', // :contentReference[oaicite:4]{index=4}
  // 3) Estados (ADM1) Opendatasoft (INEGI simplificado)
  // nota: este endpoint devuelve GeoJSON de estados
  'https://data.opendatasoft.com/api/explore/v2.1/catalog/datasets/georef-mexico-state-millesime%40public/exports/geojson?lang=es&timezone=America%2FMexico_City' // :contentReference[oaicite:5]{index=5}
];

function fetch_url($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'CESISS-GeoJSONFetcher/1.1',
  ]);
  $data = curl_exec($ch);
  $errno = curl_errno($ch);
  $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$data, $errno, $http];
}

function validar_estados($json) {
  // Acepta FeatureCollection con >= 30 features y con nombre de estado en alguna prop común.
  if (!is_array($json)) return "JSON inválido";
  if (($json['type'] ?? '') !== 'FeatureCollection') return "No es FeatureCollection";
  if (!isset($json['features']) || !is_array($json['features'])) return "Sin 'features'";
  if (count($json['features']) < 30) return "Muy pocas features (esperados 32 estados)";

  // Chequeo suave de nombres
  $okNombre = 0;
  foreach ($json['features'] as $f) {
    $p = $f['properties'] ?? [];
    if (isset($p['name']) || isset($p['NOMGEO']) || isset($p['estado']) || isset($p['state_name'])) {
      $okNombre++; 
    }
  }
  if ($okNombre === 0) return "No encontré nombres de estado en properties";
  return true;
}

$lastErr = "Sin intento";
foreach ($sources as $url) {
  [$data, $errno, $http] = fetch_url($url);
  if ($errno || $http !== 200 || !$data) {
    $lastErr = "errno=$errno http=$http url=$url";
    continue;
  }
  $json = json_decode($data, true);
  if (!$json) {
    $lastErr = "JSON inválido en url=$url";
    continue;
  }
  $val = validar_estados($json);
  if ($val === true) {
    // Normaliza para asegurar propiedad 'estado'
    foreach ($json['features'] as &$f) {
      if (!isset($f['properties']) || !is_array($f['properties'])) $f['properties'] = [];
      $p = &$f['properties'];
      $nombre = $p['estado'] ?? $p['name'] ?? $p['NOMGEO'] ?? $p['state_name'] ?? null;
      if ($nombre === null) $nombre = 'Estado';
      $p['estado'] = $nombre;
    }
    unset($f);

    if (file_put_contents($destFile, json_encode($json, JSON_UNESCAPED_UNICODE)) === false) {
      http_response_code(500);
      die("No pude guardar el archivo en: $destFile");
    }
    echo "Listo ✅ Guardado: $destFile (" . filesize($destFile) . " bytes)\nFuente: $url";
    exit;
  } else {
    $lastErr = "Validación falló: $val url=$url";
  }
}

http_response_code(502);
echo "No se pudo descargar/validar ninguna fuente. Último error: $lastErr";
