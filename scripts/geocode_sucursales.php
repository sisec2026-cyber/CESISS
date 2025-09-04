<?php
/**
 * /sisec-ui/scripts/geocode_sucursales.php
 * Geocodifica sucursales SIN lat/lng usando Nominatim (OSM) y guarda resultados.
 * - 1 req/seg
 * - Caché (geocoding_cache)
 * - Query builder inteligente (estructurado + heurísticas CDMX/malls/aeropuertos)
 *
 * Ejecutar:
 *   WEB (admin): /sisec-ui/scripts/geocode_sucursales.php?max=200&dry=0&q=Texto
 *   CLI:        php geocode_sucursales.php max=200 dry=0 q=Texto
 */

if (php_sapi_name() === 'cli') {
  foreach ($argv ?? [] as $i => $arg) {
    if ($i === 0) continue;
    if (strpos($arg, '=') !== false) { [$k, $v] = explode('=', $arg, 2); $_GET[$k] = $v; }
  }
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (php_sapi_name() !== 'cli') {
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  verificarAutenticacion();
  verificarRol(['Superadmin','Administrador']);
}

/* ===== CONFIG ===== */
$NOMINATIM_EMAIL = getenv('NOMINATIM_EMAIL') ?: 'tu_correo@dominio.com'; // pon uno real
$USER_AGENT      = "CESISS-Geocoder/1.2 ($NOMINATIM_EMAIL)";
$SLEEP_SECONDS   = 1; // 1 req/s

$MAX_REGISTROS = isset($_GET['max']) ? (int)$_GET['max'] : 200;
if ($MAX_REGISTROS < 1) $MAX_REGISTROS = 1;
$DRY_RUN  = (isset($_GET['dry']) && (int)$_GET['dry'] === 1);
$FILTRO_Q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

function out($m){ echo date('[H:i:s] ') . $m . PHP_EOL; }

/* ===== Asegura tabla de caché ===== */
$conn->query("
  CREATE TABLE IF NOT EXISTS geocoding_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query VARCHAR(255) UNIQUE,
    lat DECIMAL(9,6),
    lng DECIMAL(9,6),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )
");

/* ===== Caché helpers ===== */
function cache_get(mysqli $conn, string $q) {
  try {
    $stmt = $conn->prepare("SELECT lat,lng FROM geocoding_cache WHERE query=?");
    if (!$stmt) return null;
    $stmt->bind_param('s', $q);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ?: null;
  } catch (Throwable $e) { return null; }
}
function cache_put(mysqli $conn, string $q, ?float $lat, ?float $lng) {
  try {
    $stmt = $conn->prepare("
      INSERT INTO geocoding_cache (query, lat, lng)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng)
    ");
    if (!$stmt) return;
    $stmt->bind_param('sdd', $q, $lat, $lng);
    $stmt->execute(); $stmt->close();
  } catch (Throwable $e) { /* ignore */ }
}

/* ===== Utilidades de normalización ===== */

// Alcaldías de CDMX (para forzar state="Ciudad de México")
$ALCALDIAS_CDMX = [
  'Álvaro Obregón','Alvaro Obregón','Alvaro Obregon','Álvaro Obregon',
  'Azcapotzalco',
  'Benito Juárez','Benito Juarez',
  'Coyoacán','Coyoacan',
  'Cuajimalpa de Morelos','Cuajimalpa',
  'Cuauhtémoc','Cuauhtemoc',
  'Gustavo A. Madero','Gustavo A Madero','Gustavo A.  Madero','Gustavo Madero',
  'Iztacalco',
  'Iztapalapa',
  'La Magdalena Contreras','Magdalena Contreras',
  'Miguel Hidalgo',
  'Milpa Alta',
  'Tláhuac','Tlahuac',
  'Tlalpan',
  'Venustiano Carranza',
  'Xochimilco'
];

function quitar_prefijos_marca(string $s): string {
  // quita prefijos comunes "SB", "S/B", etc.
  $s = preg_replace('/^\s*(SB|S\/B|S\.B\.)\s+/i', '', $s);
  return trim($s);
}
function corregir_typos(string $s): string {
  // casos reales del log
  $s = str_ireplace('LINDVISTA', 'LINDAVISTA', $s);
  return $s;
}
function expandir_alias_lugar(string $s): string {
  // lugares icónicos que Nominatim entiende mejor con el nombre completo
  $map = [
    'TOREO' => 'Centro Comercial Toreo',
    'PARQUE VIA VALLEJO' => 'Parque Via Vallejo',
    'AEROPUERTO' => 'Aeropuerto Internacional de la Ciudad de México',
    'SAN JERONIMO' => 'San Jerónimo',
    'MIGUEL ANGEL DE QUEVEDO' => 'Miguel Ángel de Quevedo',
    'MACROPLAZA HEROES OZUMBILLA' => 'Macroplaza Héroes Ozumbilla',
  ];
  foreach ($map as $k => $v) {
    // si la sucursal contiene ese token, reemplaza todo el nombre por el expandido
    if (stripos($s, $k) !== false) { return $v; }
  }
  return $s;
}
function is_alcaldia_cdmx(string $muni): bool {
  global $ALCALDIAS_CDMX;
  foreach ($ALCALDIAS_CDMX as $a) {
    if (mb_strtolower($a, 'UTF-8') === mb_strtolower($muni, 'UTF-8')) return true;
  }
  return false;
}

/* ===== Geocoder (intentos múltiples) ===== */

function http_get_json(string $url, string $UA) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT      => $UA,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
  ]);
  $raw  = curl_exec($ch);
  $errno= curl_errno($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($errno || $http !== 200 || !$raw) return null;
  $j = json_decode($raw, true);
  return $j;
}

