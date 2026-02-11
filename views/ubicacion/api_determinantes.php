<?php
// views/ubicacion/api_determinantes.php
require_once __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/db.php';
verificarAutenticacion();

header('Content-Type: application/json; charset=utf-8');

$sucursal_id = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : 0;
if ($sucursal_id <= 0) { echo json_encode([]); exit; }

try {
  $stmt = $conn->prepare("
    SELECT id, nom_determinante AS determinante
    FROM determinantes
    WHERE sucursal_id = ?
      AND TRIM(nom_determinante) <> ''          -- evita vacías del dump
    ORDER BY CAST(nom_determinante AS UNSIGNED)  -- orden numérico si aplica
           , nom_determinante
  ");
  $stmt->bind_param("i", $sucursal_id);
  $stmt->execute();
  $res = $stmt->get_result();

  $data = [];
  while ($r = $res->fetch_assoc()) {
    $data[] = [
      'id'           => (int)$r['id'],
      'determinante' => $r['determinante'],      // clave que espera tu JS
    ];
  }

  echo json_encode($data);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error al cargar determinantes']);
}