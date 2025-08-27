<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos', 'Superadmin', 'Capturista']);

include __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso no autorizado.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =======================
   Helpers
======================= */
function move_upload_unique(string $field, string $destDir, ?string $fallback = null): ?string {
    if (!isset($_FILES[$field]) || empty($_FILES[$field]['name'])) {
        return $fallback;
    }
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0775, true);
    }
    $name     = $_FILES[$field]['name'];
    $tmp      = $_FILES[$field]['tmp_name'];
    $ext      = pathinfo($name, PATHINFO_EXTENSION);
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $final    = $basename . '_' . uniqid() . ($ext ? ('.' . $ext) : '');
    if (is_uploaded_file($tmp)) {
        move_uploaded_file($tmp, rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $final);
        return $final;
    }
    return $fallback;
}

/* =======================
   Entrada
======================= */
$id             = (int)($_POST['id'] ?? 0);
$equipo         = isset($_POST['equipo']) ? (int)$_POST['equipo'] : 0;    // FK → int
$modelo         = isset($_POST['modelo']) ? (int)$_POST['modelo'] : 0;    // FK → int
$serie          = trim($_POST['serie'] ?? '');
$mac            = trim($_POST['mac'] ?? '');
$servidor       = trim($_POST['servidor'] ?? '');
$vms            = trim($_POST['vms'] ?? '');
$vms_otro       = trim($_POST['vms_otro'] ?? '');
$switchTxt      = trim($_POST['switch'] ?? '');
$puerto         = trim($_POST['puerto'] ?? '');
$sucursal       = isset($_POST['sucursal']) ? (int)$_POST['sucursal'] : 0; // FK int
$area           = trim($_POST['area'] ?? '');
$estado         = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;
$fecha          = $_POST['fecha'] ?? date('Y-m-d');
$observaciones  = trim($_POST['observaciones'] ?? '');
$usuarioDis     = trim($_POST['usuario'] ?? '');
$contrasenaDis  = trim($_POST['contrasena'] ?? '');

if (strcasecmp($vms, 'Otro') === 0 && $vms_otro !== '') {
    $vms = $vms_otro;
}

