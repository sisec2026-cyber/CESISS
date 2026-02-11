<?php
// /sisec-ui/views/api/sucursales_set_status.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Técnico']); // ajusta si aplica
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');
// Evita que se “cuele” output accidental
if (function_exists('ob_get_level')) {
  while (ob_get_level() > 0) ob_end_clean();
}

try {
  // 1) Validar método
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'Método no permitido']);
    exit;
  }

  // 2) CSRF
  if (empty($_POST['csrf']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'CSRF inválido']);
    exit;
  }

  // 3) Parámetros
  $sucursalId = isset($_POST['sucursal_id']) ? (int)$_POST['sucursal_id'] : 0;
  $estado     = isset($_POST['estado']) ? trim((string)$_POST['estado']) : '';

  if ($sucursalId <= 0) {
    echo json_encode(['ok'=>false, 'error'=>'sucursal_id inválido']);
    exit;
  }

  // Permitidos: hecho | pendiente | proceso | siguiente | auto
  $permitidos = ['hecho','pendiente','proceso','siguiente','auto'];
  if (!in_array($estado, $permitidos, true)) {
    echo json_encode(['ok'=>false, 'error'=>'estado inválido']);
    exit;
  }

  // 4) Actualizar status_manual en sucursales
  if ($estado === 'auto') {
    $sql = "UPDATE sucursales SET status_manual = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $sucursalId);
  } else {
    $sql = "UPDATE sucursales SET status_manual = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $estado, $sucursalId);
  }

  if (!$stmt->execute()) {
    echo json_encode(['ok'=>false, 'error'=>'Error al actualizar']);
    exit;
  }

  echo json_encode(['ok'=>true]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Excepción', 'detail'=>$e->getMessage()]);
  exit;
}
