<?php
// /sisec-ui/views/dispositivos/qr_claim.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Técnico','Capturista','Prevencion']);
date_default_timezone_set('America/Mexico_City');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Función para obtener marcas por equipo
function getMarcasPorEquipo(mysqli $conn, int $equipo_id): array {
  $stmt = $conn->prepare("SELECT id, nom_marca FROM marcas WHERE equipo_id = ? ORDER BY nom_marca ASC");
  $stmt->bind_param('i', $equipo_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $marcas = [];
  while($row = $res->fetch_assoc()) {
    $marcas[] = $row;
  }
  $stmt->close();
  return $marcas;
}

// ================= AJAX: BUSCAR DETERMINANTES =================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar_determinante') {
  header('Content-Type: application/json; charset=utf-8');
  $q = trim($_GET['q'] ?? '');
  if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
  }

  $stmt = $conn->prepare("SELECT 
    d.id AS determinante_id,
    d.nom_determinante,
    s.id AS sucursal_id,
    s.nom_sucursal,
    m.id AS municipio_id,
    c.ID AS ciudad_id
    FROM determinantes d
    INNER JOIN sucursales s ON s.id = d.sucursal_id
    INNER JOIN municipios m ON m.id = s.municipio_id
    INNER JOIN ciudades c ON c.ID = m.ciudad_id
    WHERE d.nom_determinante LIKE CONCAT('%', ?, '%')
    ORDER BY d.nom_determinante ASC
    LIMIT 10");
  $stmt->bind_param('s', $q);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($row = $res->fetch_assoc()) {
    $out[] = $row;
  }
  echo json_encode($out);
  exit;
}

/* VALIDACIÓN DEL TOKEN */
$token = $_GET['t'] ?? '';
if (!$token) {
  http_response_code(400); die('Token faltante.');
}
$stmt = $conn->prepare("SELECT id, dispositivo_id FROM qr_pool WHERE token=? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$qr = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$qr) {
  http_response_code(404); die('QR no encontrado.');
}
if (!empty($qr['dispositivo_id'])) {
  // Redirigir directamente a la ficha técnica del dispositivo
  $id = (int)$qr['dispositivo_id'];
  header("Location: /sisec-ui/views/dispositivos/device.php?id={$id}");
  exit;
}

/* CATÁLOGOS */
$rol = $_SESSION['usuario_rol'] ?? '';
$isTecnico = ($rol === 'Técnico');
$ciudades = $conn->query("SELECT ID, nom_ciudad FROM ciudades ORDER BY nom_ciudad ASC");
$equipos  = $conn->query("SELECT id, nom_equipo FROM equipos ORDER BY nom_equipo ASC");
$lastDeviceId = (isset($_GET['last_id']) && ctype_digit($_GET['last_id'])) ? (int)$_GET['last_id'] : null;
$tipo_cctv   = $_POST['tipo_cctv'] ?? null;
$tipo_switch = $_POST['tipo_switch'] ?? null;
$tipo_alarma = $_POST['tipo_alarma'] ?? null;

ob_start();
?>

