<?php
// /sisec-ui/api/overpass_proxy.php
header('Content-Type: application/json; charset=UTF-8');

// (Opcional) Seguridad básica si se sirve por web:
session_start();
if (php_sapi_name() !== 'cli') {
  // Requiere login y rol (ajusta a tus necesidades)
  if (!isset($_SESSION['usuario_rol'])) { http_response_code(403); exit(json_encode(['error'=>'auth'])); }
}

if (!isset($_GET['q'])) {
  http_response_code(400);
  echo json_encode(['error'=>'missing q']);
  exit;
}
$q = base64_decode($_GET['q'], true);
if ($q === false || strlen($q) < 10) {
  http_response_code(400);
  echo json_encode(['error'=>'bad query']);
  exit;
}

$cacheDir = __DIR__ . '/cache_overpass';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }
$key = sha1($q);
$file = "$cacheDir/$key.json";
$ttl  = 86400; // 1 día

if (is_file($file) && (time() - filemtime($file) < $ttl)) {
  readfile($file);
  exit;
}

$url = 'https://overpass-api.de/api/interpreter';
$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => ['data' => $q],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CONNECTTIMEOUT => 15,
  CURLOPT_TIMEOUT        => 60,
  CURLOPT_USERAGENT      => 'CESISS-OverpassProxy/1.0'
]);
$raw = curl_exec($ch);
$errno = curl_errno($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno || $http !== 200 || !$raw) {
  http_response_code(502);
  echo json_encode(['error'=>'overpass_failed','errno'=>$errno,'http'=>$http]);
  exit;
}

file_put_contents($file, $raw);
echo $raw;
