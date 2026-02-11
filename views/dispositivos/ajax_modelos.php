<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$marca_id = isset($_GET['marca_id']) ? (int)$_GET['marca_id'] : 0;
if(!$marca_id) { echo json_encode([]); exit; }

$stmt = $conn->prepare("SELECT id, nom_modelo FROM modelos WHERE marca_id = ? ORDER BY nom_modelo ASC");
$stmt->bind_param('i', $marca_id);
$stmt->execute();
$res = $stmt->get_result();
$modelos = [];
while($row = $res->fetch_assoc()){
    $modelos[] = $row;
}
$stmt->close();

echo json_encode($modelos);