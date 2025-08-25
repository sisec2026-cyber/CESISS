<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos', 'Superadmin', 'Capturista']);

include __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso no autorizado.');
}

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
$id            = (int)($_POST['id'] ?? 0);
$equipo        = $_POST['equipo']        ?? '';
$modelo        = $_POST['modelo']        ?? '';
$serie         = $_POST['serie']         ?? '';
$mac           = $_POST['mac']           ?? '';
$servidor      = $_POST['servidor']      ?? '';
$vms           = $_POST['vms']           ?? '';
$vms_otro      = $_POST['vms_otro']      ?? '';
$switch        = $_POST['switch']        ?? '';
$puerto        = $_POST['puerto']        ?? '';
/* Estos dos eran los originales por nombre */
$sucursal      = $_POST['sucursal']      ?? '';
$area          = $_POST['area']          ?? '';
/* Estos son los nuevos por ID desde el formulario con <select> */
$sucursal_id   = isset($_POST['sucursal_id']) && $_POST['sucursal_id'] !== '' ? (int)$_POST['sucursal_id'] : null;
$area_id       = isset($_POST['area_id'])     && $_POST['area_id']     !== '' ? (int)$_POST['area_id']     : null;

$estado        = $_POST['estado']        ?? 'Activo';
$fecha         = $_POST['fecha']         ?? date('Y-m-d');
$observaciones = $_POST['observaciones'] ?? '';
$usuarioDis   = $_POST['usuario']    ?? '';
$contrasenaDis = $_POST['contrasena'] ?? '';

/* Si seleccionó "Otro", usar el valor personalizado */
if ($vms === 'Otro' && !empty($vms_otro)) {
    $vms = $vms_otro;
}

/* =======================
   Mapear IDs -> NOMBRES
   (para seguir guardando en columnas sucursal/area actuales)
======================= */
if ($sucursal_id) {
    $stmtSuc = $conn->prepare("SELECT nom_sucursal FROM sucursales WHERE id = ?");
    $stmtSuc->bind_param("i", $sucursal_id);
    $stmtSuc->execute();
    $resSuc = $stmtSuc->get_result()->fetch_assoc();
    if ($resSuc && !empty($resSuc['nom_sucursal'])) {
        $sucursal = $resSuc['nom_sucursal']; // sobrescribe el nombre
    }
    $stmtSuc->close();
}

if ($area_id) {
    $stmtArea = $conn->prepare("SELECT nom_area FROM areas WHERE id = ?");
    $stmtArea->bind_param("i", $area_id);
    $stmtArea->execute();
    $resArea = $stmtArea->get_result()->fetch_assoc();
    if ($resArea && !empty($resArea['nom_area'])) {
        $area = $resArea['nom_area']; // sobrescribe el nombre
    }
    $stmtArea->close();
}

/* =======================
   Obtener datos actuales (para conservar imágenes/qr)
======================= */
$stmt = $conn->prepare("SELECT imagen, imagen2, imagen3, qr FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$actual = $result->fetch_assoc();
$stmt->close();

if (!$actual) {
    die('Dispositivo no encontrado.');
}

/* =======================
   Manejo de archivos
======================= */
$imagen  = $actual['imagen']  ?? null;
$imagen2 = $actual['imagen2'] ?? null;
$imagen3 = $actual['imagen3'] ?? null;
$qr      = $actual['qr']      ?? null;

$uploadDir = __DIR__ . "/../../public/uploads/";
$qrDir     = __DIR__ . "/../../public/qrcodes/";

/* Imagen 1 (principal) */
$imagen  = move_upload_unique('imagen',  $uploadDir, $imagen);
/* Imagen 2 (antes) */
$imagen2 = move_upload_unique('imagen2', $uploadDir, $imagen2);
/* Imagen 3 (después) */
$imagen3 = move_upload_unique('imagen3', $uploadDir, $imagen3);
/* QR (si decides permitir reemplazo vía archivo) */
$qr      = move_upload_unique('qr',      $qrDir,     $qr);

// === Renombrar EQUIPO si el usuario lo activó ===
$equipo_edit_mode   = isset($_POST['equipo_edit_mode']) && $_POST['equipo_edit_mode'] === '1';
$equipo_edit_id     = isset($_POST['equipo_edit_id']) ? (int)$_POST['equipo_edit_id'] : 0;
$equipo_nombre_edit = trim($_POST['equipo_nombre_edit'] ?? '');

if ($equipo_edit_mode && $equipo_edit_id > 0 && $equipo_nombre_edit !== '') {
    $stmtUpEq = $conn->prepare("UPDATE equipos SET nom_equipo = ? WHERE id = ?");
    $stmtUpEq->bind_param("si", $equipo_nombre_edit, $equipo_edit_id);
    $stmtUpEq->execute();
    $stmtUpEq->close();
}

// === Renombrar MODELO si el usuario lo activó ===
$modelo_edit_mode   = isset($_POST['modelo_edit_mode']) && $_POST['modelo_edit_mode'] === '1';
$modelo_edit_id     = isset($_POST['modelo_edit_id']) ? (int)$_POST['modelo_edit_id'] : 0;
$modelo_nombre_edit = trim($_POST['modelo_nombre_edit'] ?? '');

if ($modelo_edit_mode && $modelo_edit_id > 0 && $modelo_nombre_edit !== '') {
    $stmtUpMo = $conn->prepare("UPDATE modelos SET num_modelos = ? WHERE id = ?");
    $stmtUpMo->bind_param("si", $modelo_nombre_edit, $modelo_edit_id);
    $stmtUpMo->execute();
    $stmtUpMo->close();
}


/* =======================
   Actualizar base de datos
======================= */
$update = $conn->prepare("
    UPDATE dispositivos 
    SET equipo=?, modelo=?, serie=?, mac=?, servidor=?, vms=?, switch=?, puerto=?, 
        sucursal=?, area=?, estado=?, fecha=?, observaciones=?, 
        imagen=?, imagen2=?, imagen3=?, qr=?, `user`=?, `pass`=?
    WHERE id=?
");

// 19 's' + 'i' para id
$update->bind_param(
    "sssssssssssssssssssi",
    $equipo, $modelo, $serie, $mac, $servidor, $vms, $switch, $puerto,
    $sucursal, $area, $estado, $fecha, $observaciones,
    $imagen, $imagen2, $imagen3, $qr, $usuarioDis, $contrasenaDis, $id
);

$update->execute();
$update->close();

/* =======================
   Notificación para Mantenimientos (tu lógica original)
======================= */
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

/* =======================
   Redirigir
======================= */
header("Location: device.php?id=$id");
exit;

