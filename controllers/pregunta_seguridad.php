<?php
require_once '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../views/recuperar_contrasena.php');
  exit;
}

$usuario = trim($_POST['nombre']);
$stmt = $conexion->prepare("SELECT id, pregunta_seguridad FROM usuarios WHERE nombre = ?");
$stmt->bind_param('s', $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header('Location: ../views/recuperar_contrasena.php?error=Usuario no encontrado');
  exit;
}

$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Pregunta de seguridad</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <div class="card p-4" style="max-width: 500px; width: 100%;">
    <h5>Pregunta de seguridad</h5>
    <p><?= htmlspecialchars($user['pregunta_seguridad']) ?></p>
    <form method="POST" action="verificar_respuesta.php">
      <input type="hidden" name="usuario_id" value="<?= $user['id'] ?>" />
      <div class="mb-3">
        <input type="text" name="respuesta" class="form-control" placeholder="Tu respuesta" required />
      </div>
      <button type="submit" class="btn btn-success w-100">Verificar respuesta</button>
    </form>
  </div>
</body>
</html>