/* =======================
   Estado actual
======================= */
$stmt = $conn->prepare("SELECT equipo AS equipo_actual, modelo AS modelo_actual, imagen, imagen2, imagen3, qr FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$actual = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$actual) die('Dispositivo no encontrado.');

/* Archivos */
$imagen   = move_upload_unique('imagen',  __DIR__ . "/../../public/uploads/",  $actual['imagen']  ?? null);
$imagen2  = move_upload_unique('imagen2', __DIR__ . "/../../public/uploads/",  $actual['imagen2'] ?? null);
$imagen3  = move_upload_unique('imagen3', __DIR__ . "/../../public/uploads/",  $actual['imagen3'] ?? null);
$qr       = move_upload_unique('qr',      __DIR__ . "/../../public/qrcodes/", $actual['qr']      ?? null);

/* Flags edición individual */
$equipo_edit_mode   = isset($_POST['equipo_edit_mode']) && $_POST['equipo_edit_mode'] === '1';
$equipo_nombre_edit = trim($_POST['equipo_nombre_edit'] ?? '');

$modelo_edit_mode   = isset($_POST['modelo_edit_mode']) && $_POST['modelo_edit_mode'] === '1';
$modelo_nombre_edit = trim($_POST['modelo_nombre_edit'] ?? '');

/* =======================
   Transacción
======================= */
$conn->begin_transaction();
try {
    /* 1) Equipo: si se edita nombre, crear/reutilizar sin tocar modelo */
    if ($equipo_edit_mode && $equipo_nombre_edit !== '') {
        $q = $conn->prepare("SELECT id FROM equipos WHERE UPPER(nom_equipo) = UPPER(?) LIMIT 1");
        $q->bind_param("s", $equipo_nombre_edit);
        $q->execute();
        $ex = $q->get_result()->fetch_assoc();
        $q->close();

        if ($ex) {
            $equipo = (int)$ex['id'];
        } else {
            $ins = $conn->prepare("INSERT INTO equipos (nom_equipo) VALUES (?)");
            $ins->bind_param("s", $equipo_nombre_edit);
            $ins->execute();
            $equipo = $ins->insert_id;
            $ins->close();
        }
    }

    /* 2) Modelo: si se edita nombre, crear/reutilizar sin tocar equipo */
    if ($modelo_edit_mode && $modelo_nombre_edit !== '') {
        $modeloBaseId = $modelo > 0 ? $modelo : (int)($actual['modelo_actual'] ?? 0);
        $marcaId = null;

        if ($modeloBaseId > 0) {
            $q = $conn->prepare("SELECT marca_id FROM modelos WHERE id = ? LIMIT 1");
            $q->bind_param("i", $modeloBaseId);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $marcaId = $row['marca_id'] ?? null;
            $q->close();
        }

        if ($marcaId) {
            $q = $conn->prepare("SELECT id FROM modelos WHERE marca_id = ? AND UPPER(num_modelos) = UPPER(?) LIMIT 1");
            $q->bind_param("is", $marcaId, $modelo_nombre_edit);
            $q->execute();
            $ex = $q->get_result()->fetch_assoc();
            $q->close();

            if ($ex) {
                $modelo = (int)$ex['id'];
            } else {
                $ins = $conn->prepare("INSERT INTO modelos (num_modelos, marca_id) VALUES (?, ?)");
                $ins->bind_param("si", $modelo_nombre_edit, $marcaId);
                $ins->execute();
                $modelo = $ins->insert_id;
                $ins->close();
            }
        } else {
            // No se pudo inferir marca → mantener modelo previo
            $modelo = (int)($actual['modelo_actual'] ?? 0);
        }
    }

    /* 3) Si no eligió modelo, mantener el actual */
    if ($modelo <= 0) {
        $modelo = (int)($actual['modelo_actual'] ?? 0);
    }

    /* 4) Validaciones */
    if ($id <= 0)       throw new Exception('ID inválido.');
    if ($equipo <= 0)   throw new Exception('Selecciona un equipo válido.');
    if ($modelo <= 0)   throw new Exception('Selecciona o crea un modelo válido.');
    if ($sucursal <= 0) throw new Exception('Selecciona una sucursal válida.');
    if (!$fecha)        throw new Exception('La fecha es requerida.');

    /* 5) UPDATE */
    $sql = "
        UPDATE dispositivos SET 
            equipo = ?, 
            modelo = ?, 
            serie = ?, 
            mac = ?, 
            servidor = ?, 
            vms = ?, 
            `switch` = ?, 
            puerto = ?, 
            sucursal = ?, 
            area = ?, 
            estado = ?, 
            fecha = ?, 
            observaciones = ?, 
            imagen = ?, 
            imagen2 = ?, 
            imagen3 = ?, 
            qr = ?, 
            `user` = ?, 
            `pass` = ?
        WHERE id = ?
    ";
    $up = $conn->prepare($sql);
    // 20 parámetros → 20 letras
    $up->bind_param(
        "iissssssisissssssssi",
        $equipo,        // i 1
        $modelo,        // i 2
        $serie,         // s 3
        $mac,           // s 4
        $servidor,      // s 5
        $vms,           // s 6
        $switchTxt,     // s 7
        $puerto,        // s 8
        $sucursal,      // i 9
        $area,          // s 10
        $estado,        // i 11
        $fecha,         // s 12
        $observaciones, // s 13
        $imagen,        // s 14
        $imagen2,       // s 15
        $imagen3,       // s 16
        $qr,            // s 17
        $usuarioDis,    // s 18
        $contrasenaDis, // s 19
        $id             // i 20
    );
    $up->execute();
    $up->close();

    /* 6) Notificación */
    if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
        $mensaje    = "El Mantenimientos " . ($_SESSION['nombre'] ?? 'N/D') . " modificó el dispositivo con ID #$id.";
        $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);

        $stmtNotif = $conn->prepare("
            INSERT INTO notificaciones (usuario_id, mensaje, fecha, visto, dispositivo_id) 
            VALUES (?, ?, NOW(), 0, ?)
        ");
        $stmtNotif->bind_param("isi", $usuario_id, $mensaje, $id);
        $stmtNotif->execute();
        $stmtNotif->close();
    }

    $conn->commit();
    header("Location: device.php?id=$id");
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    die('Error al actualizar: ' . htmlspecialchars($e->getMessage()));
}
