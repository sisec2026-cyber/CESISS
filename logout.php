<?php
session_start();
require_once __DIR__ . '/includes/notificaciones_mailer.php';

// Guarda datos antes de limpiar sesión
$usuario = $_SESSION['nombre'] ?? 'desconocido';

$destinatarios = ['admin1@cesiss.com', 'admin2@cesiss.com'];
$asunto = 'SISEC: Cierre de sesión';
$html   = '
    <h3>Cierre de sesión</h3>
    <p><b>Usuario:</b> '.htmlspecialchars($usuario).'</p>
    <p><b>Fecha/Hora:</b> '.date('Y-m-d H:i:s').'</p>
    <p><b>IP:</b> '.($_SERVER['REMOTE_ADDR'] ?? 'desconocida').'</p>
';

// Enviamos sin bloquear el flujo
enviarNotificacion($asunto, $html, $destinatarios);

// Ahora sí, cerrar sesión
session_unset();
session_destroy();
setcookie('usuario_id', '', time() - 3600, "/"); // Borrar cookie

header('Location: login.php');
exit;
