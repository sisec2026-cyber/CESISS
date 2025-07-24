<?php
$host = 'localhost';        // Servidor
$dbname = 'sisec'; // Tu base de datos
$usuario = 'root';          // Usuario de MySQL por defecto en XAMPP
$clave = '';                // Sin contraseña por defecto
$bd = 'sisec';              // Nombre de tu base de datos

$conexion = new mysqli($host, $usuario, $clave, $bd);

// Verificar conexión
if ($conexion->connect_error) {
    die("❌ Error de conexión: " . $conexion->connect_error);
}
?>

<?php
$host = 'localhost';
$dbname = 'sisec'; // Tu base de datos
$user = 'root';
$pass = ''; // Cambia si tu contraseña es diferente
$bd = 'sisec';              // Nombre de tu base de datos

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die("Error de conexión: " . $e->getMessage());
}