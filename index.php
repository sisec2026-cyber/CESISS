<?php

header("Location: views/inicio/index.php");
// index.php principal: carga vistas según ?view=...

$view = $_GET['views'] ?? 'dashboard'; // Vista por defecto


$allowedViews = [
  'dashboard'           => 'views/inicio/index.php',
  'registro'            => 'views/dispositivos/registro.php',
  'listar'              => 'views/dispositivos/listar.php',
  'usuarios'            => 'views/usuarios/index.php',
  'usuarios_registrar'  => 'views/usuarios/registrar.php',
  'usuarios_editar'     => 'views/usuarios/editar.php',
  // Agrega aquí más vistas cuando las tengas
];

if (array_key_exists($view, $allowedViews)) {
  include $allowedViews[$view];
} else {
  http_response_code(404);
  echo "<h2 style='padding:2rem;'>❌ Vista no encontrada</h2>";
}