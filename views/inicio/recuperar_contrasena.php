<?php
require_once __DIR__ . '/../../includes/conexion.php';

$error = null;
$step = 'usuario'; 
$user = null;

// Usuario enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre']) && !isset($_POST['respuesta']) && !isset($_POST['nueva_contrasena'])) {
  $usuario = trim($_POST['nombre']);
  $stmt = $conexion->prepare("SELECT id, pregunta_seguridad FROM usuarios WHERE nombre = ?");
  $stmt->bind_param('s', $usuario);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows === 0) {
    $error = "Usuario no encontrado";
  } else {
    $user = $result->fetch_assoc();
    $step = 'pregunta'; 
  }
}

// Respuesta enviada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respuesta']) && isset($_POST['usuario_id'])) {
  $usuario_id = intval($_POST['usuario_id']);
  $respuesta = trim($_POST['respuesta']);
  $stmt = $conexion->prepare("SELECT respuesta_seguridad_hash, pregunta_seguridad FROM usuarios WHERE id = ?");
  $stmt->bind_param('i', $usuario_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows === 0) {
    $error = "Usuario inválido.";
    $step = 'usuario';
  } else {
    $row = $result->fetch_assoc();
    $hash = $row['respuesta_seguridad_hash'];
    if (password_verify($respuesta, $hash)) {
      $user = ['id' => $usuario_id];
      $step = 'cambiar';
    } else {
      $error = "Respuesta incorrecta.";
      $user = ['id' => $usuario_id, 'pregunta_seguridad' => $row['pregunta_seguridad']];
      $step = 'pregunta';
    }
  }
}

// Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_contrasena']) && isset($_POST['usuario_id'])) {
  $usuario_id = intval($_POST['usuario_id']);
  $nueva = trim($_POST['nueva_contrasena']);
  if (strlen($nueva) < 8) {
    $error = "La contraseña debe tener al menos 8 caracteres.";
    $user = ['id' => $usuario_id];
    $step = 'cambiar';
  } else {
    $hash = password_hash($nueva, PASSWORD_DEFAULT);
    $stmt = $conexion->prepare("UPDATE usuarios SET clave = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $usuario_id);
    if ($stmt->execute()) {
      $mensaje_exito = "Contraseña actualizada correctamente. <a href='index.php'>Iniciar sesión</a>";
      $step = 'finalizado';
    } else {
      $error = "Error al actualizar la contraseña.";
      $user = ['id' => $usuario_id];
      $step = 'cambiar';
    }
  }
}
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
  <div class="card p-4" style="max-width: 500px; width: 100%;">
    <?php if ($step === 'usuario'): ?>
    <h4>Recuperar contraseña</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label for="nombre" class="form-label">Nombre de usuario</label>
          <input type="text" name="nombre" id="nombre" class="form-control" required />
        </div>
        <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
          <button type="submit" class="btn btn-primary w-100">Continuar</button>
          <a href="index.php" class="btn btn-danger flex-grow-1">Cancelar</a>
        </div>
      </form>
      <?php elseif ($step === 'pregunta' && isset($user)): ?>
      <h5>Pregunta de seguridad</h5>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <p><?= htmlspecialchars($user['pregunta_seguridad']) ?></p>
      <form method="POST">
        <input type="hidden" name="usuario_id" value="<?= $user['id'] ?>" />
        <div class="mb-3">
          <input type="text" name="respuesta" class="form-control" placeholder="Tu respuesta" required />
        </div>
        <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
          <button type="submit" class="btn btn-primary w-100">Verificar respuesta</button>
          <a href="index.php" class="btn btn-danger flex-grow-1">Cancelar</a>
        </div>
      </form>
      <?php elseif ($step === 'cambiar' && isset($user)): ?>
      <h4>Nueva contraseña</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="usuario_id" value="<?= $user['id'] ?>" />
        <!-- Campo de nueva contraseña con checklist -->
        <div class="mb-3">
          <label for="clave" class="form-label">Contraseña</label>
          <input type="password" class="form-control" id="clave" name="nueva_contrasena" required>
          <div class="mt-2" id="passwordChecklist">
            <small>
              <span id="checkLength" class="text-danger">Al menos 8 caracteres</span><br>
              <span id="checkUpper" class="text-danger">Al menos una mayúscula</span><br>
              <span id="checkLower" class="text-danger">Al menos una minúscula</span><br>
              <span id="checkNumber" class="text-danger">Al menos un número</span><br>
              <span id="checkSpecial" class="text-danger">Al menos un carácter especial (!@#$%^&*)</span>
            </small>
          </div>
        </div>
        <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
          <button type="submit" class="btn btn-primary w-100">Guardar</button>
          <a href="index.php" class="btn btn-danger flex-grow-1">Cancelar</a>
        </div>
      </form>
      <!-- Script de validación de la contraseña -->
      <script>
      const claveInput = document.getElementById('clave');
      const checkLength = document.getElementById('checkLength');
      const checkUpper  = document.getElementById('checkUpper');
      const checkLower  = document.getElementById('checkLower');
      const checkNumber = document.getElementById('checkNumber');
      const checkSpecial= document.getElementById('checkSpecial');
      claveInput.addEventListener('input', ()=> {
        const val = claveInput.value;
        if(val.length >= 8) checkLength.classList.replace('text-danger','text-success'), checkLength.textContent='✔ Al menos 8 caracteres';
        else checkLength.classList.replace('text-success','text-danger'), checkLength.textContent='Al menos 8 caracteres';
        if(/[A-Z]/.test(val)) checkUpper.classList.replace('text-danger','text-success'), checkUpper.textContent='✔ Al menos una mayúscula';
        else checkUpper.classList.replace('text-success','text-danger'), checkUpper.textContent='Al menos una mayúscula';
        if(/[a-z]/.test(val)) checkLower.classList.replace('text-danger','text-success'), checkLower.textContent='✔ Al menos una minúscula';
        else checkLower.classList.replace('text-success','text-danger'), checkLower.textContent='Al menos una minúscula';
        if(/\d/.test(val)) checkNumber.classList.replace('text-danger','text-success'), checkNumber.textContent='✔ Al menos un número';
        else checkNumber.classList.replace('text-success','text-danger'), checkNumber.textContent='Al menos un número';
        if(/[!@#$%^&*(),.?":{}|<>]/.test(val)) checkSpecial.classList.replace('text-danger','text-success'), checkSpecial.textContent='✔ Al menos un carácter especial (!@#$%^&*)';
        else checkSpecial.classList.replace('text-success','text-danger'), checkSpecial.textContent='Al menos un carácter especial (!@#$%^&*)';
      });
      </script>
      <?php elseif ($step === 'finalizado'): ?>
        <div class="alert alert-success">
          <?= $mensaje_exito ?>
        </div>
      <?php endif; ?>
  </div>
</body>
</html>