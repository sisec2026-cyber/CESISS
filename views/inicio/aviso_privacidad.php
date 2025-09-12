<?php
$TITLE = 'CESISS - Aviso de Privacidad';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
      padding: 28px;
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
      margin-bottom: 20px;
      font-style: italic;
      font-size: 0.9rem;
    }
    h2 {
      margin: 32px 0 12px;
      color: #e7f6fa;
      font-size: 1.15rem;
      display: flex;
      align-items: center;
      gap: .5rem;
      font-weight: 600;
      position: relative;
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
    .hl {
      display:inline-flex; align-items:center;
      background: linear-gradient(180deg, rgba(127,211,229,.18), rgba(127,211,229,.08));
      border: 1px solid var(--card-border);
      border-radius: 999px;
      padding: 0 .45rem;
      color: #aee6f2 !important;
      text-decoration: none !important;
      font-size: 0.9rem;
      transition: background .2s ease, transform .08s ease;
      cursor: pointer;
    }
    .hl:hover {
      background: rgba(127,211,229,.25);
      transform: translateY(-1px);
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
      <h1><i class="fa-solid fa-shield-halved"></i> Aviso de Privacidad Integral</h1>
        <p>En <strong>CESISS</strong> nos tomamos muy en serio la protección de tus datos personales.</p>
      <p>Este aviso describe qué datos recopilamos, con qué finalidad y cuáles son tus derechos.</p>
      <p>Con fundamento en los artículos 15 y 16 de la Ley Federal de Protección de Datos Personales en Posesión de Particulares, hacemos del conocimiento que el presente aviso de privacidad es un documento que tiene como finalidad informar a los usuarios cómo se recopilan, utilizan, almacenan y protegen sus datos personales, así como los derechos que tienen respecto a esa información.</p>
      <p>El presente es de carácter obligatorio y debe proporcionarse antes de recabar cualquier dato personal.</p>
      
      <h2><i class="fa-regular fa-file-lines"></i>Identidad y domicilio del responsable</h2>
        <p>El responsable del tratamiento de sus datos personales es Saúl Jiménez Hernández en lo sucesivo Sistemas de Seguridad y Comunicación con domicilio en calle 04 de diciembre de 1860 manzana 164 lote 1875 B Colonia Leyes de Reforma 3ra. Sección, Alcaldía Iztapalapa, C.P. 09310, en la Cuidad de México, quien se compromete a resguardar y proteger la información personal recabada a través de la plataforma digital.</p>
      
      <h2><i class="fa-solid fa-bullseye"></i>Datos personales que se recaban</h2>
        <p>Los siguientes datos personales son los que se recaban:</p>
          <ul class="list">
            <li>De identificación</li>
              <p>Nombre, correo electrónico y número telefónico</p>
            <li>Laborales</li>
              <p>Puesto, área o empresa a la que pertenece el usuario</p>
            <li>De contacto</li>
              <p>Correo institucional o personal, teléfono fijo o celular</p>
          </ul>
        <p>No se solicitarán datos personales sensibles, salvo que resulten estrictamente indispensables y con su consentimiento expreso.</p>
      
      <h2><i class="fa-solid fa-lock"></i>Finalidades del tratamiento</h2>
        <p>Se tratará sus datos personales con finalidades:</p>
        <p><strong>Finalidades primarias (indispensables):</strong></p>
          <ul class="list">
            <li>Identificar y registrar usuarios de la plataforma.Identificar y registrar usuarios de la plataforma.</li>
            <li>Gestionar y administrar inventarios de dispositivos de seguridad.</li>
            <li>Dar seguimiento, control y actualización de equipos registrados.</li>
            <li>Proporcionar soporte técnico y atención a usuarios.</li>
            <li>Generar reportes y análisis internos.</li>
          </ul>
        <p><strong>Finalidades secundarias (opcionales):</strong></p>
        <p>En caso de NO oponerse se podrá tratar sus datos personales para llevar a cabo alguna o todas las finalidades secundarias que se mencionan a continuación, mismas que nos permiten brindarle un mejor servicio:</p>
          <ul class="list">
              <li>Enviar información sobre actualizaciones y nuevos servicios.</li>
              <li>Realizar encuestas de satisfacción.</li>
              <li>Fines estadísticos y de mejora en la calidad del servicio.</li>
          </ul>
        <p>Las anteriores finalidades secundarias tienen como base de legitimación su consentimiento. Lo anterior quiere decir que usted en cualquier momento puede oponerse a cualquiera de ellas, o bien, revocar su consentimiento.</p>
        <p>En caso de que no desee que sus datos personales sean tratados para alguna o todas las finalidades adicionales, desde este momento usted nos puede comunicar lo anterior al correo <strong>soporte@cesiss.com</strong></p>

        <h2><i class="fa-solid fa-lock"></i>Transferencias de datos personales</h2>
          <p>Sus datos personales no serán compartidos con terceros sin su consentimiento, salvo en los casos legalmente previstos por la Ley Federal de Protección de Datos Personales en Posesión de los Particulares, como:</p>
            <ul class="list">
              <li>Autoridades competentes en cumplimiento de obligaciones legales.</li>
              <li>Proveedores de servicios tecnológicos o de almacenamiento de datos con cláusulas de confidencialidad y seguridad.</li>
            </ul>

      <h2><i class="fa-solid fa-user-shield"></i>Los derechos de Acceso, Rectificación, Caqncelación u Oposición "Derechos ARCO"</h2>
        <p>Usted tiene derecho a Acceder, Rectificar, Cancelar u Oponerse 
        (<a id="arco-link" class="hl" href="arco.php" aria-label="Conoce tus derechos ARCO">ARCO</a>)
        <span id="arco-fallback"
              role="link"
              tabindex="0"
              style="margin-left:.35rem; display:inline-block; font-size:.9rem; opacity:.85; cursor:pointer;"
              onclick="window.location.href='arco.php';"
              onkeydown="if(event.key==='Enter' || event.key===' '){ event.preventDefault(); this.click(); }">(da clic para conocer qué es ARCO) 
        </span> al tratamiento de sus datos personales.</p>

      <h2><i class="fa-solid fa-rotate"></i> Opciones para limitar uso o divulgación de Datos</h2>
      <p>
        Usted puede limitar el uso o divulgación de sus datos personales enviando un correo a la dirección señalada en el punto anterior, 
        o solicitando la cancelación de su usuario en la Plataforma.
      </p>

      <h2>Uso de cookies</h2>
        <p>El sitio web utiliza cookies y tecnologías similares para recordar su sesión y mejorar la experiencia del usuario. Usted puede deshabilitar las cookies desde su navegador, aunque ello podría afectar el funcionamiento de la plataforma. </p>

      <h2>Medidas de seguridad</h2>
        <p>El responsable adoptará medidas técnicas y administrativas necesarias para proteger sus datos personales contra daño, pérdida, alteración, destrucción o uso indebido.</p>

      <h2>Medidas técnicas</h2>
        <ul class="list">
          <li>Uso de firewalls y sistemas de detección de intrusos</li>
          <li>Antivirus y antimalware actualizados</li>
          <li>Cifrado de la información almacenada y transmitida (SSL/TLS)</li>
          <li>Control de accesos lógicos mediante contraseñas robustas, doble autenticación y perfiles de usuario</li>
          <li>Respaldos periódicos de la base de datos en servidores seguros</li>
          <li>Registro y monitoreo de actividades en la plataforma (logs)</li>
      </ul>

    <h2>Medidas administrativas</h2>
      <ul class="list">
          <li>Designación de un responsable de datos personales</li>
          <li>Políticas internas de confidencialidad y acuerdos de no divulgación (NDA).</li>
          <li>Capacitación continua del personal en protección de datos</li>
          <li>Procedimientos claros para atender solicitudes de derechos ARCO</li>
          <li>Protocolos de respuesta ante incidentes de seguridad</li>
          <li>Revisión periódica de la conformidad legal</li>
      </ul>

      <h2><i class="fa-solid fa-rotate"></i>Cambios al aviso de privacidad</h2>
      <p>Este aviso podrá ser modificado en cualquier momento para cumplir con actualizaciones legales o de la plataforma. Las modificaciones estarán disponibles en www.cesiss.com</p>

      <h2>Consentimiento</h2>
        <p>Al proporcionar sus datos personales a través de esta plataforma, usted manifiesta su consentimiento para que sean tratados conforme al presente Aviso.</p>
      <div class="subtitle">Última actualización: <?php setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'spanish'); echo strftime('%B %Y'); ?></div>
      </div>
  </div>

  <?php include __DIR__ . '/../../includes/footer.php'; ?>
  <script>
    (function () {
      const link = document.getElementById('arco-link');
      if (!link) return;
      link.addEventListener('click', () => console.log('[ARCO] click en el <a> OK'));
      requestAnimationFrame(() => {
        const r = link.getBoundingClientRect();
        const x = Math.round(r.left + r.width / 2);
        const y = Math.round(r.top + r.height / 2);
        const topEl = document.elementFromPoint(x, y);

        if (topEl && topEl !== link) {
          console.warn('[ARCO] Hay un overlay encima del enlace:', topEl);
          try { topEl.style.outline = '3px dashed red'; } catch(e){}
          try { topEl.style.pointerEvents = 'none'; console.warn('[ARCO] Se puso pointer-events:none al overlay detectado.'); } catch(e){}
        }
      });
    })();
  </script>
</body>
</html>