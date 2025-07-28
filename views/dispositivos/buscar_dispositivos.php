<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador', 'Mantenimientos', 'Invitado']);

include __DIR__ . '/../../includes/db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$stmt = $conn->prepare("
    SELECT * FROM dispositivos 
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

while ($device = $result->fetch_assoc()):
?>
  <tr>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['id']) ?></td>
    <td><?= htmlspecialchars($device['equipo']) ?></td>
    <td><?= htmlspecialchars($device['fecha']) ?></td>
    <td><?= htmlspecialchars($device['modelo']) ?></td>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['estado']) ?></td>
    <td><?= htmlspecialchars($device['sucursal']) ?></td>
    <td class="d-none d-md-table-cell" style="max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($device['observaciones']) ?></td>
    <td><?= htmlspecialchars($device['serie']) ?></td>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['mac']) ?></td>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['vms']) ?></td>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['servidor']) ?></td>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['switch']) ?></td>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['puerto']) ?></td>
    <td class="d-none d-md-table-cell"><?= htmlspecialchars($device['area']) ?></td>
    <td>
      <?php if (!empty($device['imagen'])): ?>
        <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($device['imagen']) ?>" alt="Imagen" style="max-height:50px; object-fit: contain;">
      <?php endif; ?>
    </td>
    <td>
      <a href="device.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
      <?php if (in_array($_SESSION['usuario_rol'], ['Administrador', 'Mantenimientos'])): ?>
        <a href="editar.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-regular fa-pen-to-square"></i></a>
      <?php endif; ?>
      <?php if ($_SESSION['usuario_rol'] === 'Administrador'): ?>
        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?= $device['id'] ?>"><i class="fas fa-trash-alt"></i></button>
      <?php endif; ?>
    </td>
  </tr>
<?php endwhile; ?>
