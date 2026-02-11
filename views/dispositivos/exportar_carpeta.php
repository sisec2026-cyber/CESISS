<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Invitado','Técnico','Capturista','Distrital','Prevencion','Monitorista']);

include __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../vendor/autoload.php';
ini_set('memory_limit', '2048M'); // 2 GB de límite
set_time_limit(0); // sin límite de tiempo

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../../public'));
$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');

function imagenBase64Public($rutaRelativa, $anchoMax = 400, $altoMax = 400) {
  if (!$rutaRelativa) return '';
  $rutaCompleta = __DIR__ . '/../../public/' . $rutaRelativa;
  if (!file_exists($rutaCompleta) || !is_file($rutaCompleta)) return '';
  $tipo = strtolower(pathinfo($rutaCompleta, PATHINFO_EXTENSION));
  $data = @file_get_contents($rutaCompleta);
  if (!$data) return '';
  if (in_array($tipo, ['jpg', 'jpeg', 'png', 'gif'])) {
    $img = @imagecreatefromstring($data);
    if ($img) {
      $ancho = imagesx($img);
      $alto = imagesy($img);
      if ($ancho > $anchoMax || $alto > $altoMax) {
        $ratio = min($anchoMax / $ancho, $altoMax / $alto);
        $nuevoAncho = (int)($ancho * $ratio);
        $nuevoAlto = (int)($alto * $ratio);
        $nuevaImg = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        if ($tipo == 'png') {
          imagealphablending($nuevaImg, false);
          imagesavealpha($nuevaImg, true);
          $transparente = imagecolorallocatealpha($nuevaImg, 0, 0, 0, 127);
          imagefilledrectangle($nuevaImg, 0, 0, $nuevoAncho, $nuevoAlto, $transparente);
        }
        imagecopyresampled($nuevaImg, $img, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        ob_start();
        if ($tipo == 'png') imagepng($nuevaImg, null, 7);
        else imagejpeg($nuevaImg, null, 80);
        $data = ob_get_clean();
        imagedestroy($nuevaImg);
      }
      imagedestroy($img);
    }
  }
  return 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}

function imgFile($ruta) {
  if (!$ruta) return '';
  $full = realpath(__DIR__ . '/../../public/' . $ruta);
  return $full ? 'file://' . $full : '';
}

// Variables de filtros
$qsCiudad    = isset($_GET['ciudad_id']) ? (int)$_GET['ciudad_id'] : 0;
$qsMunicipio = isset($_GET['municipio_id']) ? (int)$_GET['municipio_id'] : 0;
$qsSucursal  = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : 0;

// Obtener nombres de sucursal y determinante
$sucursalTxt = $direccionTxt = $determinanteTxt = '';
if ($qsSucursal) {
  // Obtener solo la sucursal y dirección (determinante opcional si existe)
  $q = $conn->prepare("SELECT s.nom_sucursal, s.direccion FROM sucursales s WHERE s.id = ? LIMIT 1");
  if (!$q) die('Error en prepare (sucursal): ' . $conn->error);
  $q->bind_param('i', $qsSucursal);
  $q->execute();
  $row = $q->get_result()->fetch_assoc();
  if ($row) {
    $sucursalTxt  = $row['nom_sucursal'];
    $direccionTxt = $row['direccion'];
  }
  $q->close();

  // Opcional la determinante
  $q2 = $conn->prepare("SELECT det.nom_determinante 
                        FROM dispositivos d 
                        LEFT JOIN determinantes det ON d.determinante = det.id 
                        WHERE d.sucursal = ? LIMIT 1");
  if ($q2) {
    $q2->bind_param('i', $qsSucursal);
    $q2->execute();
    $row2 = $q2->get_result()->fetch_assoc();
    if ($row2) $determinanteTxt = $row2['nom_determinante'];
    $q2->close();
  }
}

// Obtener dispositivos con JOIN para nombres reales
$equipos = [];
$sql = "SELECT 
  d.id,
  det.nom_determinante,
  s.nom_sucursal,
  d.servidor,
  eq.nom_equipo,
  mo.num_modelos,
  d.zona_alarma,
  d.area,
  COALESCE(ma.nom_marca, mad.nom_marca) AS nom_marca,
  d.imagen,
  d.imagen2,
  d.imagen3
  FROM dispositivos d
  LEFT JOIN determinantes det ON d.determinante = det.id
  LEFT JOIN sucursales s ON d.sucursal = s.id
  LEFT JOIN equipos eq ON d.equipo = eq.id
  LEFT JOIN modelos mo ON d.modelo = mo.id
  /* Marca desde modelo */
  LEFT JOIN marcas ma  ON mo.marca_id = ma.id_marcas
  /* Marca directa del dispositivo */
  LEFT JOIN marcas mad ON d.marca_id  = mad.id_marcas
  WHERE d.sucursal = ?
  ORDER BY d.id ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) die('Error en prepare (dispositivos): ' . $conn->error);
$stmt->bind_param('i', $qsSucursal);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
  $equipos[] = ['id'=> $r['id'],
    'determinante'=> $r['nom_determinante'],
    'sucursal'    => $r['nom_sucursal'],
    'equipo'      => $r['nom_equipo'],
    'zona_alarma' => trim($r['zona_alarma'] ?? '') !== '' ? $r['zona_alarma'] : '',
    'area'        => $r['area'] ?? '',
    'marca'       => $r['nom_marca'],
    'modelo'      => $r['num_modelos'],
    'servidor' => trim($r['servidor'] ?? '') !== '' ? $r['servidor'] : '',
    'imagen'      => !empty($r['imagen'])  ? 'uploads/' . $r['imagen']  : '',
    'antes'       => !empty($r['imagen2']) ? 'uploads/' . $r['imagen2'] : '',
    'despues'     => !empty($r['imagen3']) ? 'uploads/' . $r['imagen3'] : '',];
}
$stmt->close();

