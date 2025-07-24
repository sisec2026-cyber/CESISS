<?php
include __DIR__ . '/../includes/conexion.php';

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
  case 'crear':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $nombre = $conexion->real_escape_string($_POST['nombre']);
      $clave = password_hash($_POST['clave'], PASSWORD_DEFAULT);
      $rol = $conexion->real_escape_string($_POST['rol']);

      $fotoNombre = null;
      if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $fotoNombre = uniqid('usr_') . '.' . $ext;
        $rutaDestino = __DIR__ . '/../uploads/usuarios/' . $fotoNombre;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino)) {
          die("Error al subir la foto.");
        }
      }

      $sql = "INSERT INTO usuarios (nombre, clave, rol, foto) VALUES ('$nombre', '$clave', '$rol', " . ($fotoNombre ? "'$fotoNombre'" : "NULL") . ")";
      if ($conexion->query($sql)) {
        header("Location: ../views/usuarios/index.php?msg=Usuario creado correctamente");
        exit;
      } else {
        die("Error al crear usuario: " . $conexion->error);
      }
    }
    break;

  case 'actualizar':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $id = intval($_POST['id']);
      $nombre = $conexion->real_escape_string($_POST['nombre']);
      $rol = $conexion->real_escape_string($_POST['rol']);
      $clave = $_POST['clave'] ?? '';

      // Obtener datos actuales del usuario
      $res = $conexion->query("SELECT foto FROM usuarios WHERE id = $id");
      $usuario = $res->fetch_assoc();

      $fotoNombre = $usuario['foto'] ?? null;

      // Actualizar foto si se subió nueva
      if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $fotoNombre = uniqid('usr_') . '.' . $ext;
        $rutaDestino = __DIR__ . '/../uploads/usuarios/' . $fotoNombre;

        // Eliminar la foto anterior
        if (!empty($usuario['foto'])) {
          $fotoAntigua = __DIR__ . '/../uploads/usuarios/' . $usuario['foto'];
          if (file_exists($fotoAntigua)) {
            unlink($fotoAntigua);
          }
        }

        move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);
      }

      // Si se envía contraseña nueva
      if (!empty($clave)) {
        $claveHash = password_hash($clave, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, rol = ?, clave = ?, foto = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $nombre, $rol, $claveHash, $fotoNombre, $id);
      } else {
        $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, rol = ?, foto = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nombre, $rol, $fotoNombre, $id);
      }

      $stmt->execute();
      header("Location: ../views/usuarios/index.php?msg=Usuario actualizado");
      exit;
    }
    break;

  case 'eliminar':
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
      // Eliminar foto si existe
      $res = $conexion->query("SELECT foto FROM usuarios WHERE id = $id");
      if ($res && $res->num_rows > 0) {
        $usuario = $res->fetch_assoc();
        if (!empty($usuario['foto'])) {
          $rutaFoto = __DIR__ . '/../uploads/usuarios/' . $usuario['foto'];
          if (file_exists($rutaFoto)) {
            unlink($rutaFoto);
          }
        }
      }

      $conexion->query("DELETE FROM usuarios WHERE id = $id");
      header("Location: ../views/usuarios/index.php?msg=Usuario eliminado");
      exit;
    }
    break;

  default:
    die("Acción no válida.");
}
?>