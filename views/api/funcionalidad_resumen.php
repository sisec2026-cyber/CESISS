<?php
// /sisec-ui/views/api/funcionalidad_resumen.php
require_once __DIR__ . '/../../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

$COLLATION = 'utf8mb4_general_ci';

// Prefijos que ya usas para clasificar CCTV
$PREFIJOS_CCTV = ['racks%','camara%','dvr%','nvr%','servidor%','monitor%','biometrico%','videoportero%','videotelefono%','ups%'];
function esc_like_prefixes(string $col, string $collation, array $prefixes): string {
  $likes = [];
  foreach ($prefixes as $p) {
    $p = str_replace("'", "''", $p);
    $likes[] = "$col COLLATE $collation LIKE '$p'";
  }
  return '(' . implode(' OR ', $likes) . ')';
}
$whereCCTV = esc_like_prefixes('e.nom_equipo', $COLLATION, $PREFIJOS_CCTV);

$sql = "
  SELECT
    COUNT(*)                                           AS total_cctv,
    SUM(CASE WHEN (d.operativo = 1) THEN 1 ELSE 0 END) AS operativas,
    SUM(CASE WHEN (d.operativo = 0) THEN 1 ELSE 0 END) AS no_operativas
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  WHERE $whereCCTV
";
$row = $conn->query($sql)->fetch_assoc() ?: ['total_cctv'=>0,'operativas'=>0,'no_operativas'=>0];

$total = (int)($row['total_cctv'] ?? 0);
$ok    = (int)($row['operativas'] ?? 0);
$down  = (int)($row['no_operativas'] ?? 0);
$pct   = $total > 0 ? round(($ok * 100) / $total) : 0;

echo json_encode([
  'ok'   => true,
  'data' => [
    'total_cctv'   => $total,
    'operativas'   => $ok,
    'no_operativas'=> $down,
    'porcentaje'   => $pct
  ]
], JSON_UNESCAPED_UNICODE);
