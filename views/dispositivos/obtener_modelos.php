<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Técnico', 'Mantenimientos']);

include __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$equipo_id = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;
if ($equipo_id <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "
  SELECT m.id, m.num_modelos
  FROM modelos m
  INNER JOIN marcas ma ON ma.id_marcas = m.marca_id
  WHERE ma.equipo_id = ?
  ORDER BY m.num_modelos ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $equipo_id);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $out[] = ['id' => (int)$row['id'], 'num_modelos' => $row['num_modelos']];
}
$stmt->close();

echo json_encode($out);
