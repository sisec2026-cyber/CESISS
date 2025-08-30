<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>SISEC - <?= htmlspecialchars($pageTitle ?? 'Página') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Bootstrap CSS y FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="/sisec-ui/assets/css/estilos.css">

  <!-- Estilo responsivo extra -->
  <style>
    /* ====== Reseteos útiles ====== */
    html, body { height: 100%; }
    body { overflow-x: hidden; } /* evita scroll horizontal */

    /* ====== Correcciones de layout con sidebar fijo ======
       Ajusta 230px si tu sidebar tiene otro ancho */
    @media (min-width: 992px) {
      /* Si tu sidebar es fijo (position:fixed) y no participa del flex,
         deja espacio a la izquierda SOLO en desktop */
      .content-wrapper,
      main.main {
        margin-left: 230px;   /* ancho del sidebar */
      }
    }

    @media (max-width: 991.98px) {
      /* Oculta el sidebar y elimina cualquier offset sobrante en móvil/tablet */
      .sidebar { display: none !important; }

      /* Elimina márgenes/paddings izquierdos heredados */
      html, body { padding-left: 0 !important; }
      .content-wrapper,
      .page-content,
      .main,
      main {
        margin-left: 0 !important;
        width: 100% !important;
      }

      /* Ajustes suaves de UI */
      .table-responsive { overflow-x: auto; }
      .btn, .form-control { font-size: 0.9rem; }
    }
  </style>
</head>

<body>
  <div class="d-flex" style="min-height: 100vh; overflow-x: hidden;">
    
    <!-- Sidebar fijo desktop -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Contenedor de contenido -->
    <div class="flex-grow-1 d-flex flex-column content-wrapper" style="width: 100%;">
      
      <!-- Topbar -->
      <?php include __DIR__ . '/includes/topbar.php'; ?>

      <!-- separadores (si tu topbar es fixed) -->
      <br>
      <br>

      <!-- Contenido principal -->
      <main class="main flex-grow-1 px-3 py-4">
        <?= $content ?? '<p>Contenido no definido.</p>' ?>
      </main>

    </div>
  </div>

  <!-- Sidebar móvil (offcanvas) -->
  <?php include __DIR__ . '/includes/sidebar_mobile.php'; ?>

  <!-- JS -->
  <script src="/sisec-ui/assets/js/notificaciones.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
