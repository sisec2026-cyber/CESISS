<?php
$TITLE = 'CESISS - Términos y condiciones de uso';
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
  :root {
    --brand: #3C92A6;
    --brand-2: #24a3c1;
    --bg-1: #07161a;
    --bg-2: #0a2128;
    --fg: #cfe5ea;
    --muted: #9ab7bf;
    --card: #0d1e24;
    --card-border: #16323a;
    --shadow: 0 10px 30px rgba(0,0,0,.35);
    --accent: #38d4f3;
  }
  body {
    margin: 0;
    background: radial-gradient(1200px 800px at 10% -20%, #0c1b20, transparent),
                radial-gradient(1200px 800px at 100% 120%, #0b242c, transparent),
                linear-gradient(180deg, var(--bg-1), var(--bg-2));
    color: var(--fg);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Noto Sans";
    min-height: 100vh;
    padding-bottom: 84px;
  }
  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .container {
    max-width: 980px;
    margin: 90px auto 40px;
    padding: 0 16px;
  }
  .card {
    background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
    border: 1px solid var(--card-border);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 24px;
    animation: fadeInUp .8s ease;
  }
  h1 {
    display: flex;
    gap: .6rem;
    align-items: center;
    margin: 0 0 8px 0;
    font-size: clamp(22px, 2.6vw, 28px);
    font-family: "Montserrat", sans-serif;
    color: #ffffff;
    padding-bottom: .6rem;
    border-bottom: 2px solid var(--brand-2);
  }
  .subtitle {
    color: var(--muted);
    margin-bottom: 18px;
    font-style: italic;
  }
  h2 {
    margin: 32px 0 12px;
    color: #e7f6fa;
    font-size: 1.15rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    position: relative;
    font-weight: 600;
  }
  h2::after {
    content: "";
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--card-border), transparent);
    margin-left: .6rem;
  }
  p, li {
    color: #cfe5ea;
    line-height: 1.65;
    font-size: 0.95rem;
  }
  .list {
    list-style: none;
    padding-left: 1.5rem;
    margin-bottom: 16px;
    counter-reset: item;
  }
  .list li {
    counter-increment: item;
    padding: 6px 8px;
    margin-bottom: 6px;
    border-radius: 6px;
    transition: background .2s ease;
    position: relative;
  }
  .list li::before {
    content: counter(item) ".";
    position: absolute;
    left: -1.2rem;
    color: var(--accent);
    font-weight: bold;
  }
  .list li:hover {
    background: rgba(255,255,255,.03);
  }
  .highlight {
    margin-top: 22px;
    padding: 14px 18px;
    border-left: 4px solid var(--accent);
    background: rgba(56,212,243,.08);
    border-radius: 8px;
    font-size: 0.93rem;
    color: #aee6f2;
  }
  .back-home {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    text-decoration: none;
    color: var(--accent);
    font-size: .9rem;
    font-weight: 500;
    transition: color .2s ease;
  }
  .back-home:hover {
    color: #ffffff;
  }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <a href="/sisec-ui/index.php" class="back-home"><i class="fa-solid fa-house"></i> Volver al inicio</a>
      <br><br>
      <h1><i class="fa-solid fa-scale-balanced"></i> Términos y Condiciones de Uso</h1>
      <div class="subtitle">Última actualización: <?= date('F Y') ?></div>

      <h2><i class="fa-solid fa-user-lock"></i> Acceso y uso de la plataforma</h2>
      <ul class="list">
        <li>El acceso a la Plataforma se otorga únicamente a usuarios autorizados.</li>
        <li>El usuario se compromete a utilizar la Plataforma únicamente para consultar la información relacionada con los sistemas instalados y servicios de vigilancia proporcionados.</li>
        <li>El usuario es responsable de mantener la confidencialidad de su usuario y contraseña, así como de todas las actividades realizadas con estos.</li>
      </ul>

      <h2><i class="fa-solid fa-lightbulb"></i> Propiedad intelectual</h2>
      <ul class="list">
        <li>Todos los contenidos de la Plataforma, incluyendo textos, gráficos, logotipos, imágenes y software, son propiedad de Saúl Jiménez Hernández en lo sucesivo Sistemas de Seguridad y Comunicación, o cuenten con autorización para su uso.</li>
        <li>Queda prohibida la reproducción, distribución, modificación o uso no autorizado de dichos contenidos.</li>
      </ul>

      <h2><i class="fa-solid fa-user-shield"></i> Responsabilidad del usuario</h2>
      <ul class="list">
        <li>El usuario se compromete a no utilizar la Plataforma para fines ilícitos, contrarios al orden público o que puedan afectar la seguridad de los servicios de vigilancia.</li>
        <li>Queda prohibido intentar acceder a secciones restringidas, dañar, alterar o modificar el contenido de la Plataforma.</li>
      </ul>

      <h2><i class="fa-solid fa-triangle-exclamation"></i> Limitación de responsabilidad</h2>
      <ul class="list">
        <li>Saúl Jiménez Hernández en lo sucesivo Sistemas de Seguridad y Comunicación no se hace responsable por fallas técnicas, interrupciones del servicio o uso indebido de la información por parte de terceros.</li>
        <li>La información disponible en la Plataforma es únicamente de carácter informativo respecto a los servicios contratados.</li>
      </ul>

      <h2><i class="fa-solid fa-pen-ruler"></i> Modificaciones</h2>
      <ul class="list">
        <li>Saúl Jiménez Hernández en lo sucesivo Sistemas de Seguridad y Comunicación se reserva el derecho de modificar los presentes Términos y Condiciones en cualquier momento.</li>
        <li>Los cambios serán publicados en este mismo sitio web, con la fecha de última actualización.</li>
      </ul>

      <h2><i class="fa-solid fa-gavel"></i> Legislación aplicable</h2>
      <p>Estos Términos y Condiciones se rigen por las leyes mexicanas, y cualquier controversia será resuelta por los tribunales competentes en la Ciudad de México.</p>
      <p>La presente plataforma ha sido desarrollada con el único fin de servir como herramienta de apoyo operativo. La misma no persigue fines de lucro, por lo que su utilización es de carácter gratuito, no implicando contraprestación económica alguna.</p>
    </div>

    <p class="highlight">
      <i class="fa-solid fa-circle-exclamation"></i> Importante: El incumplimiento de estos términos puede resultar en la suspensión inmediata del acceso.
    </p>
  </div>

  <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>