<?php
// /sisec-ui/views/qrs/listado_qrs.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Técnico', 'Capturista']);
require_once __DIR__ . '/../../includes/db.php';

$BASE   = '/sisec-ui';
$QR_DIR = __DIR__ . '/../../public/qrcodes'; // FS de imágenes QR
$QR_URL = $BASE . '/public/qrcodes';         // URL pública QR

// Directorio/base para imágenes principales (según indicaste)
$IMG_FS_DIR   = __DIR__ . '/../../public/uploads';
$IMG_BASE_URL = $BASE . '/public/uploads';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
global $conn;

/* ========= Detectar columna de imagen en `dispositivos` ========= */
function detectDeviceImageColumn(mysqli $conn): ?string {
  $candidates = ['foto','imagen','imagen_principal','foto_principal','img','img_path','ruta_imagen'];
  foreach ($candidates as $col) {
    if ($res = $conn->query("SHOW COLUMNS FROM dispositivos LIKE '$col'")) {
      if ($res->fetch_assoc()) return $col;
    }
  }
  return null;
}
$DEVICE_IMG_COL = detectDeviceImageColumn($conn);

/* ========= Filtros (GET) ========= */
$sucursal_id     = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : 0;
$determinante_id = isset($_GET['determinante_id']) ? (int)$_GET['determinante_id'] : 0;
$action          = $_GET['action'] ?? ''; // exportzip
$trimBorders     = 1;
$hasFilter       = ($sucursal_id > 0 || $determinante_id > 0);

/* ========= Catálogos ========= */
$suc_ops = [];
if ($res = $conn->query("SELECT id, nom_sucursal FROM sucursales ORDER BY nom_sucursal ASC")) {
  while ($r = $res->fetch_assoc()) $suc_ops[(int)$r['id']] = $r['nom_sucursal'];
}

