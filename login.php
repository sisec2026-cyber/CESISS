<?php
session_start(); // asegura que la sesión esté iniciada

// Si ya hay sesión iniciada, redirige según el rol
if (isset($_SESSION['usuario_id']) && isset($_SESSION['rol'])) {
  switch ($_SESSION['rol']) {
    case 'Superadmin':
    case 'Mantenimientos':
    case 'Distrital':
    case 'Administrador':
      header('Location: views/index.php');
      exit;
    case 'Capturista':
    case 'Prevencion':
    case 'Monitorista':
    case 'Técnico':
      header('Location: views/listar.php');
      exit;
    default:
      header('Location: views/listar.php');
      exit;
  }
}

// Inicializa variables
$error = $_GET['error'] ?? null;
$redirect = $_GET['redirect'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CESISS - Iniciar Sesión</title>
  <!-- Bootstrap solo para normalizar y helpers -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Preload (opcional, mejora el primer pintado) -->
  <link rel="preload" as="image" href="/sisec-ui/public/img/bg1.jpg">
  <link rel="preload" as="image" href="/sisec-ui/public/img/bg2.jpg">
  <!-- ESTILO DE LOGIN -->
  <?php echo '<link rel="stylesheet" type="text/css" href="/sisec-ui/assets/stylelogin.css">'; ?> 
</head>
<body>
  <!-- ====== CAPAS DE FONDO ====== -->
  <div class="fx-stack" aria-hidden="true">
    <!-- Carrusel de fondo -->
    <div class="fx-carousel" id="bgCarousel">
      <!-- Cambia las rutas a tus imágenes -->
      <div class="slide is-active" style="background-image:url('/../sisec-ui/public/img/carrusel/sblindavista.jpeg');"></div>
      <div class="slide"        style="background-image:url('/../sisec-ui/public/img/carrusel/sbtorreslindavista.jpeg');"></div>
      <div class="slide"        style="background-image:url('/../sisec-ui/public/img/carrusel/sbelrosario.jpeg');"></div>
      <div class="slide"        style="background-image:url('/../sisec-ui/public/img/carrusel/logosb.jpeg');"></div>
            <div class="slide"        style="background-image:url('/../sisec-ui/public/img/carrusel/sb.jpeg');"></div>
      <!-- Agrega más slides si quieres -->
    </div>

    <!-- Tinta/gradiente para legibilidad -->
    <div class="fx-tint" id="tintLayer"></div>

    <!-- Blob teal animado MUY sutil (opcional, comenta si no lo quieres) -->
    <div class="fx-blob"></div>
  </div>

  <!-- ====== TARJETA LOGIN ====== -->
  <main class="login-card">
    <div class="brand">
      <img src="/sisec-ui/public/img/Qr3.png" alt="Logo CESISS" loading="eager">
      <h2>Bienvenido a CESISS</h2>
      <p>Consulta Exprés de Sistemas Instalados y Servicios de Suburbia</p>
    </div>

    <?php if ($error): ?>
      <div class="error-alert mb-3" role="alert" aria-live="assertive"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="controllers/login_procesar.php" method="POST" autocomplete="on" novalidate>
      <div class="form-floating mb-3 position-relative">
        <input type="text" class="form-control" id="usuario" name="nombre" placeholder="tu@correo.com o usuario" required autofocus>
        <label for="usuario">Correo o nombre de usuario</label>
      </div>

      <div class="form-floating mb-3 position-relative">
        <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
        <label for="password">Contraseña</label>
        <span class="toggle-password" id="togglePass" title="Mostrar/Ocultar">👁️</span>
      </div>

      <?php if ($redirect): ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <?php endif; ?>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
        <label class="form-check-label" for="remember_me">Mantener sesión iniciada</label>
      </div>

      <button type="submit" class="btn-brand">INICIA SESIÓN</button>
    </form>

    <div class="text-center mt-3">
      <a class="muted-link" href="views/inicio/crearuser.php">¿No tienes cuenta? ¡Regístrate!</a>
      <br>
      <a class="muted-link" href="views/inicio/recuperar_contrasena.php">¿Olvidaste tu contraseña?</a>
    </div>
  </main>

  <script>
    // Toggle mostrar/ocultar contraseña
    (function(){
      const input = document.getElementById('password');
      const btn = document.getElementById('togglePass');
      if(!input || !btn) return;
      btn.addEventListener('click', () => {
        const isText = input.getAttribute('type') === 'text';
        input.setAttribute('type', isText ? 'password' : 'text');
        btn.textContent = isText ? '👁️' : '🙈';
      });
    })();

    // Carrusel de fondo (vanilla JS, crossfade)
    (function(){
      const root = document.getElementById('bgCarousel');
      if(!root) return;

      const slides = Array.from(root.querySelectorAll('.slide'));
      if(slides.length <= 1) return; // con 1 imagen no rotamos

      let idx = 0;
      const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      function show(i){
        slides.forEach((s, k) => s.classList.toggle('is-active', k === i));
      }

      if (!reduceMotion) {
        setInterval(() => {
          idx = (idx + 1) % slides.length;
          show(idx);
        }, parseInt(getComputedStyle(document.documentElement).getPropertyValue('--carousel-interval')) || 6000);
      } else {
        show(0);
      }
    })();
  </script>
<?php include __DIR__ . '/../sisec-ui/includes/footer.php'; ?>
</body>
</html>