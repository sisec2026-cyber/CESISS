<?php
session_start();
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/notificaciones_mailer.php'; // ✅ Se agrega para enviar correo

date_default_timezone_set('America/Mexico_City');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}



$identificador = trim($_POST['nombre'] ?? '');
$password      = $_POST['password'] ?? '';
$remember      = isset($_POST['remember_me']);

if ($identificador === '' || $password === '') {
    header('Location: ../login.php?error=' . urlencode("Por favor, completa todos los campos."));
    exit;
}

try {
    // 1) Buscar SOLO usuarios aprobados
    $stmt = $pdo->prepare("
        SELECT id, nombre, email, rol, clave, foto, esta_aprobado
        FROM usuarios
        WHERE (email = ? OR nombre = ?)
          AND esta_aprobado = 1
        LIMIT 1
    ");
    $stmt->execute([$identificador, $identificador]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // 2) ¿Existe pero PENDIENTE?
        $stPend = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE (email = ? OR nombre = ?)
              AND esta_aprobado = 0
            LIMIT 1
        ");
        $stPend->execute([$identificador, $identificador]);

        if ($stPend->fetch(PDO::FETCH_ASSOC)) {
            header('Location: ../login.php?error=' . urlencode("Tu cuenta está pendiente de aprobación por un administrador."));
        } else {
            header('Location: ../login.php?error=' . urlencode("Usuario o contraseña incorrectos."));
        }
        exit;
    }

    // 3) Seguridad extra por si el rol quedó "Pendiente" por error
    if (strcasecmp($usuario['rol'], 'Pendiente') === 0) {
        header('Location: ../login.php?error=' . urlencode("Tu cuenta está pendiente de aprobación por un administrador."));
        exit;
    }

    // 4) Verificar contraseña
    if (!password_verify($password, $usuario['clave'])) {
        header('Location: ../login.php?error=' . urlencode("Usuario o contraseña incorrectos."));
        exit;
    }

    // 5) Autenticación OK → endurecer sesión
    session_regenerate_id(true);
    $_SESSION['usuario_id']  = (int)$usuario['id'];
    $_SESSION['nombre']      = $usuario['nombre'];
    $_SESSION['usuario_rol'] = $usuario['rol'];
    $_SESSION['email']       = $usuario['email'] ?? null;
    $_SESSION['foto']        = !empty($usuario['foto']) ? '/sisec-ui/uploads/usuarios/' . $usuario['foto'] : null;

    $stmt = $pdo->prepare("
      INSERT INTO sesiones_usuarios (
        usuario_id,
        usuario_nombre,
        rol,
        evento,
        fecha_evento,
        ip,
        user_agent
      ) VALUES (
        :usuario_id,
        :usuario_nombre,
        :rol,
        'inicio',
        NOW(),
        :ip,
        :user_agent
      )
    ");

    $stmt->execute([
      ':usuario_id' => $_SESSION['usuario_id'],
      ':usuario_nombre' => $_SESSION['nombre'],
      ':rol' => $_SESSION['usuario_rol'],
      ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
      ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // ✅ === Notificación de inicio de sesión ===
    $destinatarios = ['notificacionescesiss@gmail.com']; // puedes agregar más correos si deseas
    $fechaHora = date('Y-m-d H:i:s');

    $asunto = 'CESISS: Nuevo inicio de sesión';
    $htmlCorreo = '
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
      <div style="background:#0ea5e9;color:#fff;padding:14px 18px">
        <h2 style="margin:0;font-size:18px">Nuevo inicio de sesión</h2>
      </div>
      <div style="padding:16px">
        <p>El usuario <b>' . htmlspecialchars($_SESSION['nombre']) . '</b> ha iniciado sesión en el sistema <b>CESISS</b>.</p>
        <table cellspacing="0" cellpadding="6" style="border-collapse:collapse;width:100%;border:1px solid #e5e7eb">
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb"><b>Usuario</b></td><td>' . htmlspecialchars($_SESSION['nombre']) . '</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb"><b>Rol</b></td><td>' . htmlspecialchars($_SESSION['usuario_rol']) . '</td></tr>
          <tr><td style="background:#f9fafb;border:1px solid #e5e7eb"><b>Fecha/Hora</b></td><td>' . htmlspecialchars($fechaHora) . '</td></tr>
        </table>
        <p style="margin-top:14px;font-size:12px;color:#6b7280">Este mensaje fue generado automáticamente por CESISS.</p>
      </div>
    </div>';

    enviarNotificacion($asunto, $htmlCorreo, $destinatarios);
    // ✅ =======================================

    // 6) Cookie "Recuérdame"
    if ($remember) {
        $oneWeek = time() + (7 * 24 * 60 * 60);
        setcookie(
            'usuario_id',
            (string)$usuario['id'],
            [
                'expires'  => $oneWeek,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    // 7) Redirección por rol
    switch ($usuario['rol']) {
        case 'Superadmin':
        case 'Mantenimientos':
        case 'Distrital':
        case 'Capturista':
        case 'Administrador':
        case 'Técnico':
            $redirect = '/sisec-ui/views/inicio/index.php';
            break;
        case 'Prevencion':
        case 'Monitorista':
        default:
            $redirect = '/sisec-ui/views/dispositivos/listar.php';
            break;
    }

    header("Location: $redirect");
    exit;

} catch (Throwable $e) {
    header('Location: ../login.php?error=' . urlencode("Ocurrió un error al iniciar sesión."));
    exit;
}