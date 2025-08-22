<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Capturista','Técnico', 'Distrital','Prevencion','Monitorista','Mantenimientos']);
require __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../includes/db.php';
use Dompdf\Dompdf;

$ciudad = isset($_GET['ciudad']) ? (int) $_GET['ciudad'] : 0;
$municipio = isset($_GET['municipio']) ? (int) $_GET['municipio'] : 0;
$sucursal = isset($_GET['sucursal']) ? (int) $_GET['sucursal'] : 0;
$logoSisec = imagenBase64("img/logo.png");
$nombreSucursal = '';
if ($sucursal > 0) {
  $stmtSucursal = $conn->prepare("SELECT nom_sucursal FROM sucursales WHERE id = ?");
  $stmtSucursal->bind_param("i", $sucursal);
  $stmtSucursal->execute();
  $resSuc = $stmtSucursal->get_result();
  if ($resSuc && $row = $resSuc->fetch_assoc()) {
    $nombreSucursal = strtolower(str_replace(' ', '', $row['nom_sucursal']));
    $nombreSucursalTitulo = $row['nom_sucursal'];
  }
}

$rutaLogoSucursal = "img/sucursales/{$nombreSucursal}.png";
if (!file_exists(__DIR__ . "/../../public/" . $rutaLogoSucursal)) {
  $rutaLogoSucursal = "img/sucursales/default.png";
}
$logoSucursal = imagenBase64($rutaLogoSucursal);
$query = "SELECT d.*,
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
WHERE 1=1";

$params = [];
$types = "";
if ($ciudad > 0) {
  $query .= " AND c.ID = ?";
  $params[] = $ciudad;
  $types .= "i";
}if ($municipio > 0) {
  $query .= " AND m.ID = ?";
  $params[] = $municipio;
  $types .= "i";
}if ($sucursal > 0) {
  $query .= " AND s.ID = ?";
  $params[] = $sucursal;
  $types .= "i";
}

$query .= " ORDER BY d.id ASC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
function imagenABase64($nombreArchivo) {
  $ruta = __DIR__ . '/../../public/uploads/' . $nombreArchivo;
  if (file_exists($ruta) && $nombreArchivo !== '') {
    $tipo = pathinfo($ruta, PATHINFO_EXTENSION);
    $contenido = file_get_contents($ruta);
    return 'data:image/' . $tipo . ';base64,' . base64_encode($contenido);
  }
    return '';
}
function imagenBase64($rutaRelativa) {
  $rutaCompleta = __DIR__ . '/../../public/' . $rutaRelativa;
  if (file_exists($rutaCompleta)) {
    $tipo = pathinfo($rutaCompleta, PATHINFO_EXTENSION);
    $data = file_get_contents($rutaCompleta);
    return 'data:image/' . $tipo . ';base64,' . base64_encode($data);
  }
  return '';
}
ob_start();
?>

<style>
body {
  font-family: DejaVu Sans, sans-serif;
  font-size: 9px;
  margin: 0;
  padding: 5px;
}
h2 {
  text-align: center;
  margin-bottom: 10px;
  font-size: 20px;
}
table {
  width: 100%;
  border-collapse: collapse;
  page-break-inside: auto;
}
thead{
  display: table-header-group;
}
th, td {
  border: 1px solid #000;
  padding: 3px;
  text-align: center;
  vertical-align: middle;
}
th{
  background-color: paleturquoise;
  font-weight: bold;
}
tr{
  page-break-inside: avoid;
  page-break-after: auto;
}
.img-cell img {
  width: 130px;
  height: 100px;
  object-fit: cover;
  display: block;
  margin: auto;
}
</style>

<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 10px;">
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
<h2>Listado de dispositivos sucursal <?= htmlspecialchars($nombreSucursalTitulo ?? '') ?></h2>
<?php
$dispositivos = [];
if ($result && $result->num_rows > 0) {
  $dispositivos = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<table>
  <thead>
    <tr>
      <th>Equipo</th>
      <th>Fecha</th>
      <th>Modelo</th>
      <th>Estado</th>
      <!--th>Sucursal</!--th-->
      <th>Área</th>
      <th>Observaciones</th>
      <th>Serie</th>
      <th>MAC</th>
      <th>VMS</th>
      <th>Servidor</th>
      <th>Switch</th>
      <th>Puerto</th>
      <th>Imagen</th>
    </tr>
  </thead>
  <tbody>
    <?php if (count($dispositivos) === 0): ?>
      <tr><td colspan="13">No se encontraron resultados.</td></tr>
      <?php else: ?>
        <?php foreach ($dispositivos as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['nom_equipo']) ?></td>
            <td><?= htmlspecialchars($d['fecha']) ?></td>
            <td><?= htmlspecialchars($d['num_modelos']) ?></td>
            <td><?= htmlspecialchars($d['status_equipo']) ?></td>
            <!--td--><?= htmlspecialchars($d['nom_sucursal']) ?></td-->
            <td><?= htmlspecialchars($d['area']) ?></td>
            <td><?= htmlspecialchars($d['observaciones']) ?></td>
            <td><?= htmlspecialchars($d['serie']) ?></td>
            <td><?= htmlspecialchars($d['mac']) ?></td>
            <td><?= htmlspecialchars($d['vms']) ?></td>
            <td><?= htmlspecialchars($d['servidor']) ?></td>
            <td><?= htmlspecialchars($d['switch']) ?></td>
            <td><?= htmlspecialchars($d['puerto']) ?></td>
            <td class="img-cell">
              <?php if (!empty($d['imagen'])): ?>
                <img src="<?= imagenABase64($d['imagen']) ?>" alt="Imagen">
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
  </tbody>
</table>
<?php

$html = ob_get_clean();
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("lista_dispositivos.pdf", ["Attachment" => false]);
exit;