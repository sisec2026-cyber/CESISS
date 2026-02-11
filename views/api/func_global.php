<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
include __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// --- Prefijos que identifican CCTV (mismos que usas en el dashboard)
$COLLATION = 'utf8mb4_general_ci';
$PREFIJOS_CCTV = ['racks%','camara%','dvr%','nvr%','servidor%','monitor%','biometrico%','videoportero%','videotelefono%','ups%'];

function esc_like_prefixes(mysqli $conn, string $col, string $collation, array $prefixes): string {
  $likes = [];
  foreach ($prefixes as $p) {
    $p = $conn->real_escape_string($p);
    $likes[] = "$col COLLATE $collation LIKE '$p'";
  }
  return '(' . implode(' OR ', $likes) . ')';
}
$whereCCTV = esc_like_prefixes($conn, 'e.nom_equipo', $COLLATION, $PREFIJOS_CCTV);

// --- CASE para considerar “operativo” según estado (texto o numérico)
$CASE_OPERATIVO = "
  COALESCE(
    d.operativo,
    CASE 
      WHEN LOWER(COALESCE(CAST(d.estado AS CHAR), '')) = 'activo' OR d.estado = 1
      THEN 1 ELSE 0
    END
  )
";

// --- Totales globales
$sqlTotals = "
  SELECT
    SUM(CASE WHEN $whereCCTV THEN 1 ELSE 0 END) AS cctv_total,
    SUM(CASE WHEN $whereCCTV AND ($CASE_OPERATIVO = 1) THEN 1 ELSE 0 END) AS cctv_ok,
    SUM(CASE WHEN $whereCCTV AND ($CASE_OPERATIVO = 0) THEN 1 ELSE 0 END) AS cctv_bad
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
";
$tot = $conn->query($sqlTotals)->fetch_assoc() ?: ['cctv_total'=>0,'cctv_ok'=>0,'cctv_bad'=>0];
$total = (int)$tot['cctv_total'];
$ok    = (int)$tot['cctv_ok'];
$bad   = (int)$tot['cctv_bad'];
$pct   = $total > 0 ? round(($ok * 100) / $total) : 0;

// --- Detalle no operativas (para el modal)
$sqlBad = "
  SELECT s.id AS sucursal_id, s.nom_sucursal,
         d.id AS dispositivo_id, d.nombre, d.serie, d.ubicacion
  FROM dispositivos d
  INNER JOIN equipos e   ON d.equipo   = e.id
  INNER JOIN sucursales s ON d.sucursal = s.id
  WHERE ($whereCCTV) AND ($CASE_OPERATIVO = 0)
  ORDER BY s.nom_sucursal, d.nombre
";
$badList = [];
if ($res = $conn->query($sqlBad)) {
  while ($r = $res->fetch_assoc()) $badList[] = $r;
}

echo json_encode([
  'ok'     => true,
  'totals' => ['cctv_total'=>$total, 'cctv_ok'=>$ok, 'cctv_bad'=>$bad, 'porcentaje'=>$pct],
  'bad'    => $badList
], JSON_UNESCAPED_UNICODE);
