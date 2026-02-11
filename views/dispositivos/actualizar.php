<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos', 'Superadmin', 'Capturista','Técnico']);

include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/notificaciones_mailer.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso no autorizado.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } // 
/* Helper archivos */
function move_multiple_uploads(string $field, string $destDir): array {
    $saved = [];
    if (!isset($_FILES[$field])) {
        return $saved;
    }
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0775, true);
    }
    foreach ($_FILES[$field]['tmp_name'] as $key => $tmp) {
        if (empty($_FILES[$field]['name'][$key])) continue;
        $name = $_FILES[$field]['name'][$key];
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($name, PATHINFO_FILENAME));
        $final = $base . '_' . uniqid() . ($ext ? ".$ext" : '');
        if (is_uploaded_file($tmp)) {
            move_uploaded_file($tmp, rtrim($destDir, '/') . '/' . $final);
            $saved[] = $final;
        }
    }
    return $saved;
}
function move_upload_unique(string $field, string $destDir, ?string $fallback = null): ?string {
    if (!isset($_FILES[$field]) || empty($_FILES[$field]['name'])) return $fallback;
    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
    $name = $_FILES[$field]['name'];
    $tmp  = $_FILES[$field]['tmp_name'];
    $ext  = pathinfo($name, PATHINFO_EXTENSION);
    $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $final = $base . '_' . uniqid() . ($ext ? ".$ext" : '');
    if (is_uploaded_file($tmp)) {
        move_uploaded_file($tmp, rtrim($destDir, '/') . '/' . $final);
        return $final;
    }
    return $fallback;
}

/* Entrada */
$id     = (int)($_POST['id'] ?? 0);
$equipo = (int)($_POST['equipo'] ?? 0);
$modelo = (int)($_POST['modelo'] ?? 0);
$serie         = trim($_POST['serie'] ?? '');
$mac           = trim($_POST['mac'] ?? '');
$servidor      = trim($_POST['servidor'] ?? '');
$vms           = trim($_POST['vms'] ?? '');
$vms_otro      = trim($_POST['vms_otro'] ?? '');
$switchTxt     = trim($_POST['switch'] ?? '');
$puerto        = trim($_POST['puerto'] ?? '');
$area          = trim($_POST['area'] ?? '');
$estado        = (int)($_POST['estado'] ?? 1);
$fecha         = $_POST['fecha'] ?? date('Y-m-d');
$observaciones = trim($_POST['observaciones'] ?? '');
$usuarioDis    = trim($_POST['usuario'] ?? '');
$contrasenaDis= trim($_POST['contrasena'] ?? '');
$fecha_instalacion = trim($_POST['fecha_instalacion'] ?? '');
if ($fecha_instalacion === '' || $fecha_instalacion === '0000-00-00') {
    $fecha_instalacion = null;
}
$marca_id  = ($_POST['marca_id'] ?? '') !== '' ? (int)$_POST['marca_id'] : null;
$alarma_id = ($_POST['alarma_id'] ?? '') !== '' ? (int)$_POST['alarma_id'] : null;
$cctv_id   = ($_POST['cctv_id'] ?? '')   !== '' ? (int)$_POST['cctv_id']   : null;
$zona_alarma = trim($_POST['zona_alarma'] ?? '');
$tipo_sensor = trim($_POST['tipo_sensor'] ?? '');
$tiene_analitica = ($_POST['tiene_analitica'] ?? '0') === '1' ? 1 : 0;
$analiticas = '';
if (!empty($_POST['analiticas']) && is_array($_POST['analiticas'])) {
    $analiticas = implode(', ', array_map('trim', $_POST['analiticas']));
}
/* Estado actual */
$stmt = $conn->prepare("SELECT equipo, modelo, imagen, imagen2, imagen3, qr
    FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$actual = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$actual) die('Dispositivo no encontrado.');

