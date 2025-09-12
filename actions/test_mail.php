<?php
require __DIR__ . '/vendor/autoload.php';
$mail = new PHPMailer\PHPMailer\PHPMailer(true);
try {
  $mail->isSMTP();
  $mail->Host = 'mail.cesiss.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'soporte@cesiss.com';
  $mail->Password = 'sistemas2025';
  $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;

  $mail->setFrom('soporte@cesiss.com', 'CESISS Soporte');
  $mail->addAddress('tu-correo@gmail.com'); // cámbialo a uno tuyo
  $mail->Subject = 'Prueba SMTP';
  $mail->Body = 'Esto es un test de PHPMailer';

  $mail->send();
  echo "✅ Enviado correctamente";
} catch (Throwable $e) {
  echo "❌ Error: " . $e->getMessage();
}