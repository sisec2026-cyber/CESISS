<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>CESISS - <?= htmlspecialchars($pageTitle ?? 'Página') ?></title>
  <link rel="shortcut icon" href="/sisec-ui/public/img/Qr3.png">
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Bootstrap CSS y FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="/sisec-ui/assets/css/estilos.css">

  <!-- Estilo responsivo extra -->
  <style>
    :root{
      --sidebar-w: 230px;     /* ajusta si tu sidebar mide diferente */
      --topbar-h: 64px;       /* ajusta si tu topbar fijo mide diferente */
    }

    /* ====== Reset y helpers ====== */
    html, body { height: 100%; }
    body { overflow-x: hidden; padding-top: env(safe-area-inset-top); }
    img, video, canvas, svg { max-width: 100%; height: auto; }
    iframe { max-width: 100%; }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .topbar-spacer { height: var(--topbar-h); }
    @media (prefers-reduced-motion: reduce){
      * { scroll-behavior: auto !important; animation-duration: .01ms !important; transition-duration: .01ms !important; }
    }

    /* ====== Layout con sidebar fijo en desktop ====== */
    @media (min-width: 992px) {
      .content-wrapper,
      main.main {
        margin-left: var(--sidebar-w);
      }
      .content-wrapper { min-width: 0; }
    }

    /* ====== Comportamiento en móvil / tablet ====== */
    @media (max-width: 991.98px) {
      .sidebar { display: none !important; }

      html, body { padding-left: 0 !important; }
      .content-wrapper,
      .page-content,
      .main,
      main {
        margin-left: 0 !important;
        width: 100% !important;
      }

      .table-responsive, .table-wrap { overflow-x: auto; }
      .btn, .form-control { font-size: 0.95rem; }
      .container, .container-fluid { padding-left: 1rem; padding-right: 1rem; }
      .topbar-spacer { height: calc(var(--topbar-h) * .9); }
    }

    /* ====== Ajustes útiles ====== */
    .form-max-md { max-width: 920px; }
    .has-fixed-topbar .topbar-spacer { display: block; }
    .breakout { min-width: 0; }
  </style>

  <!-- Bootstrap JS (bundle con Popper) CARGADO EN HEAD (sin defer/async) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body class="has-fixed-topbar">
  <div class="d-flex" style="min-height: 100vh; overflow-x: hidden;">

    <!-- Sidebar fijo desktop -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Contenedor de contenido -->
    <div class="flex-grow-1 d-flex flex-column content-wrapper breakout" style="width: 100%;">

      <!-- Topbar -->
      <?php include __DIR__ . '/includes/topbar.php'; ?>

      <!-- Espaciador si el topbar es fixed -->
      <div class="topbar-spacer"></div>

      <!-- Contenido principal -->
      <main class="main flex-grow-1 py-4" tabindex="-1">
        <div class="container-fluid">
          <!-- Aquí se inyecta tu vista con scripts inline que usan bootstrap.Modal -->
          <?= $content ?? '<p>Contenido no definido.</p>' ?>
        </div>
      </main>

      <!-- (Opcional) Footer -->
      <!--
      <footer class="mt-auto py-3 border-top">
        <div class="container-fluid small text-muted">© CESISS</div>
      </footer>
      -->

    </div>
  </div>

  <!-- Sidebar móvil (offcanvas) -->
  <?php include __DIR__ . '/includes/sidebar_mobile.php'; ?>

  <!-- Scripts propios (pueden ir después; Bootstrap ya está disponible desde <head>) -->
  <script src="/sisec-ui/assets/js/notificaciones.js"></script>
  <script>
    // Abrir sidebar móvil (offcanvas)
    document.addEventListener('click', function(e){
      const btn = e.target.closest('[data-toggle="sidebar"]');
      if(!btn) return;
      const offcanvasEl = document.querySelector(btn.getAttribute('data-target') || '#sidebarMobile');
      if (!offcanvasEl) return;
      const off = new bootstrap.Offcanvas(offcanvasEl);
      off.show();
    });

    // Accesibilidad: enfocar main al cerrar el offcanvas
    const offEl = document.getElementById('sidebarMobile');
    if (offEl) {
      offEl.addEventListener('hidden.bs.offcanvas', () => {
        const main = document.querySelector('main.main');
        if (main) main.focus({ preventScroll: true });
      });
    }
  </script>
<?php if (!empty($_SESSION['usuario_id'])): ?>
  <script src="/sisec-ui/assets/js/unload-guard.js"></script>
<?php endif; ?>
</body>
</html>

</body>
</html>
