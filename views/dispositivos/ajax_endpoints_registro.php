<?php
// /sisec-ui/views/dispositivos/ajax_endpoints_registro.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Técnico','Capturista']);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$fn = $_GET['fn'] ?? '';
if ($fn === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Falta parámetro fn']);
  exit;
}

function j400($msg){ http_response_code(400); echo json_encode(['error'=>$msg]); exit; }
function j200($data){ echo json_encode($data); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Esquema esperado (ajusta nombres si difiere):
 * - ciudades:       ID, nom_ciudad
 * - municipios:     id, nom_municipio, ciudad_id (FK ciudades.ID)
 * - sucursales:     id, nom_sucursal, municipio_id (FK municipios.id)
 * - determinantes:  id, determinante, sucursal_id (FK sucursales.id)
 * - marcas:         id_marcas, nom_marca, equipo_id (FK equipos.id)
 * - equipos:        id, nombre (o nom_equipo)            // se usa solo para buscar id por nombre
 * - modelos:        id, nom_modelo, marca_id
 */

switch ($fn) {
  case 'municipios': {
    $ciudadId = $_GET['ciudad_id'] ?? '';
    if ($ciudadId === '' || !ctype_digit($ciudadId)) j400('Parámetro ciudad_id inválido');
    $stmt = $conn->prepare("SELECT id, nom_municipio FROM municipios WHERE ciudad_id = ? ORDER BY nom_municipio ASC");
    $stmt->bind_param('i', $ciudadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
      $out[] = ['id' => (int)$r['id'], 'nom_municipio' => $r['nom_municipio']];
    }
    j200($out);
  }

  case 'sucursales': {
    $municipioId = $_GET['municipio_id'] ?? '';
    if ($municipioId === '' || !ctype_digit($municipioId)) j400('Parámetro municipio_id inválido');
    $stmt = $conn->prepare("SELECT id, nom_sucursal FROM sucursales WHERE municipio_id = ? ORDER BY nom_sucursal ASC");
    $stmt->bind_param('i', $municipioId);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
      $out[] = ['id' => (int)$r['id'], 'nom_sucursal' => $r['nom_sucursal']];
    }
    j200($out);
  }

  case 'determinantes': {
    $sucursalId = $_GET['sucursal_id'] ?? '';
    if ($sucursalId === '' || !ctype_digit($sucursalId)) j400('Parámetro sucursal_id inválido');
    $stmt = $conn->prepare("SELECT id, determinante FROM determinantes WHERE sucursal_id = ? ORDER BY determinante ASC");
    $stmt->bind_param('i', $sucursalId);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
      $out[] = ['id' => (int)$r['id'], 'nom_determinante' => $r['determinante']];
    }
    j200($out);
  }

  // Opcional: por si en tu otro JS cargas marcas/modelos y te fallaba la RUTA
  case 'marcas': {
    // Acepta equipo_id numérico o el nombre del equipo (equipo=texto)
    $equipoId = $_GET['equipo_id'] ?? '';
    $equipoNom = trim((string)($_GET['equipo'] ?? ''));
    if (!ctype_digit($equipoId)) {
      if ($equipoNom !== '') {
        // Intento flexible: busca en equipos por nombre o nom_equipo
        $q = $conn->prepare("
          SELECT id
          FROM equipos
          WHERE UPPER(COALESCE(nombre, '')) = UPPER(?)
             OR UPPER(COALESCE(nom_equipo, '')) = UPPER(?)
          LIMIT 1
        ");
        $q->bind_param('ss', $equipoNom, $equipoNom);
        $q->execute();
        $rid = $q->get_result()->fetch_assoc()['id'] ?? null;
        if ($rid) $equipoId = (string)$rid;
      }
    }
    if (!ctype_digit($equipoId)) {
      // Si no se localiza equipo, devolvemos todas las marcas como fallback
      $sql = "SELECT id_marcas, nom_marca FROM marcas ORDER BY nom_marca ASC";
      $res = $conn->query($sql);
    } else {
      $stmt = $conn->prepare("SELECT id_marcas, nom_marca FROM marcas WHERE equipo_id = ? ORDER BY nom_marca ASC");
      $stmt->bind_param('i', $equipoId);
      $stmt->execute();
      $res = $stmt->get_result();
    }
    $out = [];
    while ($r = $res->fetch_assoc()) {
      $out[] = ['id_marcas' => (int)$r['id_marcas'], 'nom_marca' => $r['nom_marca']];
    }
    j200($out);
  }

  case 'modelos': {
    $marcaId = $_GET['marca_id'] ?? '';
    if ($marcaId === '' || !ctype_digit($marcaId)) j400('Parámetro marca_id inválido');
    $stmt = $conn->prepare("SELECT id, nom_modelo FROM modelos WHERE marca_id = ? ORDER BY nom_modelo ASC");
    $stmt->bind_param('i', $marcaId);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
      $out[] = ['id' => (int)$r['id'], 'nom_modelo' => $r['nom_modelo']];
    }
    j200($out);
  }

  default:
    j400('fn desconocido');
}
