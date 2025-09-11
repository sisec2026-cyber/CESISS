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
      max-width: 1100px;
      margin: 90px auto;
      padding: 0 16px;
    }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
      border: 1px solid var(--card-border);
      border-radius: 16px;
      box-shadow: var(--shadow);
      padding: 32px;
      margin-bottom: 40px;
      backdrop-filter: blur(6px);
    }
    h1, h2 {
      margin: 0 0 16px 0;
      font-weight: 600;
      position: relative;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    h1 {
      font-size: clamp(24px,2.8vw,32px);
      color: #fff;
    }
    h2 {
      font-size: 1.25rem;
      color: #e7f6fa;
      margin-top: 30px;
    }
    h1::after, h2::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -5px;
      width: 40px;
      height: 3px;
      background: var(--brand);
      border-radius: 2px;
      transition: width 0.3s ease;
    }
    h1:hover::after, h2:hover::after {
      width: 100%;
    }
    h1 i, h2 i {
      background: var(--brand);
      color: #fff;
      padding: .5rem;
      border-radius: 50%;
      font-size: 1rem;
    }
    p, li {
      color:#cfe5ea;
    }
    .list {
      padding-left:1.2rem;
    }
    /* Carrusel */
    .carousel-container {
      overflow: hidden;
      width: 100%;
      margin: 20px auto;
      position: relative;
    }
    .carousel-track {
      display: flex;
      gap: 40px;
      animation: scrollBrands 30s linear infinite;
      width: max-content;
    }
    /* Logos de marcas */
    .carousel-track img {
      height: 120px;
      width: auto;
      object-fit: contain;
      background: rgba(255,255,255,0);
      border-radius: 12px;
      padding: 5px;
      box-shadow: var(--shadow);
      filter: grayscale(20%) brightness(0.95);
      transition: transform 0.3s ease, filter 0.3s ease;
      cursor: pointer;
    }
    .carousel-track img:hover {
      transform: scale(1.15);
      filter: grayscale(0) brightness(1.1);
    }

    /* Oficinas - fotos grandes */
    .oficinas .carousel-track img {
      height: 200px;   /* antes 300px */
      width: auto;
      object-fit: cover;
      border-radius: 16px;
      padding: 0;
      filter: none;
    }

    .carousel-track img:hover {
      transform: scale(1.15);
      filter: grayscale(0) brightness(1.1);
    }

    @keyframes scrollBrands {
      from { transform: translateX(0); }
      to { transform: translateX(-50%); }
    }
    /* Certificaciones */
    .certificaciones {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 25px;
    margin-top: 30px;
  }

  .certificaciones .cert-card {
    background: linear-gradient(145deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
    border: 1px solid var(--card-border);
    border-radius: 18px;
    box-shadow: var(--shadow);
    padding: 20px;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
  }

  .certificaciones .cert-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 35px rgba(0,0,0,.6);
  }

  .certificaciones img {
    width: 100%;
    max-width: 200px;
    height: auto;
    object-fit: contain;
    border-radius: 12px;
    background: #fff;
    padding: 10px;
    transition: transform 0.3s ease;
  }

  .certificaciones img:hover {
    transform: scale(1.08);
  }

    /* Overlay */
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.8);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      visibility: hidden;
      opacity: 0;
      transition: opacity .3s ease;
    }
    .overlay.active {
      visibility: visible;
      opacity: 1;
    }
    .overlay img {
      max-width: 90%;
      max-height: 90%;
      border-radius: 12px;
      box-shadow: 0 0 20px rgba(0,0,0,.7);
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
      color: #ffffffff;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Historia -->
    <div class="card">
      <a href="/sisec-ui/index.php" class="back-home"><i class="fa-solid fa-house"></i> Volver al inicio</a>
      <br><br>
      <h1><i class="fa-solid fa-shield-halved"></i> Historia</h1>
      <p>El Ing. Saúl Jiménez Hernández, con el objetivo de minimizar la pérdida de equipo y producto de sus clientes, inició la instalación y mantenimiento de sistemas de alarmas y
         radiocomunicación, dando origen a una organización especializada que denominó comercialmente Sistemas de Seguridad y Comunicación “SISEC”.</p>
      <p>Actualmente, SISEC se encuentra en un proceso de mejora continua, enfocado en incrementar la satisfacción de sus clientes actuales y futuros. De manera paralela, 
        nuestro equipo se mantiene en capacitación constante, adaptándose a la evolución tecnológica en el ámbito de seguridad electrónica y radiocomunicaciones. Esta dedicación 
        nos ha permitido consolidar relaciones sólidas con nuestros clientes, posicionándonos como una opción confiable para cubrir sus necesidades, requerimientos y expectativas.</p>
    </div>

    <!-- Misión y Valores -->
    <div class="card">
      <h1><i class="fa-solid fa-bullseye"></i> Misión y Valores</h1>
      <p>En SISEC, nuestra misión es satisfacer de manera eficaz y eficiente las necesidades de nuestros clientes en reparación e instalación de tecnología de seguridad privada y 
        radiocomunicaciones, ofreciendo siempre las mejores condiciones de servicio y venta. Buscamos consolidarnos como empresa líder en nuestra especialidad, promoviendo al mismo 
        tiempo la participación y desarrollo de nuestro personal, así como la apertura de nuevos mercados, asegurando resultados positivos y sostenibles para todos nuestros clientes y 
        colaboradores.</p>
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
      <div class="carousel-container oficinas">
        <div class="carousel-track">
          <img src="/../sisec-ui/public/img/oficinas/CDMX1.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/CDMX2.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/CDMX3.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/CDMX4.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/CDMX5.jpeg">
          <!-- duplicación automática -->
          <img src="/../sisec-ui/public/img/oficinas/CDMX1.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/CDMX2.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/CDMX3.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/CDMX4.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/CDMX5.jpeg">
        </div>
      </div>
      <p>Puebla</p>
      <div class="carousel-container oficinas">
        <div class="carousel-track">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA1.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA2.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA3.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA4.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA5.jpeg">
          <!-- duplicación automática -->
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA1.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA2.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA3.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA4.jpeg">
          <img src="/../sisec-ui/public/img/oficinas/PUEBLA5.jpeg">
        </div>
      </div>
    </div>

    <!-- Certificaciones -->
    <div class="card">
      <h1 style="text-align:center"><i class="fa-solid fa-briefcase"></i> Nuestras Certificaciones</h1>
      <div class="certificaciones">
        <div class="cert-card">
          <img src="/../sisec-ui/public/img/certificados/CERT1.jpg">
        </div>
        <div class="cert-card">
          <img src="/../sisec-ui/public/img/certificados/CERT2.jpg">
        </div>
        <div class="cert-card">
          <img src="/../sisec-ui/public/img/certificados/CERT3.png">
        </div>
        <div class="cert-card">
          <img src="/../sisec-ui/public/img/certificados/CERT4.jpg">
        </div>
        <div class="cert-card">
          <img src="/../sisec-ui/public/img/certificados/CERT5.jpg">
        </div>
        <div class="cert-card">
          <img src="/../sisec-ui/public/img/certificados/CERT6.jpg">
        </div>
        <div class="cert-card">
          <img src="/../sisec-ui/public/img/certificados/CERT7.jpg">
        </div>
      </div>
    </div>
    
    <!-- Carrusel de Marcas -->
    <div class="card">
      <h1><i class="fa-solid fa-industry"></i> Marcas y Herramientas de Trabajo</h1>
      <div class="carousel-container">
        <div class="carousel-track">
          <img src="/../sisec-ui/public/img/marcas/AVI.png">
          <img src="/../sisec-ui/public/img/marcas/AXIS.png">
          <img src="/../sisec-ui/public/img/marcas/CISCO.png">
          <img src="/../sisec-ui/public/img/marcas/DMP.png">
          <img src="/../sisec-ui/public/img/marcas/HAN.png">
          <img src="/../sisec-ui/public/img/marcas/HIK.png">
          <img src="/../sisec-ui/public/img/marcas/INO.png">
          <img src="/../sisec-ui/public/img/marcas/UNI.png">
          <img src="/../sisec-ui/public/img/marcas/MILE.png">
          <!-- duplicación automática -->
          <img src="/../sisec-ui/public/img/marcas/AVI.png">
          <img src="/../sisec-ui/public/img/marcas/AXIS.png">
          <img src="/../sisec-ui/public/img/marcas/CISCO.png">
        </div>
      </div>
    </div>
  </div>

  <!-- Overlay para imágenes -->
  <div class="overlay" id="overlay">
    <img src="" alt="Imagen ampliada">
  </div>

  <script>
    const overlay = document.getElementById('overlay');
    const overlayImg = overlay.querySelector('img');

    // Selecciona todas las imágenes clickeables
    const imgs = document.querySelectorAll('.carousel-track img, .certificaciones img');

    imgs.forEach(img => {
      img.addEventListener('click', () => {
        overlayImg.src = img.src;
        overlay.classList.add('active');
      });
    });

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.classList.remove('active');
        overlayImg.src = "";
      }
    });
  </script>
  <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>