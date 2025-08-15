<?php
include __DIR__ . '/../includes/conexion.php';
include __DIR__ . '/../public/vendor/phpqrcode/qrlib.php'; // Asegúrate de que exista esta ruta

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
  case 'crear':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $equipo = $conexion->real_escape_string($_POST['equipo']);
      $modelo = $conexion->real_escape_string($_POST['modelo']);
      $sucursal = $conexion->real_escape_string($_POST['sucursal']);
      $estado = $conexion->real_escape_string($_POST['estado']);
      $fecha = $conexion->real_escape_string($_POST['fecha']);
      $observaciones = $conexion->real_escape_string($_POST['observaciones']);

      // Imagen principal
      $imagen = null;
      if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $imagen = uniqid('dev_') . '.' . $ext;
        $rutaImagen = __DIR__ . '/../public/uploads/' . $imagen;
        move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaImagen);
      }

      // Imagen adjunta
      $imagen2 = null;
      if (isset($_FILES['imagen2']) && $_FILES['imagen2']['error'] === UPLOAD_ERR_OK) {
        $ext2 = pathinfo($_FILES['imagen2']['name'], PATHINFO_EXTENSION);
        $imagen2 = uniqid('adj_') . '.' . $ext2;
        $rutaImagen2 = __DIR__ . '/../public/uploads/' . $imagen2;
        move_uploaded_file($_FILES['imagen2']['tmp_name'], $rutaImagen2);
      }

      // Guardar en base de datos sin QR aún
      $sql = "INSERT INTO dispositivos (equipo, modelo, sucursal, estado, fecha, observaciones, imagen, imagen2)
              VALUES ('$equipo', '$modelo', '$sucursal', '$estado', '$fecha', '$observaciones', " .
              ($imagen ? "'$imagen'" : "NULL") . ", " .
              ($imagen2 ? "'$imagen2'" : "NULL") . ")";

      if ($conexion->query($sql)) {
        $nuevoId = $conexion->insert_id;
        
        // === QR ===
        @mkdir(QR_DIR, 0775, true);
        $qrFile   = 'qr_' . $nuevoId . '.png';
        $qrPath   = rtrim(QR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $qrFile;
        $qrTarget = rtrim(BASE_URL, '/') . '/d/' . $nuevoId;  // URL corta y pública

        QRcode::png($qrTarget, $qrPath, QR_ECLEVEL_H, 10);

        // Guardar nombre de archivo (solo filename, NO dominio)
        $conexion->query("UPDATE dispositivos SET qr = '$qrFile' WHERE id = $nuevoId");

        header("Location: ../views/dispositivos/index.php?msg=Dispositivo registrado");
        exit;
      } else {
        die("Error al registrar dispositivo: " . $conexion->error);
      }
    }
    break;

  default:
    die("Acción no válida.");
}