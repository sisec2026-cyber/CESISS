<?php
// /sisec-ui/views/api/mto_audit_list.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
require_once __DIR__ . '/../../includes/db.php';

$sucursal_id = (int)($_GET['sucursal_id'] ?? 0);
$evento_id   = (int)($_GET['evento_id']   ?? 0);
$limit       = (int)($_GET['limit']       ?? 20);

if ($sucursal_id<=0) {
  echo json_encode(['ok'=>false,'error'=>'sucursal_id requerido']); exit;
}

$where = "WHERE a.sucursal_id={$sucursal_id}";
if ($evento_id>0) $where .= " AND a.evento_id={$evento_id}";

$sql = "
  SELECT a.id, a.sucursal_id, a.evento_id, a.actor_id, a.accion, a.detalles_json, a.created_at,
         u.nombre as actor_nombre, u.email as actor_email
  FROM mto_audit a
  LEFT JOIN usuarios u ON u.id = a.actor_id
  {$where}
  ORDER BY a.created_at DESC
  LIMIT {$limit}
";
$res = $conn->query($sql);
$out = [];
while ($row = $res->fetch_assoc()) {
  $row['detalles'] = $row['detalles_json'] ? json_decode($row['detalles_json'], true) : null;
  unset($row['detalles_json']);
  $out[] = $row;
}
echo json_encode(['ok'=>true,'items'=>$out]);
