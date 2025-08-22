<?php
// views/dispositivos/actualizar.php
session_start();
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Técnico','Mantenimientos','Capturista']);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/notificaciones_mailer.php';

date_default_timezone_set('America/Mexico_City');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: listar.php');
  exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  die('ID inválido.');
}

/* ========= Helpers ========= */
function estado_label($v) {
  $map = [1=>'Activo', 2=>'En mantenimiento', 3=>'Desactivado'];
  return $map[(int)$v] ?? (string)$v;
}

function fetch_nombre($conn, $sql, $id) {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_row();
  $stmt->close();
  return $res ? $res[0] : null;
}

function save_upload($field, $oldFilename, $uploadsDir) {
  if (!isset($_FILES[$field]) || empty($_FILES[$field]['tmp_name'])) {
    return $oldFilename; // conservar
  }
  $f = $_FILES[$field];
  if ($f['error'] !== UPLOAD_ERR_OK) {
    return $oldFilename; // opcional: manejar error
  }
  @mkdir($uploadsDir, 0775, true);

  $tmp = $f['tmp_name'];
  $info = @getimagesize($tmp);
  if (!$info) return $oldFilename;

  $mime = $info['mime'] ?? '';
  $ext = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => null
  };
  if (!$ext) return $oldFilename;

  $base = bin2hex(random_bytes(8)) . '_' . time();
  $filename = $base . '.' . $ext;
  $dest = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

  if (!move_uploaded_file($tmp, $dest)) {
    return $oldFilename;
  }
  return $filename;
}

