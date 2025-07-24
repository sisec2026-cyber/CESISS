<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos', 'Invitado']);

include __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido o no especificado.');
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$device = $result->fetch_assoc();

if (!$device) {
    die('Dispositivo no encontrado.');
}

// Preparar ruta del logo de sucursal
$sucursal = strtolower(str_replace(' ', '', $device['sucursal']));
$logoPath = "/sisec-ui/public/img/sucursales/$sucursal.png";
$logoAbsolutePath = $_SERVER['DOCUMENT_ROOT'] . $logoPath;

// Si no existe el logo, usar default
if (!file_exists($logoAbsolutePath)) {
    $logoPath = "/sisec-ui/public/img/sucursales/default.png";
}

ob_start();
?>

<h2>Ficha técnica</h2>

<!-- Mostrar logo de la sucursal -->
<div class="text-center mb-3">
  <img src="<?= $logoPath ?>" alt="Logo Sucursal <?= htmlspecialchars($device['sucursal']) ?>" 
    style="max-height: 100px;">
</div>

<div class="row">
  <!-- Columna izquierda: imagen principal -->
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

  <!-- Columna derecha: datos -->
  <div class="col-md-8">
    <table class="table table-striped table-bordered">
      <tbody>
        <tr><th>Equipo</th><td><?= htmlspecialchars($device['equipo']) ?></td></tr>
        <tr><th>Fecha de instalación</th><td><?= htmlspecialchars($device['fecha']) ?></td></tr>
        <tr><th>Modelo</th><td><?= htmlspecialchars($device['modelo']) ?></td></tr>
        <tr><th>Estado del equipo</th><td><?= htmlspecialchars($device['estado']) ?></td></tr>
        <tr><th>Ubicación del equipo</th><td><?= htmlspecialchars($device['sucursal']) ?></td></tr>
        <tr><th>Área de la tienda</th><td><?= htmlspecialchars($device['area']) ?></td></tr>
        <tr><th>Serie</th><td><?= htmlspecialchars($device['serie']) ?></td></tr>
        <tr><th>Dirección MAC</th><td><?= htmlspecialchars($device['mac']) ?></td></tr>
        <tr><th>VMS</th><td><?= htmlspecialchars($device['vms']) ?></td></tr>
        <tr><th>Servidor</th><td><?= htmlspecialchars($device['servidor']) ?></td></tr>
        <tr><th>Switch</th><td><?= htmlspecialchars($device['switch']) ?></td></tr>
        <tr><th>Puerto</th><td><?= htmlspecialchars($device['puerto']) ?></td></tr>
        <tr><th>Observaciones</th><td><?= nl2br(htmlspecialchars($device['observaciones'])) ?></td></tr>
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

    <!-- Botones -->
    <div class="mt-3 d-flex gap-2">
      <a href="listar.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver al listado
      </a>
      <a href="exportar_pdf.php?id=<?= $device['id'] ?>" class="btn btn-danger" target="_blank">
        <i class="fas fa-file-pdf"></i> Exportar PDF
      </a>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = "Ficha dispositivo #$id";
$pageHeader = "Dispositivo #$id";
$activePage = "";
include __DIR__ . '/../../layout.php';
