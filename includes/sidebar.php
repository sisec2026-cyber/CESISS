<?php
// views/includes/sidebar.php
?>
<!-- ===== SIDEBAR CESISS (animado: blob / waves / grid) ===== -->
<style>
  :root{
    --brand:#3C92A6;
    --brand-2:#24a3c1;

    --side-base-1:#07161a;
    --side-base-2:#0a2128;
    --side-fg:#e6f2f4;
    --side-muted:#9ab7bf;
    --side-sep:#16323a;
    --side-shadow: 0 12px 24px rgba(0,0,0,.28);

    /* Alturas del topbar (las usa el JS del topbar) */
    --tb-height: 64px;
    --tb-height-scrolled: 52px;

    --sidebar-w: 300px;
    --item-h: 44px;
    --radius: 10px;

    --safe-top: env(safe-area-inset-top, 0px);
  }

  nav.sidebar { background: transparent !important; }

  .sidebar{
    position: fixed;
    top: var(--tb-height, 64px); /* seguirá al topbar */
    left: 0;
    bottom: 0;
    width: var(--sidebar-w);
    z-index: 1030;
    color: var(--side-fg);
    box-shadow: var(--side-shadow);
    overflow: clip; /* evita recortes del footer sticky; usa 'visible' si tu navegador no soporta clip */
    display: block;
    transition: top .35s ease;
    will-change: top;
  }
  body.has-scrolled .sidebar{
    top: var(--tb-height-scrolled, 52px);
  }

  .sidebar::after{
    content:"";
    position:absolute; inset:0; z-index:0; pointer-events:none;
    background: linear-gradient(180deg, var(--side-base-1) 0%, var(--side-base-2) 60%, var(--side-base-1) 100%);
  }
  .sidebar::before{
    content:"";
    position:absolute; inset:0; z-index:0; pointer-events:none;
    opacity: 1;
    transform: translateZ(0);
    will-change: transform, background-position, background-size;
    background:
      radial-gradient(380px 280px at 120% -10%, rgba(36,163,193,.36) 0%, rgba(36,163,193,0) 70%),
      radial-gradient(340px 260px at -20% 110%, rgba(60,146,166,.42) 0%, rgba(60,146,166,0) 75%);
    animation: sb-blob 18s ease-in-out infinite;
  }
  .sidebar[data-effect="blob"]::before{
    background:
      radial-gradient(380px 280px at 120% -10%, rgba(36,163,193,.36) 0%, rgba(36,163,193,0) 70%),
      radial-gradient(340px 260px at -20% 110%, rgba(60,146,166,.42) 0%, rgba(60,146,166,0) 75%),
      radial-gradient(420px 320px at 50% 40%, rgba(60,146,166,.18) 0%, rgba(60,146,166,0) 80%);
    animation: sb-blob 18s ease-in-out infinite;
  }
  @keyframes sb-blob{
    0%,100% { transform: translate3d(0,0,0) scale(1); }
    50%     { transform: translate3d(0,-10px,0) scale(1.02); }
  }
  .sidebar[data-effect="waves"]::before{
    background:
      radial-gradient(800px 600px at 50% 120%, rgba(36,163,193,.25), rgba(36,163,193,0) 70%),
      radial-gradient(900px 680px at 50% -40%, rgba(60,146,166,.28), rgba(60,146,166,0) 75%);
    animation: sb-waves 22s ease-in-out infinite;
  }
  @keyframes sb-waves{
    0%   { transform: translate3d(0,0,0) scale(1); }
    50%  { transform: translate3d(0,-18px,0) scale(1.02); }
    100% { transform: translate3d(0,0,0) scale(1); }
  }
  .sidebar[data-effect="grid"]::before{
    background:
      repeating-linear-gradient(0deg,  rgba(60,146,166,.22) 0 2px, transparent 2px 70px),
      repeating-linear-gradient(90deg, rgba(60,146,166,.22) 0 2px, transparent 2px 70px);
    mix-blend-mode: screen;
    animation: sb-grid 26s linear infinite;
  }
  @keyframes sb-grid{
    0%   { background-position: 0 0, 0 0; }
    100% { background-position: 0 220px, 220px 0; }
  }

  .sidebar .inner{
    position: relative; z-index: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 0; /* permite scroll interno */
  }

  .sidebar .brand {
    text-align: center; padding: 18px 12px 10px;
  }
  .sidebar .brand img {
    max-height: 120px; width:auto;
    filter: drop-shadow(0 6px 14px rgba(36,163,193,.28));
  }

  /* El menú es el que scrollea para que el footer siempre se vea */
  .sidebar .menu{
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto; overflow-x: hidden;
    padding: 8px;
    padding-bottom: 72px; /* aire final */
    scrollbar-width: thin;
    scrollbar-color: rgba(36,163,193,.45) transparent;
    scroll-behavior: smooth;
  }
  .sidebar .menu::-webkit-scrollbar{ width: 8px; }
  .sidebar .menu::-webkit-scrollbar-thumb{ background: rgba(36,163,193,.45); border-radius: 8px; }

  .sidebar a{
    display: block;
    height: var(--item-h);
    line-height: var(--item-h);
    border-radius: var(--radius);
    color: var(--side-fg);
    text-decoration: none;
    padding: 0 12px;
    margin: 6px 8px;
    position: relative;
    border: 1px solid transparent;
    transition: background .15s ease, transform .08s ease, border-color .2s ease;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .sidebar a i{ width: 22px; margin-right: 8px; opacity: .9; }

  .sidebar a:hover{
    background: rgba(36,163,193,.12);
    border-color: rgba(36,163,193,.25);
    transform: translateX(1px);
  }
  .sidebar a:focus-visible{
    outline: 2px solid var(--brand-2);
    outline-offset: 2px;
  }
  .sidebar a.active{
    background: rgba(36,163,193,.18);
    border-color: rgba(36,163,193,.45);
    box-shadow: inset 0 0 0 1px rgba(36,163,193,.25);
  }
  .sidebar a.active::before{
    content:"";
    position:absolute; left:-2px; top:8px; bottom:8px; width:4px;
    background: linear-gradient(180deg, var(--brand), var(--brand-2));
    border-radius: 4px;
  }

  /* Footer: sticky para que SIEMPRE se vea */
  .sidebar .footer{
    position: sticky;
    bottom: 0;
    z-index: 2;
    margin-top: auto;
    padding: 8px;
    border-top: 1px solid var(--side-sep);
    background: linear-gradient(180deg, rgba(7,22,26,.85), rgba(10,33,40,.85));
    backdrop-filter: blur(6px);
  }
  .sidebar .footer a{
    display: flex; align-items: center; gap: 8px;
    height: 44px; line-height: 44px;
    margin: 6px 8px;
    padding: 0 14px;
    color: var(--side-fg);
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: var(--radius);
    transition: background .15s ease, border-color .2s ease, transform .08s ease;
  }
  .sidebar .footer a:hover{
    background: rgba(36,163,193,.15);
    border-color: rgba(36,163,193,.35);
    transform: translateY(-1px);
  }

  @media (min-width: 992px){
    .content-wrapper{
      margin-left: var(--sidebar-w) !important;
      padding-left: 0 !important;
    }
    main.main{
      margin-left: 0 !important;
      padding-left: 0 !important;
    }
    .content-wrapper > .container-fluid,
    .content-wrapper .container-fluid,
    main.main > .container-fluid,
    main.main .container-fluid{
      padding-left: 0 !important;
      padding-right: 1rem;
    }
  }

  @media (prefers-reduced-motion: reduce){
    .sidebar *, .sidebar::before { transition: none !important; animation: none !important; }
  }
</style>

<nav class="sidebar d-none d-lg-block" data-effect="blob">
  <div class="inner">
    <div class="brand">
      <img src="/sisec-ui/public/img/Qr3.png" alt="Logo CESISS">
    </div>

    <div class="menu">
      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin', 'Administrador', 'Mantenimientos', 'Técnico', 'Distrital'])): ?>
        <a href="/sisec-ui/views/inicio/index.php" class="<?= ($activePage ?? '') === 'inicio' ? 'active' : '' ?>">
          <i class="fas fa-home"></i> Inicio
        </a>
      <?php endif; ?>

      <a href="/sisec-ui/views/dispositivos/listar.php" class="<?= ($activePage ?? '') === 'dispositivos' ? 'active' : '' ?>">
        <i class="fas fa-camera"></i> Dispositivos
      </a>

      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin','Administrador', 'Capturista','Técnico'])): ?>
        <a href="/sisec-ui/views/dispositivos/registro.php" class="<?= ($activePage ?? '') === 'registro' ? 'active' : '' ?>">
          <i class="fas fa-plus-circle"></i> Registrar dispositivo
        </a>
      <?php endif; ?>

      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin','Administrador'])): ?>
        <a href="/sisec-ui/views/usuarios/index.php" class="<?= ($activePage ?? '') === 'usuarios' ? 'active' : '' ?>">
          <i class="fa-solid fa-users"></i> Usuarios
        </a>
        <a href="/sisec-ui/views/usuarios/registrar.php" class="<?= ($activePage ?? '') === 'registrar' ? 'active' : '' ?>">
          <i class="fa-solid fa-user-plus"></i> Registrar usuario
        </a>
      <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['usuario_id'])): ?>
      <div class="footer">
        <a href="/sisec-ui/logout.php" class="px-3 d-block">
          <i class="fas fa-sign-out-alt"></i>
          <span>Cerrar sesión</span>
        </a>
      </div>
    <?php endif; ?>
  </div>
</nav>
