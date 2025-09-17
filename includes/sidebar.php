<?php
// views/includes/sidebar.php
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
    --sidebar-w: 300px;
    --item-h: 44px;
    --radius: 10px;

    --safe-top: env(safe-area-inset-top, 0px);

    /* Acentos para el botón neon */
    --sb-neon-1: #24a3c1;
    --sb-neon-2: #3C92A6;
    --sb-neon-3: #74e0ff;
    --sb-glass-bg: rgba(8, 26, 31, .55);
    --sb-border: rgba(255,255,255,.16);
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
    overflow: hidden; /* fallback */
    overflow: clip;   /* preferido */
    display: block;
    transition: top .35s ease, transform .34s cubic-bezier(.22,1,.36,1);
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
      padding-right: 1rem;
    }

    /* Colapsada: sidebar fuera, contenido ancho completo */
    body.sb-collapsed .sidebar{
      transform: translateX(-100%);
    }
    body.sb-collapsed .content-wrapper{ margin-left: 0 !important; }
    body.sb-collapsed main.main{ margin-left: 0 !important; }
  }

  /* ===== BOTÓN TOGGLE SÚPER ANIMADO ===== */
  .sb-toggle{
    position: fixed;
    top: calc(var(--tb-height, 64px) + 12px);
    left: calc(var(--sidebar-w) - 16px);
    z-index: 1040;
    width: 42px; height: 42px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 12px;
    border: 1px solid var(--sb-border);
    background:
      linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.02)),
      linear-gradient(180deg, var(--sb-glass-bg), rgba(10,33,40,.55));
    backdrop-filter: blur(10px);
    box-shadow:
      0 10px 26px rgba(0,0,0,.35),
      0 0 0 1px rgba(255,255,255,.06) inset;
    color: var(--side-fg);
    cursor: pointer;
    transition: left .25s ease, transform .08s ease, box-shadow .2s ease, background .3s ease;
    overflow: hidden;
  }
  .sb-toggle:hover{
    transform: translateY(-1px);
    box-shadow:
      0 12px 30px rgba(0,0,0,.38),
      0 0 24px rgba(36,163,193,.25);
  }
  body.has-scrolled .sb-toggle{
    top: calc(var(--tb-height-scrolled, 52px) + 12px);
  }
  @media (min-width: 992px){
    body.sb-collapsed .sb-toggle{ left: 14px; }
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

  /* Icono rota según estado */
  .sb-toggle .ico{
    position: relative;
    z-index: 2;
    font-size: 1.05rem;
    transition: transform .35s ease;
  }
  body.sb-collapsed .sb-toggle .ico{ transform: rotate(180deg); }

  /* Sheen (destello) */
  .sb-toggle .sheen{
    position: absolute; inset: 0;
    background:
      linear-gradient(120deg, transparent 15%, rgba(255,255,255,.22) 35%, transparent 55%);
    transform: translateX(-120%);
    animation: sheen-move 2.6s ease-in-out infinite;
    mix-blend-mode: screen;
    z-index: 1;
  }
  @keyframes sheen-move{
    0%, 12% { transform: translateX(-120%); }
    36%     { transform: translateX(120%); }
    100%    { transform: translateX(120%); }
  }

  /* Halo / pulso */
  .sb-toggle .pulse{
    position: absolute; inset: -14px;
    border-radius: 16px;
    pointer-events: none;
    box-shadow: 0 0 0 0 rgba(36,163,193,.0);
    animation: pulse-glow 2.2s ease-in-out infinite;
  }
  @keyframes pulse-glow{
    0%   { box-shadow: 0 0 0 0 rgba(36,163,193,.0); }
    40%  { box-shadow: 0 0 0 12px rgba(36,163,193,.12), 0 0 28px 8px rgba(36,163,193,.22); }
    100% { box-shadow: 0 0 0 0 rgba(36,163,193,.0); }
  }

  /* ===== GOOEY BUBBLES ===== */
  .sb-bubbles{
    position: fixed;
    pointer-events: none;
    z-index: 1035;
    left: 0;
    top: var(--tb-height, 64px);
    width: var(--sidebar-w);
    bottom: 0;
    filter: url(#gooey);
  }
  body.has-scrolled .sb-bubbles{ top: var(--tb-height-scrolled, 52px); }

  .sb-bubbles .bubble{
    position: absolute;
    left: var(--x);
    bottom: -40px;
    width: calc(14px * var(--s));
    height: calc(14px * var(--s));
    border-radius: 999px;
    background: radial-gradient(circle at 30% 30%, var(--sb-neon-3), var(--sb-neon-2));
    opacity: .28;
    animation: bubble-up var(--d) linear infinite;
  }
  @keyframes bubble-up{
    0%   { transform: translateY(0) scale(1); opacity: .0; }
    10%  { opacity: .35; }
    80%  { opacity: .28; }
    100% { transform: translateY(-92vh) scale(1.1); opacity: 0; }
  }

  /* ===== Respeto a reduced motion ===== */
  @media (prefers-reduced-motion: reduce){
    .sidebar *, .sidebar::before,
    .sb-toggle, .sb-bubbles .bubble, .sb-bling::before,
    .sb-toggle .sheen, .sb-toggle .pulse{
      animation: none !important;
      transition: none !important;
    }
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

      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin', 'Administrador'])): ?>
        <a href="/sisec-ui/views/usuarios/pendientes.php" class="<?= ($activePage ?? '') === 'pendiente' ? 'active' : '' ?>">
          <i class="fas fa-users"></i> Usuarios pendientes
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

<!-- Botón flotante para ocultar/mostrar sidebar -->

<button id="sbToggle"
        class="sb-toggle d-none d-lg-flex sb-bling"
        type="button"
        aria-label="Alternar barra lateral"
        aria-pressed="false"
        title="Ocultar/mostrar barra lateral (Alt+S)">
  <span class="ico"><i class="fas fa-angles-left" aria-hidden="true"></i></span>
  <span class="sheen"></span>
  <span class="pulse"></span>
</button>

<!-- Definición del filtro Gooey -->
<svg class="sb-gooey-defs" width="0" height="0" aria-hidden="true" focusable="false">
  <defs>
    <filter id="gooey">
      <feGaussianBlur in="SourceGraphic" stdDeviation="6" result="blur"></feGaussianBlur>
      <feColorMatrix in="blur" mode="matrix"
        values="1 0 0 0 0
                0 1 0 0 0
                0 0 1 0 0
                0 0 0 18 -7" result="goo"></feColorMatrix>
      <feBlend in="SourceGraphic" in2="goo"></feBlend>
    </filter>
  </defs>
</svg>

<!-- Burbujas decorativas -->
<div class="sb-bubbles d-none d-lg-block" aria-hidden="true">
  <span class="bubble" style="--x:8%;  --d:10s; --s:1;"></span>
  <span class="bubble" style="--x:22%; --d:14s; --s:1.25;"></span>
  <span class="bubble" style="--x:38%; --d:12s; --s:0.95;"></span>
  <span class="bubble" style="--x:56%; --d:16s; --s:1.3;"></span>
  <span class="bubble" style="--x:74%; --d:13s; --s:1.15;"></span>
  <span class="bubble" style="--x:88%; --d:11s; --s:0.9;"></span>
</div>



<script>
(function(){
  const STORAGE_KEY = 'cesiss.sidebar.collapsed';
  const body = document.body;
  const btn  = document.getElementById('sbToggle');
  const bubbles = document.querySelector('.sb-bubbles');

  if (!btn) return;

  // Estado guardado
  const saved = localStorage.getItem(STORAGE_KEY);
  const startCollapsed = saved === '1';
  setCollapsed(startCollapsed, {skipConfetti:true});

  // Click
  btn.addEventListener('click', () => {
    setCollapsed(!body.classList.contains('sb-collapsed'));
  });

  // Alt+S
  window.addEventListener('keydown', (e) => {
    if (e.altKey && (e.key.toLowerCase() === 's')) {
      e.preventDefault();
      setCollapsed(!body.classList.contains('sb-collapsed'));
    }
  }, { passive: false });

  function setCollapsed(collapsed, opts = {}){
    const { skipConfetti = false } = opts;
    if (collapsed) {
      body.classList.add('sb-collapsed');
      btn.setAttribute('aria-pressed', 'true');
      btn.querySelector('.ico').innerHTML = '<i class="fas fa-angles-right" aria-hidden="true"></i>';
      localStorage.setItem(STORAGE_KEY, '1');
      if (!skipConfetti) burst(btn, { mode: 'open' });
    } else {
      body.classList.remove('sb-collapsed');
      btn.setAttribute('aria-pressed', 'false');
      btn.querySelector('.ico').innerHTML = '<i class="fas fa-angles-left" aria-hidden="true"></i>';
      localStorage.setItem(STORAGE_KEY, '0');
      if (!skipConfetti) burst(btn, { mode: 'close' });
    }
  }

  // Mini confeti / chispas
  function burst(anchor, { mode }){
    const rect = anchor.getBoundingClientRect();
    const cx = rect.left + rect.width/2;
    const cy = rect.top  + rect.height/2;
    const pieces = 16;
    const colors = ['#24a3c1','#3C92A6','#74e0ff','#9af0ff'];

    for (let i=0; i<pieces; i++){
      const d = document.createElement('span');
      d.className = 'spark';
      const angle = (Math.PI*2) * (i/pieces);
      const dist = 24 + Math.random()*14;
      const tx = Math.cos(angle)*dist;
      const ty = Math.sin(angle)*dist;
      d.style.position = 'fixed';
      d.style.left = (cx - 3) + 'px';
      d.style.top  = (cy - 3) + 'px';
      d.style.width = d.style.height = (3 + Math.random()*3) + 'px';
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

  // Posiciona el contenedor de burbujas como la sidebar (izq)
  function syncBubblesTop(){
    const top = body.classList.contains('has-scrolled')
      ? getComputedStyle(document.documentElement).getPropertyValue('--tb-height-scrolled') || '52px'
      : getComputedStyle(document.documentElement).getPropertyValue('--tb-height') || '64px';
    if (bubbles){
      bubbles.style.top = top.trim();
    }
  }
  syncBubblesTop();
  window.addEventListener('scroll', () => {
    // tu topbar ya cambia has-scrolled; sincronizamos el top de bubbles
    syncBubblesTop();
  }, { passive: true });
})();
</script>
