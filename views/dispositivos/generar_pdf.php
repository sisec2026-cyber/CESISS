<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Capturista','Técnico', 'Distrital','Prevencion','Mantenimientos','Monitorista']);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido.');
}

$id = (int)$_GET['id'];

// Consultar el dispositivo
$stmt = $conn->prepare("SELECT * FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$device = $result->fetch_assoc();

if (!$device) {
    die('Dispositivo no encontrado.');
}

// Rutas absolutas de imágenes
$rootPath = realpath(__DIR__ . '/../../public');

$pathImagen = $rootPath . '/uploads/' . $device['imagen'];
$pathImagen2 = $rootPath . '/uploads/' . $device['imagen2'];
$pathQR = $rootPath . '/qrcodes/' . $device['qr'];

$imgSrc1 = (file_exists($pathImagen))  ? 'file://' . $pathImagen : '';
$imgSrc2 = (file_exists($pathImagen2)) ? 'file://' . $pathImagen2 : '';
$imgQR   = (file_exists($pathQR))      ? 'file://' . $pathQR : '';

// Construir HTML para el PDF
$html = '
    <h2 style="text-align: center;">Ficha técnica</h2>
    <table border="1" cellspacing="0" cellpadding="8" width="100%">
      <tr>
        <td width="40%" align="center">
            ' . ($imgSrc1 ? '<img src="' . $imgSrc1 . '" style="max-height: 250px;">' : '<div style="color: red;">Sin imagen</div>') . '
        </td>
        <td width="60%">
            <table width="100%" cellspacing="0" cellpadding="5">
              <tr><td><strong>Equipo:</strong></td><td>' . htmlspecialchars($device['equipo']) . '</td></tr>
              <tr><td><strong>Fecha de instalación:</strong></td><td>' . htmlspecialchars($device['fecha']) . '</td></tr>
              <tr><td><strong>Modelo:</strong></td><td>' . htmlspecialchars($device['modelo']) . '</td></tr>
              <tr><td><strong>Estado del equipo:</strong></td><td>' . htmlspecialchars($device['estado']) . '</td></tr>
              <tr><td><strong>Ubicación del equipo:</strong></td><td>' . htmlspecialchars($device['sucursal']) . '</td></tr>
              <tr><td><strong>Observaciones:</strong></td><td>' . nl2br(htmlspecialchars($device['observaciones'])) . '</td></tr>
              <tr><td><strong>Serie:</strong></td><td>' . htmlspecialchars($device['serie']) . '</td></tr>
              <tr><td><strong>Dirección MAC:</strong></td><td>' . htmlspecialchars($device['mac']) . '</td></tr>
              <tr><td><strong>VMS:</strong></td><td>' . htmlspecialchars($device['vms']) . '</td></tr>
              <tr><td><strong>Servidor:</strong></td><td>' . htmlspecialchars($device['servidor']) . '</td></tr>
              <tr><td><strong>Switch:</strong></td><td>' . htmlspecialchars($device['switch']) . '</td></tr>
              <tr><td><strong>Puerto:</strong></td><td>' . htmlspecialchars($device['puerto']) . '</td></tr>
              <tr><td><strong>Área de la tienda:</strong></td><td>' . htmlspecialchars($device['area']) . '</td></tr>
            </table>
        </td>
      </tr>
      <tr>
        <td colspan="2"><strong>Imagen adjunta:</strong><br>' . ($imgSrc2 ? '<img src="' . $imgSrc2 . '" style="max-height: 200px;">' : 'No disponible') . '</td>
      </tr>
      <tr>
        <td colspan="2"><strong>Código QR:</strong><br>' . ($imgQR ? '<img src="' . $imgQR . '" style="max-height: 200px;">' : 'No disponible') . '</td>
      </tr>
    </table>
';

// Crear y mostrar el PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("ficha_dispositivo_{$id}.pdf", ["Attachment" => false]); // true para forzar descarga
exit;
