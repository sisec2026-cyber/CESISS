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
?>