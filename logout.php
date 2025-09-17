<?php
session_start();
require_once __DIR__ . '/includes/notificaciones_mailer.php';

/* Zona horaria CDMX */
date_default_timezone_set('America/Mexico_City');

/* ===== 1) Leer datos de sesión ANTES de limpiar ===== */
$usuario = $_SESSION['nombre']       ?? 'desconocido';
$rol     = $_SESSION['usuario_rol']  ?? '—';
$foto    = $_SESSION['foto']         ?? null;

/* Auditoría: contadores y tiempos */
$audit    = $_SESSION['audit'] ?? [];
$loginAt  = isset($audit['login_at']) ? (int)$audit['login_at'] : null;

$registros = (int)($audit['dispositivos_registrados'] ?? 0);
$ediciones = (int)($audit['dispositivos_editados']   ?? 0);
// Si luego quieres llevar eliminados:
// $eliminados = (int)($audit['dispositivos_eliminados'] ?? 0);

/* Fechas “bonitas” */
$tzMx  = new DateTimeZone('America/Mexico_City');
$nowMx = new DateTime('now', $tzMx);

if (class_exists('IntlDateFormatter')) {
    $fmt = new IntlDateFormatter(
        'es_MX',
        IntlDateFormatter::FULL,
        IntlDateFormatter::SHORT,
        'America/Mexico_City',
        IntlDateFormatter::GREGORIAN,
        "EEEE d 'de' MMMM 'de' y 'a las' HH:mm:ss"
    );
    $fechaHoraCierre = ucfirst($fmt->format($nowMx));

    $loginPretty = '—';
    if ($loginAt) {
        $loginDT = (new DateTime('@' . $loginAt))->setTimezone($tzMx);
        $loginPretty = ucfirst($fmt->format($loginDT));
    }
} else {
    $fechaHoraCierre = $nowMx->format('d/m/Y H:i:s');
    $loginPretty = $loginAt ? date('d/m/Y H:i:s', $loginAt) : '—';
}

/* Duración de sesión */
$duracion = '—';
if ($loginAt) {
    $seg = max(0, time() - $loginAt);
    // H:i:s (UTC) para duración
    $duracion = gmdate('H:i:s', $seg);
}

/* ===== 2) Construir URLs absolutas para el correo ===== */
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($basePath === '.' || $basePath === '/') $basePath = '';
$baseUrl  = $scheme . '://' . $host . $basePath; // p.ej. https://dominio/sisec-ui

/* Foto absoluta si la hay */
$fotoAbs = null;
if (!empty($foto)) {
    $fotoAbs = preg_match('#^https?://#i', $foto) ? $foto : ($scheme . '://' . $host . $foto);
}

/* Sanitiza */
$usuarioHtml = htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
$rolHtml     = htmlspecialchars($rol, ENT_QUOTES, 'UTF-8');

/* ===== 3) Armar correo ===== */
$destinatarios = ['notificacionescesiss@gmail.com'];
$asunto = 'Cierre de sesión (resumen de actividad)';

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
                  Se ha registrado un <strong>cierre de sesion</strong> en tu cuenta.
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
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px;"><strong>Inicio de sesión</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$loginPretty}</td>
                  </tr>
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px;"><strong>Duración</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$duracion}</td>
                  </tr>
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px;"><strong>Fecha y hora de cierre (CDMX)</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$fechaHoraCierre}</td>
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

/* Resumen de actividad */
$html .= <<<HTML
                <h3 style="margin:24px 0 8px 0; font-size:16px;">Resumen de actividad</h3>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px; width:70%;">Dispositivos registrados</td>
                    <td align="right" style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;"><strong>{$registros}</strong></td>
                  </tr>
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px;">Dispositivos editados</td>
                    <td align="right" style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;"><strong>{$ediciones}</strong></td>
                  </tr>
                </table>

                <div style="margin:20px 0 8px 0;">
                  <a href="{$baseUrl}/login.php"
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

/* ===== 4) Enviar y cerrar ===== */
try {
    enviarNotificacion($asunto, $html, $destinatarios);
} catch (Throwable $e) {
    // No interrumpir el logout
}

/* Limpiar sesión y cookie remember */
session_unset();
session_destroy();

setcookie('usuario_id', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

/* Redirigir al login */
header('Location: login.php');
exit;
