<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Mantenimientos', 'Invitado', 'Distrital','Capturista', 'Prevencion', 'Técnico']);

include __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido o no especificado.');
}
$id = (int)$_GET['id'];

/* ==============================
   1) Traer dispositivo + joins
   ============================== */
$stmt = $conn->prepare("
    SELECT 
      d.*,
      s.nom_sucursal,
      m.nom_municipio,
      c.nom_ciudad,
      eq.nom_equipo,
      mo.num_modelos,
      es.status_equipo,
      a.tipo_alarma   AS tipo_alarma_label,
      cc.tipo_cctv    AS tipo_cctv_label,
      sw.tipo_switch  AS tipo_switch_label,
      mar.nom_marca   AS marca_label
    FROM dispositivos d
    LEFT JOIN sucursales s ON d.sucursal = s.id
    LEFT JOIN municipios m ON s.municipio_id = m.id
    LEFT JOIN ciudades c   ON m.ciudad_id = c.id
    LEFT JOIN equipos eq   ON d.equipo = eq.id
    LEFT JOIN modelos mo   ON d.modelo = mo.id
    LEFT JOIN marcas  mar  ON d.marca_id = mar.id_marcas
    LEFT JOIN status  es   ON d.estado = es.id
    LEFT JOIN alarma  a    ON a.id  = d.alarma_id
    LEFT JOIN cctv    cc   ON cc.id = d.cctv_id
    LEFT JOIN `switch` sw  ON sw.id = d.switch_id
    WHERE d.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$device = $result->fetch_assoc();
$stmt->close();

if (!$device) {
    die('Dispositivo no encontrado.');
}


/* ==========================================
   2) Meta: quién lo registró y en qué fecha
   ========================================== */
$meta = null;
$stmtMeta = $conn->prepare("
  SELECT u.nombre, u.rol, n.fecha
  FROM notificaciones n
  INNER JOIN usuarios u ON u.id = n.usuario_id
  WHERE n.dispositivo_id = ?
    AND n.mensaje LIKE '%registró un nuevo dispositivo%'
  ORDER BY n.fecha ASC
  LIMIT 1
");
$stmtMeta->bind_param("i", $id);
$stmtMeta->execute();
$meta = $stmtMeta->get_result()->fetch_assoc();
$stmtMeta->close();

/* ==================
   3) Logo sucursal
   ================== */
$sucursalNombre = strtolower(str_replace(' ', '', $device['nom_sucursal'] ?? ''));
$logoPath = "/sisec-ui/public/img/sucursales/$sucursalNombre.png";
$logoAbsolutePath = $_SERVER['DOCUMENT_ROOT'] . $logoPath;
if (!file_exists($logoAbsolutePath)) {
    $logoPath = "/sisec-ui/public/img/sucursales/default.png";
}

/* ==============================
   4) Helpers de presentación
   ============================== */
function quitarAcentosPHP($s) {
  $s = (string)$s;
  $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  return $s ?: '';
}
function normUphp($s) {
  $s = mb_strtoupper(trim((string)$s), 'UTF-8');

  $replacements = [
    'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
    'á'=>'A','é'=>'E','í'=>'I','ó'=>'O','ú'=>'U','ü'=>'U','ñ'=>'N'
  ];

  return strtr($s, $replacements);
}
function fmtDate(?string $s): string {
  if (!$s || $s === '0000-00-00') return '';
  $ts = strtotime($s);
  return $ts ? date('d/m/Y', $ts) : htmlspecialchars($s);
}
function renderInstalacionCell(?string $s): string {
  if (!$s || $s === '0000-00-00') {
    return '<span class="chip off" title="Fecha pendiente">Pendiente</span>';
  }
  return htmlspecialchars(fmtDate($s));
}
function catDesdeEquipo($nomEquipo) {
  $v = normUphp($nomEquipo);
  $v = preg_replace('/[_-]+/',' ', $v);
  $v = preg_replace('/[^A-Z0-9 ]+/',' ', $v);
  $v = preg_replace('/\s+/',' ', $v);

  $map = [
    'switch' => ['SWITCH'],
    'camara' => ['CAMARA','CCTV'],
    'nvr'    => ['NVR'],
    'dvr'    => ['DVR'],
    'servidor' => ['SERVIDOR','SERVER'],
    'monitor'  => ['MONITOR','DISPLAY'],
    'estacion_trabajo' => [
      'ESTACION TRABAJO','ESTACION DE TRABAJO','WORKSTATION','PC','COMPUTADORA'
    ],
  ];

  foreach ($map as $cat => $keys) {
    foreach ($keys as $k) {
      if (strpos($v, $k) !== false) return $cat;
    }
  }

$alarmaKeys = [
  // genérico
  'ALARMA','PANEL','TRANSMISOR','RECEPTOR','EMISOR',

  // sensores
  'SENSOR','DETECTOR','PIR','HUMO','CRISTAL','RUPTURA',
  'CONTACTO','MAGNETICO','CM',

  // botones / pánico
  'BOTON','BTN','PANICO',

  // dispositivos físicos
  'ESTACION','MANUAL','RATONERA','DH','OVERHEAD','OH','DRC',

  // periféricos
  'SIRENA','ESTROBO','TECLADO','ZONA','REP'
];


  foreach ($alarmaKeys as $k) {
    if (strpos($v, $k) !== false) return 'alarma';
  }

  return 'otro';
}
function row($label, $value) {
  if ($value === null) return;
  $v = is_string($value) ? trim($value) : $value;
  if ($v === '' || $v === false) return;
  echo '<tr><th>'.htmlspecialchars($label).'</th><td>'.(is_string($v) ? nl2br(htmlspecialchars($v)) : htmlspecialchars((string)$v)).'</td></tr>';
}
/* Permite imprimir HTML “seguro” ya construido (p.ej. chips o span) */
function rowHtml($label, $html) {
  $html = trim((string)$html);
  echo '<tr><th>'.htmlspecialchars($label).'</th><td>'.$html.'</td></tr>';
}
function badge($value, $fallback='N/D') {
  $v = trim((string)($value ?? ''));
  $txt = $v !== '' ? htmlspecialchars($v) : $fallback;
  $class = $v !== '' ? 'bg-primary' : 'bg-secondary';
  return '<span class="badge '.$class.'">'. $txt .'</span>';
}

/* === Normalizadores visuales (sin tocar BD) === */
function normalizaCctv($v) {
  $v = mb_strtolower(trim((string)$v));
  if ($v === '') return '';
  if (preg_match('/\bip\b/u', $v)) return 'IP';
  if (strpos($v, 'anal') !== false) return 'Analógica';
  return mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1);
}
function normalizaAlarma($v) {
  $v = mb_strtolower(trim((string)$v));
  if ($v === '') return '';
  if (strpos($v, 'inalambr') !== false) return 'Inalámbrico';
  if (strpos($v, 'alambr')   !== false) return 'Alámbrico';
  return mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1);
}
function normalizaSwitch($v) {
  $v = trim((string)$v);
  if ($v === '') return '';
  if (preg_match('/poe/i', $v)) return 'PoE';
  if (preg_match('/plano/i', $v)) return 'Plano';
  return $v;
}

