<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
include __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

/* -------- Parámetros -------- */
$zona = isset($_GET['zona']) ? trim($_GET['zona']) : '';

/* -------- Helpers -------- */
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

/* 
 * Subconsulta (dc) por dispositivo:
 * - is_cctv = 1 si el nombre del equipo matchea prefijos de CCTV
 * - is_ok   = 1 si el dispositivo está operativo.
 *   Reglas: si d.operativo es NULL, lo derivamos de d.estado:
 *           'activo' => 1; 'en mantenimiento'/'desactivado' => 0; también aceptamos 1/0 numéricos.
 */
$zonaWhere = '';
if ($zona !== '') {
  $zonaEsc = $conn->real_escape_string($zona);
  $zonaWhere = "AND r.nom_region = '$zonaEsc'";
}

$sql = "
  SELECT
    s.id AS sucursal_id,
    s.nom_sucursal,
    COALESCE(SUM(dc.is_cctv), 0)                                     AS total_cctv,
    COALESCE(SUM(dc.is_cctv * dc.is_ok), 0)                           AS cctv_ok,
    COALESCE(SUM(dc.is_cctv * (1 - dc.is_ok)), 0)                     AS cctv_bad
  FROM sucursales s
  /* joins para poder filtrar por zona (región) cuando se pida */
  INNER JOIN municipios m ON s.municipio_id = m.id
  INNER JOIN ciudades   c ON m.ciudad_id    = c.id
  INNER JOIN regiones   r ON c.region_id    = r.id

  /* dispositivos clasificados */
  LEFT JOIN (
    SELECT
      d.sucursal,
      /* CCTV? */
      CASE WHEN $whereCCTV THEN 1 ELSE 0 END AS is_cctv,
      /* Operativo? */
      CASE
        WHEN d.operativo IS NOT NULL THEN CASE WHEN d.operativo = 1 THEN 1 ELSE 0 END
        ELSE
          CASE
            WHEN LOWER(COALESCE(CAST(d.estado AS CHAR), '')) = 'activo' OR d.estado = 1 THEN 1
            ELSE 0
          END
      END AS is_ok
    FROM dispositivos d
    LEFT JOIN equipos e ON d.equipo = e.id
  ) AS dc ON dc.sucursal = s.id

  WHERE 1=1
  $zonaWhere
  GROUP BY s.id, s.nom_sucursal
  ORDER BY s.nom_sucursal
";

$res = $conn->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$conn->error], JSON_UNESCAPED_UNICODE);
  exit;
}

$out = [];
while ($row = $res->fetch_assoc()) {
  $tot = (int)$row['total_cctv'];
  $ok  = (int)$row['cctv_ok'];
  $bad = (int)$row['cctv_bad'];
  $pct = $tot > 0 ? round(($ok * 100) / $tot) : 0;

  $out[] = [
    'sucursal_id'  => (int)$row['sucursal_id'],
    'nom_sucursal' => $row['nom_sucursal'],
    'total_cctv'   => $tot,
    'cctv_ok'      => $ok,
    'cctv_bad'     => $bad,
    'pct'          => $pct,
  ];
}

echo json_encode(['ok'=>true, 'por_sucursal'=>$out], JSON_UNESCAPED_UNICODE);
