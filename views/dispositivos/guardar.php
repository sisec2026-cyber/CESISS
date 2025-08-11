<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos']);

include __DIR__ . '/../../includes/db.php';
include __DIR__ . '/../../vendor/phpqrcode/qrlib.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ===========================
   Helpers de validación/normalize
   =========================== */
function normalize_upper($s) {
  $s = trim((string)$s);
  return mb_strtoupper($s, 'UTF-8');
}
function normalize_modelo($s) {
  // Mantén el modelo como lo escribe el usuario salvo recorte de espacios
  return trim((string)$s);
}
function normalize_mac($macRaw) {
  $hex = preg_replace('/[^0-9A-Fa-f]/', '', (string)$macRaw);
  if (strlen($hex) !== 12) return [null, "La MAC debe tener 12 hex dígitos."];
  $hex = strtoupper($hex);
  $pairs = str_split($hex, 2);
  $mac = implode(':', $pairs);
  if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
    return [null, "La MAC no tiene el formato correcto AA:BB:CC:DD:EE:FF."];
  }
  return [$mac, null];
}
function validate_ipv4_or_null($ipRaw) {
  $ip = trim((string)$ipRaw);
  if ($ip === '') return [null, null]; // opcional
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return [$ip, null];
  }
  return [null, "La IP no es una IPv4 válida."];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $conn->begin_transaction();

    // ===== 1) Normaliza inputs =====
    // Catálogos en mayúsculas
    $equipo_nombre   = normalize_upper($_POST['equipo'] ?? '');
    $marca_nombre    = normalize_upper($_POST['marca'] ?? '');
    $tipo_alarma_nombre = normalize_upper($_POST['tipo_alarma'] ?? '');
    $tipo_switch_nombre = normalize_upper($_POST['tipo_switch'] ?? '');
    $tipo_cctv_nombre   = normalize_upper($_POST['tipo_cctv'] ?? '');

    // Modelo: respeta tal cual (solo trim)
    $modelo_nombre   = normalize_modelo($_POST['modelo'] ?? '');

    // Otros campos
    $fecha           = $_POST['fecha'] ?? null;
    $estado_nombre   = trim($_POST['estado'] ?? '');
    $sucursal_nombre = trim($_POST['sucursal'] ?? '');
    $observaciones   = trim($_POST['observaciones'] ?? '');
    $serie           = trim($_POST['serie'] ?? '');
    $vms             = trim($_POST['vms'] ?? '');
    $servidor        = trim($_POST['servidor'] ?? '');
    $switch_txt      = trim($_POST['switch'] ?? '');
    $puerto          = trim($_POST['puerto'] ?? '');
    $area            = trim($_POST['area'] ?? '');
    $rc              = trim($_POST['rc'] ?? '');
    $user_txt        = trim($_POST['user'] ?? '');
    $pass_txt        = trim($_POST['pass'] ?? '');
    $ubicacion_rc    = trim($_POST['Ubicacion_rc'] ?? '');
    $version_vms     = trim($_POST['version_vms'] ?? '');
    $version_windows = trim($_POST['version_windows'] ?? '');
    $zona_alarma     = trim($_POST['zona_alarma'] ?? '');
    $tipo_sensor     = trim($_POST['tipo_sensor'] ?? '');
    $determinante_nom = trim($_POST['determinante'] ?? '');

    // Selects de ubicación
    $ciudad_id     = isset($_POST['ciudad']) ? (int)$_POST['ciudad'] : null;
    $municipio_id  = isset($_POST['municipio']) ? (int)$_POST['municipio'] : null;

    // Validación/normalización MAC
    list($mac, $macErr) = normalize_mac($_POST['mac'] ?? '');
    if ($macErr) {
      throw new Exception($macErr);
    }

    // Validación IP (opcional si queda vacío)
    list($ip, $ipErr) = validate_ipv4_or_null($_POST['ipTag'] ?? '');
    if ($ipErr) {
      throw new Exception($ipErr);
    }

    // Normaliza variantes "ANÁLOGO"->"ANALÓGICO"
    if ($tipo_cctv_nombre !== '') {
      $tipo_cctv_nombre = str_ireplace('ANÁLOGO', 'ANALÓGICO', $tipo_cctv_nombre);
    }

    // ===== 2) Helpers de DB =====
    $selId = function($sql, $types, ...$params) use ($conn) {
      $stmt = $conn->prepare($sql);
      if ($types) $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
      return $res->num_rows ? (int)$res->fetch_assoc()['id'] : null;
    };
    $insReturnId = function($sql, $types, ...$params) use ($conn) {
      $stmt = $conn->prepare($sql);
      if ($types) $stmt->bind_param($types, ...$params);
      $stmt->execute();
      return $stmt->insert_id;
    };
    $getOrCreate = function($selectSql, $insertSql, $typesSel, $typesIns, $selParams, $insParams) use ($selId, $insReturnId) {
      $id = $selId($selectSql, $typesSel, ...$selParams);
      if ($id !== null) return $id;
      return $insReturnId($insertSql, $typesIns, ...$insParams);
    };

    // ===== 3) Catálogos =====
    $equipo_id = $getOrCreate(
      "SELECT id FROM equipos WHERE nom_equipo = ?",
      "INSERT INTO equipos (nom_equipo) VALUES (?)",
      "s", "s", [$equipo_nombre], [$equipo_nombre]
    );
    $marca_id = $getOrCreate(
      "SELECT id_marcas AS id FROM marcas WHERE nom_marca = ? AND equipo_id = ?",
      "INSERT INTO marcas (nom_marca, equipo_id) VALUES (?, ?)",
      "si", "si", [$marca_nombre, $equipo_id], [$marca_nombre, $equipo_id]
    );
    $modelo_id = $getOrCreate(
      "SELECT id FROM modelos WHERE num_modelos = ? AND marca_id = ?",
      "INSERT INTO modelos (num_modelos, marca_id) VALUES (?, ?)",
      "si", "si", [$modelo_nombre, $marca_id], [$modelo_nombre, $marca_id]
    );
    $sucursal_id = $getOrCreate(
      "SELECT id FROM sucursales WHERE nom_sucursal = ?",
      "INSERT INTO sucursales (nom_sucursal, municipio_id) VALUES (?, ?)",
      "s", "si", [$sucursal_nombre], [$sucursal_nombre, $municipio_id]
    );
    $determinante_id = $getOrCreate(
      "SELECT id FROM determinantes WHERE nom_determinante = ? AND sucursal_id = ?",
      "INSERT INTO determinantes (nom_determinante, sucursal_id) VALUES (?, ?)",
      "si", "si", [$determinante_nom, $sucursal_id], [$determinante_nom, $sucursal_id]
    );
    $estado_id = $getOrCreate(
      "SELECT id FROM status WHERE status_equipo = ?",
      "INSERT INTO status (status_equipo) VALUES (?)",
      "s", "s", [$estado_nombre], [$estado_nombre]
    );

    // Alarma / Switch / CCTV (NULL si vacío)
    $alarma_id = null;
    if ($tipo_alarma_nombre !== '') {
      $alarma_id = $getOrCreate(
        "SELECT id FROM alarma WHERE tipo_alarma = ?",
        "INSERT INTO alarma (tipo_alarma) VALUES (?)",
        "s", "s", [$tipo_alarma_nombre], [$tipo_alarma_nombre]
      );
    }
    $switch_id = null;
    if ($tipo_switch_nombre !== '') {
      $switch_id = $getOrCreate(
        "SELECT id FROM `switch` WHERE tipo_switch = ?",
        "INSERT INTO `switch` (tipo_switch) VALUES (?)",
        "s", "s", [$tipo_switch_nombre], [$tipo_switch_nombre]
      );
    }
    $cctv_id = null;
    if ($tipo_cctv_nombre !== '') {
      $cctv_id = $getOrCreate(
        "SELECT id FROM cctv WHERE tipo_cctv = ?",
        "INSERT INTO cctv (tipo_cctv) VALUES (?)",
        "s", "s", [$tipo_cctv_nombre], [$tipo_cctv_nombre]
      );
    }

    // ===== 4) Imágenes =====
    $imagenes = ['imagen1' => '', 'imagen2' => '', 'imagen3' => ''];
    $destino = __DIR__ . "/../../public/uploads/";
    if (!is_dir($destino)) { @mkdir($destino, 0775, true); }

    foreach (['imagen1', 'imagen2', 'imagen3'] as $idx => $campo) {
      if (!empty($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
        $tmpName   = $_FILES[$campo]['tmp_name'];
        $ext       = pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION);
        $nombreOut = uniqid("img" . ($idx + 1) . "_") . "." . $ext;
        $ruta      = $destino . $nombreOut;
        if (move_uploaded_file($tmpName, $ruta)) {
          $imagenes[$campo] = $nombreOut;
        }
      }
    }

    // ===== 5) INSERT principal =====
    $qr_placeholder = '';

    $stmt = $conn->prepare("
      INSERT INTO dispositivos (
        determinante, equipo, fecha, modelo, estado, sucursal,
        observaciones, serie, mac, vms, servidor, `switch`, puerto,
        area, rc, imagen, imagen2, imagen3, qr, `user`, `pass`,
        marca_id, alarma_id, switch_id, cctv_id, ubicacion_rc,
        ip, version_vms, version_windows, zona_alarma, tipo_sensor
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // "iisiii" + 15*s + "iiii" + 6*s = 31 parámetros
    $types = "iisiii" . str_repeat("s", 15) . "iiii" . str_repeat("s", 6);

    $stmt->bind_param(
      $types,
      $determinante_id, $equipo_id, $fecha, $modelo_id, $estado_id, $sucursal_id,
      $observaciones, $serie, $mac, $vms, $servidor, $switch_txt, $puerto,
      $area, $rc, $imagenes['imagen1'], $imagenes['imagen2'], $imagenes['imagen3'], $qr_placeholder, $user_txt, $pass_txt,
      $marca_id, $alarma_id, $switch_id, $cctv_id,
      $ubicacion_rc, $ip, $version_vms, $version_windows, $zona_alarma, $tipo_sensor
    );

    $stmt->execute();
    $id = $stmt->insert_id;

    // ===== 6) Notificación =====
    if (!empty($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] !== 'Administrador') {
      $mensaje = "El Mantenimientos " . ($_SESSION['nombre'] ?? '') . " registró un nuevo dispositivo.";
      $stmtNotif = $conn->prepare("
        INSERT INTO notificaciones (usuario_id, mensaje, fecha, visto, dispositivo_id)
        VALUES (?, ?, NOW(), 0, ?)
      ");
      $stmtNotif->bind_param("isi", $_SESSION['usuario_id'], $mensaje, $id);
      $stmtNotif->execute();
    }

    // ===== 7) QR =====
    $qr_filename = 'qr_' . $id . '.png';
    $qr_dir = __DIR__ . '/../../public/qrcodes/';
    if (!is_dir($qr_dir)) { @mkdir($qr_dir, 0775, true); }

    $qr_url = 'http://localhost/sisec-ui/views/dispositivos/device.php?id=' . $id; // ajusta al dominio real
    QRcode::png($qr_url, $qr_dir . $qr_filename, QR_ECLEVEL_H, 10);

    $stmtQr = $conn->prepare("UPDATE dispositivos SET qr = ? WHERE id = ?");
    $stmtQr->bind_param("si", $qr_filename, $id);
    $stmtQr->execute();

    $conn->commit();

    header("Location: device.php?id=" . $id);
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo "Error al guardar el dispositivo: " . htmlspecialchars($e->getMessage());
  }
}