$det_ops = [];
if ($res2 = $conn->query("SELECT d.id, d.nom_determinante, s.id AS suc_id, s.nom_sucursal
  FROM determinantes d
  JOIN sucursales s ON s.id = d.sucursal_id
  ORDER BY s.nom_sucursal, d.nom_determinante")) {
  while ($r = $res2->fetch_assoc()) {
    $det_ops[(int)$r['id']] = ['nom_determinante' => $r['nom_determinante'],'suc_id' => (int)$r['suc_id'],'nom_sucursal' => $r['nom_sucursal']];
  }
}

/* ========= Helpers de consulta ========= */
function buildCommonWhere(array &$params, array &$types, int $sucursal_id, int $determinante_id): string {
  $where = "d.qr IS NOT NULL AND d.qr <> ''";
  if ($determinante_id > 0) {
    $where .= " AND d.determinante = ?"; $params[] = $determinante_id; $types[] = 'i';
  } elseif ($sucursal_id > 0) {
    $where .= " AND d.sucursal = ?";     $params[] = $sucursal_id;     $types[] = 'i';
  }
  return $where;
}

function fetchCctvRows(mysqli $conn, int $sucursal_id, int $determinante_id, ?string $imgCol): array {
  $params=[]; $types=[];
  $where = buildCommonWhere($params,$types,$sucursal_id,$determinante_id);
  $where .= " AND NOT (d.alarma_id IS NOT NULL OR (d.zona_alarma IS NOT NULL AND d.zona_alarma <> ''))";
  $imgSelect = $imgCol ? "d.`$imgCol` AS img_path," : "NULL AS img_path,";
  $sql = "SELECT d.id, d.qr, d.servidor, d.area,
           $imgSelect
           s.nom_sucursal, det.nom_determinante,
           e.nom_equipo AS equipo_nombre
    FROM dispositivos d
    LEFT JOIN determinantes det ON det.id = d.determinante
    LEFT JOIN sucursales   s   ON s.id = d.sucursal
    LEFT JOIN equipos      e   ON e.id = d.equipo
    WHERE $where
    ORDER BY s.nom_sucursal ASC, det.nom_determinante ASC, d.id ASC";
  $st = $conn->prepare($sql);
  if (!$st) return [];
  if ($params) { $bind = implode('', $types); $st->bind_param($bind, ...$params); }
  $st->execute(); $res = $st->get_result(); $out=[];
  while ($r=$res->fetch_assoc()) $out[]=$r;
  return $out;
}

function fetchAlarmRows(mysqli $conn, int $sucursal_id, int $determinante_id, ?string $imgCol): array {
  $params=[]; $types=[];
  $where = buildCommonWhere($params,$types,$sucursal_id,$determinante_id);
  $where .= " AND (d.alarma_id IS NOT NULL OR (d.zona_alarma IS NOT NULL AND d.zona_alarma <> ''))";
  $imgSelect = $imgCol ? "d.`$imgCol` AS img_path," : "NULL AS img_path,";
  $sql = "SELECT d.id, d.qr, d.zona_alarma,
           $imgSelect
           s.nom_sucursal, det.nom_determinante,
           e.nom_equipo AS equipo_nombre
    FROM dispositivos d
    LEFT JOIN determinantes det ON det.id = d.determinante
    LEFT JOIN sucursales   s   ON s.id = d.sucursal
    LEFT JOIN equipos      e   ON e.id = d.equipo
    WHERE $where
    ORDER BY s.nom_sucursal ASC, det.nom_determinante ASC, d.id ASC";
  $st = $conn->prepare($sql);
  if (!$st) return [];
  if ($params) { $bind = implode('', $types); $st->bind_param($bind, ...$params); }
  $st->execute(); $res = $st->get_result(); $out=[];
  while ($r=$res->fetch_assoc()) $out[]=$r;
  return $out;
}

/* ========= Helpers de nombres ZIP y recorte PNG ========= */
function fileNameForZipCctv(array $r): string {
  $name = trim((string)($r['equipo_nombre'] ?: 'Equipo'));
  if (!empty($r['servidor'])) $name .= ' - ' . $r['servidor'];
  $safe = preg_replace('/[^A-Za-z0-9._()\- ]+/', '_', $name);
  $safe = preg_replace('/\s+/', ' ', trim($safe));
  return ($safe ?: ('equipo_'.$r['id'])) . '.png';
}
function fileNameForZipAlarma(array $r): string {
  $name = trim((string)($r['equipo_nombre'] ?: 'Equipo'));
  if (!empty($r['zona_alarma'])) $name .= ' - ' . $r['zona_alarma'];
  $safe = preg_replace('/[^A-Za-z0-9._()\- ]+/', '_', $name);
  $safe = preg_replace('/\s+/', ' ', trim($safe));
  return ($safe ?: ('equipo_'.$r['id'])) . '.png';
}
function slugify_filename(string $s): string {
  if (function_exists('iconv')) { $conv = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); if ($conv !== false) $s = $conv; }
  $s = preg_replace('/[^A-Za-z0-9._()\- ]+/', '_', $s);
  $s = preg_replace('/\s+/', ' ', trim($s));
  return $s !== '' ? $s : 'export';
}

/* ========= Recortar bordes QR (GD) ========= */
function crop_png_borders(string $srcPath): ?string {
  if (!function_exists('imagecreatefrompng')) return null;
  $img = @imagecreatefrompng($srcPath);
  if (!$img) return null;
  imagesavealpha($img, true);
  $w = imagesx($img); $h = imagesy($img);
  if ($w <= 0 || $h <= 0) { imagedestroy($img); return null; }

  $is_bg = function($rgba): bool {
    if (isset($rgba['alpha']) && $rgba['alpha'] === 127) return true;
    return ($rgba['red'] >= 250 && $rgba['green'] >= 250 && $rgba['blue'] >= 250);
  };

  // top
  $top = 0;
  for ($y=0; $y<$h; $y++) { $found=false; for ($x=0; $x<$w; $x++) { $rgba = imagecolorsforindex($img, imagecolorat($img,$x,$y)); if (!$is_bg($rgba)) { $found=true; break; } } if ($found) { $top=$y; break; } }
  // bottom
  $bottom = $h-1;
  for ($y=$h-1; $y>=0; $y--) { $found=false; for ($x=0; $x<$w; $x++) { $rgba = imagecolorsforindex($img, imagecolorat($img,$x,$y)); if (!$is_bg($rgba)) { $found=true; break; } } if ($found) { $bottom=$y; break; } }
  // left
  $left = 0;
  for ($x=0; $x<$w; $x++) { $found=false; for ($y=$top; $y<=$bottom; $y++) { $rgba = imagecolorsforindex($img, imagecolorat($img,$x,$y)); if (!$is_bg($rgba)) { $found=true; break; } } if ($found) { $left=$x; break; } }
  // right
  $right = $w-1;
  for ($x=$w-1; $x>=0; $x--) { $found=false; for ($y=$top; $y<=$bottom; $y++) { $rgba = imagecolorsforindex($img, imagecolorat($img,$x,$y)); if (!$is_bg($rgba)) { $found=true; break; } } if ($found) { $right=$x; break; } }
  if ($right <= $left || $bottom <= $top) { ob_start(); imagepng($img); $data = ob_get_clean(); imagedestroy($img); return $data ?: null; }

  $cw = $right - $left + 1; $ch = $bottom - $top + 1;
  $dst = imagecreatetruecolor($cw, $ch);
  imagesavealpha($dst, true); imagealphablending($dst, false);
  $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
  imagefilledrectangle($dst, 0, 0, $cw, $ch, $transparent);
  imagecopy($dst, $img, 0,0, $left,$top, $cw,$ch);
  ob_start(); imagepng($dst); $data = ob_get_clean();
  imagedestroy($dst); imagedestroy($img);
  return $data ?: null;
}

