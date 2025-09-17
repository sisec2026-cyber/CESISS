<?php
// /sisec-ui/controllers/usuarios_aprobar.php
require_once __DIR__ . '/../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador','Superadmin']);

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/notificaciones_mailer.php';

/* Helpers */
function nombre_de(mysqli $cn, string $tabla, string $colNombre, int $id = null): ?string {
    if (!$id) return null;
    $sql = "SELECT {$colNombre} AS n FROM {$tabla} WHERE id = ?";
    $st  = $cn->prepare($sql);
    if (!$st) return null;
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row['n'] ?? null;
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'aprobar') {
    $id  = (int)($_POST['id'] ?? 0);
    $rol = trim($_POST['rol'] ?? '');

    // Ámbito (opcionales)
    $region    = isset($_POST['region'])    && $_POST['region']    !== '' ? (int)$_POST['region']    : null;
    $ciudad    = isset($_POST['ciudad'])    && $_POST['ciudad']    !== '' ? (int)$_POST['ciudad']    : null;
    $municipio = isset($_POST['municipio']) && $_POST['municipio'] !== '' ? (int)$_POST['municipio'] : null;
    $sucursal  = isset($_POST['sucursal'])  && $_POST['sucursal']  !== '' ? (int)$_POST['sucursal']  : null;

    if ($id <= 0 || $rol === '') {
        header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('Datos incompletos.'));
        exit;
    }

    // Whitelist de roles (no permitimos aprobar como Superadmin desde esta UI)
    $rolesPermitidos = ['Administrador','Capturista','Técnico','Distrital','Prevencion','Mantenimientos','Monitorista'];
    if (!in_array($rol, $rolesPermitidos, true)) {
        header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('Rol no permitido.'));
        exit;
    }

    // UPDATE de aprobación
    $sql = "UPDATE usuarios
               SET esta_aprobado = 1,
                   rol = ?,
                   region = ?,
                   ciudad = ?,
                   municipio = ?,
                   sucursal = ?,
                   aprobado_por = ?,
                   aprobado_el = NOW(),
                   actualizado_el = NOW()
             WHERE id = ?
               AND esta_aprobado = 0";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('Error de preparación.'));
        exit;
    }

    $aprobador = (int)($_SESSION['usuario_id'] ?? 0);
    // tipos: s i i i i i i
    $stmt->bind_param('siiiiii', $rol, $region, $ciudad, $municipio, $sucursal, $aprobador, $id);

    if (!$stmt->execute()) {
        header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('No fue posible aprobar.'));
        exit;
    }
    if ($stmt->affected_rows <= 0) {
        header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('El usuario ya fue atendido o no existe.'));
        exit;
    }

    /* ====== Preparar correo “Acceso concedido” ====== */
    // Traer datos del usuario aprobado
    $stU = $conexion->prepare("SELECT nombre, email, foto FROM usuarios WHERE id = ?");
    $stU->bind_param('i', $id);
    $stU->execute();
    $u = $stU->get_result()->fetch_assoc();

    $nombreUsr = $u['nombre'] ?? 'Usuario';
    $emailUsr  = $u['email']  ?? null;
    $fotoUsr   = $u['foto']   ?? null;

    // Nombres del ámbito (si aplica)
    $regionNom    = nombre_de($conexion, 'regiones',   'nom_region',    $region);
    $ciudadNom    = nombre_de($conexion, 'ciudades',   'nom_ciudad',    $ciudad);
    $municipioNom = nombre_de($conexion, 'municipios', 'nom_municipio', $municipio);
    $sucursalNom  = nombre_de($conexion, 'sucursales', 'nom_sucursal',  $sucursal);

    // Construcción de URL absolutas
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Subir un nivel para salir de /controllers → base de la app (/sisec-ui)
    $baseApp = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\'); // p.ej. /sisec-ui
    if ($baseApp === '.' || $baseApp === '/') $baseApp = '';
    $baseUrl = $scheme . '://' . $host . $baseApp;

    $loginUrl = $baseUrl . '/login.php';
    $fotoAbs  = $fotoUsr ? ($baseUrl . '/uploads/usuarios/' . $fotoUsr) : null;

    // Fecha/Hora bonita (CDMX)
    date_default_timezone_set('America/Mexico_City');
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

    // Sanitizar para HTML
    $nombreHtml = htmlspecialchars($nombreUsr, ENT_QUOTES, 'UTF-8');
    $rolHtml    = htmlspecialchars($rol,        ENT_QUOTES, 'UTF-8');
    $regionHtml    = $regionNom    ? htmlspecialchars($regionNom,    ENT_QUOTES, 'UTF-8') : null;
    $ciudadHtml    = $ciudadNom    ? htmlspecialchars($ciudadNom,    ENT_QUOTES, 'UTF-8') : null;
    $municipioHtml = $municipioNom ? htmlspecialchars($municipioNom, ENT_QUOTES, 'UTF-8') : null;
    $sucursalHtml  = $sucursalNom  ? htmlspecialchars($sucursalNom,  ENT_QUOTES, 'UTF-8') : null;

    $asunto = 'CESISS: Acceso concedido';
    $destinatarios = [];
    if (!empty($emailUsr)) $destinatarios[] = $emailUsr;
    $destinatarios[] = 'notificacionescesiss@gmail.com';

    // Sección ámbito (solo si hay algún campo)
    $ambitoHtml = '';
    if ($regionHtml || $ciudadHtml || $municipioHtml || $sucursalHtml) {
        $ambitoHtml = '<h3 style="margin:16px 0 8px 0; font-size:15px;">Ámbito asignado</h3>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:8px 0;">
          ' . ($regionHtml ? '<tr><td style="padding:8px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:13px; width:40%;"><strong>Región</strong></td><td style="padding:8px 12px; border:1px solid #edf2f7; font-size:13px;">'.$regionHtml.'</td></tr>' : '') . '
          ' . ($ciudadHtml ? '<tr><td style="padding:8px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:13px;"><strong>Ciudad</strong></td><td style="padding:8px 12px; border:1px solid #edf2f7; font-size:13px;">'.$ciudadHtml.'</td></tr>' : '') . '
          ' . ($municipioHtml ? '<tr><td style="padding:8px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:13px;"><strong>Municipio</strong></td><td style="padding:8px 12px; border:1px solid #edf2f7; font-size:13px;">'.$municipioHtml.'</td></tr>' : '') . '
          ' . ($sucursalHtml ? '<tr><td style="padding:8px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:13px;"><strong>Sucursal</strong></td><td style="padding:8px 12px; border:1px solid #edf2f7; font-size:13px;">'.$sucursalHtml.'</td></tr>' : '') . '
        </table>';
    }

    $fotoBlock = '';
    if ($fotoAbs) {
        $fotoEsc = htmlspecialchars($fotoAbs, ENT_QUOTES, 'UTF-8');
        $fotoBlock = <<<HTML
        <div style="margin:8px 0 16px 0;">
          <p style="margin:0 0 6px 0; font-size:13px; color:#444;">Foto del usuario:</p>
          <img src="{$fotoEsc}" alt="Foto de {$nombreHtml}" width="80" height="80" style="border-radius:50%; display:block; border:1px solid #e6e6e6; object-fit:cover;">
        </div>
HTML;
    }

    $loginEsc = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

    // HTML del correo (acceso concedido)
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
                <p style="margin:6px 0 0 0; font-size:13px; opacity:.95;">Acceso concedido</p>
              </td>
            </tr>
            <tr>
              <td style="padding:24px; font-family:Arial, Helvetica, sans-serif; color:#222;">
                <p style="margin:0 0 12px 0; font-size:15px; line-height:1.5;">
                  ¡Hola, <strong>{$nombreHtml}</strong>! Un administrador aprobó tu cuenta en <strong>CESISS</strong>.
                </p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:16px 0;">
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px; width:40%;"><strong>Rol asignado</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$rolHtml}</td>
                  </tr>
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px;"><strong>Fecha y hora (CDMX)</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$fechaHora}</td>
                  </tr>
                </table>
                {$fotoBlock}
                {$ambitoHtml}
                <div style="margin:20px 0 8px 0;">
                  <a href="{$loginEsc}"
                     style="display:inline-block; padding:12px 18px; background:#3C92A6; color:#ffffff; text-decoration:none; font-weight:bold; border-radius:8px; font-size:14px;">
                     Iniciar sesión
                  </a>
                </div>
                <p style="margin:16px 0 0 0; font-size:12px; color:#6b7280;">
                  Si no solicitaste este acceso, por favor ignora este mensaje.
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

    // Enviar notificación (no bloquear si falla)
    try {
        enviarNotificacion($asunto, $html, $destinatarios);
    } catch (Throwable $e) {
        // Silenciar error de envío
    }

    header('Location: /sisec-ui/views/usuarios/pendientes.php?msg=' . urlencode('Usuario aprobado y notificado por correo.'));
    exit;
}

