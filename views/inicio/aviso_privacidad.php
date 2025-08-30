<?php
// Página pública: NO requiere login
$TITLE = 'Aviso de Privacidad - CESISS';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Font Awesome (iconos) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    :root{
      --brand: #3C92A6;
      --brand-2:#24a3c1;
      --bg-1:#07161a;
      --bg-2:#0a2128;
      --fg:#cfe5ea;
      --muted:#9ab7bf;
      --card:#0d1e24;
      --card-border:#16323a;
      --shadow: 0 10px 30px rgba(0,0,0,.35);
    }
    body{
      margin:0; background: radial-gradient(1200px 800px at 10% -20%, #0c1b20, transparent),
                           radial-gradient(1200px 800px at 100% 120%, #0b242c, transparent),
                           linear-gradient(180deg, var(--bg-1), var(--bg-2));
      color:var(--fg); font-family: system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans";
      min-height:100vh; padding-bottom:84px; /* espacio para el footer fijo */
    }
    .container{ max-width: 980px; margin: 90px auto 40px; padding: 0 16px; position: relative; z-index: 1; }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
      border:1px solid var(--card-border); border-radius:16px; box-shadow: var(--shadow);
      padding:24px; position: relative; z-index: 2;
    }
    h1{
      display:flex; gap:.6rem; align-items:center; margin:0 0 8px 0;
      font-size: clamp(22px, 2.6vw, 28px);
    }
    .subtitle{ color:var(--muted); margin-bottom: 18px; }
    h2{ margin-top: 24px; color:#e7f6fa; font-size: 1.15rem; }
    p, li{ color:#cfe5ea; line-height:1.6; }
    .tag{
      display:inline-flex; align-items:center; gap:.4rem;
      border:1px solid var(--card-border); background:#0c1b20; color:#aee6f2;
      font-size:.85rem; padding:.2rem .6rem; border-radius:999px; margin-right:.4rem;
    }
    a { color:#7fd3e5; text-decoration:none; }
    a:hover { color:#a6e9f5; text-decoration:underline; }
    .list{ padding-left: 1rem; }

    /* Chip ARCO */
    .hl{
      display:inline-flex; align-items:center;
      background: linear-gradient(180deg, rgba(127,211,229,.18), rgba(127,211,229,.08));
      border: 1px solid var(--card-border);
      border-radius: 999px;
      padding: 0 .45rem;
      color: #aee6f2 !important;
      text-decoration: none !important;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.04);
      transition: box-shadow .2s ease, transform .08s ease;
      white-space: nowrap;
      cursor: pointer;
      position: relative; z-index: 3;
      pointer-events: auto !important; /* fuerza clic en el propio elemento */
    }
    .hl:hover, .hl:focus-visible{
      text-decoration: none;
      box-shadow: 0 0 0 3px rgba(127,211,229,.25), 0 0 18px rgba(127,211,229,.25);
      transform: translateY(-1px);
      outline: none;
    }

    /* Si usas topbar con pseudo-elementos, que no bloqueen clics */
    header.topbar::before,
    header.topbar::after { pointer-events: none !important; }

    /* --- DEBUG opcional para ver overlays (quitalo luego) --- */
    /* * { outline: 1px dashed rgba(255,0,0,.15); } */
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h1><i class="fa-solid fa-shield-halved"></i> Aviso de Privacidad</h1>
      <div class="subtitle">Última actualización: <?= date('F Y') ?></div>

      <p>
        En <strong>CESISS</strong> nos tomamos muy en serio la protección de tus datos personales.
        Este aviso describe qué datos recopilamos, con qué finalidad y cuáles son tus derechos.
      </p>

      <h2><i class="fa-regular fa-file-lines"></i> ¿Qué datos recopilamos?</h2>
      <ul class="list">
        <li>Nombre completo.</li>
        <li>Correo electrónico.</li>
        <li>Teléfono de contacto.</li>
        <li>Usuario y contraseña asignados por la empresa.</li>
        <li>Historial de consultas en el sistema.</li>
      </ul>

      <h2><i class="fa-solid fa-bullseye"></i> Finalidades</h2>
      <p>Los datos personales serán utilizados para las siguientes finalidades:</p>
      <ul class="list">
        <li>Permitir el acceso seguro a la plataforma digital</li>
        <li>Consultar sistemas instalados y servicios de mantenimiento.</li>
        <li>Mantener un historial de servicios otorgados.</li>
        <li>Contacto para aclaraciones y soporte técnico</li>
      </ul>

      <h2><i class="fa-solid fa-lock"></i> Transferencia de datos personales</h2>
      <p>
       Sus datos personales no serán transferidos a terceros, únicamente para fines de verificación de servicios, 
       así como los casos previstos por la Ley.
      </p>

      <h2><i class="fa-solid fa-user-shield"></i> Derechos ARCO</h2>
      <p>
        Usted tiene derecho a Acceder, Rectificar, Cancelar u Oponerse 
        <!-- 1) ENLACE normal -->
        (<a id="arco-link" class="hl" href="arco.php" aria-label="Conoce tus derechos ARCO">ARCO</a>)
        <!-- 2) WRAPPER con onClick por si algún CSS anula el <a> -->
        <span id="arco-fallback"
              role="link"
              tabindex="0"
              style="margin-left:.35rem; display:inline-block; font-size:.9rem; opacity:.85; cursor:pointer;"
              onclick="window.location.href='arco.php';"
              onkeydown="if(event.key==='Enter' || event.key===' '){ event.preventDefault(); this.click(); }">
          (clic alternativo)
        </span>
        al tratamiento de sus datos personales.
      </p>

      <p>
        Para ejercer estos derechos, podrá enviar una solicitud al correo: soporte@cesiss.com,  
        indicando su nombre completo, los datos a los que desea acceder, rectificar, cancelar u oponerse, y adjuntando copia de una identificación oficial.
      </p>

      <h2><i class="fa-solid fa-rotate"></i> Opciones para limitar uso o divulgación de Datos</h2>
      <p>
        Usted puede limitar el uso o divulgación de sus datos personales enviando un correo a la dirección señalada en el punto anterior, 
        o solicitando la cancelación de su usuario en la Plataforma.
      </p>

      <h2><i class="fa-solid fa-user-shield"></i> Uso de Cookies</h2>
      <p>Usted tiene derecho a Acceder, Rectificar, Cancelar u Oponerse (ARCO) el tratamiento de sus datos personales.</p>

      <h2><i class="fa-solid fa-rotate"></i> Cambios al aviso</h2>
      <p>
        Este aviso de Privacidad puede sufrir modificaciones o actualizaciones. 
        Cualquier cambio será publicado en la presente página web, (indicando la fecha de la última actualización).
      </p>

      <h2><i class="fa-solid fa-building"></i> Información de contacto</h2>
      <p class="subtitle">
        <span class="tag"><i class="fa-solid fa-building-shield"></i> CESISS</span>
        <span class="tag"><i class="fa-solid fa-envelope"></i> soportecesiss@gmail.com</span>
      </p>
    </div>
  </div>

  <?php include __DIR__ . '/../../includes/footer.php'; ?>

  <!-- 3) DIAGNÓSTICO: resalta el elemento que está arriba del link (quítalo luego) -->
  <script>
    (function () {
      const link = document.getElementById('arco-link');
      if (!link) return;

      // Si el click llega al <a>, lo sabremos en consola.
      link.addEventListener('click', () => console.log('[ARCO] click en el <a> OK'));

      // Detecta qué elemento está por encima del centro del enlace:
      requestAnimationFrame(() => {
        const r = link.getBoundingClientRect();
        const x = Math.round(r.left + r.width / 2);
        const y = Math.round(r.top + r.height / 2);
        const topEl = document.elementFromPoint(x, y);

        if (topEl && topEl !== link) {
          console.warn('[ARCO] Hay un overlay encima del enlace:', topEl);
          try { topEl.style.outline = '3px dashed red'; } catch(e){}
          // Intenta permitir que el enlace reciba clics:
          try { topEl.style.pointerEvents = 'none'; console.warn('[ARCO] Se puso pointer-events:none al overlay detectado.'); } catch(e){}
        }
      });
    })();
  </script>
</body>
</html>
