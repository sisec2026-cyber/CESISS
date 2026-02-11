<?php
// /sisec-ui/views/api/mantenimiento_events.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
require_once __DIR__ . '/../../includes/db.php';

date_default_timezone_set('America/Mexico_City');

// Headers: JSON + no-cache para evitar respuestas viejas en refetchEvents()
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$STATUS_HECHO     = 'Mantenimiento hecho';
$STATUS_PENDIENTE = 'Mantenimiento pendiente';
$STATUS_PROCESO   = 'Mantenimiento en proceso';
$STATUS_SIGUIENTE = 'Siguiente mantenimiento';

$COLOR_BASE = [
  $STATUS_HECHO     => ['bg'=>'#01a806', 'text'=>'#fff', 'slug'=>'hecho'],
  $STATUS_PENDIENTE => ['bg'=>'#f39c12', 'text'=>'#000', 'slug'=>'pendiente'],
  $STATUS_PROCESO   => ['bg'=>'#f1c40f', 'text'=>'#000', 'slug'=>'proceso'],
  $STATUS_SIGUIENTE => ['bg'=>'#2980b9', 'text'=>'#fff', 'slug'=>'siguiente'],
];

// Normaliza a slug (tolerante a acentos/mayús/espacios)
function norm_slug(string $s): string {
  $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  if ($t === false) $t = $s;
  $t = strtolower(preg_replace('/[^a-z0-9\s]/', '', $t));
  $t = preg_replace('/\s+/', ' ', trim($t));
  if (preg_match('/\b(hecho|realizado|completado|finalizado|cerrado|ok|listo)\b/', $t)) return 'hecho';
  if (preg_match('/\b(proceso|en curso|ejecucion|trabajando|hoy)\b/', $t))       return 'proceso';
  if (preg_match('/\b(sig|siguiente|prox\.?|proximo|programado)\b/', $t))        return 'siguiente';
  if (preg_match('/\b(pend|pendiente|por hacer|programar|sin fecha)\b/', $t))    return 'pendiente';
  return 'pendiente';
}

function respond($ok, $payload = [], $code = 200){
  http_response_code($code);
  echo json_encode(['ok'=>$ok] + $payload, JSON_UNESCAPED_UNICODE);
  exit;
}

// Parámetros
$start = $_GET['start'] ?? null; // YYYY-MM-DD
$end   = $_GET['end']   ?? null; // YYYY-MM-DD (FullCalendar: exclusivo)
$filter = $_GET['status'] ?? []; // puede venir como status[]=...

$DATE_RX = '/^\d{4}-\d{2}-\d{2}$/';
if (!$start || !$end || !preg_match($DATE_RX, $start) || !preg_match($DATE_RX, $end)) {
  respond(false, ['error'=>'Parámetros inválidos: start y end (YYYY-MM-DD) son requeridos.'], 400);
}

// Normalizamos filtro a slugs
$wantSlugs = array_values(array_unique(array_filter(array_map('strval', (array)$filter))));
$wantSlugs = array_map('norm_slug', $wantSlugs);
$applyFilter = !empty($wantSlugs);

// Consulta (end exclusivo): [inicio, fin] se solapa con [start, end)
$sql = "
  SELECT
    me.id                   AS event_id,
    s.id                    AS sucursal_id,
    s.nom_sucursal          AS sucursal,
    dtr.nom_determinante    AS determinante,
    r.nom_region            AS region,
    c.nom_ciudad            AS ciudad,
    m.nom_municipio         AS municipio,
    me.status_label         AS status_label,
    COALESCE(me.fecha_inicio, me.fecha) AS fecha_inicio,
    COALESCE(me.fecha_fin,    me.fecha) AS fecha_fin
  FROM mantenimiento_eventos me
  JOIN sucursales  s   ON s.id            = me.sucursal_id
  LEFT JOIN determinantes dtr ON dtr.sucursal_id = s.id
  LEFT JOIN municipios m ON s.municipio_id = m.id
  LEFT JOIN ciudades   c ON m.ciudad_id    = c.id
  LEFT JOIN regiones   r ON c.region_id    = r.id
  WHERE
    COALESCE(me.fecha_inicio, me.fecha) <  ?   -- end exclusivo
    AND COALESCE(me.fecha_fin,    me.fecha) >= ?   -- start inclusivo
  ORDER BY COALESCE(me.fecha_inicio, me.fecha) ASC, s.nom_sucursal ASC
