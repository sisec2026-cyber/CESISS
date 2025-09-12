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
$vms_otro       = trim($_POST['vms_otro'] ?? ''); // por si lo usas en el form
$switchTxt      = trim($_POST['switch'] ?? '');
$puerto         = trim($_POST['puerto'] ?? '');
// $sucursal     (no se permite editar desde este flujo)
$area           = trim($_POST['area'] ?? '');
$estado         = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;
$fecha          = $_POST['fecha'] ?? date('Y-m-d');
$observaciones  = trim($_POST['observaciones'] ?? '');
$usuarioDis     = trim($_POST['usuario'] ?? '');
$contrasenaDis  = trim($_POST['contrasena'] ?? '');

/* === Nuevos campos === */
$marca_id       = (isset($_POST['marca_id']) && $_POST['marca_id'] !== '') ? (int)$_POST['marca_id'] : null; // FK → marcas.id_marcas (nullable)
$alarma_id      = (isset($_POST['alarma_id']) && $_POST['alarma_id'] !== '') ? (int)$_POST['alarma_id'] : null; // FK → alarma.id (nullable)
$zona_alarma    = trim($_POST['zona_alarma'] ?? '');
$tipo_sensor    = trim($_POST['tipo_sensor'] ?? '');
$cctv_id        = (isset($_POST['cctv_id']) && $_POST['cctv_id'] !== '') ? (int)$_POST['cctv_id'] : null; // FK → cctv.id (nullable)

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
        $marcaIdInferida = null;

        if ($modeloBaseId > 0) {
            $q = $conn->prepare("SELECT marca_id FROM modelos WHERE id = ? LIMIT 1");
            $q->bind_param("i", $modeloBaseId);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $marcaIdInferida = $row['marca_id'] ?? null;
            $q->close();
        }

        if ($marcaIdInferida) {
            $q = $conn->prepare("SELECT id FROM modelos WHERE marca_id = ? AND UPPER(num_modelos) = UPPER(?) LIMIT 1");
            $q->bind_param("is", $marcaIdInferida, $modelo_nombre_edit);
            $q->execute();
            $ex = $q->get_result()->fetch_assoc();
            $q->close();

            if ($ex) {
                $modelo = (int)$ex['id'];
            } else {
                $ins = $conn->prepare("INSERT INTO modelos (num_modelos, marca_id) VALUES (?, ?)");
                $ins->bind_param("si", $modelo_nombre_edit, $marcaIdInferida);
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

    /* 4) Validaciones (sucursal ya no se valida aquí) */
    if ($id <= 0)       throw new Exception('ID inválido.');
    if ($equipo <= 0)   throw new Exception('Selecciona un equipo válido.');
    if ($modelo <= 0)   throw new Exception('Selecciona o crea un modelo válido.');
    if (!$fecha)        throw new Exception('La fecha es requerida.');

    /* 5) UPDATE (sin sucursal) + marca y alarma/cctv */
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
            area = ?, 
            estado = ?, 
            fecha = ?, 
            observaciones = ?, 
            imagen = ?, 
            imagen2 = ?, 
            imagen3 = ?, 
            qr = ?, 
            `user` = ?, 
            `pass` = ?,
            marca_id = ?, 
            alarma_id = ?, 
            zona_alarma = ?, 
            tipo_sensor = ?, 
            cctv_id = ?
        WHERE id = ?
    ";
    $up = $conn->prepare($sql);
    /* Tipos (24 parámetros):
       1:i equipo
       2:i modelo
       3:s serie
       4:s mac
       5:s servidor
       6:s vms
       7:s switch
       8:s puerto
       9:s area
      10:i estado
      11:s fecha
      12:s observaciones
      13:s imagen
      14:s imagen2
      15:s imagen3
      16:s qr
      17:s user
      18:s pass
      19:i marca_id
      20:i alarma_id
      21:s zona_alarma
      22:s tipo_sensor
      23:i cctv_id
      24:i id
    */
    $up->bind_param(
        "iisssssssissssssssiissii",
        $equipo,        // 1
        $modelo,        // 2
        $serie,         // 3
        $mac,           // 4
        $servidor,      // 5
        $vms,           // 6
        $switchTxt,     // 7
        $puerto,        // 8
        $area,          // 9
        $estado,        // 10
        $fecha,         // 11
        $observaciones, // 12
        $imagen,        // 13
        $imagen2,       // 14
        $imagen3,       // 15
        $qr,            // 16
        $usuarioDis,    // 17
        $contrasenaDis, // 18
        $marca_id,      // 19
        $alarma_id,     // 20
        $zona_alarma,   // 21
        $tipo_sensor,   // 22
        $cctv_id,       // 23
        $id             // 24
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
