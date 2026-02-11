<?php
// /sisec-ui/views/api/mantenimiento_extender.php
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

$STATUS_HECHO     = 'Mantenimiento hecho';
$STATUS_SIGUIENTE = 'Siguiente mantenimiento';

// Configuración de evidencias (extensión)
$MAX_SIZE  = 10 * 1024 * 1024; // 10 MB
$ALLOWED   = ['image/jpeg','image/png','application/pdf'];

function respond(bool $ok, array $payload = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok] + $payload, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // === CSRF ===
  $csrfServer = $_SESSION['csrf_token'] ?? '';
  $csrfForm   = $_POST['csrf'] ?? '';
  if (!$csrfServer || !$csrfForm || !hash_equals($csrfServer, $csrfForm)) {
    respond(false, ['error' => 'CSRF inválido'], 403);
  }

  // === Inputs ===
  $sucursal_id = (int)($_POST['sucursal_id'] ?? 0);
  $evento_id   = (int)($_POST['evento_id'] ?? 0);
  $nueva_fecha = (string)($_POST['nueva_fecha'] ?? '');
  $motivo      = trim((string)($_POST['motivo'] ?? ''));

  if ($sucursal_id <= 0 || $evento_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nueva_fecha) || $motivo === '') {
    respond(false, ['error' => 'Parámetros inválidos'], 400);
  }

  // === Leer evento ===
  $stmt = $conn->prepare("
    SELECT id, sucursal_id, status_label,
           COALESCE(fecha_inicio, fecha) AS fecha_inicio,
           COALESCE(fecha_fin,    fecha) AS fecha_fin
    FROM mantenimiento_eventos
    WHERE id = ? AND sucursal_id = ?
  ");
  $stmt->bind_param('ii', $evento_id, $sucursal_id);
  $stmt->execute();
  $ev = $stmt->get_result()->fetch_assoc();
  if (!$ev) respond(false, ['error' => 'Evento no encontrado'], 404);

  $status_actual = (string)($ev['status_label'] ?? '');
  $fi_actual     = (string)($ev['fecha_inicio'] ?? $nueva_fecha);

  // Si estaba HECHO, lo pasamos a SIGUIENTE; en otro caso se mantiene
  $nuevo_status  = ($status_actual === $STATUS_HECHO) ? $STATUS_SIGUIENTE : $status_actual;

  // Garantizar que fecha_fin_nueva >= fecha_inicio
  $fecha_fin_nueva = ($nueva_fecha < $fi_actual) ? $fi_actual : $nueva_fecha;

  // === Actualizar evento ===
  $stmt2 = $conn->prepare("
    UPDATE mantenimiento_eventos
    SET fecha_fin = ?, status_label = ?, motivo_extension = ?
    WHERE id = ? AND sucursal_id = ?
  ");
  // motivo_extension es opcional en tu tabla; si no existe, quítalo de la query
  $stmt2->bind_param('sssii', $fecha_fin_nueva, $nuevo_status, $motivo, $evento_id, $sucursal_id);
  $stmt2->execute();

  // === Guardar evidencia(s) de extensión ===
  // Estructura: /public/mto_evidencias/{evento_id}/ext/
  $extDir = __DIR__ . '/../../public/mto_evidencias/' . $evento_id . '/ext';
  if (!is_dir($extDir)) @mkdir($extDir, 0775, true);

  // Helper para mover un archivo validado
  $saveFile = function(string $tmp, string $origName) use ($ALLOWED, $MAX_SIZE, $extDir): void {
    if (!is_file($tmp)) return;

    // Validar tamaño
    $size = @filesize($tmp);
    if ($size === false || $size > $MAX_SIZE) return;

    // Mime seguro
    $mime = @mime_content_type($tmp) ?: '';
    if (!in_array($mime, $ALLOWED, true)) return;

    // Extensión por mime
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($mime === 'image/jpeg' && !in_array($ext, ['jpg','jpeg'], true)) $ext = 'jpg';
    if ($mime === 'image/png'  && $ext !== 'png') $ext = 'png';
    if ($mime === 'application/pdf' && $ext !== 'pdf') $ext = 'pdf';

    // Nombre seguro
    $base = pathinfo($origName, PATHINFO_FILENAME);
    $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $base);
    $dest = $extDir . '/' . $safe . '_' . uniqid('', true) . '.' . $ext;

    @move_uploaded_file($tmp, $dest);
  };

  // A) Un solo archivo: name="evidencia"
  if (!empty($_FILES['evidencia']) && is_uploaded_file($_FILES['evidencia']['tmp_name'] ?? '')) {
    $saveFile($_FILES['evidencia']['tmp_name'], $_FILES['evidencia']['name']);
  }

  // B) Múltiples archivos: name="evidencia[]"  (solo si tu form los envía así)
  if (!empty($_FILES['evidencia']) && is_array($_FILES['evidencia']['name'] ?? null)) {
    $names  = $_FILES['evidencia']['name'];
    $tmps   = $_FILES['evidencia']['tmp_name'];
    $errors = $_FILES['evidencia']['error'];
    $sizes  = $_FILES['evidencia']['size'];
    $n = count($names);
    for ($i = 0; $i < $n; $i++) {
      if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      // Protección extra: is_uploaded_file
      if (!is_uploaded_file($tmps[$i] ?? '')) continue;
      // Tamaño ya se valida en $saveFile, pero descartamos aquí >0
      if (($sizes[$i] ?? 0) <= 0) continue;
      $saveFile($tmps[$i], $names[$i]);
    }
  }

  // Nota: si quieres registrar en DB que hubo evidencia de extensión, puedes agregar un INSERT en
  // una tabla de logs o evidencias. Aquí solo guardamos a disco como en “cerrar”.

  $ajustada = ($fecha_fin_nueva !== $nueva_fecha);
  respond(true, [
    'ajustada'    => $ajustada,
    'fecha_fin'   => $fecha_fin_nueva,
    'status_final'=> $nuevo_status
  ]);

} catch (Throwable $e) {
  respond(false, [
    'error'  => 'No se pudo extender el mantenimiento',
    'detail' => $e->getMessage()
  ], 500);
}
