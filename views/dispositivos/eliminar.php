eliminar
<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Capturista','Técnico']);

include __DIR__ . '/../../includes/db.php';

// Asegura que la sesión está disponible para flashes (por si auth no la inició)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// ===== Helper: sanitizar return_url para evitar open redirects =====
function sanitize_return_url($raw) {
  if (!$raw) return 'listar.php';
  $decoded = urldecode((string)$raw);
  $parts   = @parse_url($decoded);
  $path    = $parts['path']  ?? '';
  $query   = isset($parts['query']) ? ('?' . $parts['query']) : '';

  // Permite rutas internas o archivos locales; bloquea URLs absolutas externas
  if ($path === '' || preg_match('#^(?:[a-z]+:)?//#i', $decoded)) {
    return 'listar.php';
  }
  // Si quieres forzar volver SOLO al listado:
  // if (strpos($path, 'listar.php') === false) return 'listar.php' . $query;

  return $path . $query;
}

$returnUrl = sanitize_return_url($_GET['return_url'] ?? 'listar.php');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  $_SESSION['flash_warning'] = 'Solicitud inválida: falta el ID del dispositivo.';
  header('Location: ' . $returnUrl);
  exit;
}

$id = (int)$_GET['id'];

// Manejo de errores como excepciones (FK, etc.)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn->begin_transaction();

  // 1) Verificar existencia y obtener imagen(es)
  $get = $conn->prepare("SELECT imagen FROM dispositivos WHERE id = ?");
  $get->bind_param("i", $id);
  $get->execute();
  $row = $get->get_result()->fetch_assoc();

  if (!$row) {
    $conn->rollback();
    $_SESSION['flash_warning'] = 'El dispositivo no existe o ya fue eliminado.';
    header('Location: ' . $returnUrl);
    exit;
  }

  // 2) Eliminar el registro
  $del = $conn->prepare("DELETE FROM dispositivos WHERE id = ?");
  $del->bind_param("i", $id);
  $del->execute();

  $conn->commit();

  // 3) (Opcional) Eliminar imagen del disco
  if (!empty($row['imagen'])) {
    $uploadDir = realpath(__DIR__ . '/../../public/uploads');
    if ($uploadDir !== false) {
      $file = $uploadDir . DIRECTORY_SEPARATOR . basename($row['imagen']);
      if (is_file($file)) { @unlink($file); }
    }
  }

  $_SESSION['flash_success'] = 'Dispositivo eliminado correctamente.';
  header('Location: ' . $returnUrl);
  exit;

} catch (mysqli_sql_exception $e) {
  $conn->rollback();

  // Código MySQL de FK suele ser 1451 (Cannot delete or update a parent row)
  if ((int)$e->getCode() === 1451) {
    $_SESSION['flash_error'] = 'No se puede eliminar: el dispositivo está referenciado por otros registros (restricción de llave foránea).';
  } else {
    // Evita filtrar detalles internos en producción, pero puedes loguearlos
    $_SESSION['flash_error'] = 'Ocurrió un error al eliminar el dispositivo. Código: ' . (int)$e->getCode();
  }

  header('Location: ' . $returnUrl);
  exit;
}