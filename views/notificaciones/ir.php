<?php
// /sisec-ui/views/notificaciones/ir.php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();

include __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  die('Notificación inválida.');
}
$notifId = (int)$_GET['id'];

// 1) Traer notificación
$stmt = $conn->prepare("
  SELECT id, mensaje, visto, dispositivo_id
  FROM notificaciones
  WHERE id = ?
");
$stmt->bind_param("i", $notifId);
$stmt->execute();
$notif = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$notif) {
  die('Notificación no encontrada.');
}

// 2) Marcar como vista (opcional: actualiza también 'vista' si la usas)
$upd = $conn->prepare("UPDATE notificaciones SET visto = 1 WHERE id = ?");
$upd->bind_param("i", $notifId);
$upd->execute();
$upd->close();

// 3) Resolver destino SIN agregar columnas
$destino = '/sisec-ui/views/notificaciones/notificaciones.php';

// (a) Directo por dispositivo_id
$deviceId = null;
if (!empty($notif['dispositivo_id']) && ctype_digit((string)$notif['dispositivo_id'])) {
  $deviceId = (int)$notif['dispositivo_id'];
}

// (b) Fallback: intenta extraer "ID #123" o "device.php?id=123" del mensaje
if ($deviceId === null && !empty($notif['mensaje'])) {
  $msg = $notif['mensaje'];

  // Busca "ID #123"
  if (preg_match('/ID\\s*#\\s*(\\d+)/i', $msg, $m)) {
    $deviceId = (int)$m[1];
  }
  // O bien una URL que contenga "id=123"
  if ($deviceId === null && preg_match('/\\bid\\s*=\\s*(\\d+)/i', $msg, $m)) {
    $deviceId = (int)$m[1];
  }

  // Verifica que exista en dispositivos (evita mandar a un 404)
  if ($deviceId !== null) {
    $chk = $conn->prepare("SELECT id FROM dispositivos WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $deviceId);
    $chk->execute();
    $exists = $chk->get_result()->num_rows > 0;
    $chk->close();
    if (!$exists) {
      $deviceId = null; // no existe; mejor no redirigir mal
    }
  }
}

if ($deviceId !== null) {
  $destino = '/sisec-ui/views/dispositivos/device.php?id=' . $deviceId;
}

// 4) Redirigir
header('Location: ' . $destino);
exit;
