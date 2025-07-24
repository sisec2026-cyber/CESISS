<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();

$usuarioId = $_POST['id'];
$nombre = $_POST['nombre'];
$password = $_POST['password'] ?? null;
$fotoNombre = null;

// Procesar la imagen si se subió
if (!empty($_FILES['foto']['name'])) {
  $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
  $fotoNombre = uniqid('perfil_') . '.' . $ext;
  $rutaDestino = __DIR__ . '/../../uploads/usuarios/' . $fotoNombre;
  move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);
}

// Actualizar datos
$sql = "UPDATE usuarios SET nombre = ?, ";
$params = [$nombre];
$types = "s";

if (!empty($password)) {
  $sql .= "password = ?, ";
  $params[] = password_hash($password, PASSWORD_DEFAULT);
  $types .= "s";
}

if ($fotoNombre) {
  $sql .= "foto = ?, ";
  $params[] = $fotoNombre;
  $types .= "s";
}

$sql = rtrim($sql, ", ") . " WHERE id = ?";
$params[] = $usuarioId;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

// Actualizar sesión
$_SESSION['nombre'] = $nombre;
if ($fotoNombre) {
  $_SESSION['foto'] = '/sisec-ui/uploads/usuarios' . $fotoNombre;
}

header('Location: perfil.php');
exit;