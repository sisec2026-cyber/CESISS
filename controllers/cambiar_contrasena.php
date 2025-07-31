<?php
require_once '../includes/conexion.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID de usuario requerido.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nueva = $_POST['nueva_contrasena'];
  if (strlen($nueva) < 6) {
    die("La contraseña debe tener al menos 6 caracteres.");
  }
  $hash = password_hash($nueva, PASSWORD_DEFAULT);
  $stmt = $conexion->prepare("UPDATE usuarios SET clave = ? WHERE id = ?");
  $stmt->bind_param('si', $hash, $id);
  if ($stmt->execute()) {
    echo "Contraseña actualizada correctamente. <a href='../login.php'>Iniciar sesión</a>";
  } else {
    echo "Error al actualizar la contraseña.";
  }
  exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Cambiar contraseña</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <div class="card p-4" style="max-width: 400px; width: 100%;">
    <h4>Nueva contraseña</h4>
    <form method="POST">
      <div class="mb-3">
        <input type="password" name="nueva_contrasena" placeholder="Nueva contraseña" class="form-control" required />
      </div>
      <button type="submit" class="btn btn-primary w-100">Guardar</button>
    </form>
  </div>
</body>
</html>
