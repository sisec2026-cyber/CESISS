<?php
include __DIR__ . '/../../includes/db.php';

$ciudad_id = intval($_GET['ciudad_id'] ?? 0);
$municipios = [];

if ($ciudad_id > 0) {
    $stmt = $conn->prepare("SELECT ID, nom_municipio FROM municipios WHERE ciudad_id = ? ORDER BY nom_municipio");
    $stmt->bind_param("i", $ciudad_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $municipios[] = $row;
    }
}

echo json_encode($municipios);