/* ========= 1) Traer registro actual (OLD) ========= */
$stmt = $conn->prepare("SELECT * FROM dispositivos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$old) { die('Dispositivo no encontrado.'); }

/* ========= 2) Entradas del formulario ========= */
$equipoId = (int)($_POST['equipo'] ?? 0);
$modeloId = (int)($_POST['modelo'] ?? 0);
$serie    = trim($_POST['serie'] ?? '');
$mac      = trim($_POST['mac'] ?? '');
$servidor = trim($_POST['servidor'] ?? '');
$vms      = trim($_POST['vms'] ?? '');
$user     = trim($_POST['usuario'] ?? '');
$pass     = (string)($_POST['contrasena'] ?? ''); // no enmascarar al guardar
$switch   = trim($_POST['switch'] ?? '');
$puerto   = trim($_POST['puerto'] ?? '');
$sucursal = (int)($_POST['sucursal'] ?? 0);
$area     = trim($_POST['area'] ?? '');
$estado   = (int)($_POST['estado'] ?? 1);
$fecha    = trim($_POST['fecha'] ?? '');
$obs      = trim($_POST['observaciones'] ?? '');

$equipoEditMode = (int)($_POST['equipo_edit_mode'] ?? 0);
$equipoEditId   = (int)($_POST['equipo_edit_id'] ?? 0);
$equipoNombreEd = trim($_POST['equipo_nombre_edit'] ?? '');

$modeloEditMode = (int)($_POST['modelo_edit_mode'] ?? 0);
$modeloEditId   = (int)($_POST['modelo_edit_id'] ?? 0);
$modeloNombreEd = trim($_POST['modelo_nombre_edit'] ?? '');

/* ========= 3) Renombrar catálogo (si aplica) ========= */
if ($equipoEditMode === 1 && $equipoEditId > 0 && $equipoNombreEd !== '') {
  $upd = $conn->prepare("UPDATE equipos SET nom_equipo = ? WHERE id = ?");
  $upd->bind_param("si", $equipoNombreEd, $equipoEditId);
  $upd->execute();
  $upd->close();
}
if ($modeloEditMode === 1 && $modeloEditId > 0 && $modeloNombreEd !== '') {
  $upd = $conn->prepare("UPDATE modelos SET num_modelos = ? WHERE id = ?");
  $upd->bind_param("si", $modeloNombreEd, $modeloEditId);
  $upd->execute();
  $upd->close();
}

/* ========= 4) Subidas de imagen ========= */
$uploadsDir = __DIR__ . '/../../public/uploads';
$imagen  = save_upload('imagen',  $old['imagen']  ?? null, $uploadsDir);
$imagen2 = save_upload('imagen2', $old['imagen2'] ?? null, $uploadsDir);
$imagen3 = save_upload('imagen3', $old['imagen3'] ?? null, $uploadsDir);

/* ========= 5) Actualizar dispositivo ========= */
$sql = "UPDATE dispositivos
        SET equipo=?, modelo=?, serie=?, mac=?, servidor=?, vms=?, `user`=?, `pass`=?,
            `switch`=?, puerto=?, sucursal=?, area=?, estado=?, fecha=?, observaciones=?,
            imagen=?, imagen2=?, imagen3=?
        WHERE id=?";
$stmt = $conn->prepare($sql);
$types = 'iissssssss' . 'i' . 's' . 'i' . 'sssss' . 'i'; // = iissssssssisisssssi
$stmt->bind_param(
  $types,
  $equipoId, $modeloId, $serie, $mac, $servidor, $vms, $user, $pass,
  $switch, $puerto, $sucursal, $area, $estado, $fecha, $obs,
  $imagen, $imagen2, $imagen3,
  $id
);
$stmt->execute();
$stmt->close();

/* ========= 6) Preparar correo con DIFERENCIAS ========= */
$editorNombre = $_SESSION['nombre'] ?? 'desconocido';
$editorRol    = $_SESSION['usuario_rol'] ?? '—';
$editorFoto   = $_SESSION['foto'] ?? null;

/* Nombre visibles (old/new) */
$oldEquipoNom = fetch_nombre($conn, "SELECT nom_equipo FROM equipos WHERE id=?", (int)($old['equipo'] ?? 0));
$newEquipoNom = fetch_nombre($conn, "SELECT nom_equipo FROM equipos WHERE id=?", $equipoId);

$oldModeloNom = fetch_nombre($conn, "SELECT num_modelos FROM modelos WHERE id=?", (int)($old['modelo'] ?? 0));
$newModeloNom = fetch_nombre($conn, "SELECT num_modelos FROM modelos WHERE id=?", $modeloId);

$oldSucursalNom = fetch_nombre($conn, "SELECT nom_sucursal FROM sucursales WHERE id=?", (int)($old['sucursal'] ?? 0));
$newSucursalNom = fetch_nombre($conn, "SELECT nom_sucursal FROM sucursales WHERE id=?", $sucursal);

$oldEstadoNom = estado_label($old['estado'] ?? '');
$newEstadoNom = estado_label($estado);

/* Construir tabla de cambios (solo cambios) */
$diffs = [];

$push = function($campo, $antes, $ahora) use (&$diffs) {
  $a = (string)($antes ?? '');
  $n = (string)($ahora ?? '');
  if ($a !== $n) {
    $diffs[] = ['campo'=>$campo, 'antes'=>$a, 'ahora'=>$n];
  }
};

$push('Equipo', $oldEquipoNom, $newEquipoNom);
$push('Modelo', $oldModeloNom, $newModeloNom);
$push('Serie', $old['serie'] ?? '', $serie);
$push('MAC', $old['mac'] ?? '', $mac);
$push('Servidor', $old['servidor'] ?? '', $servidor);
$push('VMS', $old['vms'] ?? '', $vms);
if ((string)($old['user'] ?? '') !== $user) {
  $diffs[] = ['campo'=>'Usuario', 'antes'=>($old['user'] ?? ''), 'ahora'=>$user];
}
// para contraseña, no mostramos valores
if ((string)($old['pass'] ?? '') !== $pass) {
  $diffs[] = ['campo'=>'Contraseña', 'antes'=>'(no se muestra)', 'ahora'=>'(modificada)'];
}
$push('Switch', $old['switch'] ?? '', $switch);
$push('Puerto', $old['puerto'] ?? '', $puerto);
$push('Sucursal', $oldSucursalNom, $newSucursalNom);
$push('Área', $old['area'] ?? '', $area);
$push('Estado', $oldEstadoNom, $newEstadoNom);
$push('Fecha', $old['fecha'] ?? '', $fecha);
if ((string)($old['observaciones'] ?? '') !== $obs) {
  $diffs[] = ['campo'=>'Observaciones', 'antes'=>($old['observaciones'] ?? ''), 'ahora'=>$obs];
}
if (($old['imagen'] ?? null) !== $imagen)   { $diffs[] = ['campo'=>'Imagen 1', 'antes'=>($old['imagen'] ?? ''), 'ahora'=>$imagen]; }
if (($old['imagen2'] ?? null) !== $imagen2) { $diffs[] = ['campo'=>'Imagen 2', 'antes'=>($old['imagen2'] ?? ''), 'ahora'=>$imagen2]; }
if (($old['imagen3'] ?? null) !== $imagen3) { $diffs[] = ['campo'=>'Imagen 3', 'antes'=>($old['imagen3'] ?? ''), 'ahora'=>$imagen3]; }

/* Fecha/hora bonita */
$nowMx = new DateTime('now', new DateTimeZone('America/Mexico_City'));
if (class_exists('IntlDateFormatter')) {
  $fmt = new IntlDateFormatter(
    'es_MX', IntlDateFormatter::FULL, IntlDateFormatter::SHORT, 'America/Mexico_City',
    IntlDateFormatter::GREGORIAN, "EEEE d 'de' MMMM 'de' y 'a las' HH:mm:ss"
  );
  $fechaHora = ucfirst($fmt->format($nowMx));
} else {
  $fechaHora = $nowMx->format('d/m/Y H:i:s');
}

/* URLs absolutas */
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host . '/sisec-ui';
$deviceUrl = $baseUrl . '/views/dispositivos/device.php?id=' . $id;

$fotoEditorAbs = null;
if (!empty($editorFoto)) {
  $fotoEditorAbs = preg_match('#^https?://#i', $editorFoto) ? $editorFoto : ($scheme . '://' . $host . $editorFoto);
}

$imgAbs = $imagen ? ($baseUrl . '/public/uploads/' . $imagen) : null;

/* Tabla HTML de diffs */
$rows = '';
if (empty($diffs)) {
  $rows = '<tr><td colspan="3" style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">No hubo cambios en los campos.</td></tr>';
} else {
  foreach ($diffs as $d) {
    $campo = htmlspecialchars($d['campo'], ENT_QUOTES, 'UTF-8');
    $antes = htmlspecialchars((string)$d['antes'], ENT_QUOTES, 'UTF-8');
    $ahora = htmlspecialchars((string)$d['ahora'], ENT_QUOTES, 'UTF-8');
    $rows .= "<tr>
      <td style=\"padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px; width:30%;\"><strong>{$campo}</strong></td>
      <td style=\"padding:10px 12px; border:1px solid #edf2f7; font-size:14px;\">{$antes}</td>
      <td style=\"padding:10px 12px; border:1px solid #edf2f7; font-size:14px;\">{$ahora}</td>
    </tr>";
  }
}

/* Correo con estilo */
$destinatarios = ['marcojazzelarzate@gmail.com', 'marc0_ruiz@hotmail.com'];
$asunto = 'CESISS · Edición de dispositivo #' . $id;

$editorNombreHtml = htmlspecialchars($editorNombre, ENT_QUOTES, 'UTF-8');
$editorRolHtml    = htmlspecialchars($editorRol, ENT_QUOTES, 'UTF-8');

$html = <<<HTML
<!doctype html>
<html lang="es">
  <body style="margin:0; padding:0; background:#f5f7fb;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;">
      <tr>
        <td align="center" style="padding:24px 12px;">
          <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.06);">
            <tr>
              <td style="background:#3C92A6; padding:20px 24px; color:#ffffff; font-family:Arial, Helvetica, sans-serif;">
                <h2 style="margin:0; font-size:20px; letter-spacing:.3px;">CESISS</h2>
                <p style="margin:6px 0 0 0; font-size:13px; opacity:.95;">Notificación de edición de dispositivo</p>
              </td>
            </tr>

            <tr>
              <td style="padding:24px; font-family:Arial, Helvetica, sans-serif; color:#222;">
                <p style="margin:0 0 12px 0; font-size:15px; line-height:1.5;">
                  Se han realizado cambios al <strong>dispositivo #{$id}</strong>.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:16px 0;">
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px; width:40%;"><strong>Editado por</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$editorNombreHtml} ({$editorRolHtml})</td>
                  </tr>
                  <tr>
                    <td style="padding:10px 12px; background:#f7fafc; border:1px solid #edf2f7; font-size:14px;"><strong>Fecha y hora (CDMX)</strong></td>
                    <td style="padding:10px 12px; border:1px solid #edf2f7; font-size:14px;">{$fechaHora}</td>
                  </tr>
                </table>

                <h4 style="margin:18px 0 8px 0; font-size:15px;">Cambios</h4>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:8px 0 16px 0;">
                  <tr>
                    <th align="left" style="padding:10px 12px; background:#eaf6f9; border:1px solid #edf2f7; font-size:13px;">Campo</th>
                    <th align="left" style="padding:10px 12px; background:#eaf6f9; border:1px solid #edf2f7; font-size:13px;">Antes</th>
                    <th align="left" style="padding:10px 12px; background:#eaf6f9; border:1px solid #edf2f7; font-size:13px;">Ahora</th>
                  </tr>
                  {$rows}
                </table>
