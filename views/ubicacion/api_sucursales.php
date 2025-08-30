<?php
// views/ubicacion/api_sucursales.php
require_once __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/db.php';
verificarAutenticacion();

header('Content-Type: application/json; charset=utf-8');

$ciudad_id    = isset($_GET['ciudad_id']) ? (int)$_GET['ciudad_id'] : 0; // (no se usa en el SQL, pero lo dejamos por coherencia)
$municipio_id = isset($_GET['municipio_id']) ? (int)$_GET['municipio_id'] : 0;

if ($municipio_id <= 0) { echo json_encode([]); exit; }

try {
  $stmt = $conn->prepare("
    SELECT id, nom_sucursal
    FROM sucursales
    WHERE municipio_id = ?
    ORDER BY nom_sucursal ASC
  ");
  $stmt->bind_param("i", $municipio_id);
  $stmt->execute();
  $res = $stmt->get_result();

  $data = [];
  while ($r = $res->fetch_assoc()) {
    $data[] = [
      'id'           => (int)$r['id'],
      'nom_sucursal' => $r['nom_sucursal'],
    ];
  }

  echo json_encode($data);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error al cargar sucursales']);
}