/**
 * Intenta 1) búsqueda estructurada, 2) estructurada alterna, 3) libre focalizada, 4) libre genérica
 */
function geocode_inteligente(array $row, string $UA, string $email): array {
  $pais = 'México';

  // Limpia y enriquece el nombre de sucursal
  $sucursal = expandir_alias_lugar(corregir_typos(quitar_prefijos_marca($row['nom_sucursal'] ?? '')));
  $municipio = trim($row['municipio'] ?? '');
  $estado    = trim($row['estado'] ?? '');

  // Heurística CDMX: si municipio es una alcaldía, forzar state="Ciudad de México"
  $es_cdmx = false;
  if (is_alcaldia_cdmx($municipio) || preg_match('/ciudad\s*de\s*m[eé]xico/i', $estado)) {
    $estado  = 'Ciudad de México';
    $es_cdmx = true;
  }

  // 1) Estructurado
  // search?street,city,county,state,country
  $q1 = [
    'street'  => $sucursal,            // ej. "Centro Comercial Toreo"
    'city'    => $es_cdmx ? null : $municipio, // a veces Nominatim prefiere county en lugar de city
    'county'  => $municipio,           // en MX suele mapear alcaldía/municipio
    'state'   => $estado,
    'country' => $pais
  ];
  $url1 = "https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=0&".
          "email=".urlencode($email).
          "&street=".urlencode((string)$q1['street']).
          ($q1['city'] ? "&city=".urlencode($q1['city']) : "").
          "&county=".urlencode($q1['county']).
          "&state=".urlencode($q1['state']).
          "&country=".urlencode($q1['country']);

  $j = http_get_json($url1, $UA);
  if (is_array($j) && !empty($j[0])) {
    return ['ok'=>true,'lat'=>(float)$j[0]['lat'],'lng'=>(float)$j[0]['lon'],'pass'=>1,'url'=>$url1];
  }

  // 2) Estructurado alterno (city priorizado, sin county)
  $url2 = "https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=0&".
          "email=".urlencode($email).
          "&street=".urlencode($sucursal).
          "&city=".urlencode($municipio).
          "&state=".urlencode($estado).
          "&country=".urlencode($pais);

  $j = http_get_json($url2, $UA);
  if (is_array($j) && !empty($j[0])) {
    return ['ok'=>true,'lat'=>(float)$j[0]['lat'],'lng'=>(float)$j[0]['lon'],'pass'=>2,'url'=>$url2];
  }

  // 3) Libre focalizada (usa viewbox para CDMX y bounded=1)
  $free = $sucursal.' '.$municipio.' '.$estado.' '.$pais;
  $url3 = "https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=0&".
          "email=".urlencode($email).
          "&q=".urlencode($free);
  if ($es_cdmx) {
    // bbox aprox CDMX: oeste, sur, este, norte (lon1, lat1, lon2, lat2)
    $viewbox = "-99.364,19.006,-98.940,19.592";
    $url3 .= "&viewbox=".$viewbox."&bounded=1";
  }
  $j = http_get_json($url3, $UA);
  if (is_array($j) && !empty($j[0])) {
    return ['ok'=>true,'lat'=>(float)$j[0]['lat'],'lng'=>(float)$j[0]['lon'],'pass'=>3,'url'=>$url3];
  }

  // 4) Libre genérica (por si el nombre de sucursal solo no funciona)
  $free2 = $sucursal.' '.$estado.' '.$pais;
  $url4 = "https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=0&".
          "email=".urlencode($email).
          "&q=".urlencode($free2);
  $j = http_get_json($url4, $UA);
  if (is_array($j) && !empty($j[0])) {
    return ['ok'=>true,'lat'=>(float)$j[0]['lat'],'lng'=>(float)$j[0]['lon'],'pass'=>4,'url'=>$url4];
  }

  return ['ok'=>false,'err'=>'not_found','lat'=>null,'lng'=>null,'debug'=>[
    'sucursal'=>$sucursal,'municipio'=>$municipio,'estado'=>$estado,'cdmx'=>$es_cdmx
  ]];
}