if ($accion === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('ID inválido.'));
        exit;
    }

    // Obtener datos del usuario para el correo y para borrar foto
    $res = $conexion->prepare("SELECT nombre, email, foto FROM usuarios WHERE id = ? AND esta_aprobado = 0");
    $res->bind_param('i', $id);
    $res->execute();
    $row = $res->get_result()->fetch_assoc();

    if (!$row) {
        header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('No se pudo eliminar; quizá ya fue aprobada o no existe.'));
        exit;
    }

    $nombreUsr = $row['nombre'] ?? 'Usuario';
    $emailUsr  = $row['email']  ?? null;
    $fotoUsr   = $row['foto']   ?? null;

    // Borrar foto si existe (antes de eliminar registro)
    if (!empty($fotoUsr)) {
        $ruta = __DIR__ . '/../uploads/usuarios/' . $fotoUsr;
        if (is_file($ruta)) @unlink($ruta);
    }

    // Eliminar registro (solo si estaba pendiente)
    $del = $conexion->prepare("DELETE FROM usuarios WHERE id = ? AND esta_aprobado = 0");
    $del->bind_param('i', $id);
    $del->execute();

    if ($del->affected_rows > 0) {
        /* ====== Correo “Solicitud rechazada” (no bloqueante) ====== */
        // Construcción de URL absolutas
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseApp = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\'); // p.ej. /sisec-ui
        if ($baseApp === '.' || $baseApp === '/') $baseApp = '';
        $baseUrl = $scheme . '://' . $host . $baseApp;

        $loginUrl = $baseUrl . '/login.php';

        // Fecha/Hora bonita (CDMX)
        date_default_timezone_set('America/Mexico_City');
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

        $nombreHtml = htmlspecialchars($nombreUsr, ENT_QUOTES, 'UTF-8');
        $loginEsc   = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        $asuntoR = 'CESISS: Solicitud rechazada';
        $destinatariosR = [];
        if (!empty($emailUsr)) $destinatariosR[] = $emailUsr;
        $destinatariosR[] = 'notificacionescesiss@gmail.com';

        $htmlR = <<<HTML
<!doctype html>
<html lang="es">
  <body style="margin:0; padding:0; background:#f5f7fb;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;">
      <tr>
        <td align="center" style="padding:24px 12px;">
          <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.06);">
            <tr>
              <td style="background:#b91c1c; padding:20px 24px; color:#ffffff; font-family:Arial, Helvetica, sans-serif;">
                <h2 style="margin:0; font-size:20px; letter-spacing:.3px;">CESISS</h2>
                <p style="margin:6px 0 0 0; font-size:13px; opacity:.95;">Solicitud rechazada</p>
              </td>
            </tr>
            <tr>
              <td style="padding:24px; font-family:Arial, Helvetica, sans-serif; color:#222;">
                <p style="margin:0 0 12px 0; font-size:15px; line-height:1.5;">
                  Hola, <strong>{$nombreHtml}</strong>.<br>
                  Lamentamos informarte que tu solicitud de acceso a <strong>CESISS</strong> fue <strong>rechazada</strong>.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:16px 0;">
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px; width:40%;"><strong>Fecha y hora (CDMX)</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$fechaHora}</td>
                  </tr>
                </table>

                <p style="margin:0 0 10px 0; font-size:14px; color:#374151;">
                  Si crees que se trata de un error o requieres más información, por favor contacta al administrador del sistema.
                </p>

                <div style="margin:20px 0 8px 0;">
                  <a href="{$loginEsc}"
                     style="display:inline-block; padding:12px 18px; background:#3C92A6; color:#ffffff; text-decoration:none; font-weight:bold; border-radius:8px; font-size:14px;">
                     Ir al inicio de sesión
                  </a>
                </div>

                <p style="margin:16px 0 0 0; font-size:12px; color:#6b7280;">
                  Este mensaje es informativo; no respondas a este correo.
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

        try {
            enviarNotificacion($asuntoR, $htmlR, $destinatariosR);
        } catch (Throwable $e) {
            // Silenciar error de envío
        }

        header('Location: /sisec-ui/views/usuarios/pendientes.php?msg=' . urlencode('Solicitud eliminada y usuario notificado.'));
        exit;
    } else {
        header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('No se pudo eliminar; quizá ya fue aprobada.'));
        exit;
    }
}

// Acción desconocida
header('Location: /sisec-ui/views/usuarios/pendientes.php?error=' . urlencode('Acción no válida.'));
exit;
