device
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
$stmt->close();

if (!$device) {
    die('Dispositivo no encontrado.');
}

/* ==========================================
   2) Meta: quién lo registró y en qué fecha
   - Busca la PRIMERA notificación "registró..."
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

/* ==================
   4) Render
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
    <table class="table table-striped table-bordered" style="max-width:800px;">
      <tbody>
        <tr><th>Equipo</th><td><?= htmlspecialchars($device['nom_equipo'] ?? '') ?></td></tr>
        <tr><th>Fecha de instalación</th><td><?= htmlspecialchars($device['fecha'] ?? '') ?></td></tr>
        <tr><th>Modelo</th><td><?= htmlspecialchars($device['num_modelos'] ?? '') ?></td></tr>
        <tr><th>Estado del equipo</th><td><?= htmlspecialchars($device['status_equipo'] ?? '') ?></td></tr>
        <tr><th>Sucursal</th><td><?= htmlspecialchars($device['nom_sucursal'] ?? '') ?></td></tr>
        <tr><th>Municipio</th><td><?= htmlspecialchars($device['nom_municipio'] ?? '') ?></td></tr>
        <tr><th>Ciudad</th><td><?= htmlspecialchars($device['nom_ciudad'] ?? '') ?></td></tr>
        <tr><th>Área de la tienda</th><td><?= htmlspecialchars($device['area'] ?? '') ?></td></tr>
        <tr><th>Serie</th><td><?= htmlspecialchars($device['serie'] ?? '') ?></td></tr>
        <tr><th>Dirección MAC</th><td><?= htmlspecialchars($device['mac'] ?? '') ?></td></tr>
        <tr><th>VMS</th><td><?= htmlspecialchars($device['vms'] ?? '') ?></td></tr>
        <tr><th>Servidor</th><td><?= htmlspecialchars($device['servidor'] ?? '') ?></td></tr>
        <tr><th>Switch</th><td><?= htmlspecialchars($device['switch'] ?? '') ?></td></tr>
        <tr><th>Puerto</th><td><?= htmlspecialchars($device['puerto'] ?? '') ?></td></tr>
        <tr><th>Observaciones</th><td><?= nl2br(htmlspecialchars($device['observaciones'] ?? '')) ?></td></tr>
        <tr><th>Usuario</th><td><?= htmlspecialchars($device['user'] ?? '') ?></td></tr>
        <tr><th>Contraseña</th><td><?= htmlspecialchars($device['pass'] ?? '') ?></td></tr>
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
          if (strpos($ru, '://') === false) { // evita URLs absolutas externas
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