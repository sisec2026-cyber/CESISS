<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Técnico', 'Mantenimientos', 'Capturista']);

include __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$equipo_id = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;
if ($equipo_id <= 0) { echo json_encode([]); exit; }

$stmt = $conn->prepare("SELECT id_marcas AS id, nom_marca FROM marcas WHERE equipo_id = ? ORDER BY nom_marca ASC");
$stmt->bind_param("i", $equipo_id);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($r = $res->fetch_assoc()) {
  $out[] = ['id' => (int)$r['id'], 'nom_marca' => (string)$r['nom_marca']];
}
$stmt->close();

echo json_encode($out);
