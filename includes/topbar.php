<!-- ===== Topbar CESISS con fondo animado (blob / waves / grid) ===== -->
<style>
  :root{
    /* Colores marca y base oscura */
    --brand: #3C92A6;
    --brand-2: #24a3c1;
    --topbar-base-1:#07161a;
    --topbar-base-2:#0a2128;
    --topbar-fg:#ffffff;
    --topbar-shadow: 0 6px 14px rgba(0,0,0,.25);
  }

  /* Reset por si Bootstrap mete fondos claros */
  header.topbar { background: transparent !important; }

  .topbar{
    position: fixed; top:0; left:0; right:0; z-index:1040;
    height:64px; display:flex; align-items:center;
    padding: .5rem .75rem;
    color: var(--topbar-fg);
    isolation: isolate;      /* aísla blending de pseudo-elementos */
    box-shadow: var(--topbar-shadow);
    overflow: visible;        /* oculta blobs fuera del header */
  }

  /* Base oscura siempre visible (evita “blanco”) */
  .topbar::after{
    content:"";
    position:absolute; inset:0; z-index:0;
    background: linear-gradient(90deg, var(--topbar-base-1) 0%, var(--topbar-base-2) 50%, var(--topbar-base-1) 100%);
  }

  /* Capa animada: cambia según data-effect */
  .topbar::before{
    content:"";
    position:absolute; inset:0; z-index:0;
    pointer-events:none;
    opacity: 1;
    transform: translateZ(0);
    will-change: transform, background-position, background-size;
    /* valor por defecto si falta data-effect */
    background:
      radial-gradient(240px 180px at 12% 80%, rgba(60,146,166,.70) 0%, rgba(60,146,166,0) 70%),
      radial-gradient(300px 220px at 92% 20%, rgba(36,163,193,.55) 0%, rgba(36,163,193,0) 75%),
      radial-gradient(380px 260px at 55% -40%, rgba(60,146,166,.35) 0%, rgba(60,146,166,0) 80%);
    animation: tb-blob 12s ease-in-out infinite;
  }

  /* ====== Variantes ====== */

  /* Blob (recomendado) */
  .topbar[data-effect="blob"]::before{
    background:
      radial-gradient(240px 180px at 12% 80%, rgba(60,146,166,.70) 0%, rgba(60,146,166,0) 70%),
      radial-gradient(300px 220px at 92% 20%, rgba(36,163,193,.55) 0%, rgba(36,163,193,0) 75%),
      radial-gradient(380px 260px at 55% -40%, rgba(60,146,166,.35) 0%, rgba(60,146,166,0) 80%);
    animation: tb-blob 12s ease-in-out infinite;
  }
  @keyframes tb-blob{
    0%,100% { transform: translate3d(0,0,0) scale(1); }
    50%     { transform: translate3d(0,-8px,0) scale(1.03); }
  }

  /* Waves (ondas) */
  .topbar[data-effect="waves"]::before{
    background:
      radial-gradient(600px 400px at -10% -100%, rgba(60,146,166,.32), rgba(60,146,166,0) 70%),
      radial-gradient(700px 480px at 110% 200%, rgba(36,163,193,.28), rgba(36,163,193,0) 75%),
      linear-gradient(90deg, transparent, transparent); /* placeholder */
    animation: tb-waves 16s ease-in-out infinite;
  }
  @keyframes tb-waves{
    0%   { transform: translate3d(0,0,0) scale(1); }
    50%  { transform: translate3d(0,-18px,0) scale(1.02); }
    100% { transform: translate3d(0,0,0) scale(1); }
  }

  /* Grid */
  .topbar[data-effect="grid"]::before{
    background:
      repeating-linear-gradient(0deg,  rgba(60,146,166,.22) 0 2px, transparent 2px 70px),
      repeating-linear-gradient(90deg, rgba(60,146,166,.22) 0 2px, transparent 2px 70px);
    mix-blend-mode: screen;        /* líneas en teal sobre base oscura */
    animation: tb-grid 20s linear infinite;
  }
  @keyframes tb-grid{
    0%   { background-position: 0 0, 0 0; }
    100% { background-position: 0 180px, 180px 0; }
  }

  /* Respeta preferencias de movimiento */
  @media (prefers-reduced-motion: reduce){
    .topbar::before{ animation: none !important; }
  }

  /* Contenido por encima del fondo */
  .topbar > * { position: relative; z-index: 1; }
  .topbar .btn, .topbar .dropdown-toggle, .topbar i, .topbar h5 { color: var(--topbar-fg) !important; }

  /* Dropdown más legible sobre topbar oscuro */
  .topbar .dropdown-menu{ min-width:300px;  
  z-index: 2000; }
