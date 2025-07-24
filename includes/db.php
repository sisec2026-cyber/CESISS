<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'sisec'; // Cambia esto si usas otro nombre

$conn = new mysqli($host, $user, $password, $database);

// Verifica conexión
if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

// Opcional: establecer charset UTF-8
$conn->set_charset('utf8');
