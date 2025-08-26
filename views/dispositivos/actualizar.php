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
$equipo         = isset($_POST['equipo']) ? (int)$_POST['equipo'] : 0;      // FK → int
$modelo         = isset($_POST['modelo']) ? (int)$_POST['modelo'] : 0;      // FK → int
$serie          = trim($_POST['serie'] ?? '');
$mac            = trim($_POST['mac'] ?? '');
$servidor       = trim($_POST['servidor'] ?? '');
$vms            = trim($_POST['vms'] ?? '');
$vms_otro       = trim($_POST['vms_otro'] ?? '');
$switchTxt      = trim($_POST['switch'] ?? '');
$puerto         = trim($_POST['puerto'] ?? '');
/* Estos dos eran los originales por nombre */
$sucursal       = trim($_POST['sucursal'] ?? '');
$area           = trim($_POST['area'] ?? '');
/* Estos son los nuevos por ID desde el formulario con <select> (opcional) */
$sucursal_id    = isset($_POST['sucursal_id']) && $_POST['sucursal_id'] !== '' ? (int)$_POST['sucursal_id'] : null;
$area_id        = isset($_POST['area_id'])     && $_POST['area_id']     !== '' ? (int)$_POST['area_id']     : null;

$estado         = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;      // enum numérico (1,2,3)
$fecha          = $_POST['fecha'] ?? date('Y-m-d');
$observaciones  = trim($_POST['observaciones'] ?? '');
$usuarioDis     = trim($_POST['usuario'] ?? '');
$contrasenaDis  = trim($_POST['contrasena'] ?? '');

/* Si seleccionó "Otro", usar el valor personalizado */
if (strcasecmp($vms, 'Otro') === 0 && $vms_otro !== '') {
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
    if ($row = $stmtSuc->get_result()->fetch_assoc()) {
        $sucursal = $row['nom_sucursal'] ?? $sucursal;
    }
    $stmtSuc->close();
}

if ($area_id) {
    $stmtArea = $conn->prepare("SELECT nom_area FROM areas WHERE id = ?");
    $stmtArea->bind_param("i", $area_id);
    $stmtArea->execute();
    if ($row = $stmtArea->get_result()->fetch_assoc()) {
        $area = $row['nom_area'] ?? $area;
    }
    $stmtArea->close();
}

/* =======================
   Obtener datos actuales (para conservar imágenes/qr)
======================= */
$stmt = $conn->prepare("SELECT imagen, imagen2, imagen3, qr, modelo AS modelo_actual FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$actual = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$actual) {
    die('Dispositivo no encontrado.');
}

/* =======================
   Manejo de archivos
======================= */
$imagen   = $actual['imagen']  ?? null;
$imagen2  = $actual['imagen2'] ?? null;
$imagen3  = $actual['imagen3'] ?? null;
$qr       = $actual['qr']      ?? null;

$uploadDir = __DIR__ . "/../../public/uploads/";
$qrDir     = __DIR__ . "/../../public/qrcodes/";

$imagen  = move_upload_unique('imagen',  $uploadDir, $imagen);
$imagen2 = move_upload_unique('imagen2', $uploadDir, $imagen2);
$imagen3 = move_upload_unique('imagen3', $uploadDir, $imagen3);
/* Si permites reemplazar el archivo QR manualmente (normalmente no es necesario) */
$qr      = move_upload_unique('qr',      $qrDir,     $qr);

/* =======================
   Edición individual: EQUIPO
   (crear/reutilizar y reasignar SOLO este dispositivo)
======================= */
$equipo_edit_mode   = isset($_POST['equipo_edit_mode']) && $_POST['equipo_edit_mode'] === '1';
$equipo_nombre_edit = trim($_POST['equipo_nombre_edit'] ?? '');

