<?php
session_start();
function verificarAutenticacion() {
    if (!isset($_SESSION['usuario_id'])) {
        $urlActual = $_SERVER['REQUEST_URI'];
        header('Location: /sisec-ui/login.php?redirect=' . urlencode($urlActual));
        exit;
    }
}
function verificarRol($rolesPermitidos = []) {
    verificarAutenticacion(); // Primero verifica que esté autenticado
    if (!in_array($_SESSION['usuario_rol'], $rolesPermitidos)) {
        echo "Acceso no autorizado.";
        exit;
    }
}
/**Verificación automática usando permisos.php y mapa de páginas*/
function verificarRolAutomatico() {
    verificarAutenticacion();
    require_once __DIR__ . '/permisos.php';
    if (!isset($_SESSION['usuario_rol'])) {
        die("🚫 No has iniciado sesión");
    }
    $rol = strtolower($_SESSION['usuario_rol']); // minúsculas para evitar problemas
    $pagina = basename($_SERVER['PHP_SELF']);    // ejemplo: index.php
    // Mapa de páginas → permiso necesario
    $mapaPermisos = [
        'views/inicio/index.php'         => 'ver_index',
        'views/dispositivos/listar.php'        => 'ver_dispositivos',
        'views/dispositivos/device.php'        => 'editar_dispositivos',
        //'eliminar.php'      => 'eliminar_dispositivos',
        'usuarios/index.php'      => 'ver_usuarios',
        'usuarios/registrar.php' => 'agregar_usuarios',
    ];
    if (isset($mapaPermisos[$pagina])) {
        $permisoNecesario = $mapaPermisos[$pagina];
        if (!puede($permisoNecesario)) {
            die("🚫 No tienes permiso para acceder a esta página");
        }
    } else {
        // Si la página no está mapeada, por seguridad se bloquea
        die("🚫 Acceso denegado");
    }
}
?>