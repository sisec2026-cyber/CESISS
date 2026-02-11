<?php
// /sisec-ui/controllers/UserController.php
include __DIR__ . '/../includes/conexion.php';

$accion = $_REQUEST['accion'] ?? '';

/* ========= Helpers ========= */
function validar_password_servidor(string $pwd): array {
    $errores = [];
    if (strlen($pwd) < 8)               $errores[] = "mínimo 8 caracteres";
    if (!preg_match('/[A-Z]/', $pwd))   $errores[] = "una mayúscula";
    if (!preg_match('/[a-z]/', $pwd))   $errores[] = "una minúscula";
    if (!preg_match('/\d/', $pwd))      $errores[] = "un número";
    if (!preg_match('/[!@#$%^&*(),.?\":{}|<>]/', $pwd)) $errores[] = "un carácter especial";
    return $errores;
}

function subir_foto_usuario(?array $file): ?string {
    if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $permitidas = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $permitidas, true)) {
        return null;
    }
    $dir = __DIR__ . '/../uploads/usuarios';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $nombre = uniqid('usr_', true) . '.' . $ext;
    $dest = $dir . '/' . $nombre;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return $nombre;
}

/* ========= Router ========= */
switch ($accion) {
    case 'crear':
    case 'actualizar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Campos base
            $id        = intval($_POST['id'] ?? 0);
            $nombre    = trim($_POST['nombre'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $cargo     = trim($_POST['cargo'] ?? '');
            $empresa   = trim($_POST['empresa'] ?? '');
            $rol_post  = trim($_POST['rol'] ?? ''); // se usa sólo en actualizar

            // Jerarquía opcional
            $region     = ($_POST['region'] ?? '') !== '' ? intval($_POST['region']) : null;
            $ciudad     = ($_POST['ciudad'] ?? '') !== '' ? intval($_POST['ciudad']) : null;
            $municipio  = ($_POST['municipio'] ?? '') !== '' ? intval($_POST['municipio']) : null;
            $sucursal   = ($_POST['sucursal'] ?? '') !== '' ? intval($_POST['sucursal']) : null;

            // Credenciales y seguridad
            $clave = trim($_POST['clave'] ?? '');
            $pregunta_seguridad  = trim($_POST['pregunta_seguridad'] ?? '');
            $respuesta_seguridad = trim($_POST['respuesta_seguridad'] ?? '');
            $respuesta_hash = $respuesta_seguridad !== '' ? password_hash($respuesta_seguridad, PASSWORD_DEFAULT) : null;

            // Foto
            $fotoNueva = subir_foto_usuario($_FILES['foto'] ?? null);
            $fotoNombre = null;
            $fotoAnterior = null;

            if ($accion === 'actualizar' && $id > 0) {
                $resU = $conexion->prepare("SELECT foto FROM usuarios WHERE id = ?");
                $resU->bind_param('i', $id);
                $resU->execute();
                $rowU = $resU->get_result()->fetch_assoc();
                $fotoAnterior = $rowU['foto'] ?? null;
                $fotoNombre = $fotoAnterior;
                if ($fotoNueva) {
                    if (!empty($fotoAnterior)) {
                        $rutaAnterior = __DIR__ . '/../uploads/usuarios/' . $fotoAnterior;
                        if (is_file($rutaAnterior)) @unlink($rutaAnterior);
                    }
                    $fotoNombre = $fotoNueva;
                }
            } else {
                if ($fotoNueva) $fotoNombre = $fotoNueva;
            }

            /* ------------------ CREAR ------------------ */
            if ($accion === 'crear') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    header("Location: ../views/usuarios/crearuser.php?error=Correo inválido");
                    exit;
                }
                if ($nombre === '' || $cargo === '' || $empresa === '') {
                    header("Location: ../views/usuarios/crearuser.php?error=Faltan campos obligatorios");
                    exit;
                }
                if ($clave === '') {
                    header("Location: ../views/usuarios/crearuser.php?error=La contraseña es obligatoria");
                    exit;
                }
                $erroresPwd = validar_password_servidor($clave);
                if (!empty($erroresPwd)) {
                    $msg = "Contraseña no válida: " . implode(", ", $erroresPwd);
                    header("Location: ../views/usuarios/crearuser.php?error=" . urlencode($msg));
                    exit;
                }

                // ¿Correo ya existe?
                $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $existe = $stmt->get_result()->fetch_assoc();
                if ($existe) {
                    header("Location: ../views/usuarios/crearuser.php?error=El correo ya está registrado");
                    exit;
                }

                // Hash de password
                $claveHash = password_hash($clave, PASSWORD_DEFAULT);

                // Forzar flujo de aprobación
                $rolFinal = 'Pendiente';
                $estaAprobado = 0;

                // *** FIX: variables para bind (nada de expresiones) ***
                $pregSegVar  = ($pregunta_seguridad !== '') ? $pregunta_seguridad : null;
                $respHashVar = $respuesta_hash ?: null;
                $fotoVar     = $fotoNombre ?: null;

                // INSERT
                $sql = "INSERT INTO usuarios
                        (nombre, email, cargo, empresa, clave, rol, pregunta_seguridad, respuesta_seguridad_hash, foto,
                         region, ciudad, municipio, sucursal, esta_aprobado, creado_el)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
                $stmt = $conexion->prepare($sql);
                if (!$stmt) {
                    die("Error en prepare (crear): " . $conexion->error);
                }

                // 14 placeholders -> 14 tipos (9 's' + 5 'i')
                $types = 'sssssssssiiiii';
                $stmt->bind_param(
                    $types,
                    $nombre,
                    $email,
                    $cargo,
                    $empresa,
                    $claveHash,
                    $rolFinal,
                    $pregSegVar,
                    $respHashVar,
                    $fotoVar,
                    $region,
                    $ciudad,
                    $municipio,
                    $sucursal,
                    $estaAprobado
                );

                if (!$stmt->execute()) {
                    die("Error al crear usuario: " . $stmt->error);
                }

                header("Location: ../login.php?msg=" . urlencode("Solicitud enviada. Un administrador debe aprobar tu acceso."));
                exit;
            }

            /* ------------------ ACTUALIZAR ------------------ */
            else {
                if ($id <= 0) {
                    die("ID inválido para actualización.");
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    header("Location: ../views/usuarios/index.php?error=Correo inválido");
                    exit;
                }

                // Email duplicado en otro usuario
                $chk = $conexion->prepare("SELECT id FROM usuarios WHERE email = ? AND id <> ?");
                $chk->bind_param('si', $email, $id);
                $chk->execute();
                if ($chk->get_result()->fetch_assoc()) {
                    header("Location: ../views/usuarios/index.php?error=" . urlencode("El correo ya está en uso por otro usuario"));
                    exit;
                }

                // Construcción dinámica de SET
                $campos = [];
                $params = [];
                $tipos  = '';

                $campos[] = "nombre = ?";   $params[] = $nombre;  $tipos .= 's';
                $campos[] = "email = ?";    $params[] = $email;   $tipos .= 's';
                $campos[] = "cargo = ?";    $params[] = $cargo;   $tipos .= 's';
                $campos[] = "empresa = ?";  $params[] = $empresa; $tipos .= 's';

                $campos[] = "rol = ?";      $params[] = $rol_post; $tipos .= 's';

                // numéricos (NULL permitido)
                $campos[] = "region = ?";     $params[] = $region;    $tipos .= 'i';
                $campos[] = "ciudad = ?";     $params[] = $ciudad;    $tipos .= 'i';
                $campos[] = "municipio = ?";  $params[] = $municipio; $tipos .= 'i';
                $campos[] = "sucursal = ?";   $params[] = $sucursal;  $tipos .= 'i';

                if (!empty($clave)) {
                    $erroresPwd = validar_password_servidor($clave);
                    if (!empty($erroresPwd)) {
                        $msg = "Contraseña no válida: " . implode(", ", $erroresPwd);
                        header("Location: ../views/usuarios/index.php?error=" . urlencode($msg));
                        exit;
                    }
                    $campos[] = "clave = ?";
                    $params[] = password_hash($clave, PASSWORD_DEFAULT);
                    $tipos .= 's';
                }

                if ($pregunta_seguridad !== '') {
                    $campos[] = "pregunta_seguridad = ?";
                    $params[] = $pregunta_seguridad;
                    $tipos .= 's';
                }

                if ($respuesta_hash) {
                    $campos[] = "respuesta_seguridad_hash = ?";
                    $params[] = $respuesta_hash;
                    $tipos .= 's';
                }

                if ($fotoNombre) {
                    $campos[] = "foto = ?";
                    $params[] = $fotoNombre;
                    $tipos .= 's';
                }

                $sql = "UPDATE usuarios SET " . implode(', ', $campos) . ", actualizado_el = NOW() WHERE id = ?";
                $params[] = $id;
                $tipos   .= 'i';

                $stmt = $conexion->prepare($sql);
                if (!$stmt) {
                    die("Error en prepare (actualizar): " . $conexion->error);
                }

                $stmt->bind_param($tipos, ...$params);
                if (!$stmt->execute()) {
                    die("Error al actualizar usuario: " . $stmt->error);
                }

                header("Location: ../views/usuarios/index.php?msg=Usuario actualizado");
                exit;
            }
        }
        break;

    case 'eliminar':
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $res = $conexion->prepare("SELECT foto FROM usuarios WHERE id = ?");
            $res->bind_param('i', $id);
            $res->execute();
            $usuario = $res->get_result()->fetch_assoc();
            if ($usuario && !empty($usuario['foto'])) {
                $rutaFoto = __DIR__ . '/../uploads/usuarios/' . $usuario['foto'];
                if (is_file($rutaFoto)) @unlink($rutaFoto);
            }
            $del = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
            $del->bind_param('i', $id);
            $del->execute();
            header("Location: ../views/usuarios/index.php?msg=Usuario eliminado");
            exit;
        }
        break;

    /* ===== Endpoints JSON ===== */
    case 'regiones':
        $res = $conexion->query("SELECT id, nom_region AS nombre FROM regiones WHERE id IN (1,2,3,4,6)");
        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;

    case 'ciudades':
        if (isset($_GET['region'])) {
            $regionId = (int)$_GET['region'];
            $res = $conexion->query("SELECT id, nom_ciudad AS nombre FROM ciudades WHERE region_id = $regionId");
            $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
        break;

    case 'municipios':
        if (isset($_GET['ciudad'])) {
            $ciudadId = (int)$_GET['ciudad'];
            $res = $conexion->query("SELECT id, nom_municipio AS nombre FROM municipios WHERE ciudad_id = $ciudadId");
            $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
        break;

    case 'sucursales':
        if (isset($_GET['municipio'])) {
            $municipioId = (int)$_GET['municipio'];
            $res = $conexion->query("SELECT id, nom_sucursal AS nombre FROM sucursales WHERE municipio_id = $municipioId");
            $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
        break;

    default:
        die("Acción no válida.");
}