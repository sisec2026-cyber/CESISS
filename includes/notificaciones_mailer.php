<?php
// includes/notificaciones_mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php'; // Ajusta si tu vendor está en otra ruta

/**
 * Envía un correo HTML usando Gmail SMTP (App Password).
 */
function enviarNotificacion(string $asunto, string $htmlCuerpo, array $destinatarios): bool {
    $mail = new PHPMailer(true);
    try {
        // SMTP Gmail (Google Workspace)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'cesiss@cesiss.com';   // TU correo
        $mail->Password   = 'ptyzfymfyqekkkbm';        // TU App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Remitente
        $mail->setFrom('notificaciones@cesiss.com', 'Sistema SISEC');
        $mail->addReplyTo('notificaciones@cesiss.com', 'Sistema SISEC');

        // Destinatarios
        foreach ($destinatarios as $to) {
            if (!empty($to)) $mail->addAddress($to);
        }

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $htmlCuerpo;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // No bloquear el flujo de la app por fallas de correo
        error_log("Correo no enviado: {$mail->ErrorInfo}");
        return false;
    }
}

