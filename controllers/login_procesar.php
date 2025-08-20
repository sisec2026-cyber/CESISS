<?php
session_start();
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/notificaciones_mailer.php';

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
            // agrega hasta 5 si quieres
        ];
        $asunto = 'SISEC: Inicio de sesión';
        $html   = '
            <h3>Inicio de sesión</h3>
            <p><b>Usuario:</b> '.htmlspecialchars($usuario['nombre']).'</p>
            <p><b>Rol:</b> '.htmlspecialchars($usuario['rol']).'</p>
            <p><b>Fecha/Hora:</b> '.date('Y-m-d H:i:s').'</p>
            <p><b>IP:</b> '.($_SERVER['REMOTE_ADDR'] ?? 'desconocida').'</p>
        ';
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
                $redirect = '/sisec-ui/views/dispositivos/listar.php';
                break;

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
