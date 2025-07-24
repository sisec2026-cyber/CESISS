<?php

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos']);

include __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso no autorizado.');
}

$id            = (int)$_POST['id'];
$equipo        = $_POST['equipo'];
$modelo        = $_POST['modelo'];
$serie         = $_POST['serie'] ?? '';
$mac           = $_POST['mac'] ?? '';
$servidor      = $_POST['servidor'] ?? '';
$vms           = $_POST['vms'] ?? '';
$vms_otro      = $_POST['vms_otro'] ?? '';
$switch        = $_POST['switch'] ?? '';
$puerto        = $_POST['puerto'] ?? '';
$sucursal      = $_POST['sucursal'];
$area          = $_POST['area'] ?? '';
$estado        = $_POST['estado'];
$fecha         = $_POST['fecha'];
$observaciones = $_POST['observaciones'] ?? '';

// Si seleccionó "Otro", usar el valor personalizado
if ($vms === 'Otro' && !empty($vms_otro)) {
    $vms = $vms_otro;
}

// Obtener datos actuales
$stmt = $conn->prepare("SELECT imagen, imagen2, imagen3, qr FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$actual = $result->fetch_assoc();
$stmt->close();

// Manejo de imágenes
$imagen = $actual['imagen'];
$imagen2 = $actual['imagen2'];
$imagen3 = $actual['imagen3'];
$qr      = $actual['qr'];

$uploadDir = __DIR__ . "/../../public/uploads/";
$qrDir     = __DIR__ . "/../../public/qrcodes/";

// Imagen 1 (principal)
if (!empty($_FILES['imagen']['name'])) {
    $imagen = basename($_FILES['imagen']['name']);
    move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $imagen);
}

// Imagen 2 (antes)
if (!empty($_FILES['imagen2']['name'])) {
    $imagen2 = basename($_FILES['imagen2']['name']);
    move_uploaded_file($_FILES['imagen2']['tmp_name'], $uploadDir . $imagen2);
}

// Imagen 3 (después)
if (!empty($_FILES['imagen3']['name'])) {
    $imagen3 = basename($_FILES['imagen3']['name']);
    move_uploaded_file($_FILES['imagen3']['tmp_name'], $uploadDir . $imagen3);
}

// QR
if (!empty($_FILES['qr']['name'])) {
    $qr = basename($_FILES['qr']['name']);
    move_uploaded_file($_FILES['qr']['tmp_name'], $qrDir . $qr);
}

// Actualizar base de datos
$update = $conn->prepare("
    UPDATE dispositivos 
    SET equipo=?, modelo=?, serie=?, mac=?, servidor=?, vms=?, switch=?, puerto=?, 
        sucursal=?, area=?, estado=?, fecha=?, observaciones=?, 
        imagen=?, imagen2=?, imagen3=?, qr=? 
    WHERE id=?
");
$update->bind_param(
    "sssssssssssssssssi",
    $equipo, $modelo, $serie, $mac, $servidor, $vms, $switch, $puerto,
    $sucursal, $area, $estado, $fecha, $observaciones,
    $imagen, $imagen2, $imagen3, $qr, $id
);
$update->execute();
$update->close();

// Notificación para Mantenimientoss
if ($_SESSION['usuario_rol'] !== 'Administrador') {
    $mensaje     = "El Mantenimientos " . $_SESSION['nombre'] . " modificó el dispositivo con ID #" . $id . ".";
    $usuario_id  = $_SESSION['usuario_id'];

    $stmtNotif = $conn->prepare("
        INSERT INTO notificaciones (usuario_id, mensaje, fecha, visto, dispositivo_id) 
        VALUES (?, ?, NOW(), 0, ?)
    ");
    $stmtNotif->bind_param("isi", $usuario_id, $mensaje, $id);
    $stmtNotif->execute();
    $stmtNotif->close();
}

// Redirigir
header("Location: device.php?id=$id");
exit;
