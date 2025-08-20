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
$stmt = $conn->prepare("SELECT d.*,
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
WHERE d.id = ?");

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
$logoSisec = imagenBase64("img/logoCESISS.jpeg");
$nombreSucursal = strtolower(str_replace(' ', '', $device['nom_sucursal']));
$logoSucursal = imagenBase64("img/sucursales/default.png");
$img1 = !empty($device['imagen']) ? imagenBase64("uploads/" . $device['imagen']) : '';
$img2 = !empty($device['imagen2']) ? imagenBase64("uploads/" . $device['imagen2']) : '';
$img3 = !empty($device['imagen3']) ? imagenBase64("uploads/" . $device['imagen3']) : '';
$qr   = !empty($device['qr'])      ? imagenBase64("qrcodes/" . $device['qr'])      : '';
// HTML del PDF
ob_start();
?>

<style>
body {
  font-family: DejaVu Sans, sans-serif;
  font-size: 15px;
  color: #333;
}
h1 {
  text-align: center;
  color: #2c3e50;
  font-size: 30px;
  margin-bottom: 10px;
  text-transform: uppercase;
}
.logo-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  border-bottom: 1px solid #ccc;
  padding-bottom: 10px;
}
.logo-row img {
  height: 50px;
  max-width: 40%;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1px;
}
th {
  background-color: #f5f5f5;
  text-align: left;
  padding: 8px;
  font-weight: bold;
  border: 1px solid #ccc;
}
td {
  padding: 8px;
  border: 1px solid #ccc;
  vertical-align: top;
}
tr:nth-child(even) {
  background-color: #f9f9f9;
}
.img-block {
  text-align: center;
  margin: 25px 0;
}
.img-block img,
.image-pair img {
  display: block;
  max-width: 90%;
  max-height: 250px;
  margin: 10px auto;
  border: 1px solid #ccc;
  padding: 5px;
}
.section-title {
  font-weight: bold;
  margin-top: 1px;
  margin-bottom: 1px;
  font-size: 20px;
  text-align: center;
  color: #34495e;
}
</style>

<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 1px;">
  <tr>
    <td style="text-align: left; width: 50%; border: none;">
      <?php if ($logoSisec): ?>
        <img src="<?= $logoSisec ?>" alt="Logo SISEC" style="height: 50px;">
      <?php endif; ?>
    </td>
    <td style="text-align: right; width: 50%; border: none;">
      <?php if ($logoSucursal): ?>
        <img src="<?= $logoSucursal ?>" alt="Logo Sucursal" style="height: 50px;">
        <?php endif; ?>
    </td>
  </tr>
</table>
<h1 style="font-size:20px;">Ficha técnica de dispositivo <?= htmlspecialchars($device['nom_equipo']) ?></h1>
<h1 style="font-size:10px;">Ubicación: <?= htmlspecialchars($device['nom_ciudad'])?>, <?= htmlspecialchars($device['nom_municipio']) ?>, <?= htmlspecialchars($device['nom_sucursal']) ?></h1>
<?php if ($img1): ?>
  <div class="img-block">
    <div class="section-title">Imagen principal</div>
    <img src="<?= $img1 ?>">
  </div>
<?php endif; ?>

<table>
  <!-- <tr><th>ID</th><td><?= $device['id'] ?></td></tr> -->
  <!--tr><th>Equipo</th><td><?= htmlspecialchars($device['nom_equipo']) ?></td></tr-->
  <tr><th>Fecha de instalación</th><td><?= htmlspecialchars($device['fecha']) ?></td></tr>
  <tr><th>Modelo</th><td><?= htmlspecialchars($device['num_modelos']) ?></td></tr>
  <tr><th>Estado</th><td><?= htmlspecialchars($device['status_equipo']) ?></td></tr>
  <tr><th>Área</th><td><?= htmlspecialchars($device['area']) ?></td></tr>
  <tr><th>Serie</th><td><?= htmlspecialchars($device['serie']) ?></td></tr>
  <tr><th>MAC</th><td><?= htmlspecialchars($device['mac']) ?></td></tr>
  <tr><th>Servidor</th><td><?= htmlspecialchars($device['servidor']) ?></td></tr>
  <tr><th>VMS</th><td><?= htmlspecialchars($device['vms']) ?></td></tr>
  <tr><th>Switch</th><td><?= htmlspecialchars($device['switch']) ?></td></tr>
  <tr><th>Puerto</th><td><?= htmlspecialchars($device['puerto']) ?></td></tr>
  <tr><th>Usuario</th><td><?= htmlspecialchars($device['user']) ?></td></tr>
  <tr><th>Contraseña</th><td><?= htmlspecialchars($device['pass']) ?></td></tr>
  <tr><th>Observaciones</th><td><?= nl2br(htmlspecialchars($device['observaciones'])) ?></td></tr>
</table>

<?php if ($img2 || $img3 || $qr): ?>
  <div class="img-block">
    <?php if ($img2 || $img3): ?>
      <div class="section-title">Visualización de imágenes</div>
      <div class="image-pair">
        <?php if ($img2): ?>
          <div>
            <div><strong>Antes</strong></div>
            <img src="<?= $img2 ?>">
          </div>
        <?php endif; ?>
        <?php if ($img3): ?>
          <div>
            <div><strong>Después</strong></div>
            <img src="<?= $img3 ?>">
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($qr): ?>
      <div class="section-title">Código QR del dispositivo</div>
      <img src="<?= $qr ?>" style="width: 130px;">
      <?php endif; ?>
  </div>
<?php endif; ?>
</div>

<?php
$html = ob_get_clean();
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("dispositivo_{$device['nom_equipo']}.pdf", ["Attachment" => false]);
exit;