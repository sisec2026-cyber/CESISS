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


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Filtro de tráfico: Solo actualiza la BD si han pasado más de 60 segundos
if (isset($_SESSION['usuario_id'])) { // Asegúrate que 'usuario_id' sea el nombre de tu sesión
    $ahora = time();
    $id_user = $_SESSION['usuario_id'];
    
    if (!isset($_SESSION['last_db_update']) || ($ahora - $_SESSION['last_db_update']) > 60) {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET last_activity = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id_user]);
            
            // Guardamos en sesión el tiempo del último update exitoso
            $_SESSION['last_db_update'] = $ahora;
        } catch (Exception $e) {
            // Error silencioso para no interrumpir la navegación del usuario
            error_log("Error actualizando last_activity: " . $e->getMessage());
        }
    }
}