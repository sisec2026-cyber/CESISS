<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Técnico']);

include __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../vendor/autoload.php'; // Para PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$dispositivo_id = $_POST['dispositivo_id'] ?? null;
$status_actual  = $_POST['status_actual'] ?? null;
$status_nuevo   = $_POST['status_nuevo'] ?? null;
$descripcion    = trim($_POST['descripcion_ticket'] ?? '');
$usuario        = $_SESSION['nombre'] ?? 'Desconocido';

if(!$dispositivo_id || !$status_nuevo || !$descripcion){
  echo json_encode(['ok'=>false,'error'=>'Faltan datos']); exit;
}

$conn->begin_transaction();

try {
  // 1) Guardar ticket
  $stmt = $conn->prepare("INSERT INTO tickets (id_dispositivo, status, descripcion, usuario) VALUES (?,?,?,?)");
  $stmt->bind_param("iiss", $dispositivo_id, $status_nuevo, $descripcion, $usuario);
  $stmt->execute();
  $stmt->close();

  // 2) Guardar historial de status
  $stmt = $conn->prepare("INSERT INTO status_historial (dispositivo_id, status_anterior, status_nuevo, usuario, motivo) VALUES (?,?,?,?,?)");
  $stmt->bind_param("iiiss", $dispositivo_id, $status_actual, $status_nuevo, $usuario, $descripcion);
  $stmt->execute();
  $stmt->close();

  // 3) Actualizar status en dispositivos
  $stmt = $conn->prepare("UPDATE dispositivos SET estado = ? WHERE id = ?");
  $stmt->bind_param("ii", $status_nuevo, $dispositivo_id);
  $stmt->execute();
  $stmt->close();

  $conn->commit();

  // === ENVÍO DE CORREO ===
  $mail = new PHPMailer(true);
  try {
      // Configuración SMTP
      $mail->isSMTP();
      $mail->Host       = 'smtp.gmail.com';
      $mail->SMTPAuth   = true;
      $mail->Username   = 'soporte@cesiss.com';
      $mail->Password   = 'gdvocwiuycyqrltp';
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = 587;

      // Remitente
      $mail->setFrom('soporte@cesiss.com', "{$usuario} (via CESISS)");
      $mail->addAddress('soporte@cesiss.com'); // Destino fijo
      $mail->addReplyTo('soporte@cesiss.com', $usuario);

      $mail->isHTML(true);
      $mail->Subject = "Cambio de status en dispositivo #$dispositivo_id";

      $body = '
      <!DOCTYPE html>
      <html lang="es">
      <head>
      <meta charset="UTF-8">
      <style>
      body { background: #f4f7f8; font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #333; margin:0; padding:0; }
      .container { max-width:600px; margin:40px auto; background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.08); overflow:hidden; border-top:6px solid #3C92A6; }
      .header { background:linear-gradient(135deg, #3C92A6, #24a3c1); color:#fff; padding:20px; text-align:center; }
      .header h2 { margin:0; font-size:22px; letter-spacing:0.5px; }
      .content { padding:24px; }
      .content h3 { color:#3C92A6; margin-top:0; }
      .data-block { margin:16px 0; background:#f8fafc; border-left:4px solid #3C92A6; padding:12px 16px; border-radius:6px; }
      .data-block b { color:#222; }
      .message { margin-top:20px; padding:14px; background:#f1f9fa; border-radius:10px; line-height:1.5; }
      .footer { background:#f8f8f8; padding:14px; text-align:center; font-size:13px; color:#666; border-top:1px solid #e0e0e0; }
      </style>
      </head>
      <body>
      <div class="container">
          <div class="header"><h2>Cambio de status en dispositivo CESISS</h2></div>
          <div class="content">
          <div class="data-block">
              <b>Usuario:</b> '.htmlentities($usuario).'<br>
              <b>Dispositivo ID:</b> '.$dispositivo_id.'<br>
              <b>Status anterior:</b> '.$status_actual.'<br>
              <b>Status nuevo:</b> '.$status_nuevo.'<br>
              <b>Fecha/Hora:</b> '.date("Y-m-d H:i:s").'
          </div>
          <h3>Motivo:</h3>
          <div class="message">'.nl2br(htmlentities($descripcion)).'</div>
          </div>
          <div class="footer">
          <p>Este mensaje fue generado automáticamente desde el sistema CESISS.</p>
          </div>
      </div>
      </body>
      </html>';

      $mail->Body = $body;
      $mail->AltBody = strip_tags($descripcion);

      $mail->send();

  } catch(Exception $e){
      // No bloquea la respuesta JSON si falla el correo
      error_log("Error al enviar correo de status: " . $mail->ErrorInfo);
  }

  echo json_encode(['ok'=>true]);
} catch(Exception $e){
  $conn->rollback();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}