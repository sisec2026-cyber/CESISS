<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
include __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'payload inválido']); exit; }
$alerts = $data['alerts'] ?? [];
if (!is_array($alerts)) { echo json_encode(['ok'=>false,'error'=>'alerts inválido']); exit; }

$sql = "INSERT INTO mto_alertas
  (sucursal_id, evento_id, titulo, start_date, end_date, days_left, severity, status, sucursal_label, ubicacion_label)
  VALUES (?, ?, ?, ?, ?, ?, ?, 
          IF(status='resolved', 'resolved', 'new'),
          ?, ?)
  ON DUPLICATE KEY UPDATE
    titulo=VALUES(titulo),
    start_date=VALUES(start_date),
    end_date=VALUES(end_date),
    days_left=VALUES(days_left),
    severity=VALUES(severity),
    -- si ya estaba dismissed/resolved respetamos eso; si no, lo ponemos en 'new' para que vuelva a aparecer
    status=IF(status IN ('dismissed','resolved'), status, 'new'),
    sucursal_label=VALUES(sucursal_label),
    ubicacion_label=VALUES(ubicacion_label),
    updated_at=CURRENT_TIMESTAMP";

$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['ok'=>false,'error'=>$conn->error]); exit; }

foreach ($alerts as $a) {
  $sid   = (int)($a['sucursal_id'] ?? 0);
  $evid  = (string)($a['evento_id'] ?? '');
  $tit   = (string)($a['titulo'] ?? 'Mantenimiento');
  $sd    = (string)($a['start_date'] ?? '');
  $ed    = (string)($a['end_date'] ?? '');
  $dl    = (int)($a['days_left'] ?? 0);
  $sev   = in_array($a['severity'] ?? 'warning', ['info','warning','danger']) ? $a['severity'] : 'warning';
  $slabel= (string)($a['sucursal_label'] ?? '');
  $ulabel= (string)($a['ubicacion_label'] ?? '');

  $stmt->bind_param('issssisss', $sid, $evid, $tit, $sd, $ed, $dl, $sev, $slabel, $ulabel);
  $stmt->execute();
}
$stmt->close();
echo json_encode(['ok'=>true, 'count'=>count($alerts)]);
