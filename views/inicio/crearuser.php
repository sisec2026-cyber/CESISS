<?php
// /sisec-ui/views/usuarios/crearuser.php
include __DIR__ . '/../../includes/conexion.php';
$error = $_GET['error'] ?? null;

// Límite de usuarios
$totalUsuarios = 0;
try {
  if ($res = $conexion->query("SELECT COUNT(*) AS total FROM usuarios")) {
    $row = $res->fetch_assoc();
    $totalUsuarios = (int)($row['total'] ?? 0);
    $res->free();
  }
} catch (Throwable $e) {
  // Si falla el conteo, no bloqueamos el alta por esto
}
$limiteAlcanzado = $totalUsuarios >= 1000;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Registro de usuario</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>

  <!-- Icons (Bootstrap Icons) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>

  <!-- Inter font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- Tu CSS global si lo necesitas -->
  <link rel="stylesheet" href="/sisec-ui/public/css/style.css">

  <style>
    :root{
      --brand:#3C92A6;
      --brand-2:#24a3c1;
      --txt:#0b1a1e;
      --muted:#6c8790;
      --ok:#1aa36f;
      --warn:#e0a800;
      --err:#dc3545;
      --card-bg: rgba(255,255,255,.78);
      --glass-blur: blur(12px);
    }
    *{ font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }

    /* Fondo con gradiente animado */
    body{
      min-height: 100vh;
      color: var(--txt);
      background:
        radial-gradient(1000px 600px at 10% -10%, rgba(36,163,193,.25), transparent 60%),
        radial-gradient(1000px 600px at 110% 110%, rgba(60,146,166,.25), transparent 60%),
        linear-gradient(135deg, #f3fbfe 0%, #eef7f9 100%);
      position: relative;
      overflow-x: hidden;
    }
    /* Blobs decorativos */
    .blob{
      position: absolute;
      filter: blur(45px);
      opacity: .35;
      z-index: 0;
    }
    .blob--1{ width: 420px; height: 420px; background: #24a3c1; top:-120px; left:-120px; border-radius: 50%; }
    .blob--2{ width: 520px; height: 520px; background: #3C92A6; bottom:-160px; right:-140px; border-radius: 50%; }

    .auth-wrap{
      position: relative;
      z-index: 1;
    }

    /* Tarjeta con efecto glass */
    .card-glass{
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      background: var(--card-bg);
      border: 1px solid rgba(36,163,193,.20);
      box-shadow:
        0 10px 30px rgba(0,0,0,.08),
        inset 0 1px 0 rgba(255,255,255,.6);
      border-radius: 20px;
    }

    .brand-badge{
      display:flex; align-items:center; gap:.6rem;
      justify-content:center;
      color:#0c323a;
      font-weight:700;
      letter-spacing:.3px;
    }
    .brand-badge .dot{
      width:12px; height:12px; border-radius:50%;
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      box-shadow: 0 0 0 3px rgba(36,163,193,.18);
    }

    .title{
      font-weight:700; letter-spacing:.2px;
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }

    .form-label{ font-weight:600; color:#0c323a; }
    .form-control, .form-select{
      border-radius: 12px;
      border: 1px solid rgba(12,50,58,.15);
      box-shadow: 0 1px 0 rgba(255,255,255,.6) inset;
      transition: border-color .2s ease, box-shadow .2s ease, transform .04s ease;
    }
    .form-control:focus, .form-select:focus{
      border-color: var(--brand-2);
      box-shadow: 0 0 0 .2rem rgba(36,163,193,.18);
    }
    .form-control:hover, .form-select:hover{ transform: translateY(-1px); }

    .btn-primary{
      border-radius: 12px;
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      border: none;
      box-shadow: 0 8px 18px rgba(36,163,193,.28);
    }
    .btn-primary:disabled{
      opacity:.75; box-shadow:none; filter: grayscale(.3);
    }
    .btn-outline-secondary{
      border-radius:12px;
    }

    .alert{ border-radius: 12px; }
    .helper{ color: var(--muted); }

    /* Password checklist y barra fuerza */
    #passwordChecklist small span{ display:inline-block; margin:.12rem 0; }
    .strength-wrap{
      margin-top:.4rem;
      background: #e9f4f7;
      border-radius: 10px;
      height: 10px;
      overflow: hidden;
    }
    .strength-bar{
      height:100%;
      width:0%;
      transition: width .25s ease;
      background: linear-gradient(90deg, #ff6b6b, #ffd166, #06d6a0);
    }

    /* Avatar preview */
    .avatar-drop{ position: relative; }
    .avatar-preview{
      width:56px; height:56px; border-radius:50%;
      background:#e7f3f6; display:inline-flex; align-items:center; justify-content:center;
      border:1px solid rgba(12,50,58,.15);
      overflow:hidden;
    }
    .avatar-preview img{ width:100%; height:100%; object-fit:cover; }
    .file-hint{ font-size:.85rem; color:var(--muted); }

    /* Footer legal */
    .legal{ font-size:.9rem; color:var(--muted); }

    /* Micro anims */
    .hover-float:hover{ transform: translateY(-2px); }

    @media (max-width: 480px){
      .brand-badge{ font-size:.95rem; }
    }
    /* Centrado perfecto con CSS Grid */
.page-center{
  min-height: 100svh;         /* cubre toda la ventana, respeta barra/UA */
  display: grid;
  place-items: center;         /* centra en X y Y */
  padding: clamp(16px, 2vw, 32px);
}
.form-pro{
  width: 100%;
  max-width: 760px;            /* el mismo ancho que ya usabas */
  margin-inline: auto;         /* asegúrate de centrar horizontalmente */
}

  </style>
</head>
<body>
  <div class="blob blob--1"></div>
  <div class="blob blob--2"></div>

  <div class="page-center auth-wrap">
    <form action="/sisec-ui/controllers/UserController.php" method="POST" enctype="multipart/form-data"
          class="card card-glass p-4 p-md-5 form-pro" novalidate>
      <input type="hidden" name="accion" value="crear">
      <!-- Para compatibilidad con controladores antiguos que esperan 'rol' -->
      <input type="hidden" name="rol" value="Pendiente">

      <!-- Encabezado -->
      <div class="text-center mb-3">
        <div class="brand-badge mb-2">
          <span class="dot"></span><span>CESISS · Acceso</span>
        </div>
        <h2 class="title mb-1">Solicitar acceso a CESISS</h2>
        <div class="helper">Regístrate con tu correo corporativo o personal y espera la aprobación.</div>
      </div>

      <?php if ($limiteAlcanzado): ?>
        <div class="alert alert-danger text-center mb-3">
          <i class="bi bi-exclamation-octagon-fill me-1"></i>
          Se ha alcanzado el límite máximo de 1000 usuarios. No se pueden registrar más.
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="alert alert-warning mb-3 text-center">
          <i class="bi bi-info-circle-fill me-1"></i>
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <!-- Nombre + Correo -->
      <div class="row g-3 mb-2">
        <div class="col-md-6">
          <label for="nombre" class="form-label">Nombre completo</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="nombre" name="nombre" autocomplete="name" required>
          </div>
        </div>
        <div class="col-md-6">
          <label for="email" class="form-label">Correo</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
          </div>
          <div class="form-text helper">Se recomienda usar el correo de tu empresa si aplica.</div>
        </div>
      </div>

      <!-- Contraseña + Confirmación -->
      <div class="row g-3 mb-2">
        <div class="col-md-6">
          <label for="clave" class="form-label">Contraseña</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
            <input type="password" class="form-control" id="clave" name="clave" autocomplete="new-password" required>
            <button class="btn btn-outline-secondary" type="button" id="toggleClave" aria-label="Mostrar/Ocultar contraseña">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="strength-wrap"><div class="strength-bar" id="strengthBar"></div></div>
          <div class="mt-2" id="passwordChecklist">
            <small>
              <span id="checkLength" class="text-danger">Al menos 8 caracteres</span><br>
              <span id="checkUpper" class="text-danger">Al menos una mayúscula</span><br>
              <span id="checkLower" class="text-danger">Al menos una minúscula</span><br>
              <span id="checkNumber" class="text-danger">Al menos un número</span><br>
              <span id="checkSpecial" class="text-danger">Al menos un carácter especial (!@#$%^&*)</span>
            </small>
          </div>
        </div>
        <div class="col-md-6">
          <label for="clave_confirm" class="form-label">Confirmar contraseña</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
            <input type="password" class="form-control" id="clave_confirm" name="clave_confirm" autocomplete="new-password" required>
            <button class="btn btn-outline-secondary" type="button" id="toggleConfirm" aria-label="Mostrar/Ocultar contraseña">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="mt-2">
            <small id="passwordMessage" class="text-danger"></small>
          </div>
        </div>
      </div>

      <!-- Cargo + Empresa -->
      <div class="row g-3 mb-2">
        <div class="col-md-6">
          <label for="cargo" class="form-label">Cargo</label>
          <input type="text" class="form-control" id="cargo" name="cargo" required>
        </div>
        <div class="col-md-6">
          <label for="empresa" class="form-label">Empresa</label>
          <input type="text" class="form-control" id="empresa" name="empresa" required>
        </div>
      </div>

      <!-- Foto de perfil (opcional) -->
      <div class="mb-3">
        <label for="foto" class="form-label">Foto de perfil (opcional)</label>
        <div class="d-flex align-items-center gap-3 avatar-drop">
          <div class="avatar-preview" id="avatarPreview" aria-label="Previsualización de foto">
            <i class="bi bi-person-fill" style="font-size:28px; color:#7aa9b3"></i>
          </div>
          <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
        </div>
        <div class="file-hint mt-1">Formatos aceptados: JPG/PNG. Tamaño recomendado: 400x400px.</div>
      </div>

      <!-- Pregunta/Respuesta de seguridad -->
      <div class="row g-3 mb-2">
        <div class="col-md-6">
          <label for="pregunta_seguridad" class="form-label">Pregunta de seguridad</label>
          <select class="form-select" id="pregunta_seguridad" name="pregunta_seguridad" required>
            <option value="">Seleccione una pregunta</option>
            <option value="¿Cuál es el nombre de tu primera mascota?">¿Cuál es el nombre de tu primera mascota?</option>
            <option value="¿Cuál es el segundo nombre de tu madre?">¿Cuál es el segundo nombre de tu madre?</option>
            <option value="¿En qué ciudad naciste?">¿En qué ciudad naciste?</option>
            <option value="¿Cuál fue tu primer colegio?">¿Cuál fue tu primer colegio?</option>
            <option value="¿Cómo se llama tu mejor amigo de la infancia?">¿Cómo se llama tu mejor amigo de la infancia?</option>
          </select>
        </div>
        <div class="col-md-6">
          <label for="respuesta_seguridad" class="form-label">Respuesta de seguridad</label>
          <input type="text" class="form-control" id="respuesta_seguridad" name="respuesta_seguridad" required>
        </div>
      </div>

      <!-- Botones -->
      <div class="d-flex flex-column flex-md-row align-items-stretch gap-2 mt-3">
        <button type="submit" id="btnGuardar" class="btn btn-primary flex-fill hover-float"
                <?= $limiteAlcanzado ? 'disabled' : '' ?> disabled>
          <i class="bi bi-send me-1"></i> Enviar solicitud
        </button>
        <a href="/sisec-ui/index.php" class="btn btn-outline-secondary flex-fill hover-float">
          <i class="bi bi-arrow-left"></i> Cancelar
        </a>
      </div>

      <div class="legal mt-3 text-center">
        Al enviar, tu cuenta quedará <strong>pendiente de aprobación</strong>. Un Administrador revisará tu solicitud.
      </div>
    </form>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  // Ejecuta cuando el DOM está listo
  document.addEventListener('DOMContentLoaded', () => {
    const claveInput      = document.getElementById('clave');
    const confirmInput    = document.getElementById('clave_confirm');
    const checkLength     = document.getElementById('checkLength');
    const checkUpper      = document.getElementById('checkUpper');
    const checkLower      = document.getElementById('checkLower');
    const checkNumber     = document.getElementById('checkNumber');
    const checkSpecial    = document.getElementById('checkSpecial');
    const btnGuardar      = document.getElementById('btnGuardar');
    const passwordMessage = document.getElementById('passwordMessage');
    const strengthBar     = document.getElementById('strengthBar');
    const fotoInput       = document.getElementById('foto');
    const avatarPreview   = document.getElementById('avatarPreview');

    const toggleClave     = document.getElementById('toggleClave');
    const toggleConfirm   = document.getElementById('toggleConfirm');

    function actualizarRequisito(el, ok, texto) {
      if (!el) return;
      if (ok) {
        el.classList.remove('text-danger'); el.classList.add('text-success');
        el.textContent = `✔ ${texto}`;
      } else {
        el.classList.remove('text-success'); el.classList.add('text-danger');
        el.textContent = texto;
      }
    }

    function scorePassword(v){
      let s = 0;
      if (!v) return 0;
      const letters = {};
      for (let i=0; i<v.length; i++) {
        letters[v[i]] = (letters[v[i]] || 0) + 1;
        s += 5.0 / letters[v[i]];
      }
      const variations = {
        digits: /\d/.test(v),
        lower: /[a-z]/.test(v),
        upper: /[A-Z]/.test(v),
        nonWords: /[^A-Za-z0-9]/.test(v)
      };
      let variationCount = 0;
      for (let check in variations) variationCount += (variations[check] === true) ? 1 : 0;
      s += (variationCount - 1) * 10;
      return parseInt(s);
    }

    function setStrengthBar(pwd){
      const score = scorePassword(pwd);
      let pct = 0;
      if (score > 80) pct = 100;
      else if (score > 60) pct = 75;
      else if (score > 40) pct = 50;
      else if (score > 20) pct = 25;
      else pct = 10;
      strengthBar.style.width = pct + '%';
    }

    function evaluar() {
      const val = claveInput.value || '';
      const cumpleLength  = val.length >= 8;
      const cumpleUpper   = /[A-Z]/.test(val);
      const cumpleLower   = /[a-z]/.test(val);
      const cumpleNumber  = /\d/.test(val);
      const cumpleSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(val);

      actualizarRequisito(checkLength, cumpleLength, 'Al menos 8 caracteres');
      actualizarRequisito(checkUpper,  cumpleUpper,  'Al menos una mayúscula');
      actualizarRequisito(checkLower,  cumpleLower,  'Al menos una minúscula');
      actualizarRequisito(checkNumber, cumpleNumber, 'Al menos un número');
      actualizarRequisito(checkSpecial,cumpleSpecial,'Al menos un carácter especial (!@#$%^&*)');

      setStrengthBar(val);

      let pendientes = [];
      if (!cumpleLength)  pendientes.push('mínimo 8 caracteres');
      if (!cumpleUpper)   pendientes.push('una mayúscula');
      if (!cumpleLower)   pendientes.push('una minúscula');
      if (!cumpleNumber)  pendientes.push('un número');
      if (!cumpleSpecial) pendientes.push('un carácter especial');

      const coincide = (claveInput.value === confirmInput.value) && confirmInput.value.length > 0;

      if (pendientes.length > 0) {
        passwordMessage.textContent = "Falta: " + pendientes.join(", ");
        passwordMessage.classList.remove("text-success");
        passwordMessage.classList.add("text-danger");
        btnGuardar.setAttribute('disabled', true);
        return;
      }

      if (!coincide) {
        passwordMessage.textContent = "Las contraseñas no coinciden";
        passwordMessage.classList.remove("text-success");
        passwordMessage.classList.add("text-danger");
        btnGuardar.setAttribute('disabled', true);
        return;
      }

      passwordMessage.textContent = "✔ Contraseña válida";
      passwordMessage.classList.remove("text-danger");
      passwordMessage.classList.add("text-success");
      btnGuardar.removeAttribute('disabled');
    }

    // Preview avatar
    if (fotoInput) {
      fotoInput.addEventListener('change', e => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
          avatarPreview.innerHTML = '<img alt="Foto de perfil" />';
          avatarPreview.querySelector('img').src = ev.target.result;
        };
        reader.readAsDataURL(file);
      });
    }

    // Toggles de visibilidad
    function togglePwd(input, btn){
      const icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    }
    toggleClave.addEventListener('click', () => togglePwd(claveInput, toggleClave));
    toggleConfirm.addEventListener('click', () => togglePwd(confirmInput, toggleConfirm));

    claveInput.addEventListener('input', evaluar);
    confirmInput.addEventListener('input', evaluar);

    // Si ya está alcanzado el límite, aseguramos botón deshabilitado
    <?php if ($limiteAlcanzado): ?> btnGuardar.setAttribute('disabled', true); <?php endif; ?>
  });
  </script>
</body>
</html>
