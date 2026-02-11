<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
include __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$id = (int)($_POST['id'] ?? 0);
$to = $_POST['to'] ?? 'read';
if (!$id || !in_array($to, ['read','dismissed','resolved'])) {
  echo json_encode(['ok'=>false,'error'=>'parámetros inválidos']); exit;
}

if ($to === 'resolved') {
  $sql = "UPDATE mto_alertas SET status='resolved', resolved_at=NOW() WHERE id=$id";
} else {
  $sql = "UPDATE mto_alertas SET status='$to' WHERE id=$id";
}
$ok = $conn->query($sql);
echo json_encode(['ok'=>(bool)$ok]);
