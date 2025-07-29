<?php
include __DIR__ . '/../../includes/db.php';

$municipio_id = intval($_GET['municipio_id'] ?? 0);
$sucursales = [];

if ($municipio_id > 0) {
    $stmt = $conn->prepare("SELECT ID, nom_sucursal FROM sucursales WHERE municipio_id = ? ORDER BY nom_sucursal");
    $stmt->bind_param("i", $municipio_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $sucursales[] = $row;
    }
}

echo json_encode($sucursales);