";

try {
  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new RuntimeException($conn->error);
  // bind: end, start (en ese orden por el WHERE)
  $stmt->bind_param('ss', $end, $start);
  $stmt->execute();
  $res = $stmt->get_result();

  $today = new DateTimeImmutable('today');
  $events = [];

  while ($r = $res->fetch_assoc()) {
    if (empty($r['fecha_inicio'])) continue;

    $label = (string)($r['status_label'] ?? '');
    $slug  = norm_slug($label);
    if ($applyFilter && !in_array($slug, $wantSlugs, true)) continue;

    // Color base desde tu mapa de labels
    $base = $COLOR_BASE[$label] ?? ['bg'=>'#888','text'=>'#fff','slug'=>$slug];

    $fi = new DateTimeImmutable($r['fecha_inicio']);
    $ff = new DateTimeImmutable($r['fecha_fin'] ?: $r['fecha_inicio']);
    if ($ff < $fi) $ff = $fi;
    // FullCalendar espera end EXCLUSIVO → sumamos 1 día
    $endExclusive = $ff->modify('+1 day');

    // Fases (para clases/colores matizados)
    $isOngoing = ($today >= $fi && $today <= $ff);
    $daysToStart = (int)$today->diff($fi)->format('%r%a');
    $isOverdue = ($today > $ff && $slug !== 'hecho');
    $isSoon3   = ($today < $fi && $daysToStart <= 3);
    $isSoon7   = ($today < $fi && $daysToStart <= 7);

    $bg = $base['bg']; $txt = $base['text'];
    $classes = ['ev-'.$slug];
    if ($isOngoing) {
      $classes[] = 'phase-ongoing';
    } elseif ($isOverdue) {
      $classes[] = 'phase-overdue'; $bg = '#dc3545'; $txt = '#fff';
    } elseif ($isSoon3) {
      $classes[] = 'phase-soon-3';  $bg = '#dc3545'; $txt = '#fff';
    } elseif ($isSoon7) {
      $classes[] = 'phase-soon-7';  $bg = '#ff9f43'; $txt = '#000';
    }

    $title = (string)($r['sucursal'] ?? 'Sucursal');
    if (!empty($r['determinante'])) $title .= ' (#'.$r['determinante'].')';

    $events[] = [
      'id'              => (string)$r['event_id'],
      'title'           => $title,
      'start'           => $fi->format('Y-m-d'),
      'end'             => $endExclusive->format('Y-m-d'),
      'allDay'          => true,
      'backgroundColor' => $bg,
      'borderColor'     => $bg,
      'textColor'       => $txt,
      'classNames'      => $classes,
      'extendedProps'   => [
        'event_id'     => (string)$r['event_id'],
        'sucursal_id'  => (string)$r['sucursal_id'],
        'sucursal'     => (string)($r['sucursal'] ?? ''),
        'status_label' => $label,
        'status_slug'  => $slug,
        'fecha_inicio' => $fi->format('Y-m-d'),
        'fecha_fin'    => $ff->format('Y-m-d'),
        'region'       => (string)($r['region'] ?? ''),
        'ciudad'       => (string)($r['ciudad'] ?? ''),
        'municipio'    => (string)($r['municipio'] ?? ''),
      ],
    ];
  }

  respond(true, ['events'=>$events]);
} catch (Throwable $e) {
  respond(false, ['error'=>'Ocurrió un error al generar los eventos.', 'detail'=>$e->getMessage()], 500);
}
