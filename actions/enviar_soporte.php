<?php
// actions/enviar_soporte.php
require_once __DIR__ . '/../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Técnico','Distrital','Prevencion','Monitorista','Invitado','Capturista']);

session_start();

function redirect_with($ok=null, $err=null){
  if ($ok)  $_SESSION['flash_ok']  = $ok;
  if ($err) $_SESSION['flash_err'] = $err;
  header('Location: /sisec-ui/views/soporte.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_with(null, 'Método no permitido.');
}

$hp  = $_POST['hp_field'] ?? '';
if ($hp !== '') {
  redirect_with(null, 'Solicitud inválida.');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!$csrf || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
  redirect_with(null, 'Token inválido. Recarga la página e inténtalo de nuevo.');
}

$asunto   = trim((string)($_POST['asunto'] ?? ''));
$nombre   = trim((string)($_POST['nombre'] ?? ''));
$correo   = trim((string)($_POST['correo'] ?? ''));
$mensaje  = trim((string)($_POST['mensaje'] ?? ''));
$prioridad= trim((string)($_POST['prioridad'] ?? 'Normal'));

if ($asunto === '' || $nombre === '' || $correo === '' || $mensaje === '') {
  redirect_with(null, 'Por favor completa los campos requeridos.');
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
  redirect_with(null, 'Correo inválido.');
}

// Construcción del contenido
$to = 'soportecesiss@gmail.com';
$subject = "[CESISS Soporte] {$asunto}";
$body_text = "Nueva solicitud de soporte\n\n"
. "Nombre: {$nombre}\n"
. "Correo: {$correo}\n"
. "Prioridad: {$prioridad}\n"
. "Fecha/Hora: " . date('Y-m-d H:i:s') . "\n\n"
. "Mensaje:\n{$mensaje}\n";

$body_html = nl2br(htmlentities($body_text, ENT_QUOTES, 'UTF-8'));

// Intentamos PHPMailer si existe
$sent = false;
$err  = null;

try {
  if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Config SMTP si tienes credenciales; si no, usa mail() al final
    // Ejemplo SMTP (comenta/ajusta según tu entorno):
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'soporte@cesiss.com';
    $mail->Password = 'sistemas2025';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Si NO configuras SMTP, PHPMailer intentará el mail() del sistema:
    $mail->setFrom('soporte@cesiss.com', 'CESISS Soporte');
    $mail->addAddress($to);
    // Responder al usuario
    $mail->addReplyTo($correo, $nombre);

    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $body_html;
    $mail->AltBody = $body_text;
    // Justo antes de $mail->send() si PHPMailer existe, agrega esto para adjuntar archivo
    if (!empty($_FILES['archivo']['name'])) {
    // Maneja múltiples archivos aunque tu input sea uno solo
    $files = is_array($_FILES['archivo']['name']) ? $_FILES['archivo'] : [
        'name' => [$_FILES['archivo']['name']],
        'type' => [$_FILES['archivo']['type']],
        'tmp_name' => [$_FILES['archivo']['tmp_name']],
        'error' => [$_FILES['archivo']['error']],
        'size' => [$_FILES['archivo']['size']]
    ];
      foreach ($files['name'] as $i => $file_name) {
          if ($files['error'][$i] === UPLOAD_ERR_OK && is_uploaded_file($files['tmp_name'][$i])) {
              $mail->addAttachment($files['tmp_name'][$i], $file_name);
          }
      }
    }

    $mail->send();
    $sent = true;
  }
} catch (Throwable $e) {
  $err = $e->getMessage();
}

// Si PHPMailer no se usó o falló, probamos mail()
if (!$sent) {
  $headers = [];
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-type: text/html; charset=UTF-8';
  $headers[] = 'From: CESISS Soporte <no-reply@cesiss.local>';
  $headers[] = 'Reply-To: ' . $nombre . ' <' . $correo . '>';

  if (@mail($to, $subject, $body_html, implode("\r\n", $headers))) {
    $sent = true;
  } else {
    if (!$err) $err = 'No se pudo enviar el correo (mail()).';
  }
}

if ($sent) {
  redirect_with('¡Tu solicitud fue enviada con éxito! Te contactaremos pronto.');
}
redirect_with(null, 'No pudimos enviar tu solicitud. ' . ($err ? ('Detalle: ' . $err) : 'Intenta más tarde.'));