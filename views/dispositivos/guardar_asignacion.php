<?php
// /sisec-ui/views/dispositivos/guardar_asignacion.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Técnico']);

date_default_timezone_set('America/Mexico_City');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Función genérica para obtener o crear registro */
function obtenerOCrear($conn, $tabla, $columna, $valor, $pk) {
    if (!$valor) return 0;
    $stmt = $conn->prepare("SELECT $pk FROM $tabla WHERE $columna = ? LIMIT 1");
    $stmt->bind_param("s", $valor);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int)$row[$pk];
    $stmt = $conn->prepare("INSERT INTO $tabla ($columna) VALUES (?)");
    $stmt->bind_param("s", $valor);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return (int)$id;
}
/* Función específica para crear o recuperar marca vinculada a equipo */
function obtenerOCrearMarca($conn, $nom_marca, $equipo_id) {
    if (!$nom_marca || !$equipo_id) return 0;

    // Buscar si ya existe la marca con ese nombre y equipo
    $stmt = $conn->prepare("SELECT id_marcas FROM marcas WHERE nom_marca = ? AND equipo_id = ? LIMIT 1");
    $stmt->bind_param("si", $nom_marca, $equipo_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) return (int)$row['id_marcas'];

    // Si no existe, crearla
    $stmt = $conn->prepare("INSERT INTO marcas (nom_marca, equipo_id) VALUES (?, ?)");
    $stmt->bind_param("si", $nom_marca, $equipo_id);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return (int)$id;
}

/* Inputs básicos */
$uid   = (int)($_SESSION['usuario_id'] ?? 0);
$token = $_POST['qr_token'] ?? '';
if (!$token) die('Token faltante');

/* Datos del formulario */
$equipo_input   = trim($_POST['equipo'] ?? '');
$marca_select   = trim($_POST['marca'] ?? '');
$marca_manual   = trim($_POST['marcaManual'] ?? '');
$marca_final    = $marca_manual ?: $marca_select;
$modelo_input   = trim($_POST['modelo'] ?? '');
$area_o_zona    = trim($_POST['area'] ?? '');
$serie          = trim($_POST['serie'] ?? '');
$user           = trim($_POST['user'] ?? '');
$pass           = trim($_POST['pass'] ?? '');
$ciudad_id      = (int)($_POST['ciudad_id'] ?? 0);
$municipio_id   = (int)($_POST['municipio_id'] ?? 0);
$sucursal_id    = (int)($_POST['sucursal_id'] ?? 0);
$determinante_id= (int)($_POST['determinante_id'] ?? 0);
$estado_id      = (int)($_POST['estado_id'] ?? 1);
$zona_alarma = $_POST['zona_alarma'] ?? null;
$fecha_inst     = $_POST['fecha_instalacion'] ?? date('Y-m-d');

/* Obtener/crear equipo, marca y modelo (compatible con tu esquema actual) */
$equipo_id = obtenerOCrear($conn, "equipos", "nom_equipo", $equipo_input, 'id');
$marca_id  = obtenerOCrearMarca($conn, $marca_final, $equipo_id);

/* === COPIAR QR FÍSICO desde /public/qr/ hacia /public/qr_dispositivos/ === */
$qrFile = $token . '.png';

$ruta_origen  = __DIR__ . "/../../public/qr/" . $qrFile;
$ruta_destino = __DIR__ . "/../../public/qr_dispositivos/" . $qrFile;

if (file_exists($ruta_origen)) {
    if (!copy($ruta_origen, $ruta_destino)) {
        error_log("No se pudo copiar el QR físico desde $ruta_origen hacia $ruta_destino");
    }
} else {
    error_log("QR físico no encontrado en: $ruta_origen");
}


