<?php
// Si ya hay sesión iniciada, redirige al index
if (isset($_SESSION['usuario_id'])) {
  header('Location: views/index.php');
  exit;
}

// Inicializa variables para evitar errores
$error = $_GET['error'] ?? null;
$redirect = $_GET['redirect'] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SISEC - Iniciar Sesión</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      background: linear-gradient(to bottom, #78c3f3, #007ea7);
      font-family: Arial, sans-serif;
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .login-box {
      background-color: #ffffff;
      padding: 2rem;
      border-radius: 10px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
      text-align: center;
      color: #212529;
    }

    .login-box h2 {
      margin-bottom: 10px;
    }

    .login-box input[type="text"],
    .login-box input[type="password"] {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border: 1px solid #ced4da;
      border-radius: 5px;
      background-color: #f8f9fa;
      color: #212529;
    }

    .login-box input::placeholder {
      color: #6c757d;
    }

    .login-box input:focus {
      background-color: #ffffff;
      border-color: #0d6efd;
      outline: none;
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .login-box button {
      width: 100%;
      padding: 10px;
      background: linear-gradient(to right, #36d1dc, #5b86e5);
      border: none;
      color: white;
      font-weight: bold;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 10px;
    }

    .login-box .error {
      background: #f8d7da;
      color: #721c24;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 10px;
    }

    .login-box .form-check {
      text-align: left;
      margin-top: 10px;
    }

    .login-box .form-check-label {
      color: #212529;
    }

    .login-box a {
      color: #007ea7;
      text-decoration: underline;
      font-size: 0.9rem;
    }

    .login-box a:hover {
      color: #005f7f;
    }
  </style>
</head>

<body>
  <div class="login-box">
    <img src="/sisec-ui/public/img/logo.png" alt="Logo SISEC" style="max-height: 100px; margin-bottom: 10px;">

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="controllers/login_procesar.php" method="POST">
      <input type="text" name="nombre" placeholder="Usuario" required>
      <input type="password" name="password" placeholder="Contraseña" required>

      <?php if ($redirect): ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <?php endif; ?>

      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
        <label class="form-check-label" for="remember_me">
          Mantener sesión iniciada
        </label>
      </div>

      <button type="submit">INICIA SESIÓN</button>
    </form>

    <p class="mt-3"><a href="views/recuperar_contrasena.php">¿Olvidaste tu contraseña?</a></p>
  </div>
</body>
</html>
