<?php
// /sisec-ui/views/dispositivos/qr_png.php
declare(strict_types=1);

// Si ya tienes phpqrcode incluida globalmente, perfecto.
// Si no, puedes require_once 'phpqrcode/qrlib.php';

$text = $_GET['text'] ?? '';
$size = max(120, min(1000, (int)($_GET['s'] ?? 220)));

if ($text === '') {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'missing text';
  exit;
}

if (class_exists('QRcode')) {
  // phpqrcode disponible
  header('Content-Type: image/png');
  // scale aproximado: 37px por módulo a tamaño ~220
  QRcode::png($text, false, QR_ECLEVEL_M, (int)max(1, round($size / 37)));
  exit;
}

// Fallback simple: proxyear imagen desde api.qrserver.com (útil en desarrollo)
$u = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode($text);
header('Content-Type: image/png');
readfile($u);
