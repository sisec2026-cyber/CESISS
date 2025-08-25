<?php
session_start();
require_once __DIR__ . '/includes/notificaciones_mailer.php';

/* Zona horaria correcta para CDMX */
date_default_timezone_set('America/Mexico_City');

/* Lee datos ANTES de limpiar la sesión */
$usuario = $_SESSION['nombre']       ?? 'desconocido';
$rol     = $_SESSION['usuario_rol']  ?? '—';
$foto    = $_SESSION['foto']         ?? null;

/* Fecha/Hora bonita en español (si está la extensión intl) */
$nowMx = new DateTime('now', new DateTimeZone('America/Mexico_City'));
if (class_exists('IntlDateFormatter')) {
    $fmt = new IntlDateFormatter(
        'es_MX',
        IntlDateFormatter::FULL,
        IntlDateFormatter::SHORT,
        'America/Mexico_City',
        IntlDateFormatter::GREGORIAN,
        "EEEE d 'de' MMMM 'de' y 'a las' HH:mm:ss"
    );
    $fechaHora = ucfirst($fmt->format($nowMx));
} else {
    $fechaHora = $nowMx->format('d/m/Y H:i:s');
}

/* Construye baseUrl absoluta para imágenes y links del correo */
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($basePath === '.' || $basePath === '/') $basePath = '';
$baseUrl  = $scheme . '://' . $host . $basePath;

/* Foto absoluta si la hay */
$fotoAbs = null;
if (!empty($foto)) {
    if (preg_match('#^https?://#i', $foto)) {
        $fotoAbs = $foto;
    } else {
        // $foto suele venir como '/sisec-ui/uploads/usuarios/archivo.jpg'
        $fotoAbs = $scheme . '://' . $host . $foto;
    }
}

/* Sanitiza */
$usuarioHtml = htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
$rolHtml     = htmlspecialchars($rol, ENT_QUOTES, 'UTF-8');

/* Armado del correo con estilos (CSS inline + tablas para compatibilidad) */
$destinatarios = ['notificacionescesiss@gmail.com'];
$asunto = 'Cierre de sesion';

$html = <<<HTML
<!doctype html>
<html lang="es">
  <body style="margin:0; padding:0; background:#f5f7fb;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;">
      <tr>
        <td align="center" style="padding:24px 12px;">
          <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.06);">
            <tr>
              <td style="background:#3C92A6; padding:20px 24px; color:#ffffff; font-family:Arial, Helvetica, sans-serif;">
                <h2 style="margin:0; font-size:20px; letter-spacing:.3px;">CESISS</h2>
                <p style="margin:6px 0 0 0; font-size:13px; opacity:.95;">Notificación de cierre de sesión</p>
              </td>
            </tr>

            <tr>
              <td style="padding:24px; font-family:Arial, Helvetica, sans-serif; color:#222;">
                <p style="margin:0 0 12px 0; font-size:15px; line-height:1.5;">
                  Se ha registrado un <strong>cierre de sesión</strong> en tu cuenta.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:16px 0;">
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px; width:40%;"><strong>Usuario</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$usuarioHtml}</td>
                  </tr>
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px;"><strong>Rol</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$rolHtml}</td>
                  </tr>
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px;"><strong>Fecha y hora (CDMX)</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$fechaHora}</td>
                  </tr>
                </table>
HTML;

if ($fotoAbs) {
$html .= <<<HTML
                <div style="margin:8px 0 16px 0;">
                  <p style="margin:0 0 6px 0; font-size:13px; color:#444;">Foto del usuario:</p>
                  <img src="{$fotoAbs}" alt="Foto de {$usuarioHtml}" width="80" height="80" style="border-radius:50%; display:block; border:1px solid #e6e6e6; object-fit:cover;">
                </div>
HTML;
}

$loginUrl = $baseUrl . '/login.php';
$html .= <<<HTML
                <div style="margin:20px 0 8px 0;">
                  <a href="{$loginUrl}" 
                     style="display:inline-block; padding:12px 18px; background:#3C92A6; color:#ffffff; text-decoration:none; font-weight:bold; border-radius:8px; font-size:14px;">
                     Volver a iniciar sesión
                  </a>
                </div>

                <p style="margin:16px 0 0 0; font-size:12px; color:#6b7280;">
                  Si no reconoces este cierre de sesión, por favor inicia sesión nuevamente y considera cambiar tu contraseña.
                </p>
              </td>
            </tr>

            <tr>
              <td style="padding:14px 24px; background:#f9fafb; color:#6b7280; font-family:Arial, Helvetica, sans-serif; font-size:12px; text-align:center;">
                © CESISS · Este es un mensaje automático, no respondas a este correo.
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

/* Enviar sin bloquear el flujo (ignora cualquier excepción interna) */
try {
    enviarNotificacion($asunto, $html, $destinatarios);
} catch (Throwable $e) {
    // Silenciar para no interrumpir el logout
}

/* Cerrar sesión y borrar cookie "remember" */
session_unset();
session_destroy();
setcookie('usuario_id', '', time() - 3600, "/", "", false, true); // HttpOnly

/* Redirigir al login */
header('Location: login.php');
exit;
