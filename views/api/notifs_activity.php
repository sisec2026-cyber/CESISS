<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
include __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
  // Parámetros opcionales
  $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 20;
  $onlyDevices = isset($_GET['onlyDevices']) ? (int)$_GET['onlyDevices'] : 0; // 1: solo notifs con dispositivo_id

  // OJO: NO excluimos superadmin ni al propio usuario para que no se “pierdan” eventos
  $where = [];
  $types = '';
  $params = [];

  if ($onlyDevices) {
    $where[] = 'n.dispositivo_id IS NOT NULL';
  }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  $sql = "
    SELECT
      n.id,
      n.mensaje,
      n.fecha,
      n.visto,
      n.dispositivo_id
    FROM notificaciones n
    $whereSql
    ORDER BY n.fecha DESC
    LIMIT ?
  ";
  $types .= 'i';
  $params[] = $limit;

  $stmt = $conn->prepare($sql);
  if ($types) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();

  $items = [];
  while ($row = $res->fetch_assoc()) {
    $id   = (int)$row['id'];
    $msg  = (string)$row['mensaje'];
    $ts   = $row['fecha'] ? (new DateTime($row['fecha']))->format(DateTime::ATOM) : null;
    $read = (int)$row['visto'] === 1;

    // Extrae URL embebida [[url:/ruta]]
    $url = null;
    if (preg_match('/\[\[url:([^\]]+)\]\]/', $msg, $m)) {
      $url = $m[1];
      $msg = trim(str_replace($m[0], '', $msg));
    }

    // Limpia “ID #123” si viene incrustado
    $msg = preg_replace('/\b(?:con\s+)?ID\s*#\s*\d+\b/i', '', $msg);
    $msg = preg_replace('/\s{2,}/', ' ', $msg);
    $msg = preg_replace('/\s+([,.;:!?)])/u', '$1', $msg);
    $msg = trim($msg);

    // Título simple por heurística
    $title = 'Actividad';
    if (stripos($msg, 'registr') !== false) $title = 'Registro de dispositivo';
    if (stripos($msg, 'edit') !== false)    $title = 'Edición de dispositivo';

    // Si no hubo url y sí hay dispositivo_id, arma una
    if (!$url && !empty($row['dispositivo_id'])) {
      $url = '/sisec-ui/views/dispositivos/device.php?id='.(int)$row['dispositivo_id'];
    }
    // Fallback al listado general
    if (!$url) {
      $url = '/sisec-ui/views/notificaciones/notificaciones.php';
    }

    $items[] = [
      'id'    => $id,
      'tsISO' => $ts,
      'title' => $title,
      'text'  => $msg,
      'url'   => $url,
      'read'  => $read,
      'type'  => 'activity'
    ];
  }
  $stmt->close();

  echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
