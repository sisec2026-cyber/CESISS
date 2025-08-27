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

  <style>
    :root{
      --brand: #3C92A6;          /* color principal (teal) */
      --brand-2: #24a3c1;        /* acento */
      --bg-deep-1: #07161a;      /* fondo base oscuro */
      --bg-deep-2: #0a2128;      /* degradado secundario */
      --card: #0f1e23;           /* tarjeta oscura suave */
      --card-border: #16323a;    /* borde sutil */
      --text: #e6f2f4;           /* texto principal */
      --muted: #9ab7bf;          /* texto secundario */
      --input-bg: #0c1a1f;       /* fondo inputs */
      --input-bd: #1a3942;       /* borde inputs */
      --input-focus: rgba(60,146,166,.45); /* glow */

      /* Ajustes del carrusel */
      --carousel-interval: 6000ms;
      --carousel-fade: 1200ms;
      --tint-strength: .55;      /* 0 = sin tinta, 1 = muy oscuro */
      --blur-bg: 2px;            /* desenfoque sutil del fondo */
      --kenburns-scale: 1.06;    /* zoom sutil */
    }

    html, body { height: 100%; }
    body{
      margin:0;
      display:flex;
      align-items:center;
      justify-content:center;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans";
      color: var(--text);
      background: linear-gradient(120deg, var(--bg-deep-1), var(--bg-deep-2) 60%, #051013);
      overflow:hidden; /* oculta scroll del fondo */
    }

    /* ====== STACK DE CAPAS DEL FONDO ====== */
    .fx-stack{
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
    }

    /* --- Carrusel de imágenes a pantalla completa --- */
    .fx-carousel{
      position:absolute;
      inset:0;
      overflow:hidden;
      filter: blur(var(--blur-bg));
      transform: translateZ(0);
    }
    .fx-carousel .slide{
      position:absolute; inset:0;
      background-size: cover;
      background-position: center center;
      opacity:0;
      transition: opacity var(--carousel-fade) ease-in-out, transform var(--carousel-interval) linear;
      will-change: opacity, transform;
      /* Fallback si no hay JS: la primera visible por CSS */
    }
    .fx-carousel .slide:first-child{ opacity:1; }

    /* Efecto Ken Burns sutil cuando está activa */
    .fx-carousel .slide.is-active{
      opacity:1;
      transform: scale(1);
      animation: kenburns var(--carousel-interval) linear forwards;
    }
    @keyframes kenburns{
      0%   { transform: scale(var(--kenburns-scale)); }
      100% { transform: scale(1); }
    }

    /* Capa de tinta/gradiente para mantener legibilidad del login */
    .fx-tint{
      position:absolute; inset:0;
      background:
        radial-gradient(1000px 700px at 80% 15%, rgba(60,146,166,.20), transparent 60%),
        linear-gradient(120deg, rgba(0,0,0, calc(var(--tint-strength) + .1)), rgba(0,0,0,var(--tint-strength)));
      mix-blend-mode: normal;
    }

    /* Opcional: blob teal animado muy sutil por encima del carrusel */
    .fx-blob{
      position:absolute; inset:0;
      background:
        radial-gradient(700px 700px at 20% 25%, rgba(60,146,166,.35), transparent 60%),
        radial-gradient(900px 900px at 85% 75%, rgba(36,163,193,.25), transparent 60%);
      animation: blobPulse 16s ease-in-out infinite;
    }
    @keyframes blobPulse{
      0%,100% { transform: scale(1) translate(0,0); }
      50%     { transform: scale(1.05) translate(-10px,-14px); }
    }

    /* Respeta preferencias del usuario (reduce motion) */
    @media (prefers-reduced-motion: reduce) {
      .fx-carousel .slide,
      .fx-blob { animation: none !important; transition: none !important; }
    }

    /* ====== TARJETA (oscura y cómoda) ====== */
    .login-card{
      position: relative;
      z-index: 2; /* por encima del fondo */
      width: min(95vw, 420px);
      padding: 28px 24px;
      border-radius: 16px;
      background: var(--card);
      border: 1px solid var(--card-border);
      box-shadow: 0 18px 50px rgba(0,0,0,.45);
      backdrop-filter: saturate(120%);
    }

    /* ====== CABECERA ====== */
    .brand{
      display:flex; flex-direction:column; align-items:center; gap:.4rem;
      margin-bottom:.6rem; text-align:center;
    }
    .brand img{ max-height: 80px; width:auto; filter: drop-shadow(0 6px 16px rgba(60,146,166,.35)); }
    .brand h2{
      margin: 6px 0 0 0;
      font-weight: 800; letter-spacing:.2px; color:#eaf6f8;
      text-shadow: 0 2px 20px rgba(60,146,166,.25);
    }
    .brand p{ margin: 0; font-size:.95rem; color: var(--muted); }

    /* ====== FORM (oscuro) ====== */
    .form-floating>label{ color:#cfe5ea; }
    .form-control{
      background: var(--input-bg);
      border:1px solid var(--input-bd);
      color: var(--text);
    }
    .form-control::placeholder{ color:#88aab3; }
    .form-control:focus{
      background: #0f2228;
      border-color: var(--brand);
      box-shadow: 0 0 0 .2rem var(--input-focus);
      color: var(--text);
    }
    .toggle-password{
      position:absolute; right:12px; top:50%;
      transform: translateY(-50%);
      cursor:pointer; user-select:none; font-size:.95rem; color:#c7dde3;
      padding:4px 8px; border-radius:8px; background: #0c1b20;
      border:1px solid #15323a;
    }
    .toggle-password:hover{ background:#12313a; }

    .btn-brand{
      width:100%;
      padding:.9rem 1rem;
      font-weight:700;
      border-radius:10px;
      border:0;
      background: linear-gradient(90deg, var(--brand), var(--brand-2));
      color:#001318;
      letter-spacing:.3px;
      box-shadow: 0 14px 28px rgba(36,163,193,.28);
      transition: transform .08s ease, box-shadow .15s ease, filter .15s ease;
      animation: btnPulse 2.4s ease-in-out infinite;
    }
    .btn-brand:hover{ transform: translateY(-1px); box-shadow: 0 18px 32px rgba(36,163,193,.35); filter: brightness(1.05); }
    .btn-brand:active{ transform: translateY(0); }
    @keyframes btnPulse {
      0%,100% { transform: translateY(0) scale(1); box-shadow: 0 14px 28px rgba(36,163,193,.28); }
      50%     { transform: translateY(-1px) scale(1.015); box-shadow: 0 20px 36px rgba(36,163,193,.38); }
    }

    .error-alert{
      border-radius: 10px;
      border: 1px solid #6b1b28;
      background: #2a0f15;
      color: #ffd2da;
      padding: .75rem .9rem;
      font-size:.95rem;
    }
    .muted-link{ color:#7fd3e5; text-decoration: none; }
    .muted-link:hover{ color:#a6e9f5; text-decoration: underline; }
  </style>
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
      <img src="/sisec-ui/public/img/logoCESISS.png" alt="Logo CESISS" loading="eager">
      <h2>Bienvenido a CESISS</h2>
      <p>Consulta Exprés de Sistemas Instalados y Servicios de Suburbia</p>
    </div>

    <?php if ($error): ?>
      <div class="error-alert mb-3" role="alert" aria-live="assertive"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="controllers/login_procesar.php" method="POST" autocomplete="on" novalidate>
      <div class="form-floating mb-3 position-relative">
        <input type="text" class="form-control" id="usuario" name="nombre" placeholder="Usuario" required autofocus>
        <label for="usuario">Usuario</label>
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
        // Si el usuario prefiere menos movimiento, dejamos la primera imagen fija
        show(0);
      }
    })();
  </script>
<?php include __DIR__ . '/../sisec-ui/includes/footer.php'; ?>
</body>
</html>