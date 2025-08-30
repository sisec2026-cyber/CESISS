<?php
/**
 * CESISS - Footer reutilizable con 2 logos opcionales
 *
 * Uso básico:
 *   // (Opcional) Personaliza antes de incluir:
 *   $cesiss_version = 'v1.3.0';
 *   $cesiss_env     = 'Producción'; // o null para ocultar
 *   $cesiss_base    = '/sisec-ui';
 *   $cesiss_site    = 'https://www.cesiss.com';
 *   $cesiss_support = 'soporte@cesiss.com';
 *
 *   include __DIR__ . '/footer.php';
 */

$cesiss_version = $cesiss_version ?? '';
$cesiss_env     = $cesiss_env     ?? null;
$cesiss_base    = $cesiss_base    ?? '/sisec-ui';
$cesiss_site    = $cesiss_site    ?? 'https://www.cesiss.com';
$cesiss_support = $cesiss_support ?? 'soporte@cesiss.com';

// Logos en el centro:
$cesiss_logo1      = $cesiss_logo1      ?? '/sisec-ui/public/img/logo.png';
$cesiss_logo1_alt  = $cesiss_logo1_alt  ?? 'Logo 1';
$cesiss_logo1_href = $cesiss_logo1_href ?? 'https://www.cesiss.com';

$cesiss_logo2      = $cesiss_logo2      ?? '/sisec-ui/public/img/sucursales/default.png';
$cesiss_logo2_alt  = $cesiss_logo2_alt  ?? 'Logo 2';
$cesiss_logo2_href = $cesiss_logo2_href ?? 'https://www.suburbia.com.mx';

$logos = [];
if (!empty($cesiss_logo1)) {
  $logos[] = [
    'src'  => $cesiss_logo1,
    'alt'  => $cesiss_logo1_alt,
    'href' => $cesiss_logo1_href,
  ];
}
if (!empty($cesiss_logo2)) {
  $logos[] = [
    'src'  => $cesiss_logo2,
    'alt'  => $cesiss_logo2_alt,
    'href' => $cesiss_logo2_href,
  ];
}

