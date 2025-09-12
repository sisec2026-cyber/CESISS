<?php
// /sisec-ui/views/ubicacion/sucursales_guardar.php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Técnico','Mantenimientos']);
require_once __DIR__ . '/../../includes/db.php';

function back($msgOk=null, $msgErr=null) {
  $params = [];
  if ($msgOk) $params['ok']  = $msgOk;
  if ($msgErr) $params['err'] = $msgErr;
  $qs = http_build_query($params);
  header('Location: sucursales_crear.php' . ($qs ? "?$qs" : ''));
  exit;
}

// Inputs
$ciudad_id       = isset($_POST['ciudad_id']) ? (int)$_POST['ciudad_id'] : 0;
$municipio_id    = isset($_POST['municipio_id']) ? (int)$_POST['municipio_id'] : 0;
$nom_sucursal    = trim($_POST['nom_sucursal'] ?? '');
$nom_determinante= trim($_POST['nom_determinante'] ?? '');
$lat             = trim($_POST['lat'] ?? '');
$lng             = trim($_POST['lng'] ?? '');

// Validaciones
if (!$ciudad_id || !$municipio_id) back(null, 'Selecciona ciudad y municipio.');
if ($nom_sucursal === '' || $nom_determinante === '') back(null, 'Captura nombre de sucursal y determinante.');
if ($lat === '' || $lng === '') back(null, 'Captura las coordenadas (Lat/Lng).');
if (!is_numeric($lat) || !is_numeric($lng)) back(null, 'Lat/Lng deben ser numéricos.');

$nom_sucursal     = mb_strtoupper($nom_sucursal, 'UTF-8');
$nom_determinante = mb_strtoupper($nom_determinante, 'UTF-8');
$lat = (float)$lat; $lng = (float)$lng;

// Opcional: normalizaciones suaves (acentos comunes)
$repls = [
  '/\bALAMBRICO\b/u'  => 'ALÁMBRICO',
  '/\bINALAMBRICO\b/u'=> 'INALÁMBRICO',
  '/\bANALOGICO\b/u'  => 'ANALÓGICO',
  '/\bCAMARA\b/u'     => 'CÁMARA',
];
$nom_sucursal = preg_replace(array_keys($repls), array_values($repls), $nom_sucursal);

try {
  $conn->begin_transaction();

  // 1) Verifica que municipio pertenece a ciudad (defensa)
  $q = $conn->prepare("SELECT m.id FROM municipios m INNER JOIN ciudades c ON m.ciudad_id=c.id WHERE m.id=? AND c.id=?");
  $q->bind_param('ii', $municipio_id, $ciudad_id);
  $q->execute();
  $okPair = $q->get_result()->fetch_assoc();
  $q->close();
  if (!$okPair) {
    $conn->rollback();
    back(null, 'El municipio no corresponde a la ciudad seleccionada.');
  }

  // 2) Busca sucursal existente (por municipio_id + nombre)
  $sucId = null;
  $q = $conn->prepare("SELECT id FROM sucursales WHERE municipio_id=? AND nom_sucursal=? LIMIT 1");
  $q->bind_param('is', $municipio_id, $nom_sucursal);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  $q->close();

  if ($r && isset($r['id'])) {
    // Ya existe sucursal: solo actualiza lat/lng si vienen vacíos
    $sucId = (int)$r['id'];
    $up = $conn->prepare("UPDATE sucursales SET lat=?, lng=? WHERE id=?");
    $up->bind_param('ddi', $lat, $lng, $sucId);
    $up->execute();
    $up->close();
  } else {
    // 3) Inserta sucursal
    $ins = $conn->prepare("INSERT INTO sucursales (municipio_id, nom_sucursal, lat, lng) VALUES (?,?,?,?)");
    $ins->bind_param('isdd', $municipio_id, $nom_sucursal, $lat, $lng);
    if (!$ins->execute()) {
      $conn->rollback();
      back(null, 'No se pudo crear la sucursal (duplicado o error de BD).');
    }
    $sucId = $ins->insert_id;
    $ins->close();
  }

  // 4) Determinante: crea si no existe para esta sucursal
  // Ajusta el nombre de tabla/columna si tu esquema usa otro (p.ej. `determinantes.nom_determinante`)
  $detId = null;
  $q = $conn->prepare("SELECT id FROM determinantes WHERE sucursal_id=? AND nom_determinante=? LIMIT 1");
  $q->bind_param('is', $sucId, $nom_determinante);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  $q->close();

  if ($r && isset($r['id'])) {
    $detId = (int)$r['id'];
  } else {
    $ins = $conn->prepare("INSERT INTO determinantes (sucursal_id, nom_determinante) VALUES (?,?)");
    $ins->bind_param('is', $sucId, $nom_determinante);
    if (!$ins->execute()) {
      $conn->rollback();
      back(null, 'No se pudo crear la determinante (duplicado o error de BD).');
    }
    $detId = $ins->insert_id;
    $ins->close();
  }

  $conn->commit();

  // Redirección con éxito
  $okMsg = 'Sucursal creada/actualizada correctamente. ID Sucursal: ' . $sucId . ' · Determinante ID: ' . $detId;
  header('Location: sucursales_crear.php?ok=' . urlencode($okMsg));
  exit;

} catch (Throwable $e) {
  if ($conn->errno) { $conn->rollback(); }
  back(null, 'Error de servidor: ' . $e->getMessage());
}
