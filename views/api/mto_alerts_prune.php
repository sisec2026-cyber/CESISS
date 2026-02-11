<?php
// /sisec-ui/views/api/mto_alerts_prune.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador']); // ajusta si aplica
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=UTF-8');

try {
  // Asume: notificaciones(tipo='mto', evento_id INT) y tabla de eventos: mantenimiento_eventos(id INT)
  // Borra notificaciones mto cuyo evento ya no exista
  $sql = "
    DELETE n FROM notificaciones n
    LEFT JOIN mantenimiento_eventos me ON me.id = n.evento_id
    WHERE n.tipo = 'mto' AND n.evento_id IS NOT NULL AND me.id IS NULL
  ";
  $conn->query($sql);

  echo json_encode(['ok'=>true,'pruned'=> $conn->affected_rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
