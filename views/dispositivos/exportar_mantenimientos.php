<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin', 'Administrador', 'Mantenimientos']);

require __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../includes/db.php';

use Dompdf\Dompdf;

$ciudad = $_GET['ciudad'] ?? '';
$municipio = $_GET['municipio'] ?? '';
$sucursal = $_GET['sucursal'] ?? '';

// Aquí iría tu consulta, por ahora ejemplo:
$html = "
    <h1>Reporte de Mantenimientos</h1>
    <p>Ciudad: $ciudad</p>
    <p>Municipio: $municipio</p>
    <p>Sucursal: $sucursal</p>
";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("mantenimientos_{$ciudad}_{$municipio}_{$sucursal}.pdf", ["Attachment" => false]);