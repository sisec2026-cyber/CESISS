<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$tipo = trim($_GET['tipo'] ?? '');
if ($tipo === '') { echo json_encode([]); exit; }

$stmt = $conn->prepare("SELECT nom_marca FROM marcas WHERE tipo_camara = ? ORDER BY nom_marca ASC");
$stmt->bind_param('s', $tipo);
$stmt->execute();
$res = $stmt->get_result();

$marcas = [];
while ($row = $res->fetch_assoc()) $marcas[] = $row;

echo json_encode($marcas, JSON_UNESCAPED_UNICODE);