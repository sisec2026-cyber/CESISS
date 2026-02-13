<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($pdo) && !isset($conexion)) {
  $connPath = __DIR__ . '/../../includes/conexion.php';
  if (is_file($connPath)) require_once $connPath;
} 
$pendCount = 0;
$rolSB = $_SESSION['usuario_rol'] ?? '';
if (in_array($rolSB, ['Superadmin','Administrador'])) {
  try {
    if (isset($pdo)) {
      $st = $pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE esta_aprobado = 0");
      $pendCount = (int)($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    } elseif (isset($conexion)) {
      $rs = $conexion->query("SELECT COUNT(*) AS c FROM usuarios WHERE esta_aprobado = 0");
      $pendCount = (int)(($rs && $rs->num_rows) ? $rs->fetch_assoc()['c'] : 0);
    }
  } catch (Throwable $e) {
    // puedes loguear si quieres: error_log($e->getMessage());
    $pendCount = 0;
  }
}
?>
<!-- ===== SIDEBAR CESISS (animado: blob / waves / grid) ===== -->
<style>
  :root{
    /* Marca */
    --brand:#3C92A6;
    --brand-2:#24a3c1;

    /* Sidebar */
    --side-base-1:#07161a;
    --side-base-2:#0a2128;
    --side-fg:#e6f2f4;
    --side-muted:#9ab7bf;
    --side-sep:#16323a;
    --side-shadow: 0 12px 24px rgba(0,0,0,.28);

    /* Topbar (usado por el JS del topbar) */
    --tb-height: 64px;
    --tb-height-scrolled: 52px;

    /* Layout */
    --sidebar-w: 280px;
    --item-h: 44px;
    --radius: 10px;

    --safe-top: env(safe-area-inset-top, 0px);

    /* Acentos para el botón neon */
    --sb-neon-1: #24a3c1;
    --sb-neon-2: #3C92A6;
    --sb-neon-3: #74e0ff;
    --sb-glass-bg: rgba(8, 26, 31, .55);
    --sb-border: rgba(255,255,255,.16);

    /* Tamaño base de logos flotantes */
    --bubble-base: 22px; /* ajústalo 16–28px según gusto */
  }
  nav.sidebar { background: transparent !important; }
  /* ===== SIDEBAR BASE ===== */
  .sidebar{
    position: fixed;
    top: var(--tb-height, 64px);
    left: 0;
    bottom: 0;
    width: var(--sidebar-w);
    z-index: 1030;
    color: var(--side-fg);
    box-shadow: var(--side-shadow);
    overflow-x: hidden;
    white-space: nowrap;
    display: block;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: top;
    transform: translateX(0);
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
  body.sb-collapsed .sidebar::before {
  background-size: 400% 400%; /* Evita que el gradiente se vea 'aplastado' */
}
  .sidebar[data-effect="waves"]::before{
    background:
      radial-gradient(800px 600px at 50% 120%, rgba(36,163,193,.25), rgba(36,163,193,0) 70%),
      radial-gradient(900px 680px at 50% -40%, rgba(60,146,166,.28), rgba(60,146,166,0) 75%);
    animation: sb-waves 22s ease-in-out infinite;

  }
  @keyframes sb-waves{
    0%   { transform: translate3d(0,0,0) scale(1); }
    50%  { transform: translate3d(-0,-18px,0) scale(1.02); }
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
  /* El menú scrollea para que el footer sea sticky visible */
  .sidebar .menu{
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto; overflow-x: hidden;
    padding: 8px;
    padding-bottom: 72px;
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
  /* Badge de notificaciones */
  .sidebar a .sb-badge{
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    min-width: 22px; height: 22px; line-height: 22px; padding: 0 6px;
    background: #d9534f; color: #fff; border-radius: 999px;
    font-size: 12px; font-weight: 700; text-align: center;
    box-shadow: 0 0 0 2px rgba(0,0,0,.12);
  }
  .sb-badge.pulse{
    animation: sb-badge-pulse 1.8s ease-in-out infinite;
  }
  @keyframes sb-badge-pulse{
    0%{ box-shadow: 0 0 0 0 rgba(217,83,79,.6); }
    70%{ box-shadow: 0 0 0 10px rgba(217,83,79,0); }
    100%{ box-shadow: 0 0 0 0 rgba(217,83,79,0); }
  }
  /* Footer sticky */
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
  /* ===== LAYOUT CON SIDEBAR COLAPSABLE ===== */
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
    
    }

    /* Colapsada: sidebar fuera, contenido ancho completo */
    body.sb-collapsed .sidebar{
    
      width: 70px;
    }
    body.sb-collapsed .content-wrapper{ margin-left: 0 !important; }
    body.sb-collapsed main.main{ margin-left: 0 !important; }
  }

  /* ===== HOTSPOT de REVELADO cuando está oculto ===== */
  .sb-reveal-zone{
    position: fixed;
    top: calc(var(--tb-height, 64px) + 10px);
    left: 0;
    width: 8px;                 /* banda delgada */
    height: calc(100vh - var(--tb-height, 64px) - 20px);
    z-index: 1039;
    display: none;               /* visible solo en colapsado (ver media below) */
    opacity: 0;                  /* no distrae; aparece levemente al hover */
    transition: opacity .18s ease;
  }
  body.has-scrolled .sb-reveal-zone{
    top: calc(var(--tb-height-scrolled, 52px) + 8px);
    height: calc(100vh - var(--tb-height-scrolled, 52px) - 16px);
  }
  .sb-reveal-zone:hover{ opacity: .35; cursor: ew-resize; }
  .sb-reveal-zone:focus{ outline: none; }

@media (min-width: 992px) {
  /* Sidebar Colapsado: Reducimos ancho, NO lo movemos fuera */
  body.sb-collapsed .sidebar {
    width: 70px;
    transform: translateX(0); /* Evita que se esconda al -100% */
  }

  /* Ajuste automático del contenido al colapsar */
  body.sb-collapsed .content-wrapper,
  body.sb-collapsed main.main {
    margin-left: 40px !important;
    transition: margin-left 0.3s ease;
  }

  /* Ocultar textos e imágenes grandes al colapsar */
  body.sb-collapsed .sidebar .brand img,
  body.sb-collapsed .sidebar .menu a span,
  body.sb-collapsed .sidebar .footer a span,
  body.sb-collapsed .sb-bubbles {
   
  }

  /* Centrar iconos en modo colapsado */
  body.sb-collapsed .sidebar a i {
    margin-right: 0;
    width: 100%;
    text-align: center;
    font-size: 1.2rem;
  }
}
  /* Neon border animado */
  .sb-bling{ position: fixed; }
  .sb-bling::before{
    content:"";
    position:absolute; inset:-2px;
    border-radius: 14px;
    background: conic-gradient(from 0deg, var(--sb-neon-2), var(--sb-neon-3), var(--sb-neon-1), var(--sb-neon-2));
    filter: blur(6px);
    opacity: .65;
    z-index: 0;
    animation: conic-spin 6s linear infinite;
  }
  .sb-bling::after{
    content:"";
    position:absolute; inset:0;
    border-radius: 12px;
    background: linear-gradient(180deg, rgba(255,255,255,.1), rgba(255,255,255,.03));
    z-index: 1;
    pointer-events: none;
  }
  @keyframes conic-spin{ to { transform: rotate(360deg); } }

  /* ===== IMÁGENES FLOTANTES (logos) ===== */
  .sidebar .sb-bubbles{
  position: absolute;        /* importante: relativo al nav.sidebar */
  inset: 0;                  /* ocupa todo el area interior del sidebar */
  z-index: 1;                /* detrás del contenido si hace falta; ajusta si es necesario */
  pointer-events: none;
  overflow: hidden;          /* recorta cualquier exceso */
  width: 100%;
  height: 100%;
  top: 0;
  left: 0;
  transition: opacity .2s ease;
}
  body.has-scrolled .sidebar .sb-bubbles { top: 0; }
  .sidebar .sb-bubbles .bubble-img{
  position: absolute;
  left: var(--x);                         /* porcentaje relativo al ancho del sidebar */
  transform: translateX(-50%);            /* centra la imagen en ese punto */
  bottom: -60px;                          /* inicio desde fuera inferior */
  width:  calc(var(--bubble-base) * var(--s));
  height: calc(var(--bubble-base) * var(--s));
  width: 50px !important;
    height: 50px !important;
  object-fit: contain;
  opacity: .95;
  animation: rise-sway var(--d) linear infinite;
  will-change: transform, opacity, bottom;
  filter: drop-shadow(0 6px 10px rgba(0,0,0,.25));
}

  /* Oculta las imágenes cuando el sidebar está colapsado */
  body.sb-collapsed .sb-bubbles{
    opacity: 0;
    display: none;
  }
  /* Cada imagen “sube” como una burbuja con leve vaivén */
  /* Ascenso vertical + leve vaivén horizontal en una sola animación */
  @keyframes rise-sway{
    0%   { transform: translate(0,   0)     scale(1);    opacity: 0;   }
    10%  { transform: translate(1px, -9vh)  scale(1.02); opacity: .9;  }
    25%  { transform: translate(-2px,-25vh) scale(1.03); opacity: .85; }
    50%  { transform: translate(2px, -50vh) scale(1.06); opacity: .78; }
    75%  { transform: translate(-3px,-75vh) scale(1.08); opacity: .72; }
    100% { transform: translate(0,  -92vh)  scale(1.10); opacity: 0;   }
  }
  /* Respeto a reduced motion */
  @media (prefers-reduced-motion: reduce){
    .sb-bubbles .bubble-img{
      animation: none !important;
      opacity: .5;
    }
  }
.sb-collapsed .sb-submenu.open .sb-children{
  padding-left: 0 !important;
  background: #24a3c1 !important;
}


  /* ==== Usuarios pendientes - estilos mejorados ==== */
  .sidebar a.sb-item-attn{ position: relative; padding-right: 74px; }
  .sidebar a.sb-item-attn.has-alert{
    background: rgba(217,83,79,.10);
    border-color: rgba(217,83,79,.35);
    box-shadow: inset 0 0 0 1px rgba(217,83,79,.18);
    
  }
  .sidebar a.sb-item-attn.has-alert:hover{
    background: rgba(217,83,79,.14);
    border-color: rgba(217,83,79,.5);
    transform: translateX(1px);
  }
  .sidebar a.sb-item-attn .sb-meta{
    position: absolute; right: 10px; top: 50%;
    transform: translateY(-50%);
    display: inline-flex; align-items: center; gap: 8px;
  }
  /* Pill con gradiente y brillo sutil */
  .sb-badge-attn{
    min-width: 26px; height: 22px; padding: 0 10px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 999px;
    font-size: 12px; font-weight: 800; letter-spacing: .2px;
    color: #fff;
    background: linear-gradient(180deg, #e35d59, #bf3f3b);
    box-shadow:
      0 2px 10px rgba(227,93,89,.35),
      inset 0 0 0 1px rgba(255,255,255,.25);
    transform-origin: center;
  }
  .sb-badge-attn.is-zero{
    opacity: .0; transform: scale(.8); pointer-events: none; /* oculta cuando es 0 */
  }
  .sb-badge-attn.has-count{ animation: sb-badge-pop .42s cubic-bezier(.2,.8,.2,1) 1; }
  @keyframes sb-badge-pop{
    0%{ transform: scale(.85); }
    60%{ transform: scale(1.08); }
    100%{ transform: scale(1); }
  }
  /* Anillo/ping a la derecha cuando hay pendientes */
  .sb-ring{
    width: 8px; height: 8px; border-radius: 999px;
    background: #e35d59;
    box-shadow: 0 0 0 0 rgba(227,93,89,.72);
  }
  .sb-item-attn.has-alert .sb-ring{
    animation: sb-ping 1.8s cubic-bezier(0,0,.2,1) infinite;
  }
  @keyframes sb-ping{
    0%   { box-shadow: 0 0 0 0 rgba(227,93,89,.65); transform: scale(1); }
    70%  { box-shadow: 0 0 0 12px rgba(227,93,89,0); transform: scale(1.05); }
    100% { box-shadow: 0 0 0 0 rgba(227,93,89,0); transform: scale(1); }
  }
  /* Micro “bump” cuando cambia el número via JS */
  .sb-badge-attn.bump{ animation: sb-bump .5s cubic-bezier(.22,1,.36,1); }
  @keyframes sb-bump{
    0%{ transform: translateY(0) scale(1); }
    30%{ transform: translateY(-2px) scale(1.06); }
    100%{ transform: translateY(0) scale(1); }
  }


/* 1. Quitamos el padding excesivo para que el icono central quede libre */
.sb-collapsed .sidebar a.sb-item-attn {
 overflow: visible !important; 
  padding-right: 0 !important;
  display: flex !important;
  justify-content: center !important;
  align-items: center !important;
}

/* 2. Reposicionamos el badge para que flote sobre el icono (estilo notificación) */
.sb-collapsed .sidebar a.sb-item-attn .sb-meta {
  display: flex !important;    /* Evita que un display: none lo oculte */
  opacity: 1 !important;       /* Evita que un opacity: 0 lo oculte */
  visibility: visible !important;
  
  position: absolute !important;
  top: 8px !important;         /* Ajusta hacia arriba */
  right: 0px !important;       /* Ajusta hacia la derecha */
  transform: scale(0.95) !important; /* Un poco más pequeño para no tapar todo el icono */
  pointer-events: none;        /* Para que no interfiera con el click del link */
  z-index: 99;
}

/* 3. Reducimos el tamaño del badge para que no tape todo el icono */
.sb-collapsed .sb-badge-attn {
  min-width: 16px;
  height: 16px;
  font-size: 9px;
  padding: 0 4px;
  border: 1px solid #fff;
}

/* 4. (Opcional) Si tienes texto dentro del sb-item que no sea el badge, ocúltalo */
.sb-collapsed .sidebar a.sb-item-attn > span:not(.sb-meta) {
    display: none !important;
}

  /* Submenú del sidebar */
  .sb-submenu .sb-parent {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
  }
  .sb-submenu .sb-parent i.fa-list {
    width: 20px;
    text-align: center;
  }
  .sb-submenu .sb-arrow {
    margin-left: auto;
    transition: transform .25s ease;
  }
  .sb-submenu.open .sb-arrow {
    transform: rotate(180deg);
  }
  .sb-children {
    display: none;
    padding-left: 20px;
  }
  .sb-submenu.open .sb-children {
    display: block;
  }

  a#Out{
    color: #FF2056;
  }
</style>
<nav class="sidebar d-none d-lg-block" data-effect="blob">
  <div class="sb-bubbles d-none d-lg-block" aria-hidden="true">
  <img src="/sisec-ui/public/img/marcas/AVI.png"   alt="" class="bubble-img" style="--x:8%;  --d:11s; --s:0.9;">
  <img src="/sisec-ui/public/img/marcas/AXIS.png"  alt="" class="bubble-img" style="--x:22%; --d:13s; --s:1.2;">
  <img src="/sisec-ui/public/img/marcas/CISCO.PNG" alt="" class="bubble-img" style="--x:38%; --d:12s; --s:1.0;">
  <img src="/sisec-ui/public/img/marcas/DMP.PNG"   alt="" class="bubble-img" style="--x:46%; --d:14s; --s:0.85;">
  <img src="/sisec-ui/public/img/marcas/HAN.png"   alt="" class="bubble-img" style="--x:54%; --d:15s; --s:1.1;">
  <img src="/sisec-ui/public/img/marcas/HIK.png"   alt="" class="bubble-img" style="--x:68%; --d:10s; --s:0.8;">
  <img src="/sisec-ui/public/img/marcas/INO.png"   alt="" class="bubble-img" style="--x:76%; --d:13s; --s:0.9;">
  <img src="/sisec-ui/public/img/marcas/MILE.png"  alt="" class="bubble-img" style="--x:88%; --d:12s; --s:1.0;">
  <img src="/sisec-ui/public/img/marcas/UNI.png" alt="" class="bubble-img" style="--x:100%; --d:12s; --s:1.0;">
</div>
  <div class="inner">
    <div class="brand">
      <img src="/sisec-ui/public/img/QRCESISS.png" alt="Logo CESISS">
    </div>
    <div class="menu">
      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin', 'Administrador', 'Mantenimientos', 'Capturista'])): ?>
        <a href="/sisec-ui/views/inicio/index.php" class="<?= ($activePage ?? '') === 'inicio' ? 'active' : '' ?>">
          <i class="fas fa-home"></i> Inicio
        </a>
      <?php endif; ?>

      <!-- Opción agrupada "Listados" -->
      <?php
      // Asumiendo que ya tenemos el rol en $_SESSION['usuario_rol']
      $rol = $_SESSION['usuario_rol'] ?? null;

      // Roles que SÍ ven el submenu "Listados"
      $rolesConListados = ['Superadmin', 'Administrador', 'Mantenimientos', 'Técnico', 'Capturista'];

      // Si el rol está en la lista muestra el submenu completo
      if (in_array($rol, $rolesConListados)): ?>
        <div class="sb-submenu <?= ($activePage === 'dispositivos' || $activePage === 'listado_qr') ? 'open' : '' ?>">
          <a href="javascript:void(0);" class="sb-parent">
            <i class="fas fa-list"></i> Listados
            <i class="fas fa-chevron-down sb-arrow"></i>
          </a>
          <div class="sb-children">
            <a href="/sisec-ui/views/dispositivos/listar.php" class="<?= ($activePage ?? '') === 'dispositivos' ? 'active' : '' ?>">
              <i class="fas fa-desktop me-2"></i> Dispositivos
            </a>
            <a href="/sisec-ui/views/dispositivos/listado_qr.php"  <?= ($activePage ?? '') === 'listado_qr' ? 'active-link' : '' ?>">
              <i class="fas fa-list-alt me-2"></i>Listado QR
            </a>
            <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin', 'Mantenimientos','Técnico', 'Capturista'])): ?>
              <a href="/sisec-ui/views/dispositivos/qr_virgenes_generar.php" <?= ($activePage ?? '') === 'listado_qr' ? 'active-link' : '' ?>">
                <i class="fas fa-plus-square me-2"></i>Generar QR virgen
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>

        <a href="/sisec-ui/views/dispositivos/listar.php" class="<?= ($activePage ?? '') === 'dispositivos' ? 'active' : '' ?>">
          <i class="fas fa-camera"></i> Dispositivos
        </a>
      <?php endif; ?>
<!-- 
      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin'])): ?>
      <div class="sb-submenu <?= in_array(($activePage ?? ''), ['sucursales','mantenimientos']) ? 'open' : '' ?>">
        <a href="javascript:void(0);" class="sb-parent">
          <i class="fas fa-cogs"></i> Administración
          <i class="fas fa-chevron-down sb-arrow"></i>
        </a>
        <div class="sb-children">
          <a href="/sisec-ui/views/ubicacion/sucursales_crear.php"
            class="<?= ($activePage ?? '') === 'sucursales' ? 'active' : '' ?>">
            <i class="fas fa-store"></i> Sucursales
          </a>
          <a href="/sisec-ui/views/mantenimientos/programar.php"
            class="<?= ($activePage ?? '') === 'mantenimientos' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Mantenimientos
          </a>
        </div>
      </div>
    <?php endif; ?> -->

      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin', 'Capturista','Técnico'])): ?>
        <a href="/sisec-ui/views/dispositivos/registro.php" class="<?= ($activePage ?? '') === 'registro' ? 'active' : '' ?>">
          <i class="fas fa-plus-circle"></i> Registrar dispositivo
        </a>
      <?php endif; ?>

      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin'])): ?>
        <a href="/sisec-ui/views/usuarios/index.php" class="<?= ($activePage ?? '') === 'usuarios' ? 'active' : '' ?>">
          <i class="fa-solid fa-users"></i> Usuarios
        </a>
        <a href="/sisec-ui/views/usuarios/registrar.php" class="<?= ($activePage ?? '') === 'registrar' ? 'active' : '' ?>">
          <i class="fa-solid fa-user-plus"></i> Registrar usuario
        </a>

        <!--a href="/views/inicio/helpdesk.php" class="<?= ($activePage ?? '') === 'dispositivos' ? 'active' : '' ?>">
      <i class="fas fa-tools"></i>Soporte
    </a-->    
    <?php endif; ?>
    <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin'])): ?>
      <a href="/sisec-ui/views/usuarios/pendientes.php"
        id="linkPendientes"
        class="sb-item-attn <?= ($activePage ?? '') === 'pendiente' ? 'active' : '' ?> <?= ($pendCount ?? 0) > 0 ? 'has-alert' : '' ?>">
        <i class="fas fa-user-clock"></i>
        <span class="sb-item-label">Usuarios pendientes</span>

        <span class="sb-meta">
          <?php if (($pendCount ?? 0) > 0): ?>
            <span id="badgePend"
                  class="sb-badge-attn has-count"
                  aria-label="Solicitudes pendientes: <?= (int)$pendCount ?>">
              <?= (int)$pendCount ?>
            </span>
            <span class="sb-ring" aria-hidden="true"></span>
          <?php else: ?>
            <span id="badgePend" class="sb-badge-attn is-zero" aria-label="Sin solicitudes">0</span>
          <?php endif; ?>
        </span>
      </a>
    <?php endif; ?>
    </div>
    <?php if (isset($_SESSION['usuario_id'])): ?>
      <div class="footer">
        <a class="px-3 d-block " id="toggle-btn">
          <i class="fa-duotone fa-solid fa-angle-left"></i>
          <span>Ocultar menu</span>
        </a>

        <a href="/sisec-ui/logout.php" class=" px-3 d-block" id="Out">
          <i class="fas fa-sign-out-alt"></i>
          <span>Cerrar sesión</span>
        </a>
      </div>
    <?php endif; ?>
  </div>
  <script>
  const btn = document.getElementById('toggle-btn');
  const sidebar = document.querySelector('.sidebar');

  btn.addEventListener('click', () => {
  sidebar.classList.toggle('collapsed');
  });
  </script>
</nav>

<!-- HOTSPOT para revelar el botón cuando el sidebar está oculto -->
<div class="sb-reveal-zone" tabindex="0" aria-label="Mostrar pestaña del sidebar"></div>
<!-- Imágenes flotantes (reemplazo de burbujas) -->




<script>
(function() {
  const STORAGE_KEY = 'cesiss.sidebar.collapsed';
  const body = document.body;
  const btn = document.getElementById('toggle-btn');
  const bubbles = document.querySelector('.sb-bubbles');
  const revealZone = document.querySelector('.sb-reveal-zone');

  if (!btn) return;

  // --- 1. Definición de funciones internas ---

  function setCollapsed(collapsed, opts = {}) {
    const { skipConfetti = false } = opts;
    const btnText = btn.querySelector('span'); // Selecciona el texto "Ocultar menu"
    const icon = btn.querySelector('i');       // Selecciona el icono

    if (collapsed) {
      body.classList.add('sb-collapsed');
      if (btnText) btnText.style.display = 'none'; 
      if (icon) icon.className = 'fa-duotone fa-solid fa-angle-right';
      localStorage.setItem(STORAGE_KEY, '1');
      if (!skipConfetti) burst(btn, { mode: 'open' });
      if (bubbles) bubbles.style.display = 'none';
    } else {
      body.classList.remove('sb-collapsed');
      if (btnText) btnText.style.display = 'inline';
      if (icon) icon.className = 'fa-duotone fa-solid fa-angle-left';
      localStorage.setItem(STORAGE_KEY, '0');
      if (!skipConfetti) burst(btn, { mode: 'close' });
      if (bubbles) bubbles.style.display = '';
    }
    resetIdle();
  }

  function burst(anchor, { mode }) {
    const rect = anchor.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const pieces = 16;
    const colors = ['#24a3c1', '#3C92A6', '#74e0ff', '#9af0ff'];

    for (let i = 0; i < pieces; i++) {
      const d = document.createElement('span');
      d.className = 'spark';
      const angle = (Math.PI * 2) * (i / pieces);
      const dist = 24 + Math.random() * 14;
      const tx = Math.cos(angle) * dist;
      const ty = Math.sin(angle) * dist;
      d.style.position = 'fixed';
      d.style.left = (cx - 3) + 'px';
      d.style.top = (cy - 3) + 'px';
      d.style.width = d.style.height = (3 + Math.random() * 3) + 'px';
      d.style.borderRadius = '2px';
      d.style.background = colors[i % colors.length];
      d.style.boxShadow = '0 0 14px rgba(36,163,193,.6)';
      d.style.zIndex = 2000;
      d.style.transform = 'translate3d(0,0,0)';
      d.style.transition = 'transform .6s cubic-bezier(.22,1,.36,1), opacity .6s ease';
      document.body.appendChild(d);

      requestAnimationFrame(() => {
        d.style.transform = `translate3d(${tx}px, ${ty}px, 0)`;
        d.style.opacity = '0';
      });
      setTimeout(() => d.remove(), 650);
    }
  }

  // --- 2. Lógica de Interacción y Timers ---

  let idleTimer = null;
  const setIdle = () => btn.classList.add('is-idle');
  const clearIdle = () => btn.classList.remove('is-idle');

  const resetIdle = () => {
    clearIdle();
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(setIdle, 2500);
  };

  ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(ev =>
    window.addEventListener(ev, resetIdle, { passive: true })
  );
  resetIdle();

  // --- 3. Inicialización y Eventos ---

  // Estado guardado inicial
  const saved = localStorage.getItem(STORAGE_KEY);
  setCollapsed(saved === '1', { skipConfetti: true });

  // Click del botón
  btn.addEventListener('click', () => {
    setCollapsed(!body.classList.contains('sb-collapsed'));
  });

  // Hotspot / Reveal Zone
  if (revealZone) {
    revealZone.addEventListener('click', () => {
      if (body.classList.contains('sb-collapsed')) setCollapsed(false);
    });
    revealZone.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (body.classList.contains('sb-collapsed')) setCollapsed(false);
      }
    });
  }

  // Atajo Alt+S
  window.addEventListener('keydown', (e) => {
    if (e.altKey && (e.key.toLowerCase() === 's')) {
      e.preventDefault();
      setCollapsed(!body.classList.contains('sb-collapsed'));
    }
  }, { passive: false });

})(); // <-- Cierre correcto de la IIFE envolviendo todo
</script>