/* Resolver modelo: tu tabla modelos usa PK `id` y campo `num_modelos` */
$modelo_id = 0;
if ($modelo_input !== '') {
    $stmt = $conn->prepare("SELECT id FROM modelos WHERE num_modelos = ? AND marca_id = ? LIMIT 1");
    $stmt->bind_param("si", $modelo_input, $marca_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $modelo_id = (int)$row['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO modelos (num_modelos, marca_id) VALUES (?, ?)");
        $stmt->bind_param("si", $modelo_input, $marca_id);
        $stmt->execute();
        $modelo_id = (int)$stmt->insert_id;
        $stmt->close();
    }
}

/* Validación final */
if (
    !$equipo_id ||
    !$marca_id ||
    !$modelo_id ||
    !$ciudad_id ||
    !$sucursal_id ||
    !$determinante_id
) {
    http_response_code(400);
    die('Campos requeridos incompletos.');
}

/* Validar QR */
$stmt = $conn->prepare("SELECT id, dispositivo_id FROM qr_pool WHERE token=? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$qr = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$qr) die('QR no encontrado.');
if (!empty($qr['dispositivo_id'])) die('QR ya reclamado.');

/* Insertar dispositivo (SIN columna 'operativo') */
$conn->begin_transaction();
try {
    $qrFile = $token . '.png';
    $area   = $area_o_zona ?: null;
    // ===============================================
    // Detección automática de tipo (CCTV o ALARMA)
    // ===============================================
    $cctv_id = null;
    $alarma_id = null;

    if (!empty($equipo_input)) {
        $eq = mb_strtolower(trim($equipo_input), 'UTF-8');

        if (preg_match('/(camara|cámara|bullet|ptz|dvr|nvr|switch|servidor|monitor|ups|mouse|transceptor|balloon|ballons|360|conector macho|plug 3,5|fuente de poder CCTV|extensores|visual tools|ax-tv|axtv|videoportero)/u', $eq)) {
            $cctv_id = 1; // Ajusta el ID real de tu tipo CCTV si aplica
        } elseif (preg_match('/(alarma|sensor|dh|cm|em|estacion manual|drc|ruptura|360|estrobos|repetidora|receptora|oh|uso rudo|bateria|relevador|transformado|weigand|sirena|teclado|panel|boton|pánico|panico|pir|fuente de poder|gp23|electro iman|liberador)/u', $eq)) {
            $alarma_id = 1; // Ajusta el ID real de tu tipo Alarma si aplica
        }
    }


    $stmt = $conn->prepare("
    INSERT INTO dispositivos
    (determinante, equipo, fecha, modelo, estado, sucursal, serie, area, zona_alarma,
     qr, `user`, `pass`, marca_id, fecha_instalacion, cctv_id, alarma_id)
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");


    // Tipos: i i s i i i s s s s s i s  => "iisiiisssssis"
    $fecha_para_fecha = $fecha_inst; // usamos la misma fecha para 'fecha' y 'fecha_instalacion'
    
    $types = "iisiiissssssisii"; // <-- agregamos dos 'i' al final (para cctv_id y alarma_id)
    $stmt->bind_param(
    $types,
    $determinante_id,
    $equipo_id,
    $fecha_para_fecha,
    $modelo_id,
    $estado_id,
    $sucursal_id,
    $serie,
    $area,
    $zona_alarma,
    $qrFile,
    $user,
    $pass,
    $marca_id,
    $fecha_inst,
    $cctv_id,
    $alarma_id
);


    $stmt->execute();
    $deviceId = (int)$stmt->insert_id;
    $stmt->close();

    /* === MOVER QR VIRGEN AL QR DEFINITIVO === */
    $origen  = __DIR__ . "/../../public/qr_virgenes/" . $token . ".png";
    $destino = __DIR__ . "/../../public/qrcodes/" . $deviceId . ".png";

    if (file_exists($origen)) {
        if (!copy($origen, $destino)) {
            error_log("No se pudo copiar el QR desde $origen hacia $destino");
        }
    } else {
        error_log("QR virgen no encontrado: " . $origen);
    }

    /* Guardar el nombre final en DB */
    $qrFinal = $deviceId . ".png";
    $stmt = $conn->prepare("UPDATE dispositivos SET qr = ? WHERE id = ?");
    $stmt->bind_param("si", $qrFinal, $deviceId);
    $stmt->execute();
    $stmt->close();

    /* Subir imágenes */
    /* Subir imágenes (todas en /public/uploads/) */
$uploadsDir = __DIR__ . '/../../public/uploads/';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

$saved = [1=>null,2=>null,3=>null];

for ($i=1; $i<=3; $i++) {

    if (!isset($_FILES['imagen'.$i])) continue;

    $f = $_FILES['imagen'.$i];

    if (!empty($f['tmp_name']) && is_uploaded_file($f['tmp_name'])) {

        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg');

        // Guardar como: dispositivoID_imagen1.jpg
        $fileName = $deviceId . "_img{$i}." . $ext;
        $dest = $uploadsDir . $fileName;

        if (move_uploaded_file($f['tmp_name'], $dest)) {
            $saved[$i] = $fileName;
        } else {
            error_log("ERROR al mover imagen{$i} hacia: $dest");
        }
    }
}

/* Actualizar nombres en DB */
if ($saved[1] || $saved[2] || $saved[3]) {
    $stmt = $conn->prepare("UPDATE dispositivos SET imagen=?, imagen2=?, imagen3=? WHERE id=?");
    $stmt->bind_param('sssi', $saved[1], $saved[2], $saved[3], $deviceId);
    $stmt->execute();
    $stmt->close();
}


    /* Marcar QR como reclamado */
    $stmt = $conn->prepare("UPDATE qr_pool SET dispositivo_id=?, claimed_at=NOW() WHERE id=? AND dispositivo_id IS NULL");
    $stmt->bind_param('ii', $deviceId, $qr['id']);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    header('Location: /sisec-ui/views/dispositivos/device.php?id=' . $deviceId);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo 'Error al guardar: ' . $e->getMessage();
}