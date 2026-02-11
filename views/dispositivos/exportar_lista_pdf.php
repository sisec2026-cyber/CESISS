<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador', 'Capturista','Técnico', 'Distrital','Prevencion','Monitorista','Mantenimientos']);

require __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../includes/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ===== Primer auxilio ===== */
@ini_set('memory_limit', '1024M');       // sube si aún falta
@ini_set('max_execution_time', '180');   // 3 minutos

/* ===== Parámetros ===== */
$ciudad    = isset($_GET['ciudad']) ? (int) $_GET['ciudad'] : 0;
$municipio = isset($_GET['municipio']) ? (int) $_GET['municipio'] : 0;
$sucursal  = isset($_GET['sucursal']) ? (int) $_GET['sucursal'] : 0;

/* ===== Datos sucursal (solo texto, sin logos por ahora) ===== */
$nombreSucursalTitulo = '';
if ($sucursal > 0) {
  $stmtSucursal = $conn->prepare("SELECT nom_sucursal FROM sucursales WHERE id = ?");
  $stmtSucursal->bind_param("i", $sucursal);
  $stmtSucursal->execute();
  $resSuc = $stmtSucursal->get_result();
  if ($resSuc && $row = $resSuc->fetch_assoc()) {
    $nombreSucursalTitulo = $row['nom_sucursal'];
  }
  $stmtSucursal->close();
}

/* ===== Consulta ===== */
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
$types  = "";

if ($ciudad > 0)    { $query .= " AND c.ID = ?"; $params[] = $ciudad;    $types .= "i"; }
if ($municipio > 0) { $query .= " AND m.ID = ?"; $params[] = $municipio; $types .= "i"; }
if ($sucursal > 0)  { $query .= " AND s.ID = ?"; $params[] = $sucursal;  $types .= "i"; }

$query .= " ORDER BY d.id ASC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

/* ===== Heurística: sin imágenes y tope de filas ===== */
$TOPE_FILAS = 200;           // súbelo gradualmente si ya te funciona
$contador   = 0;

/* ===== Armado HTML compacto ===== */
ob_start();
?>
<style>
  @page { margin: 10px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; padding: 0; }
  h2 { text-align: center; margin: 6px 0 8px; font-size: 16px; }
  table { width: 100%; border-collapse: collapse; }
  thead { display: table-header-group; }
  th, td { border: 1px solid #000; padding: 3px; text-align: center; vertical-align: middle; }
  th { background: #dff5f5; font-weight: bold; }
  tr { page-break-inside: avoid; }
</style>

<h2>Listado de dispositivos <?= htmlspecialchars($nombreSucursalTitulo ?: '') ?></h2>

<table>
  <thead>
    <tr>
      <th>Equipo</th>
      <th>Fecha mant.</th>
      <th>Modelo</th>
      <th>Status</th>
      <th>Área</th>
      <th>Observaciones</th>
      <th>Serie</th>
      <th>MAC</th>
      <th>VMS</th>
      <th>Servidor</th>
      <th>Switch</th>
      <th>Puerto</th>
      <!--th>Imagen</th-->
    </tr>
  </thead>
  <tbody>
<?php if (!$result || $result->num_rows === 0): ?>
    <tr><td colspan="13">No se encontraron resultados.</td></tr>
<?php else: ?>
  <?php while ($d = $result->fetch_assoc()): $contador++; if ($contador > $TOPE_FILAS) break; ?>
    <tr>
      <td><?= htmlspecialchars($d['nom_equipo'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['fecha'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['num_modelos'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['status_equipo'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['area'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['observaciones'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['serie'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['mac'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['vms'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['servidor'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['switch'] ?? '') ?></td>
      <td><?= htmlspecialchars($d['puerto'] ?? '') ?></td>
      <!--td>< imágenes desactivadas para reducir memoria ></td-->
    </tr>
  <?php endwhile; ?>
<?php endif; ?>
  </tbody>
</table>
<?php
$html = ob_get_clean();

/* ===== Dompdf con opciones para bajar memoria ===== */
$tmp = __DIR__ . '/../../storage/dompdf_tmp';
if (!is_dir($tmp)) @mkdir($tmp, 0777, true);

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', false);   // CLAVE: evita masterminds/html5
$options->set('dpi', 72);
$options->set('tempDir', $tmp);
// $options->set('defaultFont', 'Helvetica');    // si no necesitas unicode completo

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("lista_dispositivos.pdf", ["Attachment" => false]);
exit;
