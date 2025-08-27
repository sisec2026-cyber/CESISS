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
      <h1><i class="fa-solid fa-shield-halved"></i>Términos y condiciones de uso</h1>
      <div class="subtitle">Última actualización: <?= date('F Y') ?></div>
      <p>
        <strong>ACCESO Y USO DE LA PLATAFORMA</strong>
      </p>
      <ul class="list">
        <li>El acceso a la Plataforma se otorga únicamente a usuarios autorizados.</li>
        <li>El usuario se compromete a utilizar la Plataforma únicamente para consultar la información relacionada con los sistemas instalados y servicios de vigilancia proporcionados.</li>
        <li>El usuario es responsable de mantener la confidencialidad de su usuario y contraseña, así como de todas las actividades realizadas con estos.</li>
      </ul>
      <p>
        <strong>PROPIEDAD INTELECTUAL</strong>
      </p>
      <ul class="list">
        <li>Todos los contenidos de la Plataforma, incluyendo textos, gráficos, logotipos, imágenes y software, son propiedad de Saúl Jiménez Hernández en lo sucesivo Sistemas de Seguridad y Comunicación, o cuenten con autorización para su uso.</li>
        <li>Queda prohibida la reproducción, distribución, modificación o uso no autorizado de dichos contenidos.</li>
      </ul>
      <p>
        <strong>RESPONSABILIDAD DEL USUARIO</strong>
      </p>
      <ul class="list">
        <li>El usuario se compromete a no utilizar la Plataforma para fines ilícitos, contrarios al orden público o que puedan afectar la seguridad de los servicios de vigilancia.</li>
        <li>Queda prohibido intentar acceder a secciones restringidas, dañar, alterar o modificar el contenido de la Plataforma.</li>
      </ul>
      <p>
        <strong>LIMITACIÓN DE RESPONSABILIDAD</strong>
      </p>
      <ul class="list">
        <li>Saúl Jiménez Hernández en lo sucesivo Sistemas de Seguridad y Comunicación no se hace responsable por fallas técnicas, interrupciones del servicio o uso indebido de la información por parte de terceros.</li>
        <li>La información disponible en la Plataforma es únicamente de carácter informativo respecto a los servicios contratados.</li>
      </ul>
      <p>
        <strong>MODIFICACIONES</strong>
      </p>
      <ul class="list">
        <li>Saúl Jiménez Hernández en lo sucesivo Sistemas de Seguridad y Comunicación se reserva el derecho de modificar los presentes Términos y Condiciones en cualquier momento.</li>
        <li>Los cambios serán publicados en este mismo sitio web, con la fecha de última actualización.</li>
      </ul>
      <p>
        <strong>LEGISLACIÓN APLICABLE</strong>
      </p>
      <ul class="list">
        <p>Estos Términos y Condiciones se rigen por las leyes mexicanas, y cualquier controversia será resuelta por los tribunales competentes en la Ciudad de México.</p>
      </ul>
    </div>
  </div>

  <?php
  // Si tu footer no depende de la sesión/roles, puedes incluirlo tal cual:
  include __DIR__ . '/../includes/footer.php';
  ?>
</body>
</html>