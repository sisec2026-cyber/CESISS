<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ajustar límites de subida (por si el servidor los bloquea)
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');

$asunto    = trim($_POST['asunto'] ?? '');
$nombre    = trim($_POST['nombre'] ?? '');
$correo    = trim($_POST['correo'] ?? '');
$mensaje   = trim($_POST['mensaje'] ?? '');
$prioridad = trim($_POST['prioridad'] ?? 'Normal');

if (!$asunto || !$nombre || !$correo || !$mensaje) {
    redirect_with(null, 'Por favor completa los campos requeridos.');
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    redirect_with(null, 'Correo inválido.');
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'soporte@cesiss.com';
    $mail->Password   = 'gdvocwiuycyqrltp';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // El correo sigue autenticado con soporte@cesiss.com,
    // pero se muestra como si viniera de la persona que llenó el formulario.
    $mail->setFrom('soporte@cesiss.com', "{$nombre} (via CESISS Soporte)");
    $mail->addAddress('soporte@cesiss.com');
    $mail->addReplyTo($correo, $nombre);

    // Opcional: también puedes usar una cabecera personalizada para mayor claridad
    $mail->addCustomHeader('X-Original-Sender', $correo);
    $mail->addCustomHeader('X-Original-Name', $nombre);

    $mail->isHTML(true);
    $mail->Subject = "$asunto";

    $body = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">
    <style>
    body {
        background: #f4f7f8;
        font-family: "Segoe UI", Roboto, Arial, sans-serif;
        color: #333;
        margin: 0;
        padding: 0;
    }
    .container {
        max-width: 600px;
        margin: 40px auto;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        border-top: 6px solid #3C92A6;
    }
    .header {
        background: linear-gradient(135deg, #3C92A6, #24a3c1);
        color: #fff;
        padding: 20px;
        text-align: center;
    }
    .header h2 {
        margin: 0;
        font-size: 22px;
        letter-spacing: 0.5px;
    }
    .content {
        padding: 24px;
    }
    .content h3 {
        color: #3C92A6;
        margin-top: 0;
    }
    .data-block {
        margin: 16px 0;
        background: #f8fafc;
        border-left: 4px solid #3C92A6;
        padding: 12px 16px;
        border-radius: 6px;
    }
    .data-block b {
        color: #222;
    }
    .message {
        margin-top: 20px;
        padding: 14px;
        background: #f1f9fa;
        border-radius: 10px;
        line-height: 1.5;
    }
    .footer {
        background: #f8f8f8;
        padding: 14px;
        text-align: center;
        font-size: 13px;
        color: #666;
        border-top: 1px solid #e0e0e0;
    }
    .tag {
        display: inline-block;
        padding: 4px 10px;
        background: #e0f4f9;
        color: #03647a;
        font-weight: 600;
        border-radius: 999px;
        font-size: 12px;
        margin-top: 4px;
    }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
        <h2>Nueva solicitud de soporte CESISS</h2>
        </div>
        <div class="content">
        <div class="data-block">
            <b>Nombre:</b> ' . htmlentities($nombre) . '<br>
            <b>Correo:</b> ' . htmlentities($correo) . '<br>
            <b>Prioridad:</b> <span class="tag">' . htmlentities($prioridad) . '</span><br>
            <b>Fecha/Hora:</b> ' . date("Y-m-d H:i:s") . '
        </div>

        <h3>Detalles del mensaje:</h3>
        <div class="message">' . nl2br(htmlentities($mensaje)) . '</div>
        </div>

        <div class="footer">
        <p>Este mensaje fue generado automáticamente desde el formulario de soporte de CESISS.</p>
        <p>No respondas a este correo directamente si no eres parte del equipo técnico.</p>
        </div>
    </div>
    </body>
    </html>';

    $mail->Body    = $body;
    $mail->AltBody = strip_tags($mensaje);

    // === Adjuntar archivos si existen ===
    if (isset($_FILES['archivo']) && !empty($_FILES['archivo']['name'][0])) {
        foreach ($_FILES['archivo']['tmp_name'] as $i => $tmp_name) {
            $error = $_FILES['archivo']['error'][$i];
            $name  = $_FILES['archivo']['name'][$i];

            if ($error === UPLOAD_ERR_OK && is_uploaded_file($tmp_name)) {
                $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/','_', $name);
                $mail->addAttachment($tmp_name, $safeName);
            }
        }
    }
    $mail->send();
    redirect_with('¡Tu solicitud fue enviada con éxito! Te contactaremos pronto.');

} catch (Exception $e) {
    redirect_with(null, 'Error al enviar: ' . htmlspecialchars($mail->ErrorInfo));
}