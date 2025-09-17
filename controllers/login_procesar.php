<?php
session_start();
require_once __DIR__ . '/../includes/conexion.php';
// Si no enviarás correos en el login, no incluyas el mailer

/* Zona horaria correcta para CDMX */
date_default_timezone_set('America/Mexico_City');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// Soportar login por correo o por nombre de usuario
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

    // 6) Cookie "Recuérdame" con flags seguros
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

} catch (Throwable $e) {
    // error_log("LOGIN ERROR: " . $e->getMessage());
    header('Location: ../login.php?error=' . urlencode("Ocurrió un error al iniciar sesión."));
    exit;
}
