<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin', 'Administrador', 'Técnico', 'Invitado', 'Mantenimientos']);

require __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../includes/db.php';

use Dompdf\Dompdf;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  die('ID inválido.');
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
    COALESCE(ma.nom_marca, mad.nom_marca) AS marca_label
  FROM dispositivos d
  LEFT JOIN sucursales s ON d.sucursal = s.id
  LEFT JOIN municipios m ON s.municipio_id = m.id
  LEFT JOIN ciudades  c  ON m.ciudad_id = c.id
  LEFT JOIN equipos   eq ON d.equipo = eq.id
  LEFT JOIN modelos   mo ON d.modelo = mo.id
  LEFT JOIN marcas    ma ON mo.marca_id = ma.id_marcas      -- marca desde el modelo
  LEFT JOIN marcas    mad ON d.marca_id = mad.id_marcas      -- fallback marca desde el dispositivo
  LEFT JOIN status    es ON d.estado = es.id
  LEFT JOIN alarma    a  ON a.id  = d.alarma_id
  LEFT JOIN cctv      cc ON cc.id = d.cctv_id
  LEFT JOIN `switch`  sw ON sw.id = d.switch_id
  WHERE d.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$device = $result->fetch_assoc();
if (!$device) {
  die('Dispositivo no encontrado.');
}

/* ==============================
   2) Helpers de presentación
   ============================== */
