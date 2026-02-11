<?php
// includes/notificaciones_mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php'; 

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
        $mail->Username   = 'cesiss@cesiss.com';
        $mail->Password   = 'ptyzfymfyqekkkbm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // ✅ ====== UTF-8 CORRECTO ======
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);
        // ==============================

        // Remitente
        $mail->setFrom('notificaciones@cesiss.com', 'Sistema CESISS');
        $mail->addReplyTo('notificaciones@cesiss.com', 'Sistema CESISS');

        // Destinatarios
        foreach ($destinatarios as $to) {
            if (!empty($to)) {
                $mail->addAddress($to);
            }
        }

        // Contenido
        $mail->Subject = $asunto;
        $mail->Body    = $htmlCuerpo;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Correo no enviado: {$mail->ErrorInfo}");
        return false;
    }
}