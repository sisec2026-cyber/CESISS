<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador']);

include __DIR__ . '/../../includes/db.php';

// Actualizar todas las notificaciones para que el admin las marque como vistas
$stmt = $conn->prepare("UPDATE notificaciones SET visto = 1 WHERE visto = 0");
$stmt->execute();

echo json_encode(['success' => true]);