/* ===== SELECT de sucursales sin lat/lng ===== */
$sql = "
  SELECT 
    s.id,
    s.nom_sucursal,
    m.nom_municipio AS municipio,
    c.nom_ciudad    AS ciudad,
    r.nom_region    AS estado
  FROM sucursales s
  INNER JOIN municipios m ON s.municipio_id = m.id
  INNER JOIN ciudades   c ON m.ciudad_id    = c.id
  INNER JOIN regiones   r ON c.region_id    = r.id
  WHERE (s.lat IS NULL OR s.lng IS NULL)
";
if ($FILTRO_Q !== '') {
  $sql .= " AND s.nom_sucursal LIKE ? ";
}
$sql .= " ORDER BY s.id ASC LIMIT ".(int)$MAX_REGISTROS;

if ($FILTRO_Q !== '') {
  $stmt = $conn->prepare($sql);
  if (!$stmt) { http_response_code(500); die("Error al preparar SELECT"); }
  $like = "%$FILTRO_Q%";
  $stmt->bind_param('s', $like);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $conn->query($sql);
  if (!$res) { http_response_code(500); die("Error en SELECT"); }
}

$procesados=0; $actualizados=0; $saltados=0;

out("Inicio geocodificación | max={$MAX_REGISTROS} | dry=" . ($DRY_RUN ? '1':'0') . " | filtro='{$FILTRO_Q}'");

while ($row = $res->fetch_assoc()) {
  $procesados++;
  $id = (int)$row['id'];

  // Construye un "query id" estable para caché (ya con normalizaciones aplicadas en geocode_inteligente)
  $query_id = trim(($row['nom_sucursal'] ?? '').'|'.($row['municipio'] ?? '').'|'.($row['estado'] ?? ''));

  if ($cached = cache_get($conn, $query_id)) {
    if ($cached['lat'] !== null && $cached['lng'] !== null) {
      if (!$DRY_RUN) {
        $up = $conn->prepare("UPDATE sucursales SET lat=?, lng=? WHERE id=?");
        if ($up) { $up->bind_param('ddi', $cached['lat'], $cached['lng'], $id); $up->execute(); $up->close(); }
      }
      $actualizados++;
      out("✓(cache) [{$id}] {$query_id} -> {$cached['lat']}, {$cached['lng']}");
      continue;
    } else {
      $saltados++;
      out("×(cache:miss) [{$id}] {$query_id} -> sin resultado previo");
      continue;
    }
  }

  // Geocodifica con intentos
  out("… [{$id}] geocoding: {$row['nom_sucursal']} {$row['municipio']} {$row['estado']} México");
  $resp = geocode_inteligente($row, $USER_AGENT, $NOMINATIM_EMAIL);

  sleep($SLEEP_SECONDS); // rate-limit

  if ($resp['ok']) {
    cache_put($conn, $query_id, $resp['lat'], $resp['lng']);
    if (!$DRY_RUN) {
      $up = $conn->prepare("UPDATE sucursales SET lat=?, lng=? WHERE id=?");
      if ($up) { $up->bind_param('ddi', $resp['lat'], $resp['lng'], $id); $up->execute(); $up->close(); }
    }
    $actualizados++;
    out("✓ [{$id}] pass={$resp['pass']} -> {$resp['lat']}, {$resp['lng']}");
  } else {
    cache_put($conn, $query_id, null, null);
    $saltados++;
    out("× [{$id}] not_found ".json_encode($resp['debug'], JSON_UNESCAPED_UNICODE));
  }
}

out("Fin. Procesados: {$procesados} | Actualizados: {$actualizados} | Sin resultado: {$saltados}");
