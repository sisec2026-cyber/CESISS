<?php
session_start();
$BASE = '/sisec-ui';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$TITLE = 'Soporte - CESISS';
// Flash messages (se limpian tras leerse)
$flash_ok  = $_SESSION['flash_ok']  ?? null;
$flash_err = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    :root{
      --brand:#3C92A6; --brand-2:#24a3c1;
      --bg-1:#07161a; --bg-2:#0a2128; --fg:#cfe5ea; --muted:#9ab7bf;
      --card:#0d1e24; --card-border:#16323a; --shadow:0 10px 30px rgba(0,0,0,.35);
      --ok:#16a34a; --err:#dc2626;
    }
    body{
      margin:0; background: radial-gradient(1200px 800px at 10% -20%, #0c1b20, transparent),
                           radial-gradient(1200px 800px at 100% 120%, #0b242c, transparent),
                           linear-gradient(180deg, var(--bg-1), var(--bg-2));
      color:var(--fg); font-family: system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans";
      min-height:100vh; padding-bottom:84px;
    }
    .container{ max-width: 880px; margin: 90px auto 40px; padding: 0 16px; }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
      border:1px solid var(--card-border); border-radius:16px; box-shadow:var(--shadow); padding:22px;
    }
    h1{ display:flex; gap:.6rem; align-items:center; margin:0 0 10px 0; font-size:clamp(22px,2.6vw,28px); }
    .subtitle{ color:var(--muted); margin-bottom: 16px; }

    .grid{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    .grid-1{ display:grid; grid-template-columns: 1fr; gap:12px; }
    @media (max-width:720px){ .grid{ grid-template-columns: 1fr; } }

    label{ font-size:.92rem; color:#e7f6fa; margin-bottom:6px; display:block; }
    input, textarea, select{
      width:100%; background:#0c1b20; border:1px solid var(--card-border);
      color:var(--fg); border-radius:12px; padding:10px 12px; outline:none;
    }
    input:focus, textarea:focus{ border-color: var(--brand-2); box-shadow: 0 0 0 3px rgba(36,163,193,.25); }
    textarea{ min-height:140px; resize: vertical; }

    .actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:14px; }
    .btn{
      border:1px solid transparent; background: linear-gradient(180deg, var(--brand), var(--brand-2));
      color:white; padding:10px 16px; border-radius:999px; cursor:pointer; font-weight:600;
      box-shadow: 0 8px 18px rgba(36,163,193,.25);
    }
    .btn:hover{ filter: brightness(1.05); }
    .btn:disabled{ opacity:.7; cursor:not-allowed; }

    .btn-secondary{
      background: transparent; border-color: var(--card-border); color:#aee6f2;
    }

    .alert{
      margin: 12px auto 16px; padding:12px 14px; border-radius:12px; border:1px solid;
      max-width: 880px;
    }
    .ok{ border-color:#14532d; background:#052e18; color:#bbf7d0; }
    .err{ border-color:#7f1d1d; background:#2a0f0f; color:#fecaca; }

    .hint{ color:var(--muted); font-size:.85rem; }
    .tag{ display:inline-flex; align-items:center; gap:.4rem; border:1px solid var(--card-border);
          background:#0c1b20; color:#aee6f2; font-size:.8rem; padding:.2rem .5rem; border-radius:999px; }
    .icon-label{ display:flex; align-items:center; gap:.45rem; }
    .req{ color:#7fd3e5; }
    .foot-msg{ color:var(--muted); margin-top:6px; font-size:.9rem; }

    /* Honeypot accesible */
    .hp{ position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
    /* Asegurar que inputs no se salgan */
    input, textarea, select {
      width: 100%;
      box-sizing: border-box; /* 🔥 clave */
    }

    /* Input file oculto */
    input[type="file"] {
      display: none;
    }

    /* Botón personalizado para subir archivos */
    .file-label {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: var(--brand);
      color: #fff;
      padding: .6rem 1rem;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0,0,0,.3);
      transition: .3s;
    }
    .file-label:hover {
      background: var(--brand-2);
      transform: scale(1.05);
    }
    .file-label i {
      font-size: 1rem;
    }

    /* Contenedor de previsualización */
    .preview {
      margin-top: 10px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .preview-item {
      background: #0c1b20;
      border: 1px solid var(--card-border);
      border-radius: 8px;
      padding: 6px;
      max-width: 120px;
      text-align: center;
      font-size: .8rem;
      color: var(--muted);
      overflow: hidden;
    }
    .preview-item img {
      max-width: 100px;
      max-height: 80px;
      border-radius: 6px;
      display: block;
      margin: 0 auto 4px;
    }
    .preview-item {
      position: relative;
    }

    .remove-btn {
      position: absolute;
      top: 4px;
      right: 4px;
      background: rgba(255, 0, 0, 0.8);
      border: none;
      color: white;
      font-size: 12px;
      padding: 2px 6px;
      border-radius: 50%;
      cursor: pointer;
      transition: 0.2s;
    }
    .remove-btn:hover {
      background: red;
      transform: scale(1.1);
    }
  </style>
</head>
<body>

  <?php if ($flash_ok || $flash_err): ?>
    <div class="alert <?= $flash_ok ? 'ok' : 'err' ?>" role="status" aria-live="polite">
      <i class="fa-solid <?= $flash_ok ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
      <?= htmlspecialchars($flash_ok ?: $flash_err) ?>
    </div>
  <?php endif; ?>

  <div class="container">
    <div class="card">
      <h1><i class="fa-solid fa-life-ring"></i>Soporte CESISS</h1>
      <div class="subtitle">Cuéntanos tu problema o solicitud. Te responderemos al correo proporcionado.</div>

      <form method="post" action="<?= htmlspecialchars($BASE . '/actions/enviar_soporte.php') ?>" 
      id="formSoporte" autocomplete="on" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="hp" aria-hidden="true">
          <label for="hp_field">No llenar</label>
          <input type="text" id="hp_field" name="hp_field" value="" tabindex="-1" autocomplete="off">
        </div>

        <div class="grid">
          <div>
            <label class="icon-label" for="asunto"><i class="fa-regular fa-rectangle-list"></i>Asunto <span class="req">*</span></label>
            <input type="text" id="asunto" name="asunto" maxlength="120" required placeholder="Ej. Falla en registro de dispositivo" autocomplete="off" inputmode="text">
          </div>
          <div>
            <label class="icon-label" for="nombre"><i class="fa-regular fa-user"></i>Nombre <span class="req">*</span></label>
            <input type="text" id="nombre" name="nombre" maxlength="80" required placeholder="Tu nombre completo" autocomplete="name" inputmode="text">
          </div>
          <div>
            <label class="icon-label" for="correo"><i class="fa-regular fa-envelope"></i>Correo <span class="req">*</span></label>
            <input type="email" id="correo" name="correo" maxlength="120" required placeholder="tucorreo@dominio.com" autocomplete="email" inputmode="email">
          </div>
          <div>
            <label class="icon-label" for="prioridad"><i class="fa-solid fa-bolt"></i>Prioridad</label>
            <select id="prioridad" name="prioridad">
              <option value="Normal" selected>Normal</option>
              <option value="Alta">Alta</option>
              <option value="Crítica">Crítica</option>
            </select>
          </div>
        </div>

        <div style="margin-top:10px;">
          <label class="icon-label"><i class="fa-solid fa-paperclip"></i>Adjuntar archivo</label>
          <label for="archivo" class="file-label"><i class="fa-solid fa-upload"></i>Seleccionar archivos</label>
          <input type="file" id="archivo" name="archivo[]" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.txt" multiple>
          <div id="preview" class="preview"></div>
        </div>

        <div class="grid-1" style="margin-top:10px;">
          <div>
            <label class="icon-label" for="mensaje"><i class="fa-regular fa-message"></i>Mensaje <span class="req">*</span></label>
            <textarea id="mensaje" name="mensaje" required maxlength="10000" placeholder="Describe el problema, pasos para reproducirlo, capturas si aplica (puedes pegarlas en tu correo de respuesta)."></textarea>
            <div class="foot-msg">
              <span class="tag"><i class="fa-solid fa-paper-plane"></i>Se enviará a: soporte@cesiss.com</span>
            </div>
          </div>
        </div>

        <div class="actions">
          <a class="btn btn-secondary" href="<?= htmlspecialchars($BASE . '/views/inicio/index.php') ?>"><i class="fa-solid fa-arrow-left"></i>Volver</a>
          <button class="btn" id="btnEnviar" type="submit"><i class="fa-solid fa-paper-plane"></i>Enviar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      const form = document.getElementById('formSoporte');
      const btn = document.getElementById('btnEnviar');

      form.addEventListener('submit', function(e){
        if (!form.checkValidity()){
          e.preventDefault();
          form.reportValidity();
          return;
        }
      const correo = document.getElementById('correo');
      if (correo && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(correo.value.trim())){
        e.preventDefault();
        alert('Por favor ingresa un correo válido.');
        correo.focus();
        return;
      }
      btn.disabled = true;
      btn.textContent = 'Enviando...';
      });
    })();
  </script>

  <script>
    const inputArchivo = document.getElementById('archivo');
    const preview = document.getElementById('preview');
    let archivosSeleccionados = [];

    inputArchivo.addEventListener('change', () => {
      archivosSeleccionados = [...archivosSeleccionados, ...Array.from(inputArchivo.files)];
      renderPreview();
      inputArchivo.value = '';
    });

    function renderPreview() {
      preview.innerHTML = '';

      archivosSeleccionados.forEach((file, index) => {
        const item = document.createElement('div');
        item.classList.add('preview-item');

        const removeBtn = document.createElement('button');
        removeBtn.classList.add('remove-btn');
        removeBtn.innerHTML = '×';
        removeBtn.onclick = () => {
          archivosSeleccionados.splice(index, 1);
          renderPreview();
        };
        item.appendChild(removeBtn);

        if (file.type.startsWith('image/')) {
          const img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          item.appendChild(img);
        } else {
          const icon = document.createElement('i');
          icon.className = 'fa-solid fa-file';
          item.appendChild(icon);
        }

        const name = document.createElement('div');
        name.textContent = file.name;
        item.appendChild(name);

        preview.appendChild(item);
      });
    }
  </script>

  <?php
  $footer_paths = [
    __DIR__ . '/../includes/footer.php',
  ];
  foreach ($footer_paths as $fp) {
    if (file_exists($fp)) { include $fp; break; }
  }
  ?>
</body>
</html>