/* ========= Resolver URL de imagen principal (respeta subcarpetas) ========= */
function resolve_device_image_url(?string $imgPath, string $fsDir, string $baseUrl): ?string {
  $p = trim((string)$imgPath);
  if ($p === '') return null;
  if (preg_match('~^https?://~i', $p)) return $p; // URL absoluta

  $p = ltrim(str_replace('\\', '/', $p), '/'); // normalizar
  $candidateFs = $fsDir . '/' . $p;
  if (is_file($candidateFs)) return rtrim($baseUrl, '/') . '/' . $p; // conserva subruta

  $basename = basename($p);
  if ($basename !== '' && is_file($fsDir . '/' . $basename)) return rtrim($baseUrl, '/') . '/' . $basename;

  return rtrim($baseUrl, '/') . '/' . $p; // fallback
}

/* ========= ZIP export ========= */
if ($action === 'exportzip') {
  $zipLabel = "QRs - Todas las sucursales";
  if ($determinante_id > 0 && isset($det_ops[$determinante_id])) {
    $zipLabel = "QRs - ".($det_ops[$determinante_id]['nom_sucursal'] ?? 'Sucursal')." - DET ".($det_ops[$determinante_id]['nom_determinante'] ?? (string)$determinante_id);
  } elseif ($sucursal_id > 0 && isset($suc_ops[$sucursal_id])) {
    $zipLabel = "QRs - ".($suc_ops[$sucursal_id] ?? ('Sucursal_'.$sucursal_id));
  }
  $zipFileName = slugify_filename($zipLabel) . ' - ' . date('Ymd_His') . '.zip';
  $rowsC = fetchCctvRows($conn, $sucursal_id, $determinante_id, $DEVICE_IMG_COL);
  $rowsA = fetchAlarmRows($conn, $sucursal_id, $determinante_id, $DEVICE_IMG_COL);
  $tmpZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qrs_' . uniqid('', true) . '.zip';
  $zip = new ZipArchive();
  if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No se pudo crear el ZIP.'; exit;
  }

  foreach ($rowsC as $r) {
    $qr = (string)$r['qr']; if(!$qr) continue;
    $src = rtrim($QR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $qr;
    if(!is_file($src)) continue;
    $fname = fileNameForZipCctv($r);
    $added = false;
    if ($trimBorders) { $data = crop_png_borders($src); if ($data !== null) { $zip->addFromString($fname, $data); $added = true; } }
    if (!$added) { $zip->addFile($src, $fname); }
  }
  foreach ($rowsA as $r) {
    $qr = (string)$r['qr']; if(!$qr) continue;
    $src = rtrim($QR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $qr;
    if(!is_file($src)) continue;
    $fname = fileNameForZipAlarma($r);
    $added = false;
    if ($trimBorders) { $data = crop_png_borders($src); if ($data !== null) { $zip->addFromString($fname, $data); $added = true; } }
    if (!$added) { $zip->addFile($src, $fname); }
  }
  $zip->close();

  while (ob_get_level() > 0) { ob_end_clean(); }
  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="'.$zipFileName.'"');
  header('Content-Length: '.filesize($tmpZip));
  header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
  readfile($tmpZip); @unlink($tmpZip); exit;
}

/* ========= VISTA ========= */
require_once __DIR__ . '/../../includes/head.php';

$cctvRows  = $hasFilter ? fetchCctvRows($conn, $sucursal_id, $determinante_id, $DEVICE_IMG_COL) : [];
$alarmRows = $hasFilter ? fetchAlarmRows($conn, $sucursal_id, $determinante_id, $DEVICE_IMG_COL) : [];
$summary = '';
if ($determinante_id > 0 && isset($det_ops[$determinante_id])) {
  $summary = 'Determinante: #' . h($determinante_id) . ' · Sucursal: ' . h($det_ops[$determinante_id]['nom_sucursal']);
} elseif ($sucursal_id > 0 && isset($suc_ops[$sucursal_id])) {
  $detTxt = '';
  foreach ($det_ops as $did => $info) { if ($info['suc_id'] === $sucursal_id) { $detTxt = ' (#' . h($info['nom_determinante']) . ')'; break; } }
  $summary = 'Sucursal: ' . h($suc_ops[$sucursal_id]) . $detTxt;
}

ob_start();
?>
<style>
  :root{--brand:#3C92A6; --brand-2:#24A3C1; --ink:#10343b; --muted:#486973; --bg:#F7FBFD;--surface:#FFFFFF; --border:#DDEEF3; --border-strong:#BFE2EB;--chip:#EAF7FB; --ring:0 0 0 .22rem rgba(36,163,193,.25); --ring-strong:0 0 0 .28rem rgba(36,163,193,.33);--shadow-sm:0 6px 18px rgba(20,78,90,.08);--radius-xl:1rem; --radius-2xl:1.25rem;}
  h2{ font-weight:800; letter-spacing:.2px; color:var(--ink); margin-bottom:.75rem!important; }
  h2::after{ content:""; display:block; width:78px; height:4px; border-radius:99px; margin-top:.5rem;background:linear-gradient(90deg,var(--brand),var(--brand-2)); }
  #sugerencia{ margin-bottom:1rem;background:linear-gradient(90deg, rgba(36,163,193,.08), rgba(60,146,166,.06));padding:12px 14px; border-left:6px solid var(--brand); border-radius:.9rem; color:#134954;}
  .btn-outline-primary{ --bs-btn-color: var(--ink); --bs-btn-border-color: var(--brand); --bs-btn-hover-color:#fff;--bs-btn-hover-bg:var(--brand); --bs-btn-hover-border-color:var(--brand); --bs-btn-active-bg:var(--brand);--bs-btn-active-border-color:var(--brand); border-width:1px; border-radius:999px; font-weight:800; }
</style>
<?php $activePage = 'listado_qr'; ?>
<div style="padding-left: 25px;">
  <h2 class="mb-3">Listado de QR's</h2>
  <!-- Filtros -->
  <form class="row g-2 align-items-end mb-3" method="get" action="">
    <div class="col-md-5">
      <label class="form-label mb-1">Sucursal</label>
      <input class="form-control" list="dl_sucursales" id="suc_text" placeholder="Escribe para buscar…" autocomplete="off" value="<?= $sucursal_id>0 && isset($suc_ops[$sucursal_id]) ? h($suc_ops[$sucursal_id]) : '' ?>">
      <datalist id="dl_sucursales">
        <?php foreach ($suc_ops as $sid => $name): ?>
          <option data-id="<?= (int)$sid ?>" value="<?= h($name) ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <input type="hidden" name="sucursal_id" id="sucursal_id" value="<?= (int)$sucursal_id ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label mb-1">Determinante</label>
      <input class="form-control" list="dl_determinantes" id="det_text" placeholder="Ej. 623" autocomplete="off" value="<?= $determinante_id>0 && isset($det_ops[$determinante_id]) ? h($det_ops[$determinante_id]['nom_determinante']) : '' ?>">
      <datalist id="dl_determinantes">
        <?php foreach ($det_ops as $did => $info): ?>
          <option data-id="<?= (int)$did ?>" data-sid="<?= (int)$info['suc_id'] ?>" value="<?= h($info['nom_determinante']) ?>" label="<?= h($info['nom_sucursal']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <input type="hidden" name="determinante_id" id="determinante_id" value="<?= (int)$determinante_id ?>">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button id="btnBuscar" class="btn btn-primary flex-fill" type="submit" <?= $hasFilter ? '' : 'disabled' ?>>Buscar</button>
      <a class="btn btn-outline-secondary" href="<?= h($_SERVER['PHP_SELF']) ?>">Limpiar</a>
    </div>
  </form>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-primary <?= $hasFilter ? '' : 'disabled' ?>"
       <?= $hasFilter ? '' : 'tabindex="-1" aria-disabled="true"' ?> href="<?= $hasFilter ? ('?action=exportzip&sucursal_id='.(int)$sucursal_id.'&determinante_id='.(int)$determinante_id) : '#' ?>">Descargar ZIP de QR's
    </a>
  </div>
  <?php if ($hasFilter && $summary): ?>
  <div class="alert alert-info py-2 mb-3"><strong><?= $summary ?></strong></div>
  <?php else: ?>
  <div id="sugerencia">Selecciona una <strong>Sucursal</strong> o una <strong>Determinante</strong> y luego pulsa <strong>Buscar</strong> para visualizar los QRs.</div>
  <?php endif; ?>
  <?php if ($hasFilter): ?>
  <!-- CCTV -->
  <div class="d-flex justify-content-between align-items-center mt-3">
    <h2 class="mb-2">CCTV</h2>
  </div>
  <table id="cctvTable" class="table table-bordered table-striped align-middle display nowrap" style="width:100%">
    <thead>
      <tr>
        <th>Imagen</th>
        <th>Equipo</th>
        <th>Servidor</th>
        <th>QR</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($cctvRows as $row):
        $qrPath  = $QR_DIR . '/' . $row['qr'];
        $qrUrl   = $QR_URL . '/' . rawurlencode($row['qr']);
        $equipo  = (string)($row['equipo_nombre'] ?: ($row['servidor'] ?: $row['area'] ?: 'Equipo'));
        $servidor= trim((string)($row['servidor'] ?? '')) !== '' ? (string)$row['servidor'] : '—';
        $imgUrl  = resolve_device_image_url($row['img_path'] ?? null, $IMG_FS_DIR, $IMG_BASE_URL);
      ?>
      <tr>
        <td>
          <?php if ($imgUrl): ?>
            <img src="<?= h($imgUrl) ?>" alt="Imagen dispositivo" width="80" class="rounded js-thumb" data-full="<?= h($imgUrl) ?>" style="object-fit:cover;cursor:zoom-in;">
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td><?= h($equipo) ?></td>
        <td><?= h($servidor) ?></td>
        <td>
          <?php if (is_file($qrPath) && !empty($row['qr'])): ?>
            <div class="d-flex flex-column align-items-center gap-1">
              <img src="<?= h($qrUrl) ?>" width="80" alt="QR">
              <?php $downloadName = fileNameForZipCctv($row); ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= h($qrUrl) ?>" download="<?= h($downloadName) ?>">Descargar</a>
            </div>
          <?php else: ?>
            <span class="text-muted">No existe QR</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <!-- ALARMAS -->
  <div class="d-flex justify-content-between align-items-center mt-4">
    <h2 class="mb-2">Alarmas</h2>
  </div>
  <table id="alarmasTable" class="table table-bordered table-striped align-middle display nowrap" style="width:100%">
    <thead>
      <tr>
        <th>Imagen</th>
        <th>Equipo</th>
        <th>Zona</th>
        <th>QR</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($alarmRows as $row):
        $qrPath = $QR_DIR . '/' . $row['qr'];
        $qrUrl  = $QR_URL . '/' . rawurlencode($row['qr']);
        $equipo = (string)($row['equipo_nombre'] ?: 'Equipo');
        $zona   = trim((string)($row['zona_alarma'] ?? '')) !== '' ? (string)$row['zona_alarma'] : '—';
        $imgUrl = resolve_device_image_url($row['img_path'] ?? null, $IMG_FS_DIR, $IMG_BASE_URL);
      ?>
      <tr>
        <td>
          <?php if ($imgUrl): ?>
            <img src="<?= h($imgUrl) ?>" alt="Imagen dispositivo" width="80" class="rounded js-thumb" data-full="<?= h($imgUrl) ?>" style="object-fit:cover;cursor:zoom-in;">
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td><?= h($equipo) ?></td>
        <td><?= h($zona) ?></td>
        <td>
          <?php if (is_file($qrPath) && !empty($row['qr'])): ?>
            <div class="d-flex flex-column align-items-center gap-1">
              <img src="<?= h($qrUrl) ?>" width="80" alt="QR">
              <?php $downloadName = fileNameForZipAlarma($row); ?>
                <a class="btn btn-sm btn-outline-primary"href="<?= h($qrUrl) ?>" download="<?= h($downloadName) ?>">Descargar</a>
            </div>
          <?php else: ?>
            <span class="text-muted">No existe QR</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Modal Lightbox (Bootstrap 5) -->
<div class="modal fade" id="imgLightbox" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-dark border-0">
      <div class="modal-body p-0 position-relative">
        <button type="button" class="btn btn-light position-absolute" style="top:.75rem; right:.75rem;" data-bs-dismiss="modal" aria-label="Cerrar">×</button>
        <img id="imgLightboxTarget" src="" alt="Imagen" style="width:100%; height:auto; display:block; max-height:85vh; object-fit:contain;">
      </div>
    </div>
  </div>
</div>

<script>
// Datalist → hidden ids + botón Buscar habilitado
const dlSuc  = document.getElementById('dl_sucursales');
const dlDet  = document.getElementById('dl_determinantes');
const inSuc  = document.getElementById('suc_text');
const inDet  = document.getElementById('det_text');
const hidSuc = document.getElementById('sucursal_id');
const hidDet = document.getElementById('determinante_id');
const btnBuscar = document.getElementById('btnBuscar');

function resolveIdFromDatalist(input, datalist){
  const val = (input.value || '').trim();
  for(const opt of datalist.options){
    if((opt.value||'').trim() === val) return opt.getAttribute('data-id') || '';
  }
  return '';
}
function updateBtnState(){
  const hasFilter = (parseInt(hidSuc.value||'0',10) > 0) || (parseInt(hidDet.value||'0',10) > 0);
  btnBuscar.disabled = !hasFilter;
}
inSuc.addEventListener('change', ()=>{
  hidSuc.value = resolveIdFromDatalist(inSuc, dlSuc);
  if (hidSuc.value) { inDet.value = ''; hidDet.value = ''; }
  updateBtnState();
});
inDet.addEventListener('change', ()=>{
  hidDet.value = resolveIdFromDatalist(inDet, dlDet);
  if (hidDet.value) { inSuc.value = ''; hidSuc.value = ''; }
  updateBtnState();
});
updateBtnState();

// Lightbox: abre modal con la imagen completa
document.addEventListener('click', (e) => {
  const thumb = e.target.closest('.js-thumb');
  if (!thumb) return;
  const fullSrc = thumb.getAttribute('data-full');
  if (!fullSrc) return;

  const img = document.getElementById('imgLightboxTarget');
  img.src = fullSrc;

  // Bootstrap 5 Modal
  const modal = new bootstrap.Modal(document.getElementById('imgLightbox'), { keyboard: true, focus: true });
  modal.show();
});
</script>

<!-- DataTables + Responsive -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php if ($hasFilter): ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script>
$(function(){
  const es = { url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" };
  $('#cctvTable').DataTable({
    pageLength:10, lengthChange:false, autoWidth:false,
    language: es, responsive: true, deferRender: true, order: [[1,'asc']]
  });
  $('#alarmasTable').DataTable({
    pageLength:10, lengthChange:false, autoWidth:false,
    language: es, responsive: true, deferRender: true, order: [[1,'asc']]
  });
});
</script>
<?php endif; ?>
<?php
$content    = ob_get_clean();
$pageTitle  = "Listado de QR's";
$pageHeader = "QRs";
$activePage = "qrs";
include __DIR__ . '/../../layout.php';