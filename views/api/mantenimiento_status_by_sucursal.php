<?php
// /sisec-ui/views/api/mantenimiento_status_by_sucursal.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

// Tomar solo eventos cuyo rango toca HOY o es futuro (para decidir color); si una sucursal tiene varios, priorizamos:
// 1) proceso (hoy dentro), 2) pendiente (<=3 días), 3) siguiente (>3 días), 4) hecho (si solo hay pasados recientes).
$today = new DateTimeImmutable(date('Y-m-d'));
$hasRangeCols = $conn->query("SHOW COLUMNS FROM mantenimiento_eventos LIKE 'fecha_inicio'")->num_rows > 0;

if ($hasRangeCols) {
  $sql = "
    SELECT me.sucursal_id, me.fecha_inicio, me.fecha_fin
    FROM mantenimiento_eventos me
    -- traemos todo y decidimos en PHP
    ORDER BY me.sucursal_id, me.fecha_inicio DESC
  ";
} else {
  $sql = "
    SELECT me.sucursal_id, me.fecha AS fecha_inicio, me.fecha AS fecha_fin
    FROM mantenimiento_eventos me
    ORDER BY me.sucursal_id, me.fecha DESC
  ";
}

$res = $conn->query($sql);
$rows = [];
while ($res && $row = $res->fetch_assoc()) $rows[] = $row;

function days_to(DateTimeImmutable $from, DateTimeImmutable $to): int {
  return (int)$to->diff($from)->format('%r%a');
}

function dyn_status_for(DateTimeImmutable $today, string $fi, string $ff): string {
  $dFi = new DateTimeImmutable($fi);
  $dFf = new DateTimeImmutable($ff);
  if ($today > $dFf) return 'hecho';
  if ($today >= $dFi && $today <= $dFf) return 'proceso';
  $d = days_to($today, $dFi);
  if ($d <= 3) return 'pendiente';
  return 'siguiente';
}

// Elegir un único estado por sucursal con prioridad
$bySucursal = [];
foreach ($rows as $r) {
  $sid = (string)$r['sucursal_id'];
  $st  = dyn_status_for($today, $r['fecha_inicio'], $r['fecha_fin']);
  // prioridad: proceso > pendiente <=3 > siguiente > hecho
  $rank = ['proceso'=>1,'pendiente'=>2,'siguiente'=>3,'hecho'=>4][$st] ?? 99;

  if (!isset($bySucursal[$sid]) || $rank < $bySucursal[$sid]['rank']) {
    $bySucursal[$sid] = ['status'=>$st, 'rank'=>$rank];
  }
}

$out = [];
foreach ($bySucursal as $sid => $v) $out[$sid] = $v['status'];

echo json_encode(['ok'=>true,'status_by_sucursal'=>$out], JSON_UNESCAPED_UNICODE);
