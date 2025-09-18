<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Técnico']);
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Método inválido']);
  exit;
}

$csrf        = $_POST['csrf'] ?? '';
$sucursal_id = $_POST['sucursal_id'] ?? '';
$estado      = $_POST['estado'] ?? '';

if (empty($csrf) || empty($sucursal_id) || $estado==='') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Faltan parámetros']);
  exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'CSRF inválido']);
  exit;
}

$allow = ['hecho','pendiente','proceso','siguiente','auto'];
if (!in_array($estado, $allow, true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Estado inválido']);
  exit;
}

$id = (int)$sucursal_id;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'ID inválido']);
  exit;
}

if ($estado === 'auto') {
  $sql = "UPDATE sucursales SET status_manual = NULL WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);
} else {
  $sql = "UPDATE sucursales SET status_manual = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('si', $estado, $id);
}

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB error: '.$conn->error]);
  exit;
}
echo json_encode(['ok'=>true]);
