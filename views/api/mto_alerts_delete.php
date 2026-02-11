<?php
// /sisec-ui/views/api/mto_alerts_delete.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador']); // ajusta si otros roles deben poder borrar
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=UTF-8');

try {
  // Acepta cualquiera de estos identificadores
  $id          = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
  $evento_id   = isset($_POST['evento_id']) ? trim((string)$_POST['evento_id']) : '';
  $sucursal_id = isset($_POST['sucursal_id']) ? trim((string)$_POST['sucursal_id']) : '';

  if ($id === '' && $evento_id === '' && $sucursal_id === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Faltan parámetros']); exit;
  }

  // Suposición: tus notificaciones viven en `notificaciones`
  // y guardan tipo='mto' y (evento_id, sucursal_id) si aplica.
  // Ajusta nombres de columnas según tu esquema real.
  $where = [];
  $types = '';
  $vals  = [];

  $where[] = "tipo = 'mto'";
  if ($id !== '')          { $where[] = "id = ?";           $types.='i'; $vals[]=(int)$id; }
  if ($evento_id !== '')   { $where[] = "evento_id = ?";    $types.='i'; $vals[]=(int)$evento_id; }
  if ($sucursal_id !== '') { $where[] = "sucursal_id = ?";  $types.='i'; $vals[]=(int)$sucursal_id; }

  $sql = "DELETE FROM notificaciones WHERE ".implode(' AND ', $where)." LIMIT 1";
  $stmt = $conn->prepare($sql);
  if ($types !== '') { $stmt->bind_param($types, ...$vals); }
  $stmt->execute();

  echo json_encode(['ok'=>true,'deleted_rows'=>$stmt->affected_rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
