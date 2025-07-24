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
    @media (max-width: 768px) {
      .table-responsive {
        overflow-x: auto;
      }

      .sidebar {
        display: none !important;
      }

      .main {
        padding-left: 0 !important;
        padding-right: 0 !important;
      }

      .btn, .form-control {
        font-size: 0.9rem;
      }
    }

    /* Para evitar doble scroll horizontal */
    body {
      overflow-x: hidden;
    }
  </style>
</head>

<body>
  <div class="d-flex" style="min-height: 100vh; overflow-x: hidden;">
    
    <!-- Sidebar fijo desktop -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Contenedor de contenido -->
    <div class="flex-grow-1 d-flex flex-column" style="width: 100%;">
      
      <!-- Topbar -->
      <?php include __DIR__ . '/includes/topbar.php'; ?>

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