// Separar CCTV y Alarmas
$cctvKeywords = ['CAM','CÁMARA','CAMARA','PTZ','BULLET','DOME','NVR','DVR','SERVIDOR','SERVER'];
$cctvEquipos = [];
$alarmaEquipos = [];

// Extender clasificación igual que listar.php
$nuevosCCTV = ['UPS', 'MONITOR', 'SERVIDOR', 'SERVER','ESTACIÓN DE TRABAJO', 'ESTACION DE TRABAJO'];
$nuevasAlarma = ['DH', 'DETECTOR DE HUMO','OH', 'OVERHEAD','DRC', 'RUPTURA DE CRISTAL','REP', 'REPETIDORA', 'CM','ESTACIÓN MANUAL', 'ESTACION MANUAL'];

// Unir CCTV clásico + nuevos tipos
$cctvKeywords = array_merge(
  $cctvKeywords,
  $nuevosCCTV
);

foreach ($equipos as $e) {
  $nombre       = strtoupper(trim($e['equipo'] ?? ''));
  $nombreMarca  = strtoupper(trim($e['marca'] ?? ''));
  $nombreZona   = strtoupper(trim($e['zona_alarma'] ?? ''));
  $nombreArea   = strtoupper(trim($e['area'] ?? ''));
  $esCCTV = false;
  $esAlarma = false;
  // CCTV
  foreach ($cctvKeywords as $kw) {
    if (str_contains($nombre, $kw)) {
      $esCCTV = true;
      break;
    }
  }
  // ALARMA
  if (!$esCCTV) {
    foreach ($nuevasAlarma as $kw) {
    if (
      str_contains($nombre, $kw) ||
      str_contains($nombreZona, $kw) ||
      str_contains($nombreArea, $kw)
    ) {
      $esAlarma = true;
      break;
      }
    }
  }
  // Clasificación final
  if ($esCCTV) {
    $cctvEquipos[] = $e;
  } else {
    $alarmaEquipos[] = $e;
  }
  // FALLBACK ABSOLUTO: nada se pierde
  if (!$esCCTV && !$esAlarma) {
    $esCCTV = true;
  }
}

