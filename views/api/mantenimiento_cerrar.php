<?php
// /sisec-ui/views/api/mantenimiento_cerrar.php
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

$STATUS_HECHO = 'Mantenimiento hecho';

// Config de evidencias
$MAX_FILES = 10;
$MAX_SIZE  = 10 * 1024 * 1024; // 10 MB
$ALLOWED   = ['image/jpeg','image/png','application/pdf'];

function respond($ok, $payload = [], $code = 200){
  http_response_code($code);
  echo json_encode(['ok'=>$ok] + $payload, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // CSRF
  $csrfServer = $_SESSION['csrf_token'] ?? '';
  $csrfForm   = $_POST['csrf'] ?? '';
  if (!$csrfServer || !$csrfForm || !hash_equals($csrfServer, $csrfForm)) {
    respond(false, ['error'=>'CSRF inválido'], 403);
  }

  // Inputs
  $sucursal_id = (int)($_POST['sucursal_id'] ?? 0);
  $evento_id   = (int)($_POST['evento_id'] ?? 0);
  $descripcion = trim((string)($_POST['descripcion'] ?? '')); // se guarda en DB

  if ($sucursal_id <= 0 || $evento_id <= 0 || $descripcion === '') {
    respond(false, ['error'=>'Parámetros inválidos'], 400);
  }

  // Leer evento actual (no tocar fecha_inicio)
  $stmt = $conn->prepare("
    SELECT id, sucursal_id,
           status_label,
           COALESCE(fecha_inicio, fecha) AS fecha_inicio,
           COALESCE(fecha_fin,    fecha) AS fecha_fin
    FROM mantenimiento_eventos
    WHERE id = ? AND sucursal_id = ?
  ");
  $stmt->bind_param('ii', $evento_id, $sucursal_id);
  $stmt->execute();
  $ev = $stmt->get_result()->fetch_assoc();
  if (!$ev) respond(false, ['error'=>'Evento no encontrado'], 404);

  $fi = (string)$ev['fecha_inicio'];               // respetamos
  $ff = (string)$ev['fecha_fin'];                  // respetamos si ya fue extendida
  $hoy = (new DateTimeImmutable('today'))->format('Y-m-d');

  // Saneos mínimos de fecha_fin:
  // - si está vacía/nula -> hoy
  // - si fecha_fin < fecha_inicio -> igualarla al inicio
  if (!$ff || $ff === '0000-00-00') {
    $ff = $hoy;
  }
  if ($ff < $fi) {
    $ff = $fi;
  }

  // Actualizar: status_label = HECHO, fecha_fin (respetada/saneada), descripcion_cierre
  $stmt2 = $conn->prepare("
    UPDATE mantenimiento_eventos
    SET status_label = ?, fecha_fin = ?, descripcion_cierre = ?
    WHERE id = ? AND sucursal_id = ?
  ");
  // 3 strings + 2 enteros → 'sssii'
  $stmt2->bind_param('sssii', $STATUS_HECHO, $ff, $descripcion, $evento_id, $sucursal_id);
  $stmt2->execute();

  // Guardar evidencias en disco (sin tabla adicional)
  if (!empty($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
    $dir = __DIR__ . '/../../public/mto_evidencias/' . $evento_id;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $names  = $_FILES['archivos']['name'];
    $tmp    = $_FILES['archivos']['tmp_name'];
    $sizes  = $_FILES['archivos']['size'];
    $errors = $_FILES['archivos']['error'];

    $count = min(count($names), $MAX_FILES);
    for ($i = 0; $i < $count; $i++) {
      if ($errors[$i] !== UPLOAD_ERR_OK) continue;
      if ($sizes[$i] > $MAX_SIZE) continue;

      // Detectar mime de forma segura
      $mime = @mime_content_type($tmp[$i]) ?: '';
      if (!in_array($mime, $ALLOWED, true)) continue;

      $ext  = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
      // Asegurar extensiones válidas con los mimes permitidos
      if ($mime === 'image/jpeg' && !in_array($ext, ['jpg','jpeg'], true)) $ext = 'jpg';
      if ($mime === 'image/png'  && $ext !== 'png') $ext = 'png';
      if ($mime === 'application/pdf' && $ext !== 'pdf') $ext = 'pdf';

      $basename = pathinfo($names[$i], PATHINFO_FILENAME);
      $safeBase = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $basename);
      $dest = $dir . '/' . $safeBase . '_' . uniqid('', true) . '.' . $ext;

      @move_uploaded_file($tmp[$i], $dest);
    }
  }

  respond(true, ['fecha_fin'=>$ff]); // opcional: regresamos la fecha_final efectiva

} catch (Throwable $e) {
  respond(false, ['error'=>'No se pudo cerrar el mantenimiento','detail'=>$e->getMessage()], 500);
}
