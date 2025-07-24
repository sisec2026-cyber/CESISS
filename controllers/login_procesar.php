<?php
session_start();
require_once __DIR__ . '/../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
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
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['usuario_rol'] = $usuario['rol'];
        $_SESSION['foto'] = !empty($usuario['foto']) 
            ? '/sisec-ui/uploads/usuarios/' . $usuario['foto'] 
            : null;

        if ($remember) {
            setcookie('usuario_id', $usuario['id'], time() + (7 * 24 * 60 * 60), "/");
        }

        $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
        if ($redirect) {
            $redirect = urldecode($redirect);
        } else {
            $redirect = ($usuario['rol'] === 'Invitado') 
                ? '/sisec-ui/views/dispositivos/listar.php'
                : '/sisec-ui/index.php';
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