/* === Analíticas: chips HTML (solo cámaras-like) === */
function renderAnaliticasChips($tiene, $lista) {
  $tiene = (int)$tiene;
  $alist = trim((string)$lista);
  if ($tiene && $alist !== '') {
    $chips = array_filter(array_map('trim', explode(',', $alist)));
    $out = '<div class="chips">';
    foreach ($chips as $chip) {
      $out .= '<span class="chip ok" title="Analítica activa"><i class="fa-solid fa-bolt"></i> '.htmlspecialchars($chip).'</span>';
    }
    $out .= '</div>';
    return $out;
  }
  return '<span class="chip off" title="Sin analítica configurada"><i class="fa-regular fa-circle"></i> Sin analítica (configurable)</span>';
}

/* ==========================
   5) Deducción de categorías
   ========================== */
$nomEquipo   = $device['nom_equipo'] ?? '';
$cat         = catDesdeEquipo($nomEquipo);
$esCamaraLike= in_array($cat, ['camara','nvr','dvr','servidor'], true);
$esAlarmaLike= ($cat === 'alarma');
$esSwitch    = ($cat === 'switch');
$esServidor  = ($cat === 'servidor');
$esMonitor   = ($cat === 'monitor');
$esET        = ($cat === 'estacion_trabajo');

/* ==================
   6) Render
   ================== */
