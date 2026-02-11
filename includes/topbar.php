<!-- ===== Topbar CESISS con fondo animado + compactación y swap de logo ===== -->
<style>
  :root{
    /* Marca y base oscura */
    --brand: #3C92A6;
    --brand-2: #24a3c1;

    --topbar-base-1:#07161a;
    --topbar-base-2:#0a2128;
    --topbar-fg:#ffffff;
    --topbar-shadow: 0 6px 14px rgba(0,0,0,.25);

    /* Scroll behavior */
    --tb-height: 64px;
    --tb-height-scrolled: 52px;
    --tb-logo-scale-scrolled: .90;
    --tb-bg-overlay: rgba(0,0,0,0);
    --tb-bg-overlay-scrolled: rgba(0,0,0,.12);
  }

  /* Reset por si Bootstrap mete fondos claros */
  header.topbar { background: transparent !important; }

  .topbar{
    position: fixed; top:0; left:0; right:0; z-index:1040;
    height: var(--tb-height);
    display:flex; align-items:center;
    padding: .5rem .75rem;
    color: var(--topbar-fg);
    isolation: isolate;      /* aísla blending de pseudo-elementos */
    box-shadow: var(--topbar-shadow);
    overflow: visible;        /* muestra blobs fuera del header */
    transition: height .35s ease, box-shadow .35s ease, background-color .35s ease;
  }
  @media (max-width: 991px) {
  .topbar {
    justify-content: flex-start; /* Asegura orden: botón → título */
  }

  .topbar > button {
    flex: 0 0 auto; /* botón no crece */
    margin-right: 10px; /* espacio entre botón y título */
  }

  .topbar h5 {
    flex: 1 1 auto;  /* el título crece después del botón */
    margin: 0;
    text-align: left; /* opcional: alineado a la izquierda */
  }
}

  /* Base oscura (evita “blanco”) */
  .topbar::after{
    content:"";
    position:absolute; inset:0; z-index:0;
    background: linear-gradient(90deg, var(--topbar-base-1) 0%, var(--topbar-base-2) 50%, var(--topbar-base-1) 100%);
    transition: background-color .35s ease;
    background-color: var(--tb-bg-overlay);
  }

  /* Capa animada: cambia según data-effect */
  .topbar::before{
    content:"";
    position:absolute; inset:0; z-index:0;
    pointer-events:none;
    opacity: 1;
    transform: translateZ(0);
    will-change: transform, background-position, background-size;
    /* por defecto: blobs */
    background:
      radial-gradient(240px 180px at 12% 80%, rgba(60,146,166,.70) 0%, rgba(60,146,166,0) 70%),
      radial-gradient(300px 220px at 92% 20%, rgba(36,163,193,.55) 0%, rgba(36,163,193,0) 75%),
      radial-gradient(380px 260px at 55% -40%, rgba(60,146,166,.35) 0%, rgba(60,146,166,0) 80%);
    animation: tb-blob 12s ease-in-out infinite;
    transition: opacity .35s ease;
  }

  /* ===== Variantes visuales ===== */

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
      linear-gradient(90deg, transparent, transparent);
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
    mix-blend-mode: screen;
    animation: tb-grid 20s linear infinite;
  }
  @keyframes tb-grid{
    0%   { background-position: 0 0, 0 0; }
    100% { background-position: 0 180px, 180px 0; }
  }

  /* Respeta preferencias de movimiento */
  @media (prefers-reduced-motion: reduce){
    .topbar::before{ animation: none !important; }
    .topbar, .topbar *{ transition: none !important; }
  }

  /* Contenido por encima del fondo */
  .topbar > * { position: relative; z-index: 1; }
  .topbar .btn, .topbar .dropdown-toggle, .topbar i, .topbar h5 { color: var(--topbar-fg) !important; }
  .topbar .dropdown-menu{ min-width:300px; z-index: 2000; }

  /* ===== Compactación y swap de logo en scroll ===== */
  .topbar.is-scrolled{
    height: var(--tb-height-scrolled);
  }
  .topbar.is-scrolled::after{
    background-color: var(--tb-bg-overlay-scrolled);
  }
  .topbar.is-scrolled::before{
    opacity: .9; /* baja un poco la intensidad del efecto */
  }

  /* Contenedor de logos para animar */
  .brand-swap{
    display: flex; align-items: center; gap: 10px;
    transform-origin: center center;
    transition: transform .35s ease, filter .35s ease;
  }
  .topbar.is-scrolled .brand-swap{
    transform: scale(var(--tb-logo-scale-scrolled));
  }

  /* Dos estados: full vs compact */
  .topbar [data-logo="full"],
  .topbar [data-logo="compact"]{
    display:block;
    height: 50px;
    transition: opacity .35s ease, transform .35s ease, height .35s ease;
    will-change: transform, opacity;
  }
  .topbar [data-logo="compact"]{
    opacity: 0;
    transform: scale(.90);
  }
  .topbar.is-scrolled [data-logo="full"]{
    opacity: 0;
    transform: scale(.92);
    height: 44px;
  }
  .topbar.is-scrolled [data-logo="compact"]{
    opacity: 1;
    transform: scale(1);
    height: 34px; /* más pequeño al compactar */
  }

  /* Título: opcional, reduce tamaño en scroll */
  .topbar h5{
    transition: opacity .25s ease, transform .25s ease, font-size .25s ease;
  }
  .topbar.is-scrolled h5{
    font-size: .95rem;
  }
