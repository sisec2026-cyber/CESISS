<?php
// Página pública: NO requiere login
$TITLE = '¿Qué es ARCO? - CESISS';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
      min-height:100vh; padding-bottom:84px;
    }
    .container{ max-width: 980px; margin: 90px auto 40px; padding: 0 16px; }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
      border:1px solid var(--card-border); border-radius:16px; box-shadow: var(--shadow);
      padding:24px;
    }
    h1{ display:flex; gap:.6rem; align-items:center; margin:0 0 8px 0; font-size: clamp(22px, 2.6vw, 28px); }
    h2{ margin-top: 24px; color:#e7f6fa; font-size: 1.15rem; }
    p, li{ color:#cfe5ea; line-height:1.6; }
    .list{ padding-left: 1rem; }
    .back{
      display:inline-flex; align-items:center; gap:.5rem; margin-bottom: 14px;
      color:#7fd3e5; text-decoration:none;
    }
    .back:hover{ color:#a6e9f5; text-decoration:underline; }
    .tag{
      display:inline-flex; align-items:center; gap:.4rem;
      border:1px solid var(--card-border); background:#0c1b20; color:#aee6f2;
      font-size:.85rem; padding:.2rem .6rem; border-radius:999px; margin-right:.4rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <a class="back" href="aviso_privacidad.php"><i class="fa-solid fa-arrow-left-long"></i> Volver al Aviso de Privacidad</a>
    <div class="card">
      <h1><i class="fa-solid fa-user-shield"></i> ¿Qué es ARCO?</h1>
      <p><strong>ARCO</strong> significa <em>Acceso, Rectificación, Cancelación y Oposición</em>. Son los derechos que tienes sobre tus datos personales.</p>

      <h2>Significado de cada derecho</h2>
      <ul class="list">
        <li><strong>Acceso:</strong> conocer qué datos tuyos tenemos y cómo los usamos.</li>
        <li><strong>Rectificación:</strong> corregir datos incompletos o inexactos.</li>
        <li><strong>Cancelación:</strong> solicitar que eliminemos tus datos cuando ya no sean necesarios o por incumplimiento.</li>
        <li><strong>Oposición:</strong> oponerte al uso de tus datos por una causa legítima.</li>
      </ul>

      <h2>¿Por qué lo ves en esta aplicación?</h2>
      <p>Porque buscamos transparencia y cumplimiento con la normativa de protección de datos en México, dándote control sobre tu información dentro de CESISS.</p>

      <h2>¿Cómo ejercer tus derechos aquí?</h2>
      <ol class="list">
        <li>Envía tu solicitud a <strong>soporte@cesiss.com</strong>.</li>
        <li>Incluye: nombre completo, descripción clara del derecho que deseas ejercer y los datos relacionados.</li>
        <li>Adjunta una identificación oficial para validar la titularidad.</li>
      </ol>

      <p class="tag"><i class="fa-solid fa-envelope"></i> soporte@cesiss.com</p>
    </div>
  </div>

  <!-- <?php
  // Si tu footer no depende de la sesión/roles, puedes incluirlo tal cual:
  include __DIR__ . '/../includes/footer.php';
  ?> -->
</body>
</html>
