<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
include __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$status = $_GET['status'] ?? ''; // '', 'new', 'read', 'dismissed', 'resolved'
$severity = $_GET['severity'] ?? ''; // '', 'warning','danger','info'
$limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));

$cond = [];
if ($status && in_array($status, ['new','read','dismissed','resolved'])) $cond[] = "status = '".$conn->real_escape_string($status)."'";
if ($severity && in_array($severity, ['info','warning','danger'])) $cond[] = "severity = '".$conn->real_escape_string($severity)."'";
$where = $cond ? ('WHERE '.implode(' AND ', $cond)) : '';

$sql = "SELECT id, sucursal_id, evento_id, titulo, start_date, end_date, days_left, severity, status, sucursal_label, ubicacion_label, created_at, updated_at
        FROM mto_alertas
        $where
        ORDER BY severity='danger' DESC, days_left ASC, updated_at DESC
        LIMIT $limit";

$res = $conn->query($sql);
$out = [];
while ($row = $res->fetch_assoc()) $out[] = $row;

echo json_encode(['ok'=>true, 'alerts'=>$out]);
