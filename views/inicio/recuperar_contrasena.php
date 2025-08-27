<?php
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Recuperar contraseña</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="../../public/css/style.css">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <div class="card p-4" style="max-width: 400px; width: 100%;">
    <h4>Recuperar contraseña</h4>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="../../controllers/pregunta_seguridad.php">
      <div class="mb-3">
        <label for="nombre" class="form-label">Nombre de usuario</label>
        <input type="text" name="nombre" id="nombre" class="form-control" required />
      </div>
      <button type="submit" class="btn btn-primary w-100">Continuar</button>
    </form>
  </div>
</body>
</html>