function ordenarCCTV($a, $b) {
  $aNombre = strtoupper($a['equipo'] ?? '');
  $bNombre = strtoupper($b['equipo'] ?? '');
  // Agrupar por servidor (NVR/DVR)
  $aServidor = strtoupper(trim($a['servidor'] ?? ''));
  $bServidor = strtoupper(trim($b['servidor'] ?? ''));
  if ($aServidor !== $bServidor) {
      return strcmp($aServidor, $bServidor);
  }
  // Dentro del mismo servidor: NVR/DVR primero
  $aEsGrabador = preg_match('/\b(NVR|DVR)\b/', $aNombre);
  $bEsGrabador = preg_match('/\b(NVR|DVR)\b/', $bNombre);
  if ($aEsGrabador !== $bEsGrabador) {
    return $aEsGrabador ? -1 : 1;
  }
  // Orden por número (CAM 1, CAM 2, CAM 10)
  preg_match('/(\d+)/', $aNombre, $aNum);
  preg_match('/(\d+)/', $bNombre, $bNum);
  $numA = $aNum[1] ?? PHP_INT_MAX;
  $numB = $bNum[1] ?? PHP_INT_MAX;
  if ($numA != $numB) {
    return $numA <=> $numB;
  }
  // Último desempate
  return strcmp($aNombre, $bNombre);
}
// ORDEN ALFABÉTICO //
usort($cctvEquipos, 'ordenarCCTV');
usort($alarmaEquipos, function ($a, $b) {
  $zonaA = strtoupper(trim($a['zona_alarma'] ?? ''));
  $zonaB = strtoupper(trim($b['zona_alarma'] ?? ''));
  // Extraer número de zona
  preg_match('/(\d+)/', $zonaA, $matchA);
  preg_match('/(\d+)/', $zonaB, $matchB);
  $numZonaA = isset($matchA[1]) ? (int)$matchA[1] : PHP_INT_MAX;
  $numZonaB = isset($matchB[1]) ? (int)$matchB[1] : PHP_INT_MAX;
  // Prioridad absoluta: número de zona
  if ($numZonaA !== $numZonaB) {
    return $numZonaA <=> $numZonaB;
  }
  // Misma zona → ordenar por nombre del dispositivo
  $equipoA = strtoupper($a['equipo'] ?? '');
  $equipoB = strtoupper($b['equipo'] ?? '');
  return strnatcasecmp($equipoA, $equipoB);
});

// Logos en Base64
$logoSB = 'file://' . realpath(__DIR__ . '/../../public/img/sucursales/logoSB.png');
$logoSISEC = 'file://' . realpath(__DIR__ . '/../../public/img/sucursales/logoSISEC.png');
$logoIzquierdo = $logoSB;
$logoDerecho   = $logoSISEC;

