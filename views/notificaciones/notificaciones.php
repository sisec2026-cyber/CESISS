<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador']);

include __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $idEliminar = (int)$_POST['eliminar_id'];
    $stmt = $conn->prepare("DELETE FROM notificaciones WHERE id = ?");
    $stmt->bind_param("i", $idEliminar);
    $stmt->execute();

    // Redirecciona para evitar reenvío de formulario
    header("Location: notificaciones.php?filtro=" . urlencode($_GET['filtro'] ?? 'todos'));
    exit;
}

$conn->query("UPDATE notificaciones SET visto = 1 WHERE visto = 0");

// Parámetros para paginación y filtro
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$filtro = $_GET['filtro'] ?? 'todos'; // 'todos' o 'novistas'

// Preparar consulta
$where = '';
$params = [];
$types = '';

if ($filtro === 'novistas') {
    $where = 'WHERE visto = 0';
}

$totalQuery = "SELECT COUNT(*) FROM notificaciones $where";
$stmtTotal = $conn->prepare($totalQuery);
$stmtTotal->execute();
$stmtTotal->bind_result($totalNotifications);
$stmtTotal->fetch();
$stmtTotal->close();

$sql = "SELECT id, usuario_id, mensaje, fecha, visto, dispositivo_id FROM notificaciones $where ORDER BY fecha DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if ($where) {
    $stmt->bind_param("ii", $offset, $perPage);
} else {
    $stmt->bind_param("ii", $offset, $perPage);
}
$stmt->execute();
$result = $stmt->get_result();

ob_start();
?>

<h2>Notificaciones</h2>

<div class="mb-3">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <label for="filtro">Mostrar:</label>
    <select name="filtro" id="filtro" onchange="this.form.submit()" class="form-select w-auto">
      <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todas</option>
      <option value="novistas" <?= $filtro === 'novistas' ? 'selected' : '' ?>>No vistas</option>
    </select>
  </form>
</div>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Mensaje</th>
      <th>Fecha</th>
      <th>Estado</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($result->num_rows === 0): ?>
      <tr><td colspan="4" class="text-center">No hay notificaciones.</td></tr>
    <?php else: ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="<?= $row['visto'] == 0 ? 'table-warning' : '' ?>">
          <td><?= htmlspecialchars($row['mensaje']) ?></td>
          <td><?= htmlspecialchars($row['fecha']) ?></td>
          <td><?= $row['visto'] == 0 ? '<strong>No vista</strong>' : 'Vista' ?></td>
           <td class="d-flex gap-1">
            <a href="/sisec-ui/views/dispositivos/device.php?id=<?= $row['dispositivo_id'] ?>" class="btn btn-info btn-sm" target="_blank" title="Ver dispositivo">
                <i class="fas fa-eye"></i>
            </a>
            <form method="post" action="notificaciones.php" onsubmit="return confirm('¿Eliminar esta notificación?');">
                <input type="hidden" name="eliminar_id" value="<?= $row['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar notificación">
                <i class="fas fa-trash-alt"></i>
                </button>
            </form>
            </td>
        </tr>
      <?php endwhile; ?>
    <?php endif; ?>
  </tbody>
</table>


<?php
// Paginación
$totalPages = ceil($totalNotifications / $perPage);
if ($totalPages > 1):
?>
<nav>
  <ul class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&filtro=<?= htmlspecialchars($filtro) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php
endif;

$content = ob_get_clean();
$pageTitle = "Notificaciones";
$pageHeader = "Lista de notificaciones";
$activePage = "notificaciones";

include __DIR__ . '/../../layout.php';
?>