/* Archivos */
$imagen  = move_upload_unique('imagen',  __DIR__ . '/../../public/uploads/', $actual['imagen']);
$imagen2 = move_upload_unique('imagen2', __DIR__ . '/../../public/uploads/', $actual['imagen2']);
$imagen3 = move_upload_unique('imagen3', __DIR__ . '/../../public/uploads/', $actual['imagen3']);
$qr      = move_upload_unique('qr',      __DIR__ . '/../../public/qrcodes/', $actual['qr']);
/* Nuevas imágenes de mantenimiento */
$nuevasImagenes = array_merge(
    move_multiple_uploads('nuevas_imagenes_principal', __DIR__ . '/../../public/uploads/'),
    move_multiple_uploads('nuevas_imagenes_2', __DIR__ . '/../../public/uploads/'),
    move_multiple_uploads('nuevas_imagenes_3', __DIR__ . '/../../public/uploads/')
);
/* Transacción */
$conn->begin_transaction();
try {
    /* ========= 1) EQUIPO ========= */
    $equipo_edit_mode = ($_POST['equipo_edit_mode'] ?? '0') === '1';
    $equipo_nombre_edit = trim($_POST['equipo_nombre_edit'] ?? '');
    if ($equipo_edit_mode && $equipo_nombre_edit !== '') {
        $q = $conn->prepare("SELECT id FROM equipos WHERE UPPER(nom_equipo)=UPPER(?)");
        $q->bind_param("s", $equipo_nombre_edit);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();
        if ($r) {
            $equipo = (int)$r['id'];
        } else {
            $ins = $conn->prepare("INSERT INTO equipos (nom_equipo) VALUES (?)");
            $ins->bind_param("s", $equipo_nombre_edit);
            $ins->execute();
            $equipo = $ins->insert_id;
            $ins->close();
        }
    }
    /* ========= 2) MARCA (ANTES DEL MODELO) ========= */
    $marca_edit_mode   = ($_POST['marca_edit_mode'] ?? '0') === '1';
    $marca_nombre_edit = trim($_POST['marca_nombre_edit'] ?? '');
    if ($marca_edit_mode && $marca_nombre_edit !== '') {
        $q = $conn->prepare("SELECT id_marcas FROM marcas
            WHERE UPPER(nom_marca)=UPPER(?) AND equipo_id=?");
        $q->bind_param("si", $marca_nombre_edit, $equipo);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();
        if ($r) {
            $marca_id = (int)$r['id_marcas'];
        } else {
            $ins = $conn->prepare("INSERT INTO marcas (nom_marca, equipo_id)
                VALUES (?, ?)");
            $ins->bind_param("si", $marca_nombre_edit, $equipo);
            $ins->execute();
            $marca_id = $ins->insert_id;
            $ins->close();
        }
    }
    if (!$marca_id) {
        throw new Exception('Marca inválida.');
    }
    /* ========= 3) MODELO (DEPENDIENTE DE MARCA) ========= */
    $modelo_edit_mode   = ($_POST['modelo_edit_mode'] ?? '0') === '1';
    $modelo_nombre_edit = trim($_POST['modelo_nombre_edit'] ?? '');
    if ($modelo_edit_mode && $modelo_nombre_edit !== '') {
        $q = $conn->prepare("SELECT id FROM modelos
            WHERE UPPER(num_modelos)=UPPER(?) AND marca_id=?");
        $q->bind_param("si", $modelo_nombre_edit, $marca_id);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();
        if ($r) {
            $modelo = (int)$r['id'];
        } else {
            $ins = $conn->prepare("INSERT INTO modelos (num_modelos, marca_id)
                VALUES (?, ?)");
            $ins->bind_param("si", $modelo_nombre_edit, $marca_id);
            $ins->execute();
            $modelo = $ins->insert_id;
            $ins->close();
        }
    }
    if ($modelo <= 0) {
        throw new Exception('Modelo inválido.');
    }
    /* ========= 4) UPDATE ========= */
    $sql = "UPDATE dispositivos SET
            equipo=?, modelo=?, serie=?, mac=?, servidor=?, vms=?,
            `switch`=?, puerto=?, area=?, estado=?,
            fecha_instalacion=?, fecha=?, observaciones=?,
            imagen=?, imagen2=?, imagen3=?, qr=?,
            `user`=?, `pass`=?,
            marca_id=?, alarma_id=?, zona_alarma=?,
            tipo_sensor=?, cctv_id=?,
            tiene_analitica=?, analiticas=?
        WHERE id=?";
    $types = "iisssssssisssssssssiissiisi"; // Original
    // $types = "iissssssssisssssssssissssisii"; // Error: demasiados s
    // $types = "iisssssssisssssssssiissiis"; // Corregido: eliminado un 'i'
    $up = $conn->prepare($sql);
    $up->bind_param(
        $types,
        $equipo, $modelo, $serie, $mac, $servidor, $vms,
        $switchTxt, $puerto, $area, $estado,
        $fecha_instalacion, $fecha, $observaciones,
        $imagen, $imagen2, $imagen3, $qr,
        $usuarioDis, $contrasenaDis,
        $marca_id, $alarma_id, $zona_alarma,
        $tipo_sensor, $cctv_id,
        $tiene_analitica, $analiticas,
        $id);
    $up->execute();
    $up->close();
/* Guardar nuevas imágenes en historial */
if (!empty($nuevasImagenes)) {
    $anioActual = date('Y');
    $stmtImg = $conn->prepare("INSERT INTO dispositivos_imagenes 
        (dispositivo_id, anio, imagen)
        VALUES (?, ?, ?)");
        foreach ($nuevasImagenes as $img) {
            $stmtImg->bind_param("iis", $id, $anioActual, $img);
            $stmtImg->execute();
        }
    $stmtImg->close();
}
$conn->commit();
/* ENVIAR CORREO NOTIFICACION */
 $destinatarios = ['notificacionescesiss@gmail.com'];
// URL para ver el dispositivo (usa BASE_URL si existe; si no, link relativo)
$urlDispositivo = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/views/dispositivos/device.php?id=' . $id;
if (!$urlDispositivo) $urlDispositivo = 'device.php?id=' . $id;
// Asignamos nombres a mostrar en correo respetando tu HTML original
// (si por alguna razón siguen vacíos, caen al legacy leído al inicio
// Estilo 
  $asunto = 'CESISS: Nuevo dispositivo editado (EQUIPO ' . $equipo. ')';
  $htmlCorreo = '
  <div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
    <div style="background:#0ea5e9;color:#fff;padding:14px 18px">
      <h2 style="margin:0;font-size:18px">Nuevo dispositivo actualizado</h2>
    </div>
    <div style="padding:16px">
      <p style="margin:0 0 10px 0">' .h($_SESSION['nombre'] ?? 'desconocido').' ha actualizado un nuevo dispositivo en <b>CESISS</b>.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin-top:10px">
        <tbody>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px"><b>Equipo</b></td><td style="border:1px solid #e5e7eb;padding:8px">'.h($equipo).'</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px"><b>Marca</b></td><td style="border:1px solid #e5e7eb;padding:8px">'.h($marca)?? 'Sin marca'.'</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px"><b>Modelo</b></td><td style="border:1px solid #e5e7eb;padding:8px">'.h($modelo) ?? 'Sin modelo'.'</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px"><b>Serie</b></td><td style="border:1px solid #e5e7eb;padding:8px">'.h($serie).'</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px"><b>MAC</b></td><td style="border:1px solid #e5e7eb;padding:8px">'.h($mac).'</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px"><b>Estado</b></td><td style="border:1px solid #e5e7eb;padding:8px">ACTIVO</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px"><b>Usuario</b></td><td style="border:1px solid #e5e7eb;padding:8px">'.h($_SESSION['nombre'] ?? 'desconocido').'</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px"><b>Fecha/Hora</b></td><td style="border:1px solid #e5e7eb;padding:8px">'.date('Y-m-d H:i:s').'</td></tr>
          '.($observaciones !== '' ? '<tr><td style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px;vertical-align:top"><b>Observaciones</b></td><td style="border:1px solid #e5e7eb;padding:8px">'.nl2br(h($observaciones)).'</td></tr>' : '').'
        </tbody>
      </table>
      <div style="margin-top:16px">
        <a href="'.h($urlDispositivo).'" 
           style="display:inline-block;padding:10px 16px;background:#0ea5e9;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold">
           Ver dispositivo
        </a>
      </div>
      <p style="margin-top:14px;font-size:12px;color:#6b7280">Este mensaje fue generado automáticamente por CESISS.</p>
    </div>
  </div>';
  // Enviar (no bloquea si falla)
  try {
    enviarNotificacion($asunto, $htmlCorreo, $destinatarios);
    header("Location: device.php?id=$id");
    exit;
  } catch (Exception $e_i) {
    echo "No se envio el la notficacion al correo".$e_i->getMessage(). "/n" ;
    throw new Exception("Error escalado desde el interno", 0, $e_i);
  }
} catch (Throwable $e) {
    $conn->rollback();
    die('Error al actualizar: ' . htmlspecialchars($e->getMessage()));
}