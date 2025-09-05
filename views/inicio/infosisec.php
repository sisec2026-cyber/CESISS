<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Presentación SISEC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    :root {
      --brand:#3C92A6;
      --bg-1:#07161a;
      --bg-2:#0a2128;
      --fg:#cfe5ea;
      --muted:#9ab7bf;
      --card-border:#16323a;
      --shadow:0 10px 30px rgba(0,0,0,.35);
    }
    body {
      margin: 0;
      background: linear-gradient(180deg, var(--bg-1), var(--bg-2));
      color: var(--fg);
      font-family: system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans";
      line-height: 1.6;
    }
    .container {
      max-width: 980px;
      margin: 90px auto;
      padding: 0 16px;
    }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
      border: 1px solid var(--card-border);
      border-radius: 16px;
      box-shadow: var(--shadow);
      padding: 32px;
      margin-bottom: 40px;
    }
    h1, h2 {
      margin: 0 0 16px 0;
      font-weight: 600;
    }
    h1 {
      font-size: clamp(24px,2.8vw,32px);
      color: #fff;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    h2 {
      font-size: 1.25rem;
      color: #e7f6fa;
      display: flex;
      align-items: center;
      gap: .5rem;
      margin-top: 30px;
    }
    p, li {
      color:#cfe5ea;
    }
    .list {
      padding-left:1.2rem;
    }
    .tag {
      display:inline-flex;
      align-items:center;
      gap:.4rem;
      border:1px solid var(--card-border);
      background:#0c1b20;
      color:#aee6f2;
      font-size:.85rem;
      padding:.25rem .75rem;
      border-radius:999px;
      margin:.2rem;
    }
    .subtitle {
      color: var(--muted);
      font-size:.9rem;
      margin-bottom:20px;
    }
    .carousel-container {
      overflow: hidden;
      width: 100%;
      margin: 20px auto;
      position: relative;
    }

    .carousel-track {
      display: flex;
      gap: 40px;
      animation: scrollBrands 20s linear infinite;
    }
    .carousel-track img {
      height: 200px;        /* Fuerza altura exacta */
      width: 200px;         /* Opcional: fuerza ancho exacto */
      object-fit: contain; /* Ajusta dentro del espacio sin deformarse */
      transition: filter 0.3s;
    }

    .carousel-track img:hover {
      filter: none;
    }

    .certificaciones {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 20px;
  justify-items: center;
  align-items: center;
  margin-top: 20px;
}

