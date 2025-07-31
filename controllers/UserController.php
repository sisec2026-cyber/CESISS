<?php
include __DIR__ . '/../includes/conexion.php';

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
                                                  // CREAR
  case 'crear':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $nombre = $conexion->real_escape_string($_POST['nombre']);
      $clave = password_hash($_POST['clave'], PASSWORD_DEFAULT);
      $rol = $conexion->real_escape_string($_POST['rol']);

          // NUEVO: capturar pregunta y respuesta
       $pregunta_seguridad = $conexion->real_escape_string($_POST['pregunta_seguridad']);
       $respuesta_seguridad = $_POST['respuesta_seguridad']; // no escapes, luego hash
       $respuesta_hash = password_hash($respuesta_seguridad, PASSWORD_DEFAULT);

      $fotoNombre = null;
      if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $fotoNombre = uniqid('usr_') . '.' . $ext;
        $rutaDestino = __DIR__ . '/../uploads/usuarios/' . $fotoNombre;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino)) {
          die("Error al subir la foto.");
        }
      }

         $sql = "INSERT INTO usuarios (nombre, clave, rol, pregunta_seguridad, respuesta_seguridad_hash, foto) VALUES ('$nombre', '$clave', '$rol', '$pregunta_seguridad', '$respuesta_hash', " . ($fotoNombre ? "'$fotoNombre'" : "NULL") . ")";
        if ($conexion->query($sql)) {
        header("Location: ../views/usuarios/index.php?msg=Usuario creado correctamente");
        exit;
      } else {
        die("Error al crear usuario: " . $conexion->error);
      }
    }
    break;

                                        // ACTUALIZAR
case 'actualizar':
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $rol = $conexion->real_escape_string($_POST['rol']);
    $clave = $_POST['clave'] ?? '';

    // NUEVO: Pregunta y respuesta (respuesta es opcional para cambiar)
    $pregunta_seguridad = $conexion->real_escape_string($_POST['pregunta_seguridad'] ?? '');
    $respuesta_seguridad = $_POST['respuesta_seguridad'] ?? '';
    if ($respuesta_seguridad !== '') {
      $respuesta_hash = password_hash($respuesta_seguridad, PASSWORD_DEFAULT);
    }

    // Obtener datos actuales
    $res = $conexion->query("SELECT foto FROM usuarios WHERE id = $id");
    $usuario = $res->fetch_assoc();
    $fotoNombre = $usuario['foto'] ?? null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
      $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
      $fotoNombre = uniqid('usr_') . '.' . $ext;
      $rutaDestino = __DIR__ . '/../uploads/usuarios/' . $fotoNombre;

      if (!empty($usuario['foto'])) {
        $fotoAntigua = __DIR__ . '/../uploads/usuarios/' . $usuario['foto'];
        if (file_exists($fotoAntigua)) {
          unlink($fotoAntigua);
        }
      }

      move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);
    }

    if (!empty($clave) && $respuesta_seguridad !== '') {
      $claveHash = password_hash($clave, PASSWORD_DEFAULT);
      $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, rol = ?, clave = ?, pregunta_seguridad = ?, respuesta_seguridad_hash = ?, foto = ? WHERE id = ?");
      $stmt->bind_param("ssssssi", $nombre, $rol, $claveHash, $pregunta_seguridad, $respuesta_hash, $fotoNombre, $id);
    } elseif (!empty($clave)) {
      $claveHash = password_hash($clave, PASSWORD_DEFAULT);
      $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, rol = ?, clave = ?, pregunta_seguridad = ?, foto = ? WHERE id = ?");
      $stmt->bind_param("sssssi", $nombre, $rol, $claveHash, $pregunta_seguridad, $fotoNombre, $id);
    } elseif ($respuesta_seguridad !== '') {
      $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, rol = ?, pregunta_seguridad = ?, respuesta_seguridad_hash = ?, foto = ? WHERE id = ?");
      $stmt->bind_param("sssssi", $nombre, $rol, $pregunta_seguridad, $respuesta_hash, $fotoNombre, $id);
    } else {
      $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, rol = ?, pregunta_seguridad = ?, foto = ? WHERE id = ?");
      $stmt->bind_param("ssssi", $nombre, $rol, $pregunta_seguridad, $fotoNombre, $id);
    }

    $stmt->execute();
    header("Location: ../views/usuarios/index.php?msg=Usuario actualizado");
    exit;
  }
  break;

                                        // ELIMINAR               
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