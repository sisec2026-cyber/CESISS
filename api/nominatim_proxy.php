<?php
// /sisec-ui/api/nominatim_proxy.php
header('Content-Type: application/json; charset=UTF-8');

// (Opcional) Seguridad:
session_start();
if (php_sapi_name() !== 'cli') {
  if (!isset($_SESSION['usuario_rol'])) { http_response_code(403); exit(json_encode(['error'=>'auth'])); }
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
  http_response_code(400);
  echo json_encode(['error'=>'missing q']);
  exit;
}

$cacheDir = __DIR__ . '/cache_nominatim';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }
$key = sha1(mb_strtolower($q, 'UTF-8'));
$file = "$cacheDir/$key.json";
$ttl  = 604800; // 7 días

if (is_file($file) && (time() - filemtime($file) < $ttl)) {
  readfile($file);
  exit;
}

$email = 'tucorreo@dominio.com'; // pon tu correo real (TOS de Nominatim)
$url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=mx&addressdetails=0&email=".
       urlencode($email)."&q=".urlencode($q);

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT        => 20,
  CURLOPT_USERAGENT      => "CESISS-Geocoder/1.0 ($email)"
]);
$raw = curl_exec($ch);
$errno = curl_errno($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno || $http !== 200 || !$raw) {
  http_response_code(502);
  echo json_encode(['error'=>'nominatim_failed','errno'=>$errno,'http'=>$http]);
  exit;
}

file_put_contents($file, $raw);
echo $raw;
