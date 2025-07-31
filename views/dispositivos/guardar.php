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
    $user           = $_POST['user'];
    $pass           = $_POST['pass'];

    // EQUIPO
    $equipo = $_POST['equipo'];
    // Busca si el equipo ya existe
    $stmt = $conn->prepare("SELECT id FROM equipos WHERE nom_equipo = ?");
    $stmt->bind_param("s", $equipo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $equipo = $row['id']; // ahora $equipo es el ID
    } else {
        // Inserta nuevo equipo si no existe
        $stmt = $conn->prepare("INSERT INTO equipos (nom_equipo) VALUES (?)");
        $stmt->bind_param("s", $equipo);
        $stmt->execute();
        $equipo = $stmt->insert_id; // ahora $equipo es el ID insertado
    }
    
    // SUCURSAL
    // Guarda el nombre
    $sucursal = $_POST['sucursal'];
    $municipio = $_POST['municipio'];
    // Esto busca o inserta en la TABLA SUCURSALES
    $stmt = $conn->prepare("SELECT id FROM sucursales WHERE nom_sucursal = ?");
    $stmt->bind_param("s", $sucursal);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $sucursal = $row['id']; // REUTILIZA la variable $sucursal, pero ahora como ID
    } else {
    $stmt = $conn->prepare("INSERT INTO sucursales (nom_sucursal, municipio_id) VALUES (?, ?)");
    $stmt->bind_param("si", $sucursal, $municipio);
    $stmt->execute();
    $sucursal = $stmt->insert_id; // ahora es el ID generado
    }

    //STATUS
    // Recibe el status
    $estado = $_POST['estado'];
    // Busca si el estado ya existe en la tabla `status`
    $stmt = $conn->prepare("SELECT id FROM status WHERE status_equipo = ?");
    $stmt->bind_param("s", $estado);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        // Si ya existe, obtenemos el ID
        $row = $result->fetch_assoc();
        $estado = $row['id']; // Reutilizam $estado ahora como ID
    } else {
        // Si no existe, lo inserta
        $stmt = $conn->prepare("INSERT INTO status (status_equipo) VALUES (?)");
        $stmt->bind_param("s", $estado);
        $stmt->execute();
        $estado = $stmt->insert_id; // ahora $estado es el ID insertado
    }

    //MODELO
    $modelo = $_POST['modelo'];
    // Busca si el modelo ya existe
    $stmt = $conn->prepare("SELECT id FROM modelos WHERE num_modelos = ?");
    $stmt->bind_param("s", $modelo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $modelo = $row['id']; // ahora $modelo es el ID
    } else {
        // Inserta el nuevo modelo si no existe
        $stmt = $conn->prepare("INSERT INTO modelos (num_modelos) VALUES (?)");
        $stmt->bind_param("s", $modelo);
        $stmt->execute();
        $modelo = $stmt->insert_id; // ahora $modelo es el ID insertado
    }

    //IMÁGENES
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
    $stmt = $conn->prepare("INSERT INTO dispositivos (equipo, fecha, modelo, estado, sucursal, observaciones, serie, mac, vms, servidor, switch, puerto, area, imagen, imagen2, imagen3, user, pass) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiisssssssssssss",$equipo, $fecha, $modelo, $estado, $sucursal, $observaciones, $serie, $mac, $vms, $servidor, $switch, $puerto, $area, $imagenes['imagen'], $imagenes['imagen2'], $imagenes['imagen3'], $user, $pass);
    if (!$stmt->execute()) {
        die("Error al insertar en dispositivo: " . $stmt->error);
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