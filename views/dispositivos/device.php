<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Mantenimientos', 'Invitado', 'Capturista', 'Prevencion']);

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
      /* Marca: preferimos la que venga por el modelo; si no, la del dispositivo */
      COALESCE(ma.nom_marca, mad.nom_marca) AS marca_label
    FROM dispositivos d
    LEFT JOIN sucursales s ON d.sucursal = s.id
    LEFT JOIN municipios m ON s.municipio_id = m.id
    LEFT JOIN ciudades c   ON m.ciudad_id = c.id
    LEFT JOIN equipos eq   ON d.equipo = eq.id
    LEFT JOIN modelos mo   ON d.modelo = mo.id
    LEFT JOIN marcas  ma   ON mo.marca_id = ma.id_marcas        -- <<< PK real
    LEFT JOIN marcas  mad  ON d.marca_id = mad.id_marcas         -- por si la marca viene directo en el dispositivo
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
function row($label, $value) {
  if ($value === null) return;
  $v = is_string($value) ? trim($value) : $value;
  if ($v === '' || $v === false) return;
  echo '<tr><th>'.htmlspecialchars($label).'</th><td>'.(is_string($v) ? nl2br(htmlspecialchars($v)) : htmlspecialchars((string)$v)).'</td></tr>';
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
<a href="<?= htmlspecialchars($back) ?>" class="btn btn-outline-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Volver al listado
</a>

<h2>Ficha técnica</h2>

<div class="text-center mb-3">
  <img src="<?= $logoPath ?>" alt="Logo <?= htmlspecialchars($device['nom_sucursal'] ?? '') ?>" style="max-height: 100px;">
</div>

<div class="row">
  <div class="col-md-4 text-center">
    <?php if (!empty($device['imagen'])): ?>
      <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen']) ?>" 
           alt="Imagen principal" 
           class="img-fluid rounded shadow-sm mb-3" 
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
        <?php row('Fecha de instalación', $device['fecha'] ?? ''); ?>
        <!-- <?php row('Marca', $device['marca_label'] ?? ''); ?> -->
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
    <?php $mostrarCred = !($esAlarmaLike || $esSwitch); ?>
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
                   class="img-fluid rounded shadow-sm mb-2" 
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
                   class="img-fluid rounded shadow-sm mb-2" 
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
              <img src="/sisec-ui/public/qrcodes/<?= htmlspecialchars($device['qr']) ?>" 
                   width="150" alt="Código QR">
            <?php else: ?>
              <span class="text-muted">No disponible</span>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="mt-3 d-flex gap-2">
      <?php
      // returnUrl seguro (solo relativo)
      $returnUrl = 'listar.php';
      if (isset($_GET['return_url'])) {
          $ru = (string)$_GET['return_url'];
          if (strpos($ru, '://') === false) {
              $returnUrl = $ru;
          }
      }
      ?>
      <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver al listado
      </a>
      <a href="exportar_pdf.php?id=<?= (int)$device['id'] ?>" class="btn btn-danger" target="_blank">
        <i class="fas fa-file-pdf"></i> Exportar PDF
      </a>
    </div>

    <!-- Bloque meta: quién lo registró y cuándo -->
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

<?php
$content = ob_get_clean();
$pageTitle = "Ficha dispositivo " . htmlspecialchars($device['nom_sucursal'] ?? '');
$pageHeader = "Dispositivo " . htmlspecialchars($device['nom_sucursal'] ?? '') . " | " . htmlspecialchars($device['nom_equipo'] ?? '');
$activePage = "";
include __DIR__ . '/../../layout.php';
