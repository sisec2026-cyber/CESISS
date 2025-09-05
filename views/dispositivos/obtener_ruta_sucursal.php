<?php
// views/dispositivos/obtener_ruta_sucursal.php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Invitado','Técnico','Capturista','Distrital','Prevencion','Monitorista']);
header('Content-Type: application/json; charset=utf-8');

include __DIR__ . '/../../includes/db.php';

$sucursalId = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : 0;
if ($sucursalId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Parámetro sucursal_id inválido']);
  exit;
}

$sql = "
  SELECT 
    s.ID                AS sucursal_id,
    m.ID                AS municipio_id,
    c.ID                AS ciudad_id,
    s.nom_sucursal,
    m.nom_municipio,
    c.nom_ciudad
  FROM sucursales s
  INNER JOIN municipios m ON s.municipio_id = m.ID
  INNER JOIN ciudades  c ON m.ciudad_id    = c.ID
  WHERE s.ID = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $sucursalId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
  http_response_code(404);
  echo json_encode(['error' => 'Sucursal no encontrada']);
  exit;
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