HTML;

if ($fotoEditorAbs) {
  $html .= <<<HTML
                <div style="margin:8px 0 16px 0;">
                  <p style="margin:0 0 6px 0; font-size:13px; color:#444;">Usuario:</p>
                  <img src="{$fotoEditorAbs}" alt="{$editorNombreHtml}" width="60" height="60" style="border-radius:50%; display:block; border:1px solid #e6e6e6; object-fit:cover;">
                </div>
HTML;
}
if ($imgAbs) {
  $html .= <<<HTML
                <div style="margin:8px 0 16px 0;">
                  <p style="margin:0 0 6px 0; font-size:13px; color:#444;">Imagen del dispositivo:</p>
                  <img src="{$imgAbs}" alt="Imagen dispositivo" width="160" style="display:block; border:1px solid #e6e6e6; border-radius:8px; object-fit:cover;">
                </div>
HTML;
}

$html .= <<<HTML
                <div style="margin:20px 0 8px 0;">
                  <a href="{$deviceUrl}" 
                     style="display:inline-block; padding:12px 18px; background:#3C92A6; color:#ffffff; text-decoration:none; font-weight:bold; border-radius:8px; font-size:14px;">
                     Ver dispositivo
                  </a>
                </div>

                <p style="margin:16px 0 0 0; font-size:12px; color:#6b7280;">
                  Si no reconoces esta edición, revisa el historial y ajusta permisos.
                </p>
              </td>
            </tr>

            <tr>
              <td style="padding:14px 24px; background:#f9fafb; color:#6b7280; font-family:Arial, Helvetica, sans-serif; font-size:12px; text-align:center;">
                © CESISS · Este es un mensaje automático, no respondas a este correo.
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

try {
  enviarNotificacion($asunto, $html, $destinatarios);
} catch (Throwable $e) {
  // no interrumpir el flujo por el correo
}

/* ========= 7) Redirigir ========= */
header('Location: device.php?id=' . $id);
exit;