if ($equipo_edit_mode && $equipo_nombre_edit !== '') {
    // ¿Ya existe ese equipo (case-insensitive)?
    $q = $conn->prepare("SELECT id FROM equipos WHERE UPPER(nom_equipo) = UPPER(?) LIMIT 1");
    $q->bind_param("s", $equipo_nombre_edit);
    $q->execute();
    $ex = $q->get_result()->fetch_assoc();
    $q->close();

    if ($ex) {
        $equipo = (int)$ex['id']; // reutilizar
    } else {
        // crear nuevo equipo
        $ins = $conn->prepare("INSERT INTO equipos (nom_equipo) VALUES (?)");
        $ins->bind_param("s", $equipo_nombre_edit);
        $ins->execute();
        $equipo = $ins->insert_id;
        $ins->close();
    }
}

/* =======================
   Edición individual: MODELO
   (crear/reutilizar heredando marca_id del modelo base)
======================= */
$modelo_edit_mode   = isset($_POST['modelo_edit_mode']) && $_POST['modelo_edit_mode'] === '1';
$modelo_nombre_edit = trim($_POST['modelo_nombre_edit'] ?? '');

if ($modelo_edit_mode && $modelo_nombre_edit !== '') {
    // Tomar marca_id del modelo base (el seleccionado o el que ya tenía el dispositivo)
    $modeloBaseId = $modelo ?: (int)($actual['modelo_actual'] ?? 0);
    $marcaId = null;

    if ($modeloBaseId > 0) {
        $q = $conn->prepare("SELECT marca_id FROM modelos WHERE id = ? LIMIT 1");
        $q->bind_param("i", $modeloBaseId);
        $q->execute();
        $marcaId = $q->get_result()->fetch_assoc()['marca_id'] ?? null;
        $q->close();
    }

    if ($marcaId) {
        // ¿Ya existe el modelo con ese nombre para esa marca?
        $q = $conn->prepare("SELECT id FROM modelos WHERE marca_id = ? AND UPPER(num_modelos) = UPPER(?) LIMIT 1");
        $q->bind_param("is", $marcaId, $modelo_nombre_edit);
        $q->execute();
        $ex = $q->get_result()->fetch_assoc();
        $q->close();

        if ($ex) {
            $modelo = (int)$ex['id']; // reutilizar
        } else {
            $ins = $conn->prepare("INSERT INTO modelos (num_modelos, marca_id) VALUES (?, ?)");
            $ins->bind_param("si", $modelo_nombre_edit, $marcaId);
            $ins->execute();
            $modelo = $ins->insert_id;
            $ins->close();
        }
    }
    // Si no hubo marcaId, no creamos para no romper integridad (opcional: avisar al usuario)
}

/* =======================
   Validaciones mínimas
======================= */
if ($id <= 0)            die('ID inválido.');
if ($equipo <= 0)        die('Selecciona un equipo válido.');
if ($modelo <= 0)        die('Selecciona un modelo válido.');
if (!$sucursal)          die('Selecciona una sucursal.');
if (!$fecha)             die('La fecha es requerida.');

/* =======================
   Actualizar base de datos
======================= */
$sql = "
UPDATE dispositivos 
SET 
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

$update = $conn->prepare($sql);
$update->bind_param(
    "iissssssssissssssssi",
    $equipo,          // i
    $modelo,          // i
    $serie,           // s
    $mac,             // s
    $servidor,        // s
    $vms,             // s
    $switchTxt,       // s
    $puerto,          // s
    $sucursal,        // s (sigues guardando nombre)
    $area,            // s (sigues guardando nombre)
    $estado,          // i
    $fecha,           // s (YYYY-mm-dd)
    $observaciones,   // s
    $imagen,          // s
    $imagen2,         // s
    $imagen3,         // s
    $qr,              // s
    $usuarioDis,      // s
    $contrasenaDis,   // s
    $id               // i
);
$update->execute();
$update->close();

/* =======================
   Notificación para Mantenimientos (tu lógica original)
======================= */
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

/* =======================
   Redirigir
======================= */
header("Location: device.php?id=$id");
exit;
