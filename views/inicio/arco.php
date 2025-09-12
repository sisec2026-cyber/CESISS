<?php
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
      margin:0; 
      background: radial-gradient(1200px 800px at 10% -20%, #0c1b20, transparent),
                  radial-gradient(1200px 800px at 100% 120%, #0b242c, transparent),
                  linear-gradient(180deg, var(--bg-1), var(--bg-2));
      color:var(--fg); 
      font-family: system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans";
      min-height:100vh; 
      padding-bottom:84px;
    }
    .container{ max-width: 980px; margin: 90px auto 40px; padding: 0 16px; }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
      border:1px solid var(--card-border); border-radius:16px; box-shadow: var(--shadow);
      padding:24px; animation: fadeIn 1s ease-in-out;
    }
    @keyframes fadeIn{
      from{opacity:0; transform: translateY(20px);}
      to{opacity:1; transform: translateY(0);}
    }
    h1{ display:flex; gap:.6rem; align-items:center; margin:0 0 8px 0; font-size: clamp(22px, 2.6vw, 28px); color:#7fd3e5;}
    h2{ margin-top: 24px; color:#e7f6fa; font-size: 1.2rem; border-left:4px solid var(--brand); padding-left:8px;}
    p, li{ color:#cfe5ea; line-height:1.6; }
    .list{ padding-left: 1rem; }
    .back{
      display:inline-flex; align-items:center; gap:.5rem; margin-bottom: 14px;
      color:#7fd3e5; text-decoration:none; font-weight:500;
      transition:.2s;
    }
    .back:hover{ color:#a6e9f5; text-decoration:underline; transform:translateX(-3px);}
    .tag{
      display:inline-flex; align-items:center; gap:.4rem;
      border:1px solid var(--card-border); background:#0c1b20; color:#aee6f2;
      font-size:.85rem; padding:.3rem .8rem; border-radius:999px; margin-right:.4rem;
    }
    .btn{
      display:inline-flex; align-items:center; gap:.6rem;
      background:var(--brand); color:#fff; font-weight:600;
      border:none; border-radius:10px; padding:.6rem 1.2rem;
      cursor:pointer; transition:.3s; font-size:1rem;
      box-shadow:0 4px 12px rgba(0,0,0,.3);
    }
    .btn:hover{ background:var(--brand-2); transform:scale(1.05); }
    .btn i{ font-size:1.1rem; }
    footer{
      text-align:center; padding:16px; font-size:.85rem; color:var(--muted);
      border-top:1px solid var(--card-border); margin-top:40px;
    }
    .link-soporte {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        text-decoration: none;
        color: var(--accent);
        font-size: .9rem;
        font-weight: 500;
        transition: color .2s ease;
      }
      .link-soporte:hover {
        color: #ffffff;
      }
  </style>
</head>
<body>
  <div class="container">
    <a class="back" href="aviso_privacidad.php"><i class="fa-solid fa-arrow-left-long"></i> Volver al Aviso de Privacidad</a>
    <div class="card">
      <h1><i class="fa-solid fa-user-shield"></i> ¿Qué es ARCO?</h1>
        <p>Los derechos <strong>ARCO</strong> son los que tienen todas las personas para proteger sus datos personales, se llaman sí por sus iniciales <em>Acceder, Rectificar, Cancelar u Oponerse</em> (ARCO)</p>

      <h2>Significado de cada derecho</h2>
      <ul class="list">
        <li><strong>Acceso:</strong> derecho a saber que datos personales son con los que se cuentan, como los obtuvieron y para que los usan.</li>
        <li><strong>Rectificación:</strong> derecho a corregir o actualizar tus datos si están incompletos, inexactos o desactualizados.</li>
        <li><strong>Cancelación:</strong> derecho a pedir que eliminen tus datos cuando ya no son necesarios para la finalidad por la que fueron recabados.</li>
        <li><strong>Oposición:</strong> derecho al tratamiento de sus datos o a negarte a que se usen los datos para ciertos fines (por ejemplo, publicidad o estudios de mercado).</li>
      </ul>

      <h2>¿Por qué lo ves en esta aplicación?</h2>
      <p>Porque buscamos transparencia y cumplimiento con la normativa de protección de datos en México, dándote control sobre tu información dentro de CESISS.</p>

      <h2>Para ejercer los derechos de <em>Acceso, Rectificación, Cancelación u Oposición</em> "Derechos ARCO"</h2>
        <p>El titular de los derechos o su representante legal podrán ejercer los derechos descritos anteriormente enviando un correo electrónico a la dirección: <strong>soporte@cesiss.com</strong>,
        en el que deberá indicar que solicita ejercer sus derechos ARCO, para ello deberá descargar el Formato para ejercer los derechos de <em>Acceso, Rectificar, Cancelación u Oposición</em>,
         debiendo adjuntar los documentos escaneados en formato PDF, siendo:</p>
        <ul class="list">
              <li>Identificación oficial vigente</li>
              <li>Nombre completo del Titular</li>
              <li>Descripción clara de los datos personales sujetos al ejercicio del derecho ejercido</li>
              <li>Documentos que acrediten la representación legal del Titular en caso de que los derechos sean ejercidos por su representante</li>
            </ul>
        <p>Una vez hecho lo anterior el responsable en un término no mayor a 20 días hábiles dará respuesta a la solicitud, por el medio que haya señalado para la notificación respectiva,
           en caso de no haber sido señalada se entenderá que la respuesta se enviara por el mismo medio en el que fue solicitada.</p>
        <p>Para el caso de ser procedente la solicitud, el responsable dentro de los siguientes 15 días hábiles, aplicarán el derecho ejercido por el Titular.</p>
        <p>El responsable, podrá negar el acceso a los datos personales, o a realizar la rectificación o cancelación o conceder la oposición al tratamiento de los mismos, en
           los supuestos establecidos en el artículo 34 de la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.</p>
        <p><strong>Artículo 34. El ejercicio de los derechos ARCO es gratuito, solo podrán realizarse cobros para recuperar los costos de reproducción, copias o envío.
          Cuando la persona titular proporcione el medio magnético, electrónico o el mecanismo necesario para reproducir los datos personales, los mismos deberán ser entregados sin costo a esta.
          Cuando una misma persona titular o su representante reitera su solicitud en un periodo menor a doce meses, los costos no serán mayores a tres veces la Unidad de Medida y Actualización vigente, 
          a menos que existan modificaciones sustanciales al aviso de privacidad que motiven nuevas consultas.</strong></p>
        <p>El responsable no estará obligado a cancelar los datos personales del Titular, bajo los supuestos establecidos en el artículo 26 de la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.</p>
        <p><strong>Artículo 26. La persona titular tendrá derecho en todo momento y por causa legítima a oponerse al tratamiento de sus datos o exigir que se cese en el mismo cuando: I. Exista causa legítima y su situación específica así
           lo requiera, lo cual debe justificar que aun siendo lícito el tratamiento, el mismo debe cesar para evitar que su persistencia le cause un daño o perjuicio, o II. Sus datos personales sean objeto de un tratamiento automatizado,
           el cual le produzca efectos jurídicos no deseados o afecte de manera significativa sus intereses, derechos o libertades, y estén destinados a evaluar, sin intervención humana, determinados aspectos personales de la misma o analizar o predecir,
           en particular, su rendimiento profesional, situación económica, estado de salud, preferencias sexuales, fiabilidad o comportamiento. No procederá el ejercicio del derecho de oposición en aquellos casos en los que el tratamiento sea necesario
           para el cumplimiento de una obligación legal impuesta al responsable.</strong></p>
        <p><strong>👉 Da click en el botón para descargar la guía de usuario de ARCO:</strong></p>
      <a href="/sisec-ui/views/inicio/GUÍA_USUARIO_ARCO.pdf" target="_blank" class="btn">
        <i class="fas fa-file-pdf"></i> Descargar Guía ARCO
      </a>
        
      <h2>Revocar el consentimiento previamente otorgado</h2>
      <p>Para el ejercicio de este derecho, deberá descargar el formato de solicitud de Revocación del Consentimiento, la cual deberá llenar y agregar una  descripción clara del motivo por el cual desea revocar el consentimiento previamente otorgado,
        así como señalar el medio de contacto para notificaciones debiendo escanearla en formato PDF así como su identificación oficial y ser enviada al correo electrónico:</p>
      <p class="tag"><i class="fa-solid fa-envelope"></i><a href="/sisec-ui/views/inicio/soporte.php" class="link-soporte">soporte@cesiss.com</a></p>

      <h2>Formatos de Solicitud</h2>
        <p>También puedes descargar los formatos oficiales en Word para ejercer tus derechos:</p>

        <a href="/sisec-ui/views/inicio/descarga.php?file=SOLICITUD_REVOCACION_CONSENTIMIENTO.docx" class="btn">
          <i class="fas fa-file-word"></i> Solicitud de Revocación del Consentimiento
        </a>
        <p></p>
        <a href="/sisec-ui/views/inicio/descarga.php?file=FORMATO_SOLICITUD_ EJERCER_DERECHOS_ARCO.docx" class="btn">
          <i class="fas fa-file-word"></i> Formato de solicitud para ejercer derechos ARCO
        </a>
    </div>
  </div>
  <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>