<?php
require_once '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../views/recuperar_contrasena.php');
  exit;
}

$usuario_id = $_POST['usuario_id'];
$respuesta = trim($_POST['respuesta']);

$stmt = $conexion->prepare("SELECT respuesta_seguridad_hash FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Usuario inválido.");
}

$row = $result->fetch_assoc();
$hash = $row['respuesta_seguridad_hash'];

if (password_verify($respuesta, $hash)) {
  // Respuesta correcta, redirige a cambio de contraseña
  header("Location: cambiar_contrasena.php?id=$usuario_id");
} else {
  die("Respuesta incorrecta.");
}
