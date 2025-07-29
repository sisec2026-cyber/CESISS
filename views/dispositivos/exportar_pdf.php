<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Técnico', 'Invitado']);

require __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../includes/db.php';

use Dompdf\Dompdf;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido.');
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT d.*, 
           s.nom_sucursal, 
           m.nom_municipio, 
           c.nom_ciudad,
           eq.nom_equipo,
           mo.num_modelos,
           es.status_equipo
    FROM dispositivos d
    LEFT JOIN sucursales s ON d.sucursal = s.ID
    LEFT JOIN municipios m ON s.municipio_id = m.ID
    LEFT JOIN ciudades c ON m.ciudad_id = c.ID
    LEFT JOIN equipos eq ON d.equipo = eq.ID
    LEFT JOIN modelos mo ON d.modelo = mo.ID
    LEFT JOIN status es ON d.estado = es.ID
    WHERE d.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$device = $result->fetch_assoc();

if (!$device) {
    die('Dispositivo no encontrado.');
}

// Función para convertir imagen a base64
function imagenBase64($rutaRelativa) {
    $rutaCompleta = __DIR__ . '/../../public/' . $rutaRelativa;
    if (file_exists($rutaCompleta)) {
        $tipo = pathinfo($rutaCompleta, PATHINFO_EXTENSION);
        $data = file_get_contents($rutaCompleta);
        return 'data:image/' . $tipo . ';base64,' . base64_encode($data);
    }
    return '';
}

// Rutas de imágenes
$logoSisec = imagenBase64("img/logo.png");
$nombreSucursal = strtolower(str_replace(' ', '', $device['nom_sucursal']));
$logoSucursal = imagenBase64("img/sucursales/{$nombreSucursal}.png");

$img1 = !empty($device['imagen']) ? imagenBase64("uploads/" . $device['imagen']) : '';
$img2 = !empty($device['imagen2']) ? imagenBase64("uploads/" . $device['imagen2']) : '';
$img3 = !empty($device['imagen3']) ? imagenBase64("uploads/" . $device['imagen3']) : '';
$qr   = !empty($device['qr'])      ? imagenBase64("qrcodes/" . $device['qr'])      : '';

// HTML del PDF
ob_start();
?>

<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
  h1 { text-align: center; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { border: 1px solid #999; padding: 6px; vertical-align: top; }
  .img-block { text-align: center; margin: 20px 0; }
  .img-block img { max-width: 100%; max-height: 300px; margin-bottom: 5px; }
  .logo-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
  }
  .logo-row img {
    height: 60px;
    max-width: 45%;
  }
</style>

<div class="logo-row">
  <?php if ($logoSisec): ?>
    <img src="<?= $logoSisec ?>" alt="Logo SISEC" style="width: 150px;">
  <?php endif; ?>
  <?php if ($logoSucursal): ?>
    <img src="<?= $logoSucursal ?>" alt="Logo <?= htmlspecialchars($device['nom_sucursal']) ?>" style="width: 150px;">
  <?php endif; ?>
</div>

<h1>Ficha técnica del dispositivo</h1>

<?php if ($img1): ?>
  <div class="img-block">
    <strong>Imagen principal</strong><br>
    <img src="<?= $img1 ?>" style="width: 150px;">
  </div>
<?php endif; ?>

<table>
  <tr><th>ID</th><td><?= $device['id'] ?></td></tr>
  <tr><th>Equipo</th><td><?= htmlspecialchars($device['nom_equipo']) ?></td></tr>
  <tr><th>Fecha de instalación</th><td><?= htmlspecialchars($device['fecha']) ?></td></tr>
  <tr><th>Modelo</th><td><?= htmlspecialchars($device['num_modelos']) ?></td></tr>
  <tr><th>Estado</th><td><?= htmlspecialchars($device['status_equipo']) ?></td></tr>
  <tr><th>Sucursal</th><td><?= htmlspecialchars($device['nom_sucursal']) ?></td></tr>
  <tr><th>Municipio</th><td><?= htmlspecialchars($device['nom_municipio']) ?></td></tr>
  <tr><th>Ciudad</th><td><?= htmlspecialchars($device['nom_ciudad']) ?></td></tr>
  <tr><th>Área</th><td><?= htmlspecialchars($device['area']) ?></td></tr>
  <tr><th>Serie</th><td><?= htmlspecialchars($device['serie']) ?></td></tr>
  <tr><th>MAC</th><td><?= htmlspecialchars($device['mac']) ?></td></tr>
  <tr><th>Servidor</th><td><?= htmlspecialchars($device['servidor']) ?></td></tr>
  <tr><th>VMS</th><td><?= htmlspecialchars($device['vms']) ?></td></tr>
  <tr><th>Switch</th><td><?= htmlspecialchars($device['switch']) ?></td></tr>
  <tr><th>Puerto</th><td><?= htmlspecialchars($device['puerto']) ?></td></tr>
  <tr><th>Observaciones</th><td><?= nl2br(htmlspecialchars($device['observaciones'])) ?></td></tr>
</table>

<?php if ($img2 || $img3 || $qr): ?>
  <div class="img-block">
    <?php if ($img2 || $img3): ?>
      <div style="display: flex; justify-content: center; gap: 40px;">
        <?php if ($img2): ?>
          <div style="text-align: center;">
            <strong>Imagen antes</strong><br><br>
            <img src="<?= $img2 ?>" style="max-width: 250px; max-height: 200px;">
          </div>
        <?php endif; ?>
        <?php if ($img3): ?>
          <div style="text-align: center;">
            <strong>Imagen después</strong><br><br>
            <img src="<?= $img3 ?>" style="max-width: 250px; max-height: 200px;">
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($qr): ?>
      <br><br>
      <div style="text-align: center;">
        <strong>Código QR</strong><br>
        <img src="<?= $qr ?>" style="width: 150px;">
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
$html = ob_get_clean();

// Crear PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("dispositivo_{$device['id']}.pdf", ["Attachment" => false]);
exit;
