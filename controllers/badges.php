<?php
// /sisec-ui/controllers/badges.php
// No iniciamos aquí; lo hará auth.php con guardia
require_once __DIR__ . '/../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador']);

require_once __DIR__ . '/../includes/conexion.php';

// Asegura que no haya Notices en la salida del JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $count = 0;
    if (isset($pdo)) {
        $st = $pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE esta_aprobado = 0");
        $count = (int)($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    } elseif (isset($conexion)) {
        $rs = $conexion->query("SELECT COUNT(*) AS c FROM usuarios WHERE esta_aprobado = 0");
        $count = (int)(($rs && $rs->num_rows) ? $rs->fetch_assoc()['c'] : 0);
    }
    echo json_encode(['ok' => true, 'count' => $count]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}

