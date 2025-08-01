<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos', 'Invitado']);

require __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../includes/db.php';

use Dompdf\Dompdf;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT id, equipo, fecha, modelo, estado, sucursal, area, observaciones, serie, mac, vms, servidor, switch, puerto, imagen 
        FROM dispositivos 
        WHERE 
            equipo LIKE ? OR 
            modelo LIKE ? OR 
            sucursal LIKE ? OR 
            estado LIKE ? OR 
            fecha = ? OR 
            id = ?
        ORDER BY id ASC
    ");
    $likeSearch = "%$search%";
    $stmt->bind_param("sssssi", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT id, equipo, fecha, modelo, estado, sucursal, area, observaciones, serie, mac, vms, servidor, switch, puerto, imagen FROM dispositivos ORDER BY id ASC");
}

$dispositivos = $result->fetch_all(MYSQLI_ASSOC);

function imagenABase64($nombreArchivo) {
    $ruta = __DIR__ . '/../../public/uploads/' . $nombreArchivo;
    if (file_exists($ruta) && $nombreArchivo !== '') {
        $tipo = pathinfo($ruta, PATHINFO_EXTENSION);
        $contenido = file_get_contents($ruta);
        return 'data:image/' . $tipo . ';base64,' . base64_encode($contenido);
    }
    return '';
}

ob_start();
?>

<style>
  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 9px;
  }

  h2 {
    text-align: center;
    margin-bottom: 10px;
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }

  th, td {
    border: 1px solid #000;
    padding: 4px;
    text-align: center;
    vertical-align: middle;
  }

  th {
    background-color: #f2f2f2;
  }

  .img-cell img {
    display: block;
    margin: 0 auto;
    height: 60px; /* tamaño uniforme */
    width: auto;
    object-fit: contain;
    border: 1px solid #ccc;
    padding: 2px;
  }
</style>


<h2>Listado de Dispositivos <?= $search ? "(Filtro: " . htmlspecialchars($search) . ")" : "" ?></h2>
<table>
  <thead>
    <tr>
      <!-- <th>ID</th> -->
      <th>Equipo</th>
      <th>Fecha</th>
      <th>Modelo</th>
      <th>Estado</th>
      <th>Sucursal</th>
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
    <?php foreach ($dispositivos as $d): ?>
      <tr>
        <!-- <td><?= $d['id'] ?></td> -->
        <td><?= htmlspecialchars($d['equipo']) ?></td>
        <td><?= htmlspecialchars($d['fecha']) ?></td>
        <td><?= htmlspecialchars($d['modelo']) ?></td>
        <td><?= htmlspecialchars($d['estado']) ?></td>
        <td><?= htmlspecialchars($d['sucursal']) ?></td>
        <td><?= htmlspecialchars($d['area']) ?></td>
        <td><?= htmlspecialchars($d['observaciones']) ?></td>
        <td><?= htmlspecialchars($d['serie']) ?></td>
        <td><?= htmlspecialchars($d['mac']) ?></td>
        <td><?= htmlspecialchars($d['vms']) ?></td>
        <td><?= htmlspecialchars($d['servidor']) ?></td>
        <td><?= htmlspecialchars($d['switch']) ?></td>
        <td><?= htmlspecialchars($d['puerto']) ?></td>
<td class="img-cell">
  <?php if ($d['imagen']): ?>
    <img src="<?= imagenABase64($d['imagen']) ?>" alt="Imagen">
  <?php endif; ?>
</td>

      </tr>
    <?php endforeach; ?>
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