function safe($v) {
  return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function quitarAcentosPHP($s) {
  $s = (string)$s;
  $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  return $s ?: '';
}
function normUphp($s) {
  return strtoupper(trim(quitarAcentosPHP((string)$s)));
}
function catDesdeEquipo($nomEquipo) {
  $v = preg_replace('/[_-]+/',' ', normUphp($nomEquipo));
  $v = preg_replace('/\s+/',' ', $v);

  $map = [
    'switch' => ['SWITCH'],
    'camara' => ['CAMARA','CCTV'],
    'nvr'    => ['NVR'],
    'dvr'    => ['DVR'],
    'servidor' => ['SERVIDOR','SERVER'],
    'monitor'  => ['MONITOR','DISPLAY'],
    'estacion_trabajo' => ['ESTACION TRABAJO','ESTACION DE TRABAJO','WORKSTATION','PC','COMPUTADORA'],
  ];
  foreach ($map as $cat => $keys) {
    foreach ($keys as $k) {
      if (strpos($v, $k) !== false) return $cat;
    }
  }

  $alarmaKeys = [
    "ALARMA","TRANSMISOR","SENSOR","DETECTOR","HUMO","OVER HEAD","OVERHEAD","ZONA",
    "BOTON","BOTON PANICO","PANICO","ESTACION","PULL STATION","PULL",
    "PANEL","CABLEADO","SIRENA","RECEPTOR","EMISOR","LLAVIN","TECLADO",
    "ESTROBO","CRISTAL","RUPTURA","REPETIDOR","REPETIDORA","DH","PIR","CM","BTN","OH","DRC","REP"
  ];
  foreach ($alarmaKeys as $k) {
    if (strpos($v, normUphp($k)) !== false) return 'alarma';
  }

  return 'otro';
}
function badge($value, $fallback='N/D') {
  $v = trim((string)($value ?? ''));
  $txt = $v !== '' ? safe($v) : $fallback;
  // Clase no afecta en Dompdf, usamos estilos inline simples
  return '<span style="display:inline-block;padding:2px 6px;border-radius:4px;background:#e9f5f8;border:1px solid #cce8ee;font-weight:bold;">'.$txt.'</span>';
}
function row($label, $value) {
  if ($value === null) return;
  $v = is_string($value) ? trim($value) : $value;
  if ($v === '' || $v === false) return;
  echo '<tr><th>'.safe($label).'</th><td>'.(is_string($v) ? nl2br(safe($v)) : safe((string)$v)).'</td></tr>';
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

/* ==========================
   3) Categorías
   ========================== */
$nomEquipo   = $device['nom_equipo'] ?? '';
$cat         = catDesdeEquipo($nomEquipo);
$esCamaraLike= in_array($cat, ['camara','nvr','dvr','servidor'], true);
$esAlarmaLike= ($cat === 'alarma');
$esSwitch    = ($cat === 'switch');
$esServidor  = ($cat === 'servidor');
$esMonitor   = ($cat === 'monitor');
$esET        = ($cat === 'estacion_trabajo');

/* ==========================
   4) Utilidades imagen/base64
   ========================== */
function imagenBase64Public($rutaRelativa) {
  $rutaCompleta = __DIR__ . '/../../public/' . $rutaRelativa;
  if (file_exists($rutaCompleta)) {
    $tipo = pathinfo($rutaCompleta, PATHINFO_EXTENSION);
    $data = file_get_contents($rutaCompleta);
    return 'data:image/' . $tipo . ';base64,' . base64_encode($data);
  }
  return '';
}

/* Logos */
$logoSisec = imagenBase64Public("img/logoCESISS.png");
$nombreSucursal = strtolower(str_replace(' ', '', (string)$device['nom_sucursal']));
$logoSucursal = imagenBase64Public("img/sucursales/{$nombreSucursal}.png");
if (!$logoSucursal) {
  $logoSucursal = imagenBase64Public("img/sucursales/default.png");
}

/* Imágenes dispositivo */
$img1 = !empty($device['imagen'])  ? imagenBase64Public("uploads/" . $device['imagen'])  : '';
$img2 = !empty($device['imagen2']) ? imagenBase64Public("uploads/" . $device['imagen2']) : '';
$img3 = !empty($device['imagen3']) ? imagenBase64Public("uploads/" . $device['imagen3']) : '';
$qr   = !empty($device['qr'])      ? imagenBase64Public("qrcodes/" . $device['qr'])      : '';

/* ==========================
   5) HTML
   ========================== */
ob_start();
?>
<style>
  @page { margin: 26px 26px 32px 26px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1b2b34; }
  h1,h2,h3 { margin: 0; }
  .topband {
    width:100%;
    background: #0a2128;
    color:#e6f2f4;
    padding:10px 14px;
    border-radius:6px;
    margin-bottom:10px;
  }
  .topband .title { font-size: 16px; font-weight: 700; letter-spacing:.3px; }
  .muted { color:#6e8791; }
  .meta { font-size: 10px; margin-top: 3px; }

  .logos { width:100%; margin-top:8px; margin-bottom:8px; }
  .logos td { border:none; }
  .logos img { height: 46px; }

  table.info { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.info th, table.info td { border: 1px solid #cfd8dc; padding: 7px 8px; vertical-align: top; }
  table.info th { background: #eef6f8; width: 35%; font-weight: 700; }
  table.info tr:nth-child(even) td { background: #fafcfd; }

  .section {
    margin-top: 14px;
    border: 1px solid #cfd8dc;
    border-radius: 6px;
    overflow: hidden;
  }
  .section .section-header {
    background: #e9f5f8;
    padding: 8px 10px;
    font-weight: 700;
    color:#0a2128;
    border-bottom:1px solid #cfd8dc;
  }
  .section .section-body { padding: 8px 8px 2px 8px; }

  .badge {
    display:inline-block; padding:2px 6px; border-radius:4px;
    background:#e9f5f8; border:1px solid #cce8ee; font-weight:bold;
  }

  .grid-2 { width:100%; border-collapse: separate; border-spacing: 10px 0; }
  .grid-2 td { width:50%; vertical-align: top; }

  .img-block { text-align:center; margin: 12px 0; }
  .img-block img { display:block; max-width: 92%; max-height: 260px; margin: 8px auto; border: 1px solid #cfd8dc; padding: 5px; border-radius: 6px; }

  .qr { text-align:center; margin-top: 6px; }
  .qr img { width: 120px; border:1px solid #cfd8dc; padding:4px; border-radius:6px; }
</style>

<div class="topband">
  <div class="title">Ficha técnica de dispositivo <?= safe($device['nom_equipo']) ?></div>
  <div class="meta">
    Ubicación: <?= safe($device['nom_ciudad']) ?>, <?= safe($device['nom_municipio']) ?>, <?= safe($device['nom_sucursal']) ?>
  </div>
</div>

<table class="logos">
  <tr>
    <td style="text-align:left;">
      <?php if ($logoSisec): ?><img src="<?= $logoSisec ?>" alt="Logo CESISS"><?php endif; ?>
    </td>
    <td style="text-align:right;">
      <?php if ($logoSucursal): ?><img src="<?= $logoSucursal ?>" alt="Logo Sucursal"><?php endif; ?>
    </td>
  </tr>
</table>

<?php if ($img1): ?>
  <div class="section">
    <div class="section-header">Imagen principal</div>
    <div class="section-body">
      <div class="img-block">
        <img src="<?= $img1 ?>" alt="Imagen principal">
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="section">
  <div class="section-header">Datos generales</div>
  <div class="section-body">
    <table class="info">
      <tbody>
        <?php row('Fecha de instalación', $device['fecha'] ?? ''); ?>
        <?php row('Marca', $device['marca_label'] ?? ''); ?>
        <?php row('Modelo', $device['num_modelos'] ?? ''); ?>
        <?php row('Estado', $device['status_equipo'] ?? ''); ?>
        <?php row('Área', $device['area'] ?? ''); ?>
        <?php row('Serie', $device['serie'] ?? ''); ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($esCamaraLike): ?>
  <div class="section">
    <div class="section-header">Detalles CCTV</div>
    <div class="section-body">
      <table class="info">
        <tbody>
          <tr>
            <th>Tipo de cámara</th>
            <td><?= badge(normalizaCctv($device['tipo_cctv_label'] ?? '')) ?></td>
          </tr>
          <?php row('Marca', $device['marca_label'] ?? ''); ?>
          <?php row('MAC', $device['mac'] ?? ''); ?>
          <?php row('IP', $device['ip'] ?? ''); ?>
          <?php row('VMS', $device['vms'] ?? ''); ?>
          <?php row('Versión VMS', $device['version_vms'] ?? ''); ?>
          <?php row('Servidor', $device['servidor'] ?? ''); ?>
          <?php row('Switch', $device['switch'] ?? ''); ?>
          <?php row('Puerto', $device['puerto'] ?? ''); ?>
          <?php row('Puertos', $device['puertos'] ?? ''); ?>
          <?php row('RC', $device['rc'] ?? ''); ?>
          <?php if ($esServidor): ?>
            <?php row('Windows', $device['version_windows'] ?? ''); ?>
          <?php endif; ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($esAlarmaLike): ?>
  <div class="section">
    <div class="section-header">Detalles de Alarma</div>
    <div class="section-body">
      <table class="info">
        <tbody>
          <tr>
            <th>Conexión</th>
            <td><?= badge(normalizaAlarma($device['tipo_alarma_label'] ?? '')) ?></td>
          </tr>
          <?php row('Marca', $device['marca_label'] ?? ''); ?>
          <?php row('Zona', $device['zona_alarma'] ?? ''); ?>
          <?php row('Tipo de sensor', $device['tipo_sensor'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($esSwitch): ?>
  <div class="section">
    <div class="section-header">Detalles de Switch</div>
    <div class="section-body">
      <table class="info">
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
    </div>
  </div>
<?php endif; ?>

<?php if ($cat === 'nvr'): ?>
  <div class="section">
    <div class="section-header">Detalles NVR</div>
    <div class="section-body">
      <table class="info">
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
    </div>
  </div>
<?php endif; ?>

<?php if ($cat === 'dvr'): ?>
  <div class="section">
    <div class="section-header">Detalles DVR</div>
    <div class="section-body">
      <table class="info">
        <tbody>
          <?php row('Marca', $device['marca_label'] ?? ''); ?>
          <?php row('MAC', $device['mac'] ?? ''); ?>
          <?php row('Switch', $device['switch'] ?? ''); ?>
          <?php row('Puerto', $device['puerto'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($esMonitor): ?>
  <div class="section">
    <div class="section-header">Detalles de Monitor</div>
    <div class="section-body">
      <table class="info">
        <tbody>
          <?php row('Tamaño/Pulgadas', $device['pulgadas'] ?? ''); ?>
          <?php row('Resolución', $device['resolucion'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($esET): ?>
  <div class="section">
    <div class="section-header">Detalles Estación de Trabajo</div>
    <div class="section-body">
      <table class="info">
        <tbody>
          <?php row('Windows', $device['version_windows'] ?? ''); ?>
          <?php row('Procesador', $device['cpu'] ?? ''); ?>
          <?php row('RAM', $device['ram'] ?? ''); ?>
          <?php row('Disco', $device['disco'] ?? ''); ?>
          <?php row('Observaciones', $device['observaciones'] ?? ''); ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($img2 || $img3 || $qr): ?>
  <div class="section">
    <div class="section-header">Visualización de imágenes</div>
    <div class="section-body">
      <table class="grid-2">
        <tr>
          <td>
            <?php if ($img2): ?>
              <div class="img-block">
                <div style="font-weight:bold">Antes</div>
                <img src="<?= $img2 ?>" alt="Antes">
              </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($img3): ?>
              <div class="img-block">
                <div style="font-weight:bold">Después</div>
                <img src="<?= $img3 ?>" alt="Después">
              </div>
            <?php endif; ?>
          </td>
        </tr>
      </table>
      <?php if ($qr): ?>
        <div class="qr">
          <div style="font-weight:bold;margin-top:6px;margin-bottom:4px;">Código QR del dispositivo</div>
          <img src="<?= $qr ?>" alt="QR">
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php
$html = ob_get_clean();

/* ==========================
   6) Render PDF
   ========================== */
$dompdf = new Dompdf([
  'isRemoteEnabled' => true, // por si en algún futuro jalas assets remotos
]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'dispositivo_'.preg_replace('/[^a-z0-9_-]+/i','_', (string)$device['nom_equipo']).'.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
exit;