.certificaciones img {
  width: 180px;
  height: 230px;
  object-fit: contain;
  background: #fff;
  border-radius: 12px;
  padding: 10px;
  box-shadow: var(--shadow);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.certificaciones img:hover {
  transform: scale(1.05);
  box-shadow: 0 8px 20px rgba(0,0,0,.5);
}


    /* Animación infinita de izquierda a derecha */
    @keyframes scrollBrands {
      from { transform: translateX(0); }
      to { transform: translateX(-140%); }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Historia -->
    <div class="card">
      <div>
        <h1><i class="fa-solid fa-shield-halved"></i> Historia</h1>
        <h1></h1>
      </div>
      <p>El Ing. Saúl Jiménez Hernández, con el objetivo de minimizar la pérdida de equipo y producto de sus clientes, inició la instalación y mantenimiento de sistemas de alarmas y radiocomunicación, dando origen a una organización especializada que denominó comercialmente Sistemas de Seguridad y Comunicación “SISEC”.
        Actualmente, SISEC se encuentra en un proceso de mejora continua, enfocado en incrementar la satisfacción de sus clientes actuales y futuros. De manera paralela, nuestro equipo se mantiene en capacitación constante, adaptándose a la evolución tecnológica en el ámbito de seguridad electrónica y radiocomunicaciones. Esta dedicación nos ha permitido consolidar relaciones sólidas con nuestros clientes, posicionándonos como una opción confiable para cubrir sus necesidades, requerimientos y expectativas.
      </p>
    </div>
    <!-- Misión y Valores -->
    <div class="card">
      <h1><i class="fa-solid fa-bullseye"></i> Misión y Valores</h1>
      <p>En SISEC, nuestra misión es satisfacer de manera eficaz y eficiente las necesidades de nuestros clientes en reparación e instalación de tecnología de seguridad privada y radiocomunicaciones, ofreciendo siempre las mejores condiciones de servicio y venta. Buscamos consolidarnos como empresa líder en nuestra especialidad, promoviendo al mismo tiempo la participación y desarrollo de nuestro personal, así como la apertura de nuevos mercados, asegurando resultados positivos y sostenibles para todos nuestros clientes y colaboradores.
      </p>
      <h2><i class="fa-regular fa-star"></i> Valores</h2>
      <ul class="list">
        <li>Compromiso</li>
        <li>Calidad</li>
        <li>Innovación</li>
        <li>Trabajo en equipo</li>
        <li>Orientación al cliente</li>
      </ul>
    </div>
    <!-- Instalaciones -->
    <div class="card">
      <h1><i class="fa-solid fa-building"></i> Nuestras Instalaciones</h1>
      <p>CDMX</p>
      <div class="carousel-container">
        <div class="carousel-track">
          <!-- Inserta aquí tus imágenes de marcas -->
          <img src="/../sisec-ui/public/img/oficinas/CDMX1.png" alt="cdmx1">
          <img src="/../sisec-ui/public/img/oficinas/CDMX2.png" alt="cdmx2">
          <img src="/../sisec-ui/public/img/oficinas/CDMX3.png" alt="cdmx3">
          <img src="/../sisec-ui/public/img/oficinas/CDMX4.png" alt="cdmx4">
          <img src="/../sisec-ui/public/img/oficinas/CDMX5.png" alt="cdmx5">
          <img src="/../sisec-ui/public/img/oficinas/CDMX8.png" alt="cdmx8">
          <img src="/../sisec-ui/public/img/oficinas/CDMX9.png" alt="cdmx9">
          <img src="/../sisec-ui/public/img/oficinas/CDMX10.png" alt="cdmx10">
        </div>
      </div>
      <p>Puebla</p>
      <div class="carousel-container">
        <div class="carousel-track">
          <!-- Inserta aquí tus imágenes de marcas -->
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA1.jpg" alt="puebla1">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA2.jpg" alt="puebla2">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA3.jpg" alt="puebla3">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA4.jpg" alt="puebla4">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA5.jpg" alt="puebla5">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA6.jpg" alt="puebla6">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA7.jpg" alt="puebla7">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA8.jpg" alt="puebla8">
        </div>
      </div>
    </div>
    <!-- Certificados -->
    <div class="card">
      <div class="certificaciones">
        <h1><i class="fa-solid fa-briefcase"></i>Nuestras certificaciones</h1>
        <img src="/../sisec-ui/public/img/certificados/CERT1.png" alt="Cert1">
        <img src="/../sisec-ui/public/img/certificados/CERT2.png" alt="Cert2">
        <img src="/../sisec-ui/public/img/certificados/CERT3.png" alt="Cert3">
        <img src="/../sisec-ui/public/img/certificados/CERT4.png" alt="Cert4">
        <img src="/../sisec-ui/public/img/certificados/CERT5.jpg" alt="Cert5">
      </div>
    </div>
    <!-- Carrusel de Marcas -->
    <div class="card">
      <h1><i class="fa-solid fa-industry"></i>Marcas y Herramientas de Trabajo</h1>
      <div class="carousel-container">
        <div class="carousel-track">
          <!-- Inserta aquí tus imágenes de marcas -->
          <img src="/../sisec-ui/public/img/marcas/AVI.png" alt="Marca 1">
          <img src="/../sisec-ui/public/img/marcas/AXIS.png" alt="Marca 2">
          <img src="/../sisec-ui/public/img/marcas/CISCO.png" alt="Marca 3">
          <img src="/../sisec-ui/public/img/marcas/DMP.png" alt="Marca 4">
          <img src="/../sisec-ui/public/img/marcas/HAN.png" alt="Marca 5">
          <img src="/../sisec-ui/public/img/marcas/HIK.png" alt="Marca 6">
          <img src="/../sisec-ui/public/img/marcas/INO.png" alt="Marca 7">
          <img src="/../sisec-ui/public/img/marcas/UNI.png" alt="Marca 8">
          <img src="/../sisec-ui/public/img/marcas/MILE.png" alt="Marca 9">
        </div>
      </div>
    </div>
  </div>
</body>
</html>