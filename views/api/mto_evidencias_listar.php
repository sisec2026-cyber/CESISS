<?php
// /sisec-ui/views/api/mto_evidencias_listar.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Técnico','Prevencion','Distrital']);
require_once __DIR__ . '/../../includes/db.php';

date_default_timezone_set('America/Mexico_City');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Ajusta si tu base url cambia
$BASE_URL = '/sisec-ui';

function respond($ok, $payload = [], $code = 200){
  http_response_code($code);
  echo json_encode(['ok' => $ok] + $payload, JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * Lista archivos de un directorio devolviendo metadatos y URL pública
 * @param string $dirPath   Ruta física absoluta (ej: /var/www/.../public/mto_evidencias/123)
 * @param string $urlPrefix Prefijo público (ej: /sisec-ui/public/mto_evidencias/123)
 * @return array<int, array<string, mixed>>
 */
function list_files_in_dir(string $dirPath, string $urlPrefix): array {
  $out = [];
  if (!is_dir($dirPath)) return $out;

  $finfo = new finfo(FILEINFO_MIME_TYPE);

  try {
    $it = new DirectoryIterator($dirPath);
    foreach ($it as $f) {
      if ($f->isDot()) continue;
      if (!$f->isFile()) continue;

      $name  = $f->getFilename();
      $path  = $f->getPathname();
      $mime  = $finfo->file($path) ?: 'application/octet-stream';
      $size  = (int)$f->getSize();
      $mtime = (int)$f->getMTime();

      // URL pública segura
      $url = rtrim($urlPrefix, '/') . '/' . rawurlencode($name);

      // Clasificación simple
      $kind = 'other';
      if (strpos($mime, 'image/') === 0) {
        $kind = 'image';
      } elseif ($mime === 'application/pdf') {
        $kind = 'pdf';
      }

      $out[] = [
        'name'  => $name,
        'url'   => $url,
        'mime'  => $mime,
        'kind'  => $kind,
        'size'  => $size,
        'mtime' => date('Y-m-d H:i:s', $mtime),
      ];
    }
  } catch (Throwable $e) {
    // Si el dir se borró entre la verificación y el listado, devolvemos vacío.
    return [];
  }

  // Más recientes primero
  usort($out, fn($a, $b) => strcmp($b['mtime'], $a['mtime']));

  return $out;
}

try {
  $evento_id = (int)($_GET['evento_id'] ?? 0);
  if ($evento_id <= 0) {
    respond(false, ['error' => 'evento_id inválido'], 400);
  }

  // Directorios físicos
  $baseDir      = __DIR__ . '/../../public/mto_evidencias/' . $evento_id;
  $extDir       = $baseDir . '/ext';

  // Prefijos públicos
  $baseUrl      = $BASE_URL . '/public/mto_evidencias/' . $evento_id;
  $extUrl       = $baseUrl . '/ext';

  // Listados
  $filesCierre  = list_files_in_dir($baseDir, $baseUrl);   // evidencias del cierre
  $filesExt     = list_files_in_dir($extDir,  $extUrl);    // evidencias de extensión

  // Para compatibilidad con el frontend actual, devolvemos dos arreglos:
  // { cierre: [...], extender: [...] }
  respond(true, [
    'cierre'   => $filesCierre,
    'extender' => $filesExt,
  ]);

} catch (Throwable $e) {
  respond(false, ['error' => 'No se pudo listar evidencias', 'detail' => $e->getMessage()], 500);
}