</style>

<header class="topbar d-flex align-items-center text-white" data-effect="grid">
  <!-- Botón hamburguesa móvil -->
  <button style="margin-left:10px;" class="btn btn-link text-white d-lg-none me-3" type="button"data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-label="Abrir menú">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Título -->
  <h5 class="m-0 flex-grow-1">
    <?= htmlspecialchars($pageHeader ?? 'CESISS') ?>
  </h5>

  <?php
  // ==================== NOTIFICACIONES ====================
  $notificaciones = [];
  $notificaciones_no_vistas = 0;

  if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin'])) {
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

  <!-- Logos en medio (swap Banamex-like) -->
  <div class="d-none d-sm-flex align-items-center mx-3 brand-swap">
    <!-- Logo completo (estado arriba) -->
    <img data-logo="full"
         src="/../sisec-ui/public/img/logo.png"
         alt="CESISS"
         style="height:50px;">

    <!-- Logo isotipo/compacto (estado con scroll) -->
    <img data-logo="compact"
         src="/../sisec-ui/public/img/sucursales/SBlogo.png"
         alt="CESISS"
         style="height:34px;">
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

<!-- ===== Script de scroll: aplica .is-scrolled al topbar ===== -->
<script>
(function(){
  const header = document.querySelector('header.topbar');
  if (!header) return;

  // Sentinel invisible (top de la página)
  const sentinel = document.createElement('div');
  sentinel.setAttribute('aria-hidden', 'true');
  sentinel.style.position = 'absolute';
  sentinel.style.top = '0';
  sentinel.style.left = '0';
  sentinel.style.width = '1px';
  sentinel.style.height = '1px';
  document.body.prepend(sentinel);

const setState = (scrolled) => {
  header.classList.toggle('is-scrolled', scrolled);
  document.body.classList.toggle('has-scrolled', scrolled); // NUEVO
};


  if ('IntersectionObserver' in window){
    const io = new IntersectionObserver(([entry]) => {
      setState(!entry.isIntersecting); // cuando el sentinel ya no está a la vista => scrolled
    }, { rootMargin: '0px 0px 0px 0px', threshold: [0] });
    io.observe(sentinel);
  } else {
    // Fallback
    const onScroll = () => setState(window.scrollY > 8);
    document.addEventListener('scroll', onScroll, {passive:true});
    onScroll();
  }
})();
</script>