<?php
// views/ubicacion/api_municipios.php
require_once __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/db.php';
verificarAutenticacion();

header('Content-Type: application/json; charset=utf-8');

$ciudad_id = isset($_GET['ciudad_id']) ? (int)$_GET['ciudad_id'] : 0;
if ($ciudad_id <= 0) { echo json_encode([]); exit; }

try {
  $stmt = $conn->prepare("
    SELECT ID AS id, nom_municipio
    FROM municipios
    WHERE ciudad_id = ?
    ORDER BY nom_municipio ASC
  ");
  $stmt->bind_param("i", $ciudad_id);
  $stmt->execute();
  $res = $stmt->get_result();

  $data = [];
  while ($r = $res->fetch_assoc()) {
    // Normalizamos claves en minúsculas
    $data[] = [
      'id'            => (int)$r['id'],
      'nom_municipio' => $r['nom_municipio'],
    ];
  }
  echo json_encode($data);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error al cargar municipios']);
}