ob_start();
?>

<?php
$back = !empty($_GET['return_url'])
  ? $_GET['return_url']
  : '/sisec-ui/views/dispositivos/listar.php';
?>
<!-- <a href="<?= htmlspecialchars($back) ?>" class="btn btn-outline-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Volver al listado
</a> -->

<div style="padding-left: 15px;">
<h2>Ficha técnica</h2>

<div class="text-center mb-3">
  <img src="<?= $logoPath ?>" alt="Logo <?= htmlspecialchars($device['nom_sucursal'] ?? '') ?>" style="max-height: 100px;">
</div>

<div class="row">
  <div class="col-md-4 text-center">
    <?php if (!empty($device['imagen'])): ?>
      <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen']) ?>" 
           alt="Imagen principal" 
           class="img-fluid rounded shadow-sm mb-3 zoomable" 
           style="max-height: 250px; object-fit: scale-down;">
    <?php else: ?>
      <div class="text-muted">Sin imagen principal</div>
    <?php endif; ?>
  </div>

  <div class="col-md-8">
    <!-- ===================== -->
    <!-- A) Datos generales    -->
    <!-- ===================== -->
    <table class="table table-striped table-bordered" style="max-width:900px;">
      <tbody>
        <?php row('Equipo', $device['nom_equipo'] ?? ''); ?>
        <?php rowHtml('Fecha de instalación', renderInstalacionCell($device['fecha_instalacion'] ?? null)); ?>
        <?php row('Fecha de mantenimiento', fmtDate($device['fecha'] ?? '')); ?>
        <?php row('Modelo', $device['num_modelos'] ?? ''); ?>
        <?php row('Estado del equipo', $device['status_equipo'] ?? ''); ?>
        <?php row('Sucursal', $device['nom_sucursal'] ?? ''); ?>
        <?php row('Municipio', $device['nom_municipio'] ?? ''); ?>
        <?php row('Ciudad', $device['nom_ciudad'] ?? ''); ?>
        <?php row('Área de la tienda', $device['area'] ?? ''); ?>
        <?php row('Serie', $device['serie'] ?? ''); ?>
      </tbody>
    </table>

    <!-- ============================== -->
    <!-- B) Sección por tipo de equipo  -->
    <!-- ============================== -->

    <?php if ($esCamaraLike): ?>
      <h5 class="mt-4">Detalles CCTV</h5>
      <table class="table table-sm table-bordered" style="max-width:900px;">
        <tbody>
          <tr>
            <th>Tipo de cámara</th>
            <td><?= badge(normalizaCctv($device['tipo_cctv_label'] ?? '')) ?></td>
          </tr>
          <tr>
            <th>Marca</th>
            <td><?= badge($device['marca_label'] ?? '') ?></td>
          </tr>
          <?php row('MAC', $device['mac'] ?? ''); ?>
          <?php row('VMS', $device['vms'] ?? ''); ?>
          <?php row('Versión VMS', $device['version_vms'] ?? ''); ?>
          <?php row('Servidor', $device['servidor'] ?? ''); ?>
          <?php row('Switch', $device['switch'] ?? ''); ?>
          <?php row('Puerto', $device['puerto'] ?? ''); ?>
          <?php row('IP', $device['ip'] ?? ''); ?>
          <?php row('RC', $device['rc'] ?? ''); ?>

          <?php
            // Analíticas (una sola fila, sin duplicados)
            $analiticasHTML = renderAnaliticasChips($device['tiene_analitica'] ?? 0, $device['analiticas'] ?? '');
            rowHtml('Analítica', $analiticasHTML);
          ?>

          <?php if ($esServidor): ?>
            <?php row('Ubicación RC', $device['ubicacion_rc'] ?? ''); ?>
            <?php row('Windows', $device['version_windows'] ?? ''); ?>
          <?php endif; ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($esAlarmaLike): ?>
      <h5 class="mt-4">Detalles de Alarma</h5>
      <table class="table table-sm table-bordered" style="max-width:900px;">
        <tbody>
          <tr>
            <th>Conexión</th>
            <td><?= badge(normalizaAlarma($device['tipo_alarma_label'] ?? '')) ?></td>
          </tr>
          <tr>
            <th>Marca</th>
            <td><?= badge($device['marca_label'] ?? '') ?></td>
          </tr>
          <?php row('Zona', $device['zona_alarma'] ?? ''); ?>
          <?php row('Tipo de sensor', $device['tipo_sensor'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($esSwitch): ?>
      <h5 class="mt-4">Detalles de Switch</h5>
      <table class="table table-sm table-bordered" style="max-width:900px;">
        <tbody>
          <tr>
            <th>Tipo</th>
            <td><?= badge(normalizaSwitch($device['tipo_switch_label'] ?? '')) ?></td>
          </tr>
          <?php row('Switch', $device['switch'] ?? ''); ?>
          <?php row('Puerto', $device['puerto'] ?? ''); ?>
          <?php row('MAC', $device['mac'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($esMonitor): ?>
      <h5 class="mt-4">Detalles de Monitor</h5>
      <table class="table table-sm table-bordered" style="max-width:900px;">
        <tbody>
          <?php row('Tamaño/Pulgadas', $device['pulgadas'] ?? ''); ?>
          <?php row('Resolución', $device['resolucion'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($esET): ?>
      <h5 class="mt-4">Detalles Estación de Trabajo</h5>
      <table class="table table-sm table-bordered" style="max-width:900px;">
        <tbody>
          <?php row('Windows', $device['version_windows'] ?? ''); ?>
          <?php row('Procesador', $device['cpu'] ?? ''); ?>
          <?php row('RAM', $device['ram'] ?? ''); ?>
          <?php row('Disco', $device['disco'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($cat === 'nvr'): ?>
      <h5 class="mt-4">Detalles NVR</h5>
      <table class="table table-sm table-bordered" style="max-width:900px;">
        <tbody>
          <?php row('Marca', $device['marca_label'] ?? ''); ?>
          <?php row('MAC', $device['mac'] ?? ''); ?>
          <?php row('VMS', $device['vms'] ?? ''); ?>
          <?php row('Versión VMS', $device['version_vms'] ?? ''); ?>
          <?php row('Switch', $device['switch'] ?? ''); ?>
          <?php row('Puerto', $device['puerto'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($cat === 'dvr'): ?>
      <h5 class="mt-4">Detalles DVR</h5>
      <table class="table table-sm table-bordered" style="max-width:900px;">
        <tbody>
          <?php row('Marca', $device['marca_label'] ?? ''); ?>
          <?php row('MAC', $device['mac'] ?? ''); ?>
          <?php row('Switch', $device['switch'] ?? ''); ?>
          <?php row('Puerto', $device['puerto'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    <?php endif; ?>

<!-- =========================== -->
<!-- C) Credenciales (si aplica) -->
<!-- =========================== -->
<?php
$equiposConCredenciales = [
  'camara',
  'nvr',
  'dvr',
  'servidor'
];

$mostrarCred = in_array($cat, $equiposConCredenciales, true);
?>

<?php if ($mostrarCred): ?>
  <h5 class="mt-4">Credenciales</h5>
  <table class="table table-sm table-bordered" style="max-width:900px;">
    <tbody>
      <?php row('Usuario', $device['user'] ?? ''); ?>
      <?php row('Contraseña', $device['pass'] ?? ''); ?>
    </tbody>
  </table>
<?php endif; ?>


    <!-- ===================== -->
    <!-- D) Imágenes y QR      -->
    <!-- ===================== -->
    <table class="table table-sm table-bordered" style="max-width:900px;">
      <tbody>
        <tr>
          <th>Imagen antes</th>
          <td>
            <?php if (!empty($device['imagen2'])): ?>
              <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen2']) ?>" 
                   class="img-fluid rounded shadow-sm mb-2 zoomable" 
                   alt="Imagen antes"
                   style="max-height: 150px; object-fit: scale-down;">
            <?php else: ?>
              <span class="text-muted">No disponible</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Imagen después</th>
          <td>
            <?php if (!empty($device['imagen3'])): ?>
              <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen3']) ?>" 
                   class="img-fluid rounded shadow-sm mb-2 zoomable" 
                   alt="Imagen después"
                   style="max-height: 150px; object-fit: scale-down;">
            <?php else: ?>
              <span class="text-muted">No disponible</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Código QR</th>
          <td>
            <?php if (!empty($device['qr'])): ?>
              <img src="/sisec-ui/public/qrcodes/<?= htmlspecialchars($device['qr']) ?>" width="150" alt="Código QR" class="zoomable">
                   <a class="btn btn-sm btn-outline-primary mt-2" href="/sisec-ui/public/qrcodes/<?= htmlspecialchars($device['qr']) ?>" download>Descargar</a>
            <?php else: ?>
              <span class="text-muted">No disponible</span>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="mt-3 d-flex gap-2">
      <?php
      $returnUrl = 'listar.php';
      if (isset($_GET['return_url'])) {
          $ru = (string)$_GET['return_url'];
          if (strpos($ru, '://') === false) {
              $returnUrl = $ru;
          }
      }
      ?>
      <a href="editar.php?id=<?= (int)$device['id'] ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Editar dispositivo
      </a>
      <a href="exportar_pdf.php?id=<?= (int)$device['id'] ?>" class="btn btn-danger" target="_blank">
        <i class="fas fa-file-pdf"></i> Exportar PDF
      </a>
    </div>

    <!-- Bloque meta -->
    <?php if (!empty($meta)): ?>
      <div class="mt-4 small text-muted">
        <i class="fas fa-user-check me-1"></i>
        Registrado por <strong><?= htmlspecialchars($meta['nombre']) ?></strong>
        (<?= htmlspecialchars($meta['rol']) ?>)
        el <?= date('d/m/Y H:i', strtotime($meta['fecha'])) ?>.
      </div>
    <?php else: ?>
      <div class="mt-4 small text-muted">
        <i class="fas fa-user-check me-1"></i>
        Registrante no disponible.
      </div>
    <?php endif; ?>
  </div>
</div>
    </div>

<!-- ===== Estilos extra chips + Lightbox ===== -->
<style>
  .chips{ display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
  .chip{ display:inline-flex; align-items:center; gap:6px; padding:.2rem .55rem; border-radius:999px; border:1px solid #e6eaf0; background:#fff; font-size:.85rem; }
  .chip i{ opacity:.85; }
  .chip.ok{ background:#e8fff1; border-color:#c8f2da; }
  .chip.off{ background:#f5f5f5; border-color:#e5e7eb; color:#6b7280; }

  .lb-overlay{
    position:fixed; inset:0; background:rgba(0,0,0,.9);
    display:none; align-items:center; justify-content:center;
    z-index: 3000;
  }
  .lb-overlay.open{ display:flex; }
  .lb-stage{
    position:relative; width:90vw; height:90vh;
    display:flex; align-items:center; justify-content:center;
    overflow:hidden; background:transparent;
  }
  .lb-img{
    user-select:none; -webkit-user-drag:none;
    will-change: transform;
    transform: translate(var(--tx,0px), var(--ty,0px)) scale(var(--z,1));
    transition: transform .08s ease-out;
    max-width: none; max-height: none;
    cursor: grab;
  }
  .lb-img:active{ cursor: grabbing; }
  .lb-controls{
    position:absolute; left:50%; bottom:18px; transform:translateX(-50%);
    display:flex; gap:.5rem; flex-wrap:wrap; justify-content:center;
  }
  .lb-btn{
    background:#fff; color:#111; border:0; border-radius:8px; padding:.5rem .75rem;
    font-weight:600; box-shadow:0 6px 16px rgba(0,0,0,.25); cursor:pointer;
  }
  .lb-btn:active{ transform: translateY(1px); }
  .lb-close{
    position:absolute; top:14px; right:14px;
    background:#fff; color:#111; border:0; width:42px; height:42px;
    border-radius:999px; font-size:20px; line-height:42px; text-align:center;
    cursor:pointer; box-shadow:0 6px 16px rgba(0,0,0,.25);
  }
  @media (max-width: 576px){
    .lb-controls{ bottom:12px; }
    .lb-btn{ padding:.45rem .6rem; }
  }
  /* ---------- Título ---------- */
  h2{
    font-weight:800; letter-spacing:.2px; color:var(--ink);
    margin-bottom:.75rem!important;
  }
  h2::after{
    content:""; display:block; width:78px; height:4px; border-radius:99px;
    margin-top:.5rem; background:linear-gradient(90deg,var(--brand),var(--brand-2));
  }
</style>

<div class="lb-overlay" id="lb">
  <div class="lb-stage" id="lbStage" aria-modal="true" role="dialog">
    <img id="lbImg" class="lb-img" alt="Vista ampliada">
    <button class="lb-close" id="lbClose" aria-label="Cerrar">×</button>
    <div class="lb-controls">
      <button class="lb-btn" id="lbZoomIn">+</button>
      <button class="lb-btn" id="lbZoomOut">−</button>
      <button class="lb-btn" id="lbReset">100%</button>
      <button class="lb-btn" id="lbFit">Ajustar</button>
    </div>
  </div>
</div>

<script>
(function(){
  const overlay = document.getElementById('lb');
  const stage   = document.getElementById('lbStage');
  const imgEl   = document.getElementById('lbImg');
  const btnIn   = document.getElementById('lbZoomIn');
  const btnOut  = document.getElementById('lbZoomOut');
  const btnRes  = document.getElementById('lbReset');
  const btnFit  = document.getElementById('lbFit');
  const btnClose= document.getElementById('lbClose');

  let z=1, tx=0, ty=0, isDragging=false, sx=0, sy=0;

  function apply(){
    imgEl.style.setProperty('--z', z);
    imgEl.style.setProperty('--tx', tx+'px');
    imgEl.style.setProperty('--ty', ty+'px');
  }
  function reset(){
    z=1; tx=0; ty=0; apply();
  }
  function fitToStage(){
    const maxW = stage.clientWidth, maxH = stage.clientHeight;
    const naturalW = imgEl.naturalWidth, naturalH = imgEl.naturalHeight;
    if(!naturalW || !naturalH){ reset(); return; }
    const scale = Math.min(maxW/naturalW, maxH/naturalH);
    z = Math.max(scale, 0.1);
    tx=0; ty=0; apply();
  }
  function open(src){
    imgEl.src = src;
    overlay.classList.add('open');
    if(imgEl.complete) { fitToStage(); }
    else { imgEl.onload = fitToStage; }
    document.documentElement.style.overflow = 'hidden';
  }
  function close(){
    overlay.classList.remove('open');
    imgEl.src = '';
    reset();
    document.documentElement.style.overflow = '';
  }
  document.addEventListener('click', (e)=>{
    const t = e.target;
    if(t && t.classList && t.classList.contains('zoomable')){
      e.preventDefault();
      open(t.getAttribute('src'));
    }
  });
  overlay.addEventListener('click', (e)=>{
    if(e.target === overlay) close();
  });
  btnClose.addEventListener('click', close);
  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape' && overlay.classList.contains('open')) close();
  });
  imgEl.addEventListener('mousedown', (e)=>{
    if(z <= 1) return;
    isDragging = true; sx = e.clientX; sy = e.clientY;
    e.preventDefault();
  });
  window.addEventListener('mousemove', (e)=>{
    if(!isDragging) return;
    const dx = e.clientX - sx, dy = e.clientY - sy;
    sx = e.clientX; sy = e.clientY;
    tx += dx; ty += dy; apply();
  });
  window.addEventListener('mouseup', ()=>{ isDragging=false; });
  stage.addEventListener('wheel', (e)=>{
    e.preventDefault();
    const delta = Math.sign(e.deltaY);
    const factor = (delta>0) ? 0.9 : 1.1;
    const prevZ = z;
    z = Math.min(10, Math.max(0.1, z * factor));
    const rect = imgEl.getBoundingClientRect();
    const cx = e.clientX - rect.left - rect.width/2;
    const cy = e.clientY - rect.top  - rect.height/2;
    tx -= cx * (z/prevZ - 1);
    ty -= cy * (z/prevZ - 1);
    apply();
  }, { passive:false });
  btnIn.addEventListener('click', ()=>{ z=Math.min(10, z*1.2); apply(); });
  btnOut.addEventListener('click', ()=>{ z=Math.max(0.1, z/1.2); apply(); });
  btnRes.addEventListener('click', reset);
  btnFit.addEventListener('click', fitToStage);
})();
</script>
<?php
$content = ob_get_clean();
$pageTitle = "Ficha dispositivo " . htmlspecialchars($device['nom_sucursal'] ?? '');
$pageHeader = "Dispositivo " . htmlspecialchars($device['nom_sucursal'] ?? '') . " | " . htmlspecialchars($device['nom_equipo'] ?? '');
$activePage = "";
include __DIR__ . '/../../layout.php';