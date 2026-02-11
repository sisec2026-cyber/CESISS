<?php
// /sisec-ui/views/api/mantenimiento_events_update.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Técnico','Prevencion','Distrital']);
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
    exit;
  }

  // CSRF
  if (empty($_SESSION['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'CSRF ausente']);
    exit;
  }
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'CSRF inválido']);
    exit;
  }

  $id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $start = trim((string)($_POST['start'] ?? '')); // YYYY-MM-DD
  $end   = trim((string)($_POST['end']   ?? '')); // YYYY-MM-DD (INCLUSIVO)

  if ($id <= 0) throw new Exception('ID inválido.');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)) throw new Exception('Fecha inicio inválida.');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end))   throw new Exception('Fecha fin inválida.');
  if ($end < $start) throw new Exception('La fecha fin no puede ser menor que la de inicio.');

  // ¿La tabla tiene columnas de rango?
  $hasRangeCols = $conn->query("SHOW COLUMNS FROM mantenimiento_eventos LIKE 'fecha_inicio'")->num_rows > 0;

  if ($hasRangeCols) {
    $st = $conn->prepare("UPDATE mantenimiento_eventos SET fecha_inicio=?, fecha_fin=? WHERE id=?");
    if (!$st) throw new Exception('Error de BD (prepare).');
    $st->bind_param('ssi', $start, $end, $id);
  } else {
    // Legacy: solo guardamos fecha de inicio
    $st = $conn->prepare("UPDATE mantenimiento_eventos SET fecha=? WHERE id=?");
    if (!$st) throw new Exception('Error de BD (prepare legacy).');
    $st->bind_param('si', $start, $id);
  }

  if (!$st->execute()) throw new Exception('No se pudo actualizar.');
  echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}