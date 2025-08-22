<?php
include __DIR__ . '/../includes/conexion.php';

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    // CREAR o ACTUALIZAR USUARIO
    case 'crear':
case 'actualizar':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = intval($_POST['id'] ?? 0); // 0 si es crear
        $nombre   = $conexion->real_escape_string($_POST['nombre']);
        $email    = $conexion->real_escape_string($_POST['email'] ?? '');
        $cargo    = $conexion->real_escape_string($_POST['cargo'] ?? '');
        $empresa  = $conexion->real_escape_string($_POST['empresa'] ?? '');
        $rol      = $conexion->real_escape_string($_POST['rol']);
        $region   = !empty($_POST['region']) ? intval($_POST['region']) : null;
        $ciudad   = !empty($_POST['ciudad']) ? intval($_POST['ciudad']) : null;
        $municipio= !empty($_POST['municipio']) ? intval($_POST['municipio']) : null;
        $sucursal = !empty($_POST['sucursal']) ? intval($_POST['sucursal']) : null;
        $clave    = $_POST['clave'] ?? '';
        $pregunta_seguridad = $conexion->real_escape_string($_POST['pregunta_seguridad'] ?? '');
        $respuesta_seguridad = $_POST['respuesta_seguridad'] ?? '';
        $respuesta_hash = $respuesta_seguridad !== '' ? password_hash($respuesta_seguridad, PASSWORD_DEFAULT) : null;
        
        // Manejo de foto
        $fotoNombre = null;
        if($id > 0){
            $res = $conexion->query("SELECT foto FROM usuarios WHERE id = $id");
            $usuario = $res->fetch_assoc();
            $fotoNombre = $usuario['foto'] ?? null;
        }
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $fotoNombre = uniqid('usr_') . '.' . $ext;
            $rutaDestino = __DIR__ . '/../uploads/usuarios/' . $fotoNombre;
            if (!empty($usuario['foto']) && file_exists(__DIR__ . '/../uploads/usuarios/' . $usuario['foto'])) {
                unlink(__DIR__ . '/../uploads/usuarios/' . $usuario['foto']);
            }
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino)) {
                die("Error al subir la foto.");
            }
        }

        // CREAR
        if($accion === 'crear'){
            if(empty($clave)) die("La contraseña es obligatoria.");
            $claveHash = password_hash($clave, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios 
                (nombre, email, cargo, empresa, clave, rol, pregunta_seguridad, respuesta_seguridad_hash, foto, region, ciudad, municipio, sucursal) 
                VALUES (
                    '$nombre', 
                    '$email', 
                    '$cargo', 
                    '$empresa', 
                    '$claveHash', 
                    '$rol', 
                    '$pregunta_seguridad', 
                    " . ($respuesta_hash ? "'$respuesta_hash'" : "NULL") . ", 
                    " . ($fotoNombre ? "'$fotoNombre'" : "NULL") . ", 
                    " . ($region ?? "NULL") . ", 
                    " . ($ciudad ?? "NULL") . ", 
                    " . ($municipio ?? "NULL") . ", 
                    " . ($sucursal ?? "NULL") . "
                )";
            if($conexion->query($sql)){
                header("Location: ../views/usuarios/index.php?msg=Usuario creado correctamente");
                exit;
            } else die("Error al crear usuario: ".$conexion->error);
        } else {
            // ACTUALIZAR
            $campos = [];
            $params = [];
            $tipos = '';

            $campos[] = "nombre = ?";    $params[] = $nombre;   $tipos .= 's';
            $campos[] = "email = ?";     $params[] = $email;    $tipos .= 's';
            $campos[] = "cargo = ?";     $params[] = $cargo;    $tipos .= 's';
            $campos[] = "empresa = ?";   $params[] = $empresa;  $tipos .= 's';
            $campos[] = "rol = ?";       $params[] = $rol;      $tipos .= 's';
            $campos[] = "region = ?";    $params[] = $region;   $tipos .= 'i';
            $campos[] = "ciudad = ?";    $params[] = $ciudad;   $tipos .= 'i';
            $campos[] = "municipio = ?"; $params[] = $municipio;$tipos .= 'i';
            $campos[] = "sucursal = ?";  $params[] = $sucursal; $tipos .= 'i';

            if(!empty($clave)){
                $campos[] = "clave = ?";
                $params[] = password_hash($clave, PASSWORD_DEFAULT);
                $tipos .= 's';
            }
            if($pregunta_seguridad !== ''){
                $campos[] = "pregunta_seguridad = ?";
                $params[] = $pregunta_seguridad;
                $tipos .= 's';
            }
            if($respuesta_hash){
                $campos[] = "respuesta_seguridad_hash = ?";
                $params[] = $respuesta_hash;
                $tipos .= 's';
            }
            if($fotoNombre){
                $campos[] = "foto = ?";
                $params[] = $fotoNombre;
                $tipos .= 's';
            }

            $params[] = $id;
            $tipos .= 'i';

            $sql = "UPDATE usuarios SET ".implode(',', $campos)." WHERE id = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param($tipos, ...$params);
            $stmt->execute();
            header("Location: ../views/usuarios/index.php?msg=Usuario actualizado");
            exit;
        }
    }
    break;

            // ELIMINAR USUARIO
            
            case 'eliminar':
                $id = intval($_GET['id'] ?? 0);
                if ($id > 0) {
                    $res = $conexion->query("SELECT foto FROM usuarios WHERE id = $id");
                    if ($res && $res->num_rows > 0) {
                        $usuario = $res->fetch_assoc();
                        if (!empty($usuario['foto'])) {
                            $rutaFoto = __DIR__ . '/../uploads/usuarios/' . $usuario['foto'];
                            if (file_exists($rutaFoto)) unlink($rutaFoto);
                        }
                    }
                    $conexion->query("DELETE FROM usuarios WHERE id = $id");
                    header("Location: ../views/usuarios/index.php?msg=Usuario eliminado");
                    exit;
                }
                break;
            case 'ciudades':
                if(isset($_GET['region'])){
                    $regionId = (int)$_GET['region'];
                    $res = $conexion->query("SELECT id, nom_ciudad AS nombre FROM ciudades WHERE region_id = $regionId");
                    $data = $res->fetch_all(MYSQLI_ASSOC);
                    header('Content-Type: application/json');
                    echo json_encode($data);
                    exit;
                }
                break;
            
            case 'municipios':
                if(isset($_GET['ciudad'])){
                    $ciudadId = (int)$_GET['ciudad'];
                    $res = $conexion->query("SELECT id, nom_municipio AS nombre FROM municipios WHERE ciudad_id = $ciudadId");
                    $data = $res->fetch_all(MYSQLI_ASSOC);
                    header('Content-Type: application/json');
                    echo json_encode($data);
                    exit;
                }
                break;
            
            case 'sucursales':
                if(isset($_GET['municipio'])){
                    $municipioId = (int)$_GET['municipio'];
                    $res = $conexion->query("SELECT id, nom_sucursal AS nombre FROM sucursales WHERE municipio_id = $municipioId");
                    $data = $res->fetch_all(MYSQLI_ASSOC);
                    header('Content-Type: application/json');
                    echo json_encode($data);
                exit;
            }
            break;
            default:
            die("Acción no válida.");
}