</style>

<header class="topbar d-flex align-items-center text-white"
        data-effect="grid"> <!-- Cambia a: blob | waves | grid -->
  <!-- Botón hamburguesa móvil -->
  <button class="btn btn-link text-white d-md-none me-3" type="button"
          data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"
          aria-controls="mobileMenu" aria-label="Abrir menú">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Título -->
  <h5 class="m-0 flex-grow-1">
    <?= htmlspecialchars($pageHeader ?? 'CESISS - Consulta Exprés de Sistemas Instalados y Servicios de Suburbia') ?>
  </h5>

  <?php
  // ==================== NOTIFICACIONES ====================
  $notificaciones = [];
  $notificaciones_no_vistas = 0;

  if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin','Administrador'])) {
      if (!isset($conn)) { include __DIR__ . '/db.php'; }

      $stmt = $conn->prepare("
        SELECT n.id, n.mensaje, n.fecha, n.visto, n.dispositivo_id
        FROM notificaciones n
        INNER JOIN usuarios u ON u.id = n.usuario_id
        WHERE u.rol <> 'Superadmin'
          AND n.usuario_id <> ?
        ORDER BY n.fecha DESC
        LIMIT 5
      ");
      $miId = (int)($_SESSION['usuario_id'] ?? 0);
      $stmt->bind_param('i', $miId);
      $stmt->execute();
      $result = $stmt->get_result();

      while ($row = $result->fetch_assoc()) {
          $notificaciones[] = $row;
          if ((int)$row['visto'] === 0) $notificaciones_no_vistas++;
      }
      $stmt->close();
  }
  ?>

  <!-- Logos en medio -->
  <div class="d-none d-sm-flex align-items-center mx-3">
    <img src="/../sisec-ui/public/img/logo.png" alt="Logo 1" style="height:50px; margin-right:10px;">
    <img src="/../sisec-ui/public/img/sucursales/default.png" alt="Logo 2" style="height:30px;">
  </div>

  <!-- Íconos / Notificaciones -->
  <div class="topbar-icons d-flex align-items-center me-3">
    <div class="dropdown position-relative" title="Notificaciones">
      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin', 'Administrador'])): ?>
        <a href="#" id="notifDropdown" class="text-white" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration:none; position:relative;">
          <i class="fas fa-bell"></i>
          <?php if ($notificaciones_no_vistas > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
          <?php endif; ?>
        </a>

        <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="notifDropdown">
          <?php if (count($notificaciones) === 0): ?>
            <li class="dropdown-item text-center text-muted">No hay notificaciones</li>
          <?php else: ?>
            <?php foreach ($notificaciones as $notif): ?>
              <li>
                <a href="/sisec-ui/views/notificaciones/ir.php?id=<?= (int)$notif['id'] ?>"
                   class="dropdown-item<?= ((int)$notif['visto'] === 0 ? ' fw-bold' : '') ?>"
                   style="white-space: normal;">
                  <?php
                    $texto = preg_replace('/\\[\\[url:[^\\]]+\\]\\]/', '', $notif['mensaje']);
                    echo htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
                  ?>
                  <br>
                  <small class="text-muted"><?= date('d/m/Y H:i', strtotime($notif['fecha'])) ?></small>
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
            <?php endforeach; ?>
            <li><a href="/sisec-ui/views/notificaciones/notificaciones.php" class="dropdown-item text-center">Ver todas</a></li>
          <?php endif; ?>
        </ul>
      <?php else: ?>
        <i class="fas fa-bell" style="opacity:0.5;"></i>
      <?php endif; ?>
    </div>
  </div>

  <!-- Dropdown usuario -->
  <div class="dropdown">
    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
      <div class="user-photo me-2">
        <?php if (!empty($_SESSION['foto']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $_SESSION['foto'])): ?>
          <img src="<?= htmlspecialchars($_SESSION['foto']) ?>" alt="Foto de perfil" class="rounded-circle" style="width:32px; height:32px; object-fit:cover;">
        <?php else: ?>
          <i class="fas fa-user-circle fa-2x text-white"></i>
        <?php endif; ?>
      </div>
      <span class="user-name d-none d-sm-inline"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
      <li><a class="dropdown-item" href="/sisec-ui/views/usuarios/perfil.php"><i class="fas fa-user me-2"></i>Perfil</a></li>
      <li><a class="dropdown-item" href="/sisec-ui/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
    </ul>
  </div>
</header>
