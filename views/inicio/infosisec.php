<?php
// Página pública: NO requiere login
$TITLE = '¿Quién es SISEC?';
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
    .container{ max-width: 980px; margin: 90px auto 40px; padding: 0 16px; }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
      border:1px solid var(--card-border); border-radius:16px; box-shadow: var(--shadow);
      padding:24px;
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
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h1><i class="fa-solid fa-shield-halved"></i>¿Qué es SISEC?</h1>
      <div class="subtitle">Última actualización: <?= date('F Y') ?></div>

      <p>
        El Ing. Saúl Jiménez Hernández, con el objetivo de minimizar la pérdida de equipo y producto de sus clientes, inició la instalación y mantenimiento de sistemas de alarmas y radiocomunicación, dando origen a una organización especializada que denominó comercialmente Sistemas de Seguridad y Comunicación “SISEC”.
Actualmente, SISEC se encuentra en un proceso de mejora continua, enfocado en incrementar la satisfacción de sus clientes actuales y futuros. De manera paralela, nuestro equipo se mantiene en capacitación constante, adaptándose a la evolución tecnológica en el ámbito de seguridad electrónica y radiocomunicaciones. Esta dedicación nos ha permitido consolidar relaciones sólidas con nuestros clientes, posicionándonos como una opción confiable para cubrir sus necesidades, requerimientos y expectativas.
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

      <h2><i class="fa-solid fa-lock"></i> Transferencia de datos personales</h2>
      <p>
       Sus datos personales no serán transferidos a terceros, únicamente para fines de verificación de servicios, 
       así como los casos previstos por la Ley.
      </p>

      <h2><i class="fa-solid fa-user-shield"></i> Derechos ARCO</h2>
      <p>Usted tiene derecho a Acceder, Rectificar, Cancelar u Oponerse (ARCO) el tratamiento de sus datos personales.</p>
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

  <?php
  // Si tu footer no depende de la sesión/roles, puedes incluirlo tal cual:
  include __DIR__ . '/../includes/footer.php';
  ?>
</body>
</html>