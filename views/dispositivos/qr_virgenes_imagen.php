<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

// === CONFIGURACIÓN ===
$dir = __DIR__ . '/../../public/qr_virgenes/';
$spacing = 59;
$limit = 5;

// === 1) CARGAR ARCHIVOS PNG ===
$files = glob($dir . '*.png');

if (empty($files)) {
    die("No hay QR disponibles.");
}

// === 2) OBTENER TOKENS RECLAMADOS DE LA BD ===
$sql = "SELECT token FROM qr_pool WHERE dispositivo_id IS NOT NULL";
$res = $conn->query($sql);
$claimedTokens = array_column($res->fetch_all(MYSQLI_ASSOC), 'token');

// === 3) FILTRAR ARCHIVOS RECLAMADOS ===
// Los archivos se llaman token.png → extraemos el nombre sin extensión
$files = array_filter($files, function($file) use ($claimedTokens) {
    $token = basename($file, ".png");
    return !in_array($token, $claimedTokens, true);
});

// Si después de filtrar no queda nada
if (empty($files)) {
    die("No hay QR vírgenes para imprimir.");
}

// === ORDENAR POR FECHA RECIENTE ===
usort($files, function($a, $b) {
    return filemtime($b) <=> filemtime($a);
});

// === LIMITAR ===
$files = array_slice($files, 0, $limit);

// === CARGAR IMÁGENES ===
$qrImages = [];
$totalWidth = 0;
$maxHeight = 0;

foreach ($files as $file) {
    $img = imagecreatefrompng($file);
    if (!$img) continue;

    $w = imagesx($img);
    $h = imagesy($img);

    $qrImages[] = ['img' => $img, 'w' => $w, 'h' => $h];

    $totalWidth += $w + $spacing;

    if ($h > $maxHeight) {
        $maxHeight = $h;
    }
}

if (empty($qrImages)) {
    die("No hay imágenes válidas.");
}

// Quitar el último espacio
$totalWidth -= $spacing;

// === CREAR IMAGEN FINAL ===
$final = imagecreatetruecolor($totalWidth, $maxHeight);

// Fondo blanco
$white = imagecolorallocate($final, 255, 255, 255);
imagefill($final, 0, 0, $white);

// === PEGAR ===
$x = 0;
foreach ($qrImages as $qr) {
    imagecopy($final, $qr['img'], $x, 0, 0, 0, $qr['w'], $qr['h']);
    imagedestroy($qr['img']);
    $x += $qr['w'] + $spacing;
}

// === SALIDA ===
header('Content-Type: image/jpeg');
header('Content-Disposition: attachment; filename="qr_lamina.jpg"');

imagejpeg($final, null, 100);
imagedestroy($final);
exit;
?>