// Construir HTML
$meses = [1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
  5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
  9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'];
$mesNumero = (int)date("n");
$anio = date("Y");
$mes = $meses[$mesNumero] . ' ' . $anio;
$html = '
<style>
  @page {margin: 30px;}
  .header {text-align: center;}
  .header table {width: 100%;border-collapse: collapse;}
  .header td {border: none;}
  .header img {height: 65px;}
  table { border-collapse: collapse; width: 100%; border: 2px solid black; }
  td, th { font-size: 15px; border: 1px solid black; text-align: center; }
  th { background-color: #d9d9d9; font-weight: bold; }
  .title-black { background-color: black; color: white; font-size: 15px; text-align: center; }
  .title-gray { background-color: #7f7f7f; color: black; font-size: 15px; text-align: center; }
  .subtitle { background-color: #d9d9d9; text-align: center; }
  /* IMÁGENES DE DISPOSITIVOS */
  .foto-dispositivo {width:175px !important;height:145px !important;object-fit:cover !important;display:block;margin:auto;}
  /* LOGOS */
  .logo {height:65px !important; /* Ajusta si deseas */width:auto !important;object-fit:contain !important;}
  .tabla-logos {width: 100%;border: none !important;}
  .tabla-logos td {border: none !important;}
</style>';

/* CABECERA SOLO SI HAY ALGO */
$html .= '
<div class="header">
  <table>
    <tr>
      <td style="width:30%; text-align:left;">
        <img src="'.$logoSB.'" class="logo">
      </td>
      <td style="width:40%; text-align:center;">
        <img src="'.$logoSISEC.'" class="logo">
      </td>
      <td style="width:30%; text-align:center; font-size:12px;">
        <table style="width:100%; border:1px solid black;">
          <tr><td>'.$mes.'<br>MANTENIMIENTO</td></tr>
        </table>
      </td>
    </tr>
  </table>
  <div class="title-black">'.strtoupper($sucursalTxt).'</div>
  <div class="title-gray">'.strtoupper($direccionTxt).'</div>
</div>';

/* SISTEMA CCTV – SOLO SI HAY DATOS */
if (!empty($cctvEquipos)) {
$html .= '
<div class="subtitle">SISTEMA DE CCTV</div>
<table>
  <tr>
    <th>NOMBRE</th>
    <th>IMAGEN</th>
    <th>ANTES</th>
    <th>DESPUÉS</th>
  </tr>';
foreach ($cctvEquipos as $e) {
  $html .= '<tr>';
  $html .= '<td style="text-align:left;">'
        . '<strong>Dispositivo:</strong> '.htmlspecialchars($e['equipo']).'<br>'
        . '<strong>Marca:</strong> '.htmlspecialchars($e['marca']).'<br>'
        . '<strong>Modelo:</strong> '.htmlspecialchars($e['modelo']).'</td>';
  $html .= '<td>'.($e['imagen']? '<img src="file://' . realpath(__DIR__ . '/../../public/' . $e['imagen']) . '" class="foto-dispositivo">': '').'</td>';
  $html .= '<td>'.($e['antes']? '<img src="file://' . realpath(__DIR__ . '/../../public/' . $e['antes']) . '" class="foto-dispositivo">': '').'</td>';
  $html .= '<td>'.($e['despues']? '<img src="file://' . realpath(__DIR__ . '/../../public/' . $e['despues']) . '" class="foto-dispositivo">': '').'</td>';
  $html .= '</tr>';
}
$html .= '</table>';
}

/* SALTO DE PÁGINA SOLO SI HAY CCTV Y HAY ALARMAS */
if (!empty($cctvEquipos) && !empty($alarmaEquipos)) {
  $html .= '<div style="page-break-before: always;"></div>';
}

/* SISTEMA DE ALARMA – SOLO SI HAY DATOS */
if (!empty($alarmaEquipos)) {
$html .= '
<div class="subtitle">SISTEMA DE ALARMA</div>
<table>
  <tr>
    <th>NOMBRE</th>
    <th>IMAGEN</th>
    <th>ANTES</th>
    <th>DESPUÉS</th>
  </tr>';

foreach ($alarmaEquipos as $e) {
  $html .= '<tr>';
  $html .= '<td style="text-align:left;">'
        . '<strong>Zona:</strong> '.htmlspecialchars($e['zona_alarma']).'<br>'
        . '<strong>Equipo:</strong> '.htmlspecialchars($e['equipo']).'<br>'
        . '<strong>Área:</strong> '.htmlspecialchars($e['area']).'</td>';

  $html .= '<td>'.($e['imagen']? '<img src="file://' . realpath(__DIR__ . '/../../public/' . $e['imagen']) . '" class="foto-dispositivo">': '').'</td>';
  $html .= '<td>'.($e['antes']? '<img src="file://' . realpath(__DIR__ . '/../../public/' . $e['antes']) . '" class="foto-dispositivo">': '').'</td>';
  $html .= '<td>'.($e['despues']? '<img src="file://' . realpath(__DIR__ . '/../../public/' . $e['despues']) . '" class="foto-dispositivo">': '').'</td>';
  $html .= '</tr>';
}
$html .= '</table>';
}

/* PÁGINA FINAL RELACIÓN DE EQUIPO */
$html .= '<div style="page-break-before: always;">';
$html .= '
<h2 style="text-align:center;">RELACIÓN DE EQUIPO</h2>
<table class="tabla-logos">
<tr>
  <td style="text-align:left;"><img src="'.$logoSB.'" class="logo"></td>
  <td style="text-align:right;"><img src="'.$logoSISEC.'" class="logo"></td>
</tr>
</table>
  <p>Nombre del cliente: Suburbia<br>
  Unidad del negocio: '.htmlspecialchars($sucursalTxt).'<br>
  Dirección: '.htmlspecialchars($direccionTxt).'<br>
  Tipo de sistema: CCTV</p>
<table>
<tr>
  <th>TIPO DE EQUIPO</th>
  <th># CÁM</th>
  <th>NOMBRE</th>
  <th>MARCA</th>
  <th>MODELO</th>
</tr>';

foreach ($cctvEquipos as $e) {
  $html .= '<tr>';
  $html .= '<td>'.htmlspecialchars($e['equipo']).'</td>';
  $html .= '<td>'.htmlspecialchars($e['servidor']).'</td>';
  $html .= '<td>'.htmlspecialchars($e['area']).'</td>';
  $html .= '<td>'.htmlspecialchars($e['marca']).'</td>';
  $html .= '<td>'.htmlspecialchars($e['modelo']).'</td>';
  $html .= '</tr>';
}

$html .= '</table></div>';

// Generar PDF
$dompdf->loadHtml($html);
$dompdf->render();
$nombreArchivo = ($determinanteTxt ?: 'Determinante') . ' ' . ($sucursalTxt ?: 'Sucursal') . '_Carpeta de entrega.pdf';
$dompdf->stream($nombreArchivo, ["Attachment" => true]);
exit;