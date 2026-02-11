<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Presentación SISEC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    :root {
      --brand:#3C92A6;
      --bg-1:#07161a;
      --bg-2:#0a2128;
      --fg:#cfe5ea;
      --muted:#9ab7bf;
      --card-border:#16323a;
      --shadow:0 10px 30px rgba(62, 184, 201, 0.35);
    }
    body {
      margin: 0;
      background: linear-gradient(180deg, var(--bg-1), var(--bg-2));
      color: var(--fg);
      font-family: system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans";
      line-height: 1.6;
      position: relative;
      overflow-x: hidden;
    }
    .container {
      max-width: 1100px;
      margin: 90px auto;
      padding: 0 16px;
      position: relative;
      z-index: 2;
    }
    .card{
      background: linear-gradient(180deg, rgba(56, 204, 197, 0.03), rgba(58, 194, 176, 0.01));
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
    /* Carrusel de Certificaciones */
    .carousel-container.certificaciones {
      overflow: hidden;
      width: 100%;
      margin: 20px auto;
      position: relative;
    }

    .carousel-container.certificaciones .carousel-track img {
      height: 200px;   /* Igual que oficinas */
      width: auto;
      object-fit: cover;
      border-radius: 16px;
      padding: 0;
      filter: none;
    }

    .carousel-container.certificaciones .carousel-track {
      display: flex;
      gap: 40px;
      animation: scrollCertificaciones 60s linear infinite; /* más lento */
      width: max-content;
    }

    @keyframes scrollCertificaciones {
      from { transform: translateX(0); }
      to { transform: translateX(-50%); }
    }

    /* Logos de marcas */
    .carousel-track img {
      height: 120px;
      width: auto;
      object-fit: contain;
      background: rgba(54, 170, 185, 0);
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
      height: 200px;
      width: auto;
      object-fit: cover;
      border-radius: 16px;
      padding: 0;
      filter: none;
    }
    .oficinas-titulo {
      display: inline-block;
      font-size: 1.4rem;
      font-weight: 800;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: 2px;
      padding: 8px 20px;
      background: linear-gradient(135deg, rgba(60,146,166,0.35), rgba(58,194,176,0.15));
      border: 1px solid var(--card-border);
      border-radius: 999px;
      box-shadow: 0 0 10px rgba(60,146,166,0.6);
      margin: 25px auto 15px;
      transition: transform 0.3s ease, background 0.3s ease;
    }
    .oficinas-titulo:hover {
      transform: scale(1.08);
      background: linear-gradient(135deg, rgba(60,146,166,0.55), rgba(58,194,176,0.25));
    }
    .oficinas-titulo::after {
      content: "";
      display: block;
      width: 50px;
      height: 3px;
      background: var(--brand);
      border-radius: 2px;
      margin-top: 6px;
    }
    .oficinas-titulo {
      display: block;
      text-align: center;
      font-size: 1.2rem;
      font-weight: 600;
      text-transform: uppercase;
      color: #e7f6fa;
      margin: 30px auto 15px;
      padding: 6px 20px;
      border-radius: 999px;
      background: linear-gradient(145deg, rgba(56, 204, 197, 0.15), rgba(58, 194, 176, 0.05));
      border: 1px solid var(--card-border);
      box-shadow: inset 0 0 8px rgba(56,204,197,0.3);
      width: fit-content;
    }
    .carousel-track img:hover {
      transform: scale(1.15);
      filter: grayscale(0) brightness(1.1);
    }

    @keyframes scrollBrands {
      from { transform: translateX(0); }
      to { transform: translateX(-50%); }
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

    /* ===== Fondo de burbujas (añadido) ===== */
    .bubbles {
      position: fixed;
      inset: 0;
      overflow: hidden;
      pointer-events: none;
      z-index: 1;
    }
    .bubble {
      position: absolute;
      bottom: -120px;
      border-radius: 50%;
      background: radial-gradient(
        circle at 30% 30%,
        rgba(49, 184, 161, 0.25) 0%,
        rgba(56, 204, 197, 0.12) 35%,
        rgba(58, 194, 176, 0.06) 60%,
        rgba(255,255,255,0.0) 100%
      );
      box-shadow:
        0 0 18px rgba(255, 255, 255, 0.08),
        inset 0 0 12px rgba(255, 255, 255, 0.06);
      animation: rise linear infinite;
      will-change: transform, opacity;
      filter: blur(var(--blur, 0px));
      outline: 1px solid rgba(36, 163, 193, 0.15);
    }
    @keyframes rise {
      0%   { transform: translateY(0) translateX(0) scale(var(--scale,1)); opacity: 0; }
      10%  { opacity: .9; }
      90%  { opacity: .9; }
      100% { transform: translateY(-115vh) translateX(var(--drift, 0px)) scale(var(--scale,1)); opacity: 0; }
    }
    @media (prefers-reduced-motion: reduce) {
      .bubble { animation-duration: 0s !important; opacity: .15 !important; }
    }
    .tag {
      display:inline-flex; align-items:center; gap:.4rem;
      border:1px solid var(--card-border); background:#0c1b20; color:#aee6f2;
      font-size:.85rem; padding:.3rem .8rem; border-radius:999px; margin-right:.4rem;
    }
    .oficinas-titulo {
      display: inline-block;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--brand);
      text-transform: uppercase;
      letter-spacing: 2px;
      padding: 6px 14px;
      background: rgba(60, 146, 166, 0.1);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      box-shadow: var(--shadow);
      margin: 20px 0 10px;
    }
  </style>
</head>
<body>
  <div class="bubbles" id="bubbles"></div>
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
      <p class="oficinas-titulo">CDMX</p>
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
      <p class="oficinas-titulo">Puebla</p>
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
      <h1 style="text-align:center"><i class="fa-solid fa-briefcase"></i>Certificaciones</h1>
      <div class="carousel-container certificaciones">
        <div class="carousel-track">
          <img src="/../sisec-ui/public/img/certificados/CERT13.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT14.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT15.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT16.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT1.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT2.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT3.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT5.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT6.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT7.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT8.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT9.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT10.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT11.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT12.jpg">
          <!-- duplicación -->
          <img src="/../sisec-ui/public/img/certificados/CERT13.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT14.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT15.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT16.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT1.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT2.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT3.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT5.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT6.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT7.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT8.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT9.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT10.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT11.jpg">
          <img src="/../sisec-ui/public/img/certificados/CERT12.jpg">
        </div>
      </div>
    </div>
    
    <!-- Carrusel de Marcas -->
    <div class="card">
      <h1><i class="fa-solid fa-industry"></i>Marcas y Herramientas de Trabajo</h1>
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
    
    <!--  Info sisec -->
    <div class="card">
      <h1><i class="fa-solid fa-info"></i>Contacto SISEC</h1>
        <p class="tag" style="font-size:20px;"><i class="fa-solid fa-phone"></i>Teléfono: 55 5600 5175</p>
        <p class="tag" style="font-size:20px;"><i class="fa-solid fa-envelope"></i>sisec2014@gmail.com</p>
    </div>
  </div>

  <!-- Overlay para imágenes -->
  <div class="overlay" id="overlay">
    <img src="" alt="Imagen ampliada">
  </div>

  <script>
    const overlay = document.getElementById('overlay');
    const overlayImg = overlay.querySelector('img');
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

  <script>
    (function(){
      const wrap = document.getElementById('bubbles');
      if (!wrap) return;

      const MAX_AT_ONCE = 18;
      const SPAWN_MS_MIN = 400;
      const SPAWN_MS_MAX = 1200;
      const SIZE_MIN = 14;
      const SIZE_MAX = 70;
      const DUR_MIN = 12;
      const DUR_MAX = 26;
      const DRIFT_MAX = 90;
      const BLUR_MAX = 2.5;

      let active = 0;
      const prfReduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      function rand(a,b){ return Math.random() * (b - a) + a; }
      function spawn(){
        if (active >= MAX_AT_ONCE) return schedule();
        const b = document.createElement('div');
        b.className = 'bubble';

        const size  = rand(SIZE_MIN, SIZE_MAX);
        const left  = rand(0, 100);
        const dur   = prfReduce ? 0 : rand(DUR_MIN, DUR_MAX);
        const delay = prfReduce ? 0 : rand(0, 6);
        const blur  = rand(0, BLUR_MAX);
        const drift = rand(-DRIFT_MAX, DRIFT_MAX);
        const scale = rand(0.9, 1.4);

        b.style.width  = size + 'px';
        b.style.height = size + 'px';
        b.style.left   = left + '%';
        if (dur) b.style.animationDuration = dur + 's';
        if (delay) b.style.animationDelay  = delay + 's';
        b.style.setProperty('--drift', drift + 'px');
        b.style.setProperty('--scale', scale);
        b.style.setProperty('--blur', blur + 'px');

        active++;
        b.addEventListener('animationend', () => {
          active--;
          b.remove();
        });

        wrap.appendChild(b);
        schedule();
      }
      function schedule(){
        const t = rand(SPAWN_MS_MIN, SPAWN_MS_MAX);
        setTimeout(spawn, t);
      }
      for (let i=0; i<MAX_AT_ONCE/2; i++) spawn();
    })();
  </script>

  <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>