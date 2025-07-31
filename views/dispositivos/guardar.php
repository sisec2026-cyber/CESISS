<?php

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos']);

include __DIR__ . '/../../includes/db.php';
include __DIR__ . '/../../vendor/phpqrcode/qrlib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipo         = $_POST['equipo'];
    $fecha          = $_POST['fecha'];
    $modelo         = $_POST['modelo'];
    $estado         = $_POST['estado'];
    $sucursal       = $_POST['sucursal'];
    $observaciones  = $_POST['observaciones'];
    $serie          = $_POST['serie'];
    $mac            = $_POST['mac'];
    $vms            = $_POST['vms'];
    $servidor       = $_POST['servidor'];
    $switch         = $_POST['switch'];
    $puerto         = $_POST['puerto'];
    $area           = $_POST['area'];


    // Subir imágenes (imagen, imagen2, imagen3)
    $imagenes = [];
    $nombresEsperados = ['imagen', 'imagen2', 'imagen3'];

    foreach ($nombresEsperados as $index => $campo) {
        if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES[$campo]['tmp_name'];
            $extension = pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION);
            $nombreFinal = uniqid("img" . ($index + 1) . "_") . "." . $extension;
            $rutaDestino = __DIR__ . "/../../public/uploads/" . $nombreFinal;

            if (move_uploaded_file($tmpName, $rutaDestino)) {
                $imagenes[$campo] = $nombreFinal;
            } else {
                $imagenes[$campo] = null;
            }
        } else {
            $imagenes[$campo] = null;
        }
    }

    // Insertar en la base de datos
$stmt = $conn->prepare("
    INSERT INTO dispositivos 
    (equipo, fecha, modelo, estado, sucursal, observaciones, serie, mac, vms, servidor, switch, puerto, area, imagen, imagen2, imagen3)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");


$stmt->bind_param(
    "ssssssssssssssss",
    $equipo, $fecha, $modelo, $estado, $sucursal, $observaciones,
    $serie, $mac, $vms, $servidor, $switch, $puerto, $area,
    $imagenes['imagen'], $imagenes['imagen2'], $imagenes['imagen3']
);


    if (!$stmt->execute()) {
        die("Error al insertar dispositivo: " . $stmt->error);
    }

    $id = $stmt->insert_id;

    // Registrar notificación si no es admin
    if ($_SESSION['usuario_rol'] !== 'Administrador') {
        $mensaje = "El Mantenimientos " . $_SESSION['nombre'] . " registró un nuevo dispositivo.";
        $usuario_id = $_SESSION['usuario_id'];

        $stmtNotif = $conn->prepare("
            INSERT INTO notificaciones (usuario_id, mensaje, fecha, visto, dispositivo_id)
            VALUES (?, ?, NOW(), 0, ?)
        ");
        $stmtNotif->bind_param("isi", $usuario_id, $mensaje, $id);
        $stmtNotif->execute();
        $stmtNotif->close();
    }

    // Generar QR
    $qr_filename = 'qr_' . $id . '.png';
    $qr_path = __DIR__ . '/../../public/qrcodes/' . $qr_filename;
    $qr_url = 'http://localhost/sisec-ui/views/dispositivos/device.php?id=' . $id;

    QRcode::png($qr_url, $qr_path, QR_ECLEVEL_H, 10);

    // Guardar QR en DB
    $conn->query("UPDATE dispositivos SET qr = '$qr_filename' WHERE id = $id");

    header("Location: device.php?id=" . $id);
    exit;
}

