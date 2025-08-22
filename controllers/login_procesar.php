<?php
session_start();
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/notificaciones_mailer.php';

/* Zona horaria correcta para CDMX */
date_default_timezone_set('America/Mexico_City');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);

    if (empty($nombre) || empty($password)) {
        header('Location: ../login.php?error=' . urlencode("Por favor, completa todos los campos."));
        exit;
    }

    // Traer también la foto del usuario
    $stmt = $pdo->prepare("SELECT id, nombre, rol, clave, foto FROM usuarios WHERE nombre = ?");
    $stmt->execute([$nombre]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario['clave'])) {
        $_SESSION['usuario_id']  = $usuario['id'];
        $_SESSION['nombre']      = $usuario['nombre'];
        $_SESSION['usuario_rol'] = $usuario['rol'];
        $_SESSION['foto']        = !empty($usuario['foto']) ? '/sisec-ui/uploads/usuarios/' . $usuario['foto'] : null;

        if ($remember) {
            setcookie('usuario_id', $usuario['id'], time() + (7 * 24 * 60 * 60), "/");
        }

        // === Notificación de inicio de sesión (no bloquea la redirección si falla) ===
        $destinatarios = [
            'marcojazzelarzate@gmail.com',
            'marc0_ruiz@hotmail.com',
        ];
        $asunto = 'Inicio de sesion';

        // Fecha/Hora en CDMX (bonita si está la extensión intl)
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
            // Fallback simple
            $fechaHora = $nowMx->format('d/m/Y H:i:s');
        }

        // Construir URLs absolutas para que se vean en el correo
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host . '/sisec-ui';
        $fotoAbs = !empty($usuario['foto']) ? ($baseUrl . '/uploads/usuarios/' . $usuario['foto']) : null;

        // Sanitizar
        $nombreHtml = htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8');
        $rolHtml    = htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8');

        // HTML con estilo para email (tablas + CSS inline para compatibilidad)
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
                <p style="margin:6px 0 0 0; font-size:13px; opacity:.95;">Notificación de inicio de sesión</p>
              </td>
            </tr>

            <tr>
              <td style="padding:24px; font-family:Arial, Helvetica, sans-serif; color:#222;">
                <p style="margin:0 0 12px 0; font-size:15px; line-height:1.5;">
                  ¡Hola! Se ha registrado un nuevo <strong>inicio de sesión</strong> en tu cuenta.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:16px 0;">
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px; width:40%;"><strong>Usuario</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$nombreHtml}</td>
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
                  <img src="{$fotoAbs}" alt="Foto de {$nombreHtml}" width="80" height="80" style="border-radius:50%; display:block; border:1px solid #e6e6e6; object-fit:cover;">
                </div>
HTML;
        }

        // Botón para ir al panel
        $ctaUrl = $baseUrl . '/views/inicio/index.php';
        $html .= <<<HTML
                <div style="margin:20px 0 8px 0;">
                  <a href="{$ctaUrl}" 
                     style="display:inline-block; padding:12px 18px; background:#3C92A6; color:#ffffff; text-decoration:none; font-weight:bold; border-radius:8px; font-size:14px;">
                     Abrir panel
                  </a>
                </div>

                <p style="margin:16px 0 0 0; font-size:12px; color:#6b7280;">
                  Si no fuiste tú, te recomendamos cambiar tu contraseña de inmediato.
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

        enviarNotificacion($asunto, $html, $destinatarios);

        // 🔑 Redirección según rol
        switch ($usuario['rol']) {
            case 'Superadmin':
            case 'Mantenimientos':
            case 'Distrital':
            case 'Administrador':
            case 'Técnico':
                $redirect = '/sisec-ui/views/inicio/index.php';
                break;

            case 'Capturista':
            case 'Prevencion':
            case 'Monitorista':
            default:
                $redirect = '/sisec-ui/views/dispositivos/listar.php';
                break;
        }

        header("Location: $redirect");
        exit;

    } else {
        header('Location: ../login.php?error=' . urlencode("Usuario o contraseña incorrectos."));
        exit;
    }
} else {
    header('Location: ../login.php');
    exit;
}