<!doctype html>
  <html lang="es">
    <head>
      <meta charset="utf-8" />
      <title>Reclamar QR virgen</title>
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
      <link rel="shortcut icon" href="/sisec-ui/public/img/QRCESISS.png">
      <style>
        :root{--brand:#3C92A6; --brand-2:#24A3C1; --ink:#10343b; --muted:#486973; --bg:#F7FBFD;--surface:#FFFFFF; --border:#DDEEF3; --border-strong:#BFE2EB;--chip:#EAF7FB; --ring:0 0 0 .22rem rgba(36,163,193,.25); --ring-strong:0 0 0 .28rem rgba(36,163,193,.33);--shadow-sm:0 6px 18px rgba(20,78,90,.08);--radius-xl:1rem; --radius-2xl:1.25rem;}
        body{ background:var(--bg); color:var(--ink); }
        h2{ font-weight:800; letter-spacing:.2px; color:var(--ink); margin-bottom:.75rem!important; }
        h2::after{ content:""; display:block; width:78px; height:4px; border-radius:99px; margin-top:.5rem;background:linear-gradient(90deg,var(--brand),var(--brand-2)); }
        .section-card{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-2xl);padding:1rem; box-shadow:var(--shadow-sm); }
        #sugerencia{ margin-bottom:1rem;background:linear-gradient(90deg, rgba(36,163,193,.08), rgba(60,146,166,.06));padding:12px 14px; border-left:6px solid var(--brand); border-radius:.9rem; color:#134954;}
        .form-label{ display:inline-flex; align-items:center; gap:.5rem; font-weight:700; color:var(--ink);margin-bottom:.35rem; position:relative; padding:.2rem .6rem; border-radius:999px;background: var(--chip); border:1px solid var(--border); }
        .form-label::before{ content:""; width:.55rem; height:.55rem; border-radius:50%;background:linear-gradient(90deg,var(--brand),var(--brand-2)); }
        .form-control,.form-select{ background:#fff; color:var(--ink); border:2px solid var(--border);border-radius:.9rem; padding:.7rem .95rem; box-shadow:none; transition:border-color .15s, box-shadow .15s, background .2s; }
        .form-control:hover,.form-select:hover{ border-color:var(--border-strong); }
        .form-control:focus,.form-select:focus{ border-color:var(--brand); box-shadow: var(--ring); background:#fff; }
        .btn-secondary{ background: linear-gradient(90deg, var(--brand), var(--brand-2)); border:none; color:#fff; font-weight:800;padding:.7rem 1.3rem; border-radius:999px; box-shadow: 0 10px 20px rgba(36,163,193,.25); }
        .btn-outline-primary{ --bs-btn-color: var(--ink); --bs-btn-border-color: var(--brand); --bs-btn-hover-color:#fff;--bs-btn-hover-bg:var(--brand); --bs-btn-hover-border-color:var(--brand); --bs-btn-active-bg:var(--brand);--bs-btn-active-border-color:var(--brand); border-width:1px; border-radius:999px; font-weight:800; }
        .dropzone{border:2px dashed var(--border-strong);background:radial-gradient(ellipse at 20% 10%, rgba(36,163,193,.06), transparent 60%),radial-gradient(ellipse at 80% 90%, rgba(60,146,166,.05), transparent 60%),#fff;cursor:pointer; border-radius:1.1rem; transition:border-color .15s, box-shadow .15s, transform .12s;position:relative; overflow:hidden;}
        .dropzone:hover{ border-color:var(--brand); box-shadow: var(--ring); }
        .dz-card{ height:100%; display:flex; flex-direction:column; }
        .dz-body{ flex:1 1 auto; display:flex; flex-direction:column; justify-content:center; padding:1.25rem!important; }
        .preview{max-width:100%; max-height:220px; object-fit:contain; border-radius:.9rem;background:linear-gradient(45deg,#f9fdff 25%,transparent 25% 75%,#f9fdff 75%),linear-gradient(45deg,#f9fdff 25%,transparent 25% 75%,#f9fdff 75%) 10px 10px;background-size:20px 20px; border:1px solid var(--border);}
        .camera-live{ width:100%; background:#000; aspect-ratio:16/9; border-radius:1rem; box-shadow:var(--shadow-sm); }
        .input-ghost{ position:fixed!important; left:-100vw!important; width:1px!important; height:1px!important;opacity:0!important; pointer-events:none!important; }@media (max-width:575.98px){ .preview{ max-height:180px; } .camera-live{ aspect-ratio:4/3; } }
        button.active {background-color: #0d6efd;color: white;border-color: #0d6efd;}
      </style>
    </head>
  <body>
    <div class="container py-3">
      <h2 class="mb-2">Reclamar QR virgen</h2>
      <div id="sugerencia" class="mb-3">Este QR está <strong>disponible</strong>. Completa la información mínima y se guardará como <strong>Activo</strong>.</div>
      <?php if (in_array($rol, ['Técnico'], true)): ?>
      <div class="mb-3 d-flex gap-2">
        <a href="/sisec-ui/views/dispositivos/qr_virgenes_generar.php" class="btn btn-outline-primary"><i class="fa fa-qrcode me-1"></i>Generar QR vírgenes</a>
        <a href="/sisec-ui/views/dispositivos/qr_scan.php" class="btn btn-secondary"><i class="fa fa-camera me-1"></i>Escanear QR</a>
      </div>
      <?php endif; ?>
      <!-- ========= FORMULARIO ========= -->
      <form action="/sisec-ui/views/dispositivos/guardar_asignacion.php" method="post" enctype="multipart/form-data" class="section-card" style="max-width:1500px; margin:auto;">
        <input type="hidden" name="qr_token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="qr_file" value="<?= htmlspecialchars($token) ?>.png">
        <input type="hidden" name="estado_id" value="1">
        <div class="row g-4">
          <div class="col-md-6">
            <label class="form-label">Equipo</label>
            <input type="text" name="equipo" id="equipo" class="form-control" placeholder="Ej. Cámara, DVR, Switch, NVR, Alarma…" list="equipos-sugeridos" required />
            <datalist id="equipos-sugeridos">
              <option value="CÁMARA"></option>
              <option value="ESTROBO"></option>
              <option value="TRANSMISOR"></option>
              <option value="SWITCH"></option>
              <option value="NVR"></option>
              <option value="DVR"></option>
              <option value="DH"></option>
              <option value="SERVIDOR"></option>
              <option value="MONITOR"></option>
              <option value="ESTACIÓN DE TRABAJO"></option>
              <option value="ALARMA"></option>
              <option value="PIR"></option>
            </datalist>
          </div>
          <!-- INICIO BOTONES -->
          <div id="tipoAlarmaContainer" class="mt-2" style="display:none;">
            <button type="button" class="btn btn-outline-primary me-2 tipo-alarma" onclick="document.getElementById('tipo_alarma').value = 'Alámbrico'">Alámbrico</button>
            <button type="button" class="btn btn-outline-primary tipo-alarma" onclick="document.getElementById('tipo_alarma').value = 'Inalámbrico'">Inalámbrico</button>
          </div>
          <div id="tipoSwitchContainer" class="mt-2" style="display:none;">
            <button type="button" class="btn btn-outline-primary me-2 tipo-switch" onclick="document.getElementById('tipo_switch').value = 'Plano'">Plano</button>
            <button type="button" class="btn btn-outline-primary tipo-switch" onclick="document.getElementById('tipo_switch').value = 'PoE'">PoE</button>
          </div>
          <div id="tipoCamaraContainer" style="display: none; margin-top: 10px;">
            <button type="button" id="camaraIP" class="btn btn-outline-primary" onclick="document.getElementById('tipo_cctv').value = 'IP'">IP</button>
            <button type="button" id="camaraAnalogica" class="btn btn-outline-primary" onclick="document.getElementById('tipo_cctv').value = 'Analógico'">Analógica</button>
          </div>
          <!-- FIN BOTONES -->
          <!-- Campos ocultos -->
          <input type="hidden" name="tipo_alarma" id="tipo_alarma">
          <input type="hidden" name="tipo_switch" id="tipo_switch">
          <input type="hidden" name="tipo_cctv" id="tipo_cctv">
          <div class="col-md-3 mt-2">
            <label class="form-label">Marca</label>
            <div class="input-group flex-wrap">
              <select name="marca" id="marca" class="form-control">
                <option value="">Selecciona una marca</option>
              </select>
              <button type="button" id="toggleMarcaManual" class="btn btn-outline-secondary" title="Escribir marca">✎</button>
            </div>
            <input type="text" id="marcaManual" class="form-control mt-2 d-none" placeholder="Escribe la marca">
            <div class="form-text">Elige una marca o escribe una nueva.</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Modelo del equipo</label>
            <input type="text" name="modelo" id="modelo" class="form-control" list="sugerencias-modelo" placeholder="Escribe el modelo" required>
            <datalist id="sugerencias-modelo"></datalist>
          </div>
          <div class="col-md-3 campo-user">
            <label class="form-label">IDE</label>
            <input type="text" name="user" id="ide_user" class="form-control" placeholder="IDE" data-raw required>
          </div>
          <div class="col-md-3 campo-pass">
            <label class="form-label">IDE Password</label>
            <input type="text" name="pass" id="ide_pass" class="form-control" placeholder="IDE Password" data-raw required>
          </div>
          <div class="col-md-6 position-relative">
            <label class="form-label">Determinante</label>
              <input type="text" id="determinante_input" class="form-control" placeholder="Escribe la determinante" autocomplete="off" required oninput="this.value=this.value.replace(/\D/g,'')">
              <input type="hidden" name="determinante_id" id="determinante_id">
              <input type="hidden" name="ciudad_id" id="ciudad_id">
              <input type="hidden" name="municipio_id" id="municipio_id">
              <input type="hidden" name="sucursal_id" id="sucursal_id">
            <div id="determinante_results" class="list-group position-absolute w-100 shadow" style="z-index:1000; display:none;"></div>
            <div class="form-text">Escribe la determinante y selecciónala</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Área del dispositivo</label>
            <input type="text" name="area" class="form-control" placeholder="Área en tienda" value="<?= htmlspecialchars($device['area'] ?? '') ?>">
          </div>
          <div class="col-md-3" id="zonaAlarmaContainer" style="display:none;">
            <label class="form-label">Zona</label>
            <input type="number" name="zona_alarma" id="zona_alarma" class="form-control" placeholder="Ej. 200, 501, 400..">
            <div class="form-text">Solo escribe números</div>
          </div>
          <!--div class="col-12">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 align-items-stretch">
              <?php for ($i=1; $i<=3; $i++): ?>
              <div class="col">
                <div class="border rounded dz-card p-3">
                  <label class="form-label"><?= $i===1 ? 'Imagen' : ($i===2 ? 'Imagen antes' : 'Imagen después') ?></label>
                  <div class="dropzone text-center dz-body" id="drop-imagen<?= $i ?>" data-input="imagen<?= $i ?>">
                    <i class="fas fa-image fa-2x mb-2 text-muted"></i>
                    <p class="text-muted mensaje m-0">Haz clic para <strong>elegir archivo</strong></p>
                    <input type="file" name="imagen<?= $i ?>" id="imagen<?= $i ?>" class="input-ghost" accept="image/*">
                    <img id="preview-imagen<?= $i ?>" class="preview d-none mt-2" src="#" alt="preview">
                    <div class="mt-3 d-flex gap-2 justify-content-center">
                      <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCamera(<?= $i ?>)"><i class="fas fa-camera me-1"></i> Tomar foto</button>
                      <button type="button" class="btn btn-danger btn-sm d-none remove-btn" data-target="imagen<?= $i ?>">Eliminar</button>
                    </div>
                  </div>
                </div>
              </div>
              <?php endfor; ?>
            </div>
          </div-->
          <div class="col-12 text-center mt-2">
            <button class="btn btn-secondary px-5"><i class="fas fa-check-circle me-2"></i>Guardar asignación</button>
          </div>
        </div>
      </form>
    </div>
    <!-- Modal cámara -->
    <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-camera me-1"></i>Tomar foto</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" onclick="stopCamera()"></button>
          </div>
          <div class="modal-body">
            <video id="cameraVideo" class="camera-live" autoplay playsinline></video>
            <canvas id="cameraCanvas" class="d-none"></canvas>
            <div class="d-flex gap-2 mt-3">
              <button type="button" id="switchBtn" class="btn btn-outline-secondary" onclick="switchCamera()">Cambiar cámara</button>
              <button type="button" class="btn btn-primary ms-auto" onclick="takePhoto()"><i class="fa fa-circle me-1"></i> Capturar</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/sisec-ui/views/dispositivos/validacionesregistro.js"></script>
    <script>
      // ===================== JS limpio y funcional =====================
      const baseUbicacion = '/sisec-ui/views/ubicacion/';
      const baseCatalogos = '/sisec-ui/views/catalogos/';
      async function jget(url){
        const r = await fetch(url, { credentials:'same-origin' });
        if(!r.ok) throw new Error('HTTP '+r.status);
        return r.json();
      }
      // ---------- Cascada Ubicación ----------
      const selCiudad = document.getElementById('ciudad');
      const selMpio   = document.getElementById('municipio');
      const selSuc    = document.getElementById('sucursal');
      const selDet    = document.getElementById('determinante_sel');
      const hidDet    = document.getElementById('determinante_id');
      function clearSelect(sel, ph){
        if(!sel) return;
        sel.innerHTML = '';
        const o = document.createElement('option');
        o.value = '';
        o.textContent = ph || '-- Selecciona --';
        sel.appendChild(o);
      }
      selCiudad?.addEventListener('change', async ()=>{
        clearSelect(selMpio, '-- Selecciona un municipio --');
        clearSelect(selSuc,  '-- Selecciona una sucursal --');
        clearSelect(selDet,  '-- Determinante --');
        selDet.disabled = true; hidDet.value = '';
        if(!selCiudad.value) return;
        try{
          const data = await jget(`${baseUbicacion}api_municipios.php?ciudad_id=${encodeURIComponent(selCiudad.value)}`);
          data.forEach(m=>{
            const o = document.createElement('option');
            o.value = m.id;
            o.textContent = m.nom_municipio ?? m.nombre ?? '';
            if(o.textContent) selMpio.appendChild(o);
          });
        }catch(_){ alert('No se pudieron cargar los municipios'); }
      });
      selMpio?.addEventListener('change', async ()=>{
        clearSelect(selSuc, '-- Selecciona una sucursal --');
        clearSelect(selDet, '-- Determinante --');
        selDet.disabled = true; hidDet.value = '';
        if(!selMpio.value) return;
        try{
          const q = selCiudad?.value
            ? `ciudad_id=${encodeURIComponent(selCiudad.value)}&municipio_id=${encodeURIComponent(selMpio.value)}`
            : `municipio_id=${encodeURIComponent(selMpio.value)}`;
          const data = await jget(`${baseUbicacion}api_sucursales.php?${q}`);
          data.forEach(s=>{
            const o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.nom_sucursal ?? s.nombre ?? '';
            if(o.textContent) selSuc.appendChild(o);
          });
        }catch(_){ alert('No se pudieron cargar las sucursales'); }
      });
      selSuc?.addEventListener('change', async ()=>{
        clearSelect(selDet, '-- Determinante --');
        selDet.disabled = true; hidDet.value = '';
        if(!selSuc.value) return;
        try{
          const data = await jget(`${baseUbicacion}api_determinantes.php?sucursal_id=${encodeURIComponent(selSuc.value)}`);
          let first = '';
          data.forEach(d=>{
            const name = (d.nom_determinante ?? d.determinante ?? '').trim();
            if(!name) return;
            const o = document.createElement('option');
            o.value = d.id; o.textContent = name;
            selDet.appendChild(o);
            if(!first) first = d.id;
          });
          if(first){
            selDet.value = first;
            selDet.disabled = true;
            hidDet.value = first;
          }else{
            clearSelect(selDet, '-- Sin determinantes registradas --');
          }
        }catch(_){ alert('No se pudieron cargar las determinantes'); }
      });
      selDet?.addEventListener('change', ()=>{ hidDet.value = selDet.value || ''; });
      // ---------- Equipo -> Marca -> Modelo ----------
      const selEq     = document.getElementById('equipo_id');
      const selMarca  = document.getElementById('marca_id');
      const selModelo = document.getElementById('modelo_id');
      selEq?.addEventListener('change', async ()=>{
        clearSelect(selMarca, '-- Selecciona marca --');
        clearSelect(selModelo, '-- Selecciona modelo --');
        if(!selEq.value) return;
      try{
        const response = await fetch('/sisec-ui/views/dispositivos/ajax_marcas.php?equipo_id=' + encodeURIComponent(selEq.value), { credentials:'same-origin' });
        const data = await response.json();
        data.forEach(m=>{
          const o = document.createElement('option');
          o.value = m.id;
          o.textContent = m.nom_marca;
          selMarca.appendChild(o);
        });
      }catch(e){
        alert('No se pudieron cargar las marcas');
        console.error(e);
      }
    });
    selMarca?.addEventListener('change', async ()=>{
      clearSelect(selModelo, '-- Selecciona modelo --');
      if(!selMarca.value) return;
    try{
      const response = await fetch('/sisec-ui/views/dispositivos/ajax_modelos.php?marca_id=' + encodeURIComponent(selMarca.value), { credentials:'same-origin' });
      const data = await response.json();
      data.forEach(md=>{
        const o = document.createElement('option');
        o.value = md.id;
        o.textContent = md.nom_modelo;
        selModelo.appendChild(o);
      });
      }catch(e){
        alert('No se pudieron cargar los modelos');
        console.error(e);
      }
    });
    selMarca?.addEventListener('change', async ()=>{
      clearSelect(selModelo, '-- Selecciona modelo --');
      if(!selMarca.value) return;
      try{
        const data = await jget(`${baseCatalogos}api_modelos.php?marca_id=${encodeURIComponent(selMarca.value)}`);
        data.forEach(md=>{
          const o = document.createElement('option');
          o.value = md.id;
          o.textContent = md.nom_modelo ?? md.nombre ?? '';
          if(o.textContent) selModelo.appendChild(o);
        });
      }catch(_){ alert('No se pudieron cargar los modelos'); }
    });
    // ---------- Dropzones, cámara, etc. ----------
    // --- Cámara (simple)
    let __cam = { slot:null, stream:null, devices:[], index:0, video:null, canvas:null };
    function setFileToInput(input, file){ const dt=new DataTransfer(); dt.items.add(file); input.files=dt.files; input.dispatchEvent(new Event('change',{bubbles:true})); }
    async function enumerateCameras(){ try{ const all=await navigator.mediaDevices.enumerateDevices(); return all.filter(d=>d.kind==='videoinput'); }catch(e){ return []; } }
    async function startCamera(deviceId){ stopCamera(); const constraints={ video: deviceId?{deviceId:{exact:deviceId}}:{facingMode:{ideal:'environment'}}, audio:false }; __cam.stream=await navigator.mediaDevices.getUserMedia(constraints); __cam.video.srcObject=__cam.stream; await __cam.video.play(); }
    window.openCamera = async function(slot){ const m=document.getElementById('cameraModal'); if(!m) return; __cam.slot=slot; __cam.video=document.getElementById('cameraVideo'); __cam.canvas=document.getElementById('cameraCanvas'); const modal=new bootstrap.Modal(m); modal.show(); try{ await startCamera(null); __cam.devices=await enumerateCameras(); __cam.index=0; const sb=document.getElementById('switchBtn'); if(sb) sb.style.display = (__cam.devices.length>1)?'':'none'; m.addEventListener('hidden.bs.modal', stopCamera, { once:true }); }catch(err){ alert('No se pudo acceder a la cámara, usa Elegir archivo.'); const input=document.getElementById('imagen'+slot); if(input){ try{ input.setAttribute('capture','environment'); }catch(_){} input.click(); } const inst=bootstrap.Modal.getInstance(m); inst && inst.hide(); } };
    window.switchCamera = async function(){ if(!__cam.devices.length) return; __cam.index = (__cam.index+1) % __cam.devices.length; try{ await startCamera(__cam.devices[__cam.index].deviceId); }catch(e){} };
    window.stopCamera = function(){ if(__cam.stream){ __cam.stream.getTracks().forEach(t=>t.stop()); __cam.stream=null; } };
    function dataURLToFile(dataURL, filename){ const arr=dataURL.split(','); const mime=arr[0].match(/:(.*?);/)[1]; const bstr=atob(arr[1]); let n=bstr.length; const u8=new Uint8Array(n); while(n--) u8[n]=bstr.charCodeAt(n); return new File([u8], filename, { type:mime }); }
    window.takePhoto = function(){ if(!__cam.video) return; const w=__cam.video.videoWidth||1280, h=__cam.video.videoHeight||720; __cam.canvas.width=w; __cam.canvas.height=h; const ctx=__cam.canvas.getContext('2d'); ctx.drawImage(__cam.video,0,0,w,h); const dataUrl=__cam.canvas.toDataURL('image/jpeg',0.92); const file=dataURLToFile(dataUrl, 'captura-'+Date.now()+'.jpg'); const input=document.getElementById('imagen'+__cam.slot); if(!input){ alert('No se encontró el input de imagen'); return; } setFileToInput(input, file); const m=document.getElementById('cameraModal'); const inst=bootstrap.Modal.getInstance(m); inst && inst.hide(); stopCamera(); };
  </script>
  <script>
  /**
   * Forzar MAYÚSCULAS en inputs de texto (sin mover el caret)
   * + Habilitar spellcheck del navegador (lang=es) excepto en campos técnicos.
   * No rompe validaciones existentes (MAC/IP mantienen lógica).
   */
  (() => {
    // Campos que queremos enviar SIEMPRE en MAYÚSCULAS (front)
    const UPPERCASE_IDS = [
      // básicos
      'equipo','marcaManual','modelo','estado',
      // ubicación / tienda
      'area','rc','Ubicacion_rc',
      // red
      'switch','puerto','observaciones',
      // específicos
      'servidor','vms','version_vms','version_windows','zona_alarma','tipo_sensor',
      // ocultos de tipo
      'tipo_alarma','tipo_switch','tipo_cctv'
    ];

    // Campos DONDE NO querer spellcheck (códigos, modelos, redes…)
    const NO_SPELLCHECK_IDS = new Set([
      'macInput','ipInput','modelo','serie','vms','version_vms','switch','puerto',
      'tag','ip' // los que muestras como disabled
    ]);

    function enableSpellcheck() {
      const nodes = document.querySelectorAll('input[type="text"], textarea');
      nodes.forEach(el => {
        if (NO_SPELLCHECK_IDS.has(el.id)) return;
        // Respeta si ya lo forzaste a false en HTML
        if (el.getAttribute('spellcheck') === 'false') return;
        el.setAttribute('spellcheck', 'true');
        el.setAttribute('lang', 'es');
      });
    }

    function attachUppercase(id) {
      const el = document.getElementById(id);
      if (!el) return;
      // visual
      el.classList.add('uppercase');
      // lógico (valor enviado)
      const handler = () => {
        // no tocar contraseñas ni IP/MAC
        if (el.type === 'password' || el.id === 'ipInput' || el.id === 'macInput') return;
        const s = el.selectionStart, e = el.selectionEnd;
        const upper = (el.value || '').toLocaleUpperCase('es-MX');
        if (el.value !== upper) {
          el.value = upper;
          if (typeof s === 'number' && typeof e === 'number') el.setSelectionRange(s, e);
        }
      };
      el.addEventListener('input', handler);
      // normaliza posibles autocompletados
      handler();
    }

    document.addEventListener('DOMContentLoaded', () => {
      enableSpellcheck();
      UPPERCASE_IDS.forEach(attachUppercase);

      // Última defensa: antes de enviar, volvemos a asegurar mayúsculas y trim
      const form = document.querySelector('form[action="guardar.php"]');
      if (form) {
        form.addEventListener('submit', () => {
          UPPERCASE_IDS.forEach(id => {
            const el = document.getElementById(id);
            if (el && typeof el.value === 'string') {
              el.value = el.value.toLocaleUpperCase('es-MX').trim();
            }
          });
        });
        // Sincroniza determinante_id hidden por si acaso
        const detHidden = document.getElementById('determinante_hidden');
        if (detHidden && selDet) {
          detHidden.value = selDet.value || detHidden.value || '';
        }
      }
    });
    // --- Resaltar el botón seleccionado ---
  document.querySelectorAll('#tipoCamaraContainer button, #tipoAlarmaContainer button, #tipoSwitchContainer button').forEach(boton => {
    boton.addEventListener('click', () => {
      document.querySelectorAll('.tipo-alarma, .tipo-switch, #tipoCamaraContainer button').forEach(boton => {
      boton.addEventListener('click', () => {
        const parent = boton.parentElement;
        parent.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        boton.classList.add('active');
      });
    });
    });
  });

  })();
  </script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const input   = document.getElementById('determinante_input');
    const results = document.getElementById('determinante_results');

    const detId   = document.getElementById('determinante_id');
    const cId     = document.getElementById('ciudad_id');
    const mId     = document.getElementById('municipio_id');
    const sId     = document.getElementById('sucursal_id');

    let controller = null;

    input.addEventListener('input', async () => {
      const q = input.value.trim();
      detId.value = cId.value = mId.value = sId.value = '';
      results.style.display = 'none';
      results.innerHTML = '';

      if (q.length < 2) return;

      if (controller) controller.abort();
      controller = new AbortController();

      try {
        const res = await fetch(
          `qr_claim.php?ajax=buscar_determinante&q=${encodeURIComponent(q)}`,
          { signal: controller.signal }
        );

        const data = await res.json();
        if (!data.length) return;

        data.forEach(d => {
          const item = document.createElement('button');
          item.type = 'button';
          item.className = 'list-group-item list-group-item-action';
          item.textContent = `${d.nom_determinante}, ${d.nom_sucursal}`;

          item.addEventListener('click', () => {
            input.value = `${d.nom_determinante}, ${d.nom_sucursal}`;
            detId.value = d.determinante_id;
            cId.value   = d.ciudad_id;
            mId.value   = d.municipio_id;
            sId.value   = d.sucursal_id;
            results.style.display = 'none';
          });

          results.appendChild(item);
        });

        results.style.display = 'block';
      } catch (e) {
        if (e.name !== 'AbortError') console.error(e);
      }
    });

    document.addEventListener('click', e => {
      if (!results.contains(e.target) && e.target !== input) {
        results.style.display = 'none';
      }
    });
  });
  </script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const equipoInput          = document.getElementById('equipo');
    const tipoCamaraContainer  = document.getElementById('tipoCamaraContainer');
    const tipoAlarmaContainer  = document.getElementById('tipoAlarmaContainer');
    const tipoSwitchContainer  = document.getElementById('tipoSwitchContainer');
    const zonaAlarmaContainer  = document.getElementById('zonaAlarmaContainer');
    const zonaInput            = document.getElementById('zona_alarma');
    const ALARM_KEYWORDS = ['alarma','pir','sensor','dh','cm','btn','boton','panico','panic','oh','estrobo','estrobos','estorbo','overhead','overhed','repetidor','repetidores','rep','rp','drc','ruptura','cristal','joystick','estacion manual','estacion de trabajo','workstation', 'transmisor','ratonera','em'];

    function normalizar(v){
      return v
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
    }

    function esAlarma(valor){
      const v = normalizar(valor);
      return ALARM_KEYWORDS.some(k => v.includes(k));
    }

    function evaluarEquipo(){
      const v = normalizar(equipoInput.value);

      // RESET TOTAL
      tipoCamaraContainer.style.display = 'none';
      tipoAlarmaContainer.style.display = 'none';
      tipoSwitchContainer.style.display = 'none';
      zonaAlarmaContainer.style.display = 'none';
      zonaInput.required = false;

      if (v.includes('camara')) {
        tipoCamaraContainer.style.display = 'block';
        return;
      }

      if (esAlarma(v)) {
        tipoAlarmaContainer.style.display = 'block';
        zonaAlarmaContainer.style.display = 'block';
        zonaInput.required = true;
        return;
      }

      if (v.includes('switch')) {
        tipoSwitchContainer.style.display = 'block';
      }
    }

    equipoInput.addEventListener('input', evaluarEquipo);
    equipoInput.addEventListener('change', evaluarEquipo);
  });
  </script>
</body>
</html>
<?php
ob_end_flush();
?>