<script>
(function(){
  const rol = <?= json_encode($_SESSION['usuario_rol'] ?? '') ?>;
  if (!['Superadmin','Administrador'].includes(rol)) return;

  const badge = document.getElementById('badgePend');
  if (!badge) return;

  const URL = '/sisec-ui/controllers/badges.php';

  async function refreshBadge(){
    try{
      const res = await fetch(URL, { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || data.ok !== true) return;

      const n = Number(data.count || 0);
      if (n > 0){
        badge.textContent = n;
        badge.classList.remove('d-none');
        badge.classList.add('pulse');
      }else{
        badge.textContent = '0';
        badge.classList.add('d-none');
        badge.classList.remove('pulse');
      }
    }catch(e){ /* silencioso */ }
  }

  // Primer update rápido y luego polling cada 45s
  refreshBadge();
  setInterval(refreshBadge, 45000);
})();
</script>

<script>
(function(){
  const rol = <?= json_encode($_SESSION['usuario_rol'] ?? '') ?>;
  if (!['Superadmin','Administrador'].includes(rol)) return;

  const badge = document.getElementById('badgePend');
  const link  = document.getElementById('linkPendientes');
  if (!badge || !link) return;

  const URL = '/sisec-ui/controllers/badges.php';
  let last = parseInt(badge.textContent || '0', 10);

  async function refreshBadge(){
    try{
      const res = await fetch(URL, { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || data.ok !== true) return;

      const n = Number(data.count || 0);

      // Mostrar/ocultar pill
      if (n > 0){
        badge.textContent = n;
        badge.classList.remove('is-zero');
        badge.classList.add('has-count');
        link.classList.add('has-alert');
      } else {
        badge.textContent = '0';
        badge.classList.add('is-zero');
        badge.classList.remove('has-count');
        link.classList.remove('has-alert');
      }

      // Micro-animación al cambiar el número
      if (n !== last){
        badge.classList.remove('bump'); // reset si estaba
        // forzar reflow para reiniciar animación
        void badge.offsetWidth;
        badge.classList.add('bump');
        last = n;
      }
    }catch(e){ /* silencioso */ }
  }

  // Existen ya tus otros scripts; esto no interfiere
  refreshBadge();
  setInterval(refreshBadge, 45000);
})();
</script>
<script>
document.querySelectorAll('.sb-submenu .sb-parent').forEach(parent => {
  parent.addEventListener('click', () => {
    parent.parentElement.classList.toggle('open');
  });
});
</script>