/* Inyecta el CSS una sola vez aunque este footer se incluya múltiples veces. */
if (!defined('CESISS_FOOTER_CSS')) {
  define('CESISS_FOOTER_CSS', true);
  ?>
  <style>
    /* ====== FOOTER (tema oscuro CESISS) ====== */
    .site-footer{
      position: fixed;
      left: 0; right: 0; bottom: 0;
      z-index: 10; /* deja el contenido por encima si tú lo subes, pero no debería hacer falta */
      color: var(--muted, #9ab7bf);
      /* fondo difuminado */
      backdrop-filter: blur(6px) saturate(120%);
      -webkit-backdrop-filter: blur(6px) saturate(120%);
      background: linear-gradient(to top, rgba(0,0,0,.55), rgba(0,0,0,.15) 60%, transparent);
      border-top: 1px solid var(--card-border, #16323a);
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans";
    }
    /* Asegura que cualquier capa decorativa del footer NO capture clics fuera de su área */
    .site-footer::before,
    .site-footer::after { pointer-events: none !important; }

    .site-footer .foot-inner{
      display: flex;
      gap: .75rem;
      align-items: center;
      justify-content: space-between;
      padding: .6rem 1rem;
      max-width: 1200px;
      margin: 0 auto;
      font-size: .9rem;
      position: relative; z-index: 1; /* el contenido sí es clicable */
      pointer-events: auto;
    }
    .foot-left, .foot-center, .foot-right{
      display: flex;
      align-items: center;
      gap: .5rem;
      flex-wrap: wrap;
    }
    .foot-links a{
      color: #7fd3e5;
      text-decoration: none;
    }
    .foot-links a:hover{ color: #a6e9f5; text-decoration: underline; }
    .sep{ opacity: .5; }

    /* ====== LOGOS DEL FOOTER ====== */
    .foot-logos{
      display: flex;
      align-items: center;
      gap: .6rem;
      margin-left: .6rem;
    }
    .foot-logos a{ display: inline-flex; align-items: center; }
    .foot-logos img{
      display: block;
      height: 50px;       /* ajusta tamaño aquí */
      width: auto;
      object-fit: contain;
      filter: drop-shadow(0 2px 8px rgba(0,0,0,.35));
      opacity: .95;
    }

    .badge-env{
      font-size: .75rem;
      padding: .15rem .5rem;
      border-radius: 999px;
      border: 1px solid var(--card-border, #16323a);
      background: #0c1b20;
      color: #cfe5ea;
    }

    @media (max-width: 640px){
      .site-footer .foot-inner{
        flex-direction: column;
        align-items: stretch;
        gap: .35rem;
      }
      .foot-center{
        justify-content: space-between;
      }
      .foot-logos{
        justify-content: center;
        margin-left: 0;
      }
    }
  </style>
  <?php
}

/* JS para ajustar padding-bottom del <body> según la altura real del footer, de forma reactiva */
if (!defined('CESISS_FOOTER_JS')) {
  define('CESISS_FOOTER_JS', true);
  ?>
  <script>
    (function(){
      function setBodyPaddingFromFooter(){
        var f = document.querySelector('footer.site-footer');
        if (!f) return;
        var h = f.offsetHeight || 0;
        /* deja un pequeño margen extra */
        var extra = 8;
        document.body.style.paddingBottom = (h + extra) + 'px';
        /* expone la altura como variable CSS por si la quieres usar */
        document.documentElement.style.setProperty('--footer-h', (h + extra) + 'px');
      }

      function onReady(fn){
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn, { once: true }); }
      }

      onReady(function(){
        setBodyPaddingFromFooter();
        /* Reajusta en resize/orientación */
        window.addEventListener('resize', setBodyPaddingFromFooter);
        window.addEventListener('orientationchange', setBodyPaddingFromFooter);

        /* Reajusta si cambian logos o contenido (altura variable) */
        if ('ResizeObserver' in window) {
          var f = document.querySelector('footer.site-footer');
          var ro = new ResizeObserver(setBodyPaddingFromFooter);
          if (f) ro.observe(f);
        } else {
          /* fallback: recalcula después de cargar imágenes */
          window.addEventListener('load', setBodyPaddingFromFooter, { once: true });
          setTimeout(setBodyPaddingFromFooter, 600);
        }
      });
    })();
  </script>
  <?php
}
?>

<footer class="site-footer" role="contentinfo" aria-label="Pie de página">
  <div class="foot-inner">
    <div class="foot-left">
      <strong>CESISS</strong>
      <span>© <?= date('Y') ?> Todos los derechos reservados</span>
    </div>

    <div class="foot-center">
      <div class="foot-links">
        <a href="<?= htmlspecialchars($cesiss_base) ?>/views/inicio/condiciones.php">Terminos y Condiciones</a>
        <span class="sep">·</span>
        <a href="<?= htmlspecialchars($cesiss_base) ?>/views/inicio/aviso_privacidad.php">Aviso de privacidad</a>
        <span class="sep">·</span>
        <a href="<?= htmlspecialchars($cesiss_base) ?>/views/inicio/soporte.php">Soporte</a>
        <span class="sep">·</span>
        <a href="<?= htmlspecialchars($cesiss_site) ?>" target="_blank" rel="noopener">Sitio</a>
      </div>

      <?php if (!empty($logos)): ?>
        <div class="foot-logos" aria-label="Logos">
          <?php foreach ($logos as $logo): ?>
            <?php if (!empty($logo['href'])): ?>
              <a href="<?= htmlspecialchars($logo['href']) ?>" target="_blank" rel="noopener">
                <img src="<?= htmlspecialchars($logo['src']) ?>" alt="<?= htmlspecialchars($logo['alt']) ?>" loading="lazy" decoding="async">
              </a>
            <?php else: ?>
              <img src="<?= htmlspecialchars($logo['src']) ?>" alt="<?= htmlspecialchars($logo['alt']) ?>" loading="lazy" decoding="async">
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="foot-right">
      <?php if (!empty($cesiss_env)) : ?>
        <span class="badge-env" title="Entorno"><?= htmlspecialchars($cesiss_env) ?></span>
        <span class="sep">·</span>
      <?php endif; ?>
      <span><?= htmlspecialchars($cesiss_version) ?></span>
    </div>
  </div>
</footer>
