<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos', 'Capturista']);
include __DIR__ . '/../../includes/db.php';
ob_start();

/* =========================
   CONFIGURACIÓN
========================= */
$COLLATION = 'utf8mb4_general_ci';

$STATUS_HECHO     = 'Mantenimiento hecho';
$STATUS_PENDIENTE = 'Mantenimiento pendiente';
$STATUS_PROCESO   = 'Mantenimiento en proceso';
$STATUS_SIGUIENTE = 'Siguiente mantenimiento';

$NEXT_MAINTENANCE_FALLBACK_DAYS = 180;

/* =========================
   HELPERS
========================= */
function esc_like_prefixes(string $col, string $collation, array $prefixes): string {
  $likes = [];
  foreach ($prefixes as $p) {
    $p = str_replace("'", "''", $p);
    $likes[] = "$col COLLATE $collation LIKE '$p'";
  }
  return '(' . implode(' OR ', $likes) . ')';
}

function normalize_key_es(string $s): string {
  $s = trim($s);
  if (function_exists('iconv')) {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) $s = $t;
  }
  $s = preg_replace('/[^A-Za-z0-9\s]/', '', $s);
  $s = mb_strtolower($s, 'UTF-8');
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

/* =========================
   PREFIJOS POR CATEGORÍA
========================= */
$PREFIJOS_CCTV   = ['racks%', 'camara%', 'dvr%', 'nvr%', 'servidor%', 'monitor%', 'biometrico%', 'biométrico%', 'videoportero%', 'videotelefono%', 'videoteléfono%', 'ups%', 'cam%', 'cámara%', 'camara%', 'ptz%', 'bullet%', 'dome%', 'servidor%', 'grabador%', 'grabadora%', 'storage%', 'encoder%', 'decodificador%', 'decoder%', 'switch poe%', 'poe%', 'hdd%', 'disco%', 'gabinete%', 'video portero%', 'teléfono%', 'telefono%', 'switch%', 'mouse%', 'axtv%', 'ax-tv%', 'balloon%', 'baloon%', 'balloons%', 'baloons%', 'extensores%', 'rack%', 'rc%', 'fuente%', 'plug%', 'jack%', 'rj45%', 'transceptor%', 'tranceptor%', 'visual tools%', 'joystick%', 'licencias%', 'control de acceso%', 'vms%', 'server%', 'estacion%','estación%', 'estación de trabajo%','computadora%', 'workstation%'];
$PREFIJOS_ALARMA = ['sensor%', 'dh%', 'pir%', 'cm%', 'oh%', 'estrobo%', 'estorbo%', 'estrobos%', 'rep%', 'drc%', 'teclado%', 'sirena%', 'boton%', 'botón%', 'sensor%', 'movimiento%', 'magnetico%', 'magnético%', 'contacto%', 'puerta%', 'ventana%', 'tarjeta de comunicación%', 'tarjeta de comunicacion%', 'keypad%', 'sirena%', 'panel%', 'pane%', 'control%', 'expansora%', 'modulo%', 'módulo%', 'panico%', 'pánico%', 'expansor%', 'estación manual%', 'estación manual%', 'estación manuak%', 'em%', 'receptora%', 'receptor%', 'relevador%', 'relevadora%', 'weigand%', 'fuente de poder%', 'gp23%', 'electro iman%', 'electro imán%', 'electroiman%', 'electroimamn%', 'liberador%', 'bateria%', 'batería%', 'transformador%', 'trasformador%', 'tamper%', 'rondin%', 'rondín%', 'impacto%', 'ratonera%', 'transmisor%', 'trasmisor%', 'pir 360%', 'pir360%', 'alarma%', 'detector%', 'humo%', 'overhead%', 'over head%', 'zona%', 'pull station%', 'pull%', 'cableado%', 'sirena%', 'receptor%', 'emisor%', 'llavin%', 'cristal%', 'ruptura%', 'repetidor%', 'repetidora%', 'btn%', 'rep%'];

$whereCCTV   = esc_like_prefixes('e.nom_equipo', $COLLATION, $PREFIJOS_CCTV);
$whereAlarma = esc_like_prefixes('e.nom_equipo', $COLLATION, $PREFIJOS_ALARMA);

$CASE_CCTV   = "CASE WHEN $whereCCTV THEN 1 ELSE 0 END";
$CASE_ALARMA = "CASE WHEN $whereAlarma THEN 1 ELSE 0 END";

/* =========================
   KPIs
========================= */
// Totales por categoría (para la dona) — versión consolidada
$qTotals = "
  SELECT
    SUM(CASE WHEN $whereCCTV   THEN 1 ELSE 0 END) AS cctv,
    SUM(CASE WHEN $whereAlarma THEN 1 ELSE 0 END) AS alarma
  FROM dispositivos d
  LEFT JOIN equipos e ON d.equipo = e.id
  WHERE YEAR(d.fecha) = YEAR(CURDATE())

";
$resTotals   = $conn->query($qTotals);
$rowTotals   = $resTotals ? $resTotals->fetch_assoc() : ['cctv' => 0, 'alarma' => 0];
$cctv_total  = (int)($rowTotals['cctv'] ?? 0);
$alarma_total= (int)($rowTotals['alarma'] ?? 0);


// Funcionabilidad de cámaras = % activas vs total cámaras
$qCamAct = "
  SELECT COUNT(*) AS activas
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  INNER JOIN status  s ON d.estado = s.id
  WHERE $whereCCTV
    AND UPPER(s.status_equipo) LIKE 'ACTIV%'
    AND YEAR(d.fecha) = YEAR(CURDATE())

";
$cam_activas    = (int)($conn->query($qCamAct)->fetch_assoc()['activas'] ?? 0);
$cam_total      = $cctv_total;

$func_cam_pct   = ($cam_total > 0) ? round(($cam_activas * 100) / $cam_total) : 0;

$qAlmAct = "
  SELECT COUNT(*) AS activas
  FROM dispositivos d
  INNER JOIN equipos e ON d.equipo = e.id
  INNER JOIN status  s ON d.estado = s.id
  WHERE $whereAlarma
    AND UPPER(s.status_equipo) LIKE 'ACTIV%'
    AND YEAR(d.fecha) = YEAR(CURDATE())

";
$alm_activas    = (int)($conn->query($qCamAct)->fetch_assoc()['activas'] ?? 0);
$alm_total      = $cctv_total;

$func_alm_pct   = ($alm_total > 0) ? round(($alm_activas * 100) / $alm_total) : 0;

// Otros KPIs existentes
$usuarios = (int)($conn->query("SELECT COUNT(*) AS total FROM usuarios")->fetch_assoc()['total'] ?? 0);

// Define tus constantes de estado según tu base de datos
$estados = " '$STATUS_HECHO', '$STATUS_SIGUIENTE', '$STATUS_PROCESO', '$STATUS_PENDIENTE'";

$qMto = "
  SELECT 
    id,
    sucursal_id,
    status_label AS status_manual, -- Lo pasamos como status_manual para que JS asigne el color rápido
    created_at AS last_fecha
  FROM mantenimiento_eventos
  WHERE status_label IN ($estados)
    AND YEAR(created_at) = YEAR(CURDATE())
";
$res = $conn->query($qMto);
$mantenimiento_lista = [];

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        // 2. Mapear para que JS lo entienda (usamos status_label como status_manual)
        $mantenimiento_lista[] = [
            'sucursal_id'   => $row['sucursal_id'],
            'status_manual' => $row['status_manual'], 
            'last_fecha'    => $row['last_fecha']
        ];
    }
}

//echo "<!-- DEBUG: " . count($mantenimiento_lista) . " registros encontrados -->";




/* =========================
   CÁMARAS ≠ ACTIVO (para modal)
========================= */
$sqlCamNoAct = "
  SELECT
    d.id                 AS dispositivo_id,
    e.nom_equipo         AS equipo,
    st.status_equipo     AS estatus,
    s.id                 AS sucursal_id,
    s.nom_sucursal       AS sucursal,
    m.nom_municipio      AS municipio,
    c.nom_ciudad         AS ciudad,
    r.nom_region         AS estado,
    dtr.nom_determinante AS determinante
  FROM dispositivos d
  INNER JOIN equipos   e  ON d.equipo   = e.id
  INNER JOIN status    st ON d.estado   = st.id
  INNER JOIN sucursales s ON d.sucursal = s.id
  INNER JOIN municipios m ON s.municipio_id = m.id
  INNER JOIN ciudades   c ON m.ciudad_id    = c.id
  INNER JOIN regiones   r ON c.region_id    = r.id
  LEFT  JOIN determinantes dtr ON dtr.sucursal_id = s.id
  WHERE $whereCCTV
    AND UPPER(st.status_equipo) NOT LIKE 'ACTIV%'
";
$cam_no_activas = [];
if ($res = $conn->query($sqlCamNoAct)) {
  while ($row = $res->fetch_assoc()) {
    $cam_no_activas[] = [
      'dispositivo_id' => (int)$row['dispositivo_id'],
      'equipo'         => $row['equipo'] ?? '',
      'estatus'        => $row['estatus'] ?? '',
      'sucursal_id'    => (int)$row['sucursal_id'],
      'sucursal'       => $row['sucursal'] ?? '',
      'municipio'      => $row['municipio'] ?? '',
      'ciudad'         => $row['ciudad'] ?? '',
      'estado'         => $row['estado'] ?? '',
      'determinante'   => $row['determinante'] ?? '',
    ];
  }
}

$sqlAlarmNoAct = "
  SELECT
    d.id                 AS dispositivo_id,
    e.nom_equipo         AS equipo,
    st.status_equipo     AS estatus,
    s.id                 AS sucursal_id,
    s.nom_sucursal       AS sucursal,
    m.nom_municipio      AS municipio,
    c.nom_ciudad         AS ciudad,
    r.nom_region         AS estado,
    dtr.nom_determinante AS determinante
  FROM dispositivos d
  INNER JOIN equipos   e  ON d.equipo   = e.id
  INNER JOIN status    st ON d.estado   = st.id
  INNER JOIN sucursales s ON d.sucursal = s.id
  INNER JOIN municipios m ON s.municipio_id = m.id
  INNER JOIN ciudades   c ON m.ciudad_id    = c.id
  INNER JOIN regiones   r ON c.region_id    = r.id
  LEFT  JOIN determinantes dtr ON dtr.sucursal_id = s.id
  WHERE $whereAlarma
    AND UPPER(st.status_equipo) NOT LIKE 'ACTIV%'
";
$alm_no_activas = [];
if ($res = $conn->query($sqlAlarmNoAct)) {
  while ($row = $res->fetch_assoc()) {
    $alm_no_activas[] = [
      'dispositivo_id' => (int)$row['dispositivo_id'],
      'equipo'         => $row['equipo'] ?? '',
      'estatus'        => $row['estatus'] ?? '',
      'sucursal_id'    => (int)$row['sucursal_id'],
      'sucursal'       => $row['sucursal'] ?? '',
      'municipio'      => $row['municipio'] ?? '',
      'ciudad'         => $row['ciudad'] ?? '',
      'estado'         => $row['estado'] ?? '',
      'determinante'   => $row['determinante'] ?? '',
    ];
  }
}


/* =========================
   COROPLETA POR REGIÓN
========================= */
$sqlEstados = "
  SELECT
    r.nom_region AS estado,
    SUM($CASE_CCTV)   AS cctv,
    SUM($CASE_ALARMA) AS alarma
  FROM sucursales s
  INNER JOIN municipios m ON s.municipio_id = m.id
  INNER JOIN ciudades   c ON m.ciudad_id    = c.id
  INNER JOIN regiones   r ON c.region_id    = r.id
  LEFT  JOIN dispositivos d ON d.sucursal   = s.id
  LEFT  JOIN equipos     e ON d.equipo      = e.id
  GROUP BY r.nom_region
";
$porEstado = [];
if ($res = $conn->query($sqlEstados)) {
  while ($row = $res->fetch_assoc()) {
    $k = normalize_key_es($row['estado'] ?? '');
    $cctv   = (int)($row['cctv']   ?? 0);
    $alarma = (int)($row['alarma'] ?? 0);
    $porEstado[$k] = [
      'cctv'  => $cctv,
      'alarma'=> $alarma,
      'total' => $cctv + $alarma,
      'label' => $row['estado'],
    ];
  }
}

/* =========================
   SUCURSALES (marcadores)
========================= */
$stHecho     = $conn->real_escape_string($STATUS_HECHO);
$stPendiente = $conn->real_escape_string($STATUS_PENDIENTE);
$stProceso   = $conn->real_escape_string($STATUS_PROCESO);
$stSig       = $conn->real_escape_string($STATUS_SIGUIENTE);

$sqlSucursales = "
  SELECT
    r.nom_region           AS estado,
    c.nom_ciudad           AS ciudad,
    m.nom_municipio        AS municipio,
    s.id                   AS sucursal_id,
    s.nom_sucursal         AS sucursal,
    dtr.nom_determinante   AS determinante,
    s.lat, s.lng,
    s.status_manual,

    SUM($CASE_CCTV)        AS cctv,
    SUM($CASE_ALARMA)      AS alarma,
    COUNT(disp.id)         AS disp_count,

    SUM(CASE WHEN st.status_equipo = '$stHecho'     THEN 1 ELSE 0 END) AS mto_hecho,
    SUM(CASE WHEN st.status_equipo = '$stPendiente' THEN 1 ELSE 0 END) AS mto_pendiente,
    SUM(CASE WHEN st.status_equipo = '$stProceso'   THEN 1 ELSE 0 END) AS mto_proceso,
    SUM(CASE WHEN st.status_equipo = '$stSig'       THEN 1 ELSE 0 END) AS mto_siguiente,

    MAX(disp.fecha)        AS last_fecha
  FROM sucursales s
  INNER JOIN municipios m       ON s.municipio_id = m.id
  INNER JOIN ciudades   c       ON m.ciudad_id    = c.id
  INNER JOIN regiones   r       ON c.region_id    = r.id
  LEFT  JOIN determinantes dtr  ON dtr.sucursal_id = s.id
  LEFT  JOIN dispositivos disp  ON disp.sucursal   = s.id
  LEFT  JOIN equipos      e     ON disp.equipo     = e.id
  LEFT  JOIN status       st    ON disp.estado     = st.id
  GROUP BY
    r.nom_region, c.nom_ciudad, m.nom_municipio,
    s.id, s.nom_sucursal, dtr.nom_determinante, s.lat, s.lng, s.status_manual
";

$sucursales = [];
if ($res = $conn->query($sqlSucursales)) {
  while ($row = $res->fetch_assoc()) {
    $row['cctv']          = (int)($row['cctv'] ?? 0);
    $row['alarma']        = (int)($row['alarma'] ?? 0);
    $row['total']         = $row['cctv'] + $row['alarma'];
    $row['disp_count']    = (int)($row['disp_count'] ?? 0);
    $row['mto_hecho']     = (int)($row['mto_hecho'] ?? 0);
    $row['mto_pendiente'] = (int)($row['mto_pendiente'] ?? 0);
    $row['mto_proceso']   = (int)($row['mto_proceso'] ?? 0);
    $row['mto_siguiente'] = (int)($row['mto_siguiente'] ?? 0);
    $row['last_fecha']    = $row['last_fecha'] ?? null;
    $sucursales[] = $row;
  }
}

/* =========================
   MÉTRICAS DE CÁMARAS POR SUCURSAL
========================= */
$sqlBranchStats = "
  SELECT
    s.id                   AS sucursal_id,
    s.nom_sucursal         AS sucursal,
    r.nom_region           AS estado,
    c.nom_ciudad           AS ciudad,
    m.nom_municipio        AS municipio,
    dtr.nom_determinante   AS determinante,
    SUM(CASE WHEN $whereCCTV THEN 1 ELSE 0 END) AS cctv_total,
    SUM(CASE WHEN $whereCCTV AND UPPER(st.status_equipo) LIKE 'ACTIV%' THEN 1 ELSE 0 END) AS cctv_activas
  FROM sucursales s
  INNER JOIN municipios m       ON s.municipio_id = m.id
  INNER JOIN ciudades   c       ON m.ciudad_id    = c.id
  INNER JOIN regiones   r       ON c.region_id    = r.id
  LEFT  JOIN determinantes dtr  ON dtr.sucursal_id = s.id
  LEFT  JOIN dispositivos disp  ON disp.sucursal   = s.id
  LEFT  JOIN equipos      e     ON disp.equipo     = e.id
  LEFT  JOIN status       st    ON disp.estado     = st.id
  GROUP BY s.id, s.nom_sucursal, r.nom_region, c.nom_ciudad, m.nom_municipio, dtr.nom_determinante
";

$branch_stats = [];
if ($res = $conn->query($sqlBranchStats)) {
  while ($row = $res->fetch_assoc()) {
    $total   = (int)($row['cctv_total'] ?? 0);
    $activas = (int)($row['cctv_activas'] ?? 0);
    $pct     = $total > 0 ? round(($activas * 100) / $total) : 0;
    $branch_stats[] = [
      'sucursal_id'  => (int)$row['sucursal_id'],
      'sucursal'     => $row['sucursal'] ?? '',
      'estado'       => $row['estado'] ?? '',
      'ciudad'       => $row['ciudad'] ?? '',
      'municipio'    => $row['municipio'] ?? '',
      'determinante' => $row['determinante'] ?? '',
      'cctv_total'   => $total,
      'cctv_activas' => $activas,
      'cctv_no_act'  => max(0, $total - $activas),
      'func_pct'     => $pct
    ];
  }
}

?>
<style>
  :root{ --brand:#3b82f6; --brand-2:#7c3aed; --ink:#0f172a; }
  h2{ font-weight:800; letter-spacing:.2px; color:var(--ink); margin-bottom:.75rem!important; }
  h2::after{ content:""; display:block; width:78px; height:4px; border-radius:99px; margin-top:.5rem; background:linear-gradient(90deg,var(--brand),var(--brand-2)); }

  .card { border:1px solid #eef2f7; }
  .card .card-title {letter-spacing:.2px; }
  .text-bg-light { background:#f5f7fb !important; }
  .badge.text-bg-light { background:#eef3fb !important; color:#334 !important; border:1px solid #dae4f5; }

  .leaflet-container { font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial; }
  .leaflet-control-layers, .legend { background:#fff; border-radius:8px; box-shadow:0 6px 16px rgba(0,0,0,.12); padding:.5rem .75rem; border:1px solid #e6eef5; }
  .legend .legend-title { display:none!important; }
  .legend { font-size:13px; }
  .legend-chips { display:flex; flex-wrap:wrap; gap:6px; }

  #searchResults .list-group-item { cursor:pointer; }
  #searchResults .list-group-item.active { background:#e9f2ff; }

  .det-icon { background:transparent; border:none; cursor:pointer; }
  .det-chip { display:flex; align-items:center; justify-content:center; height:28px; padding:0 14px; min-width:72px; border-radius:999px; background:#fff; border:1px solid #d5e1f0; box-shadow:0 6px 14px rgba(0,0,0,.15); font:600 13px/1 system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Helvetica Neue"; white-space:nowrap; user-select:none; }
  .det-chip.glow { box-shadow:0 0 0 3px rgba(56,132,255,.25),0 6px 14px rgba(0,0,0,.18)!important; }

  /* Colores de estado */
  .det-chip.green  { background:#01a806; border-color:#01a806; color:#fff; }
  .det-chip.orange { background:#f39c12; border-color:#f39c12; color:#000; }
  .det-chip.yellow { background:#f1c40f; border-color:#f1c40f; color:#000; }
  .det-chip.blue   { background:#2980b9; border-color:#2980b9; color:#fff; }
  .det-chip.pending { border-style:dashed; border-width:2px; }

  /* Clústers coloreados */
  .marker-cluster.marker-cluster-green div { background: rgba(1,168,6,0.6); border: 2px solid #01a806; color:#fff; }
  .marker-cluster.marker-cluster-orange div { background: rgba(243,156,18,0.6); border: 2px solid #f39c12; color:#000; }
  .marker-cluster.marker-cluster-yellow div { background: rgba(241,196,15,0.6); border: 2px solid #f1c40f; color:#000; }
  .marker-cluster.marker-cluster-blue div { background: rgba(41,128,185,0.65); border: 2px solid #2980b9; color:#fff; }
  .marker-cluster div span { font-weight:800; }

  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  .bar-chart-container{ position:relative; height:360px; width:100%; overflow:hidden; }
  .bar-chart-container canvas{ width:100%!important; height:100%!important; display:block; }
  .mini-bar-container{ position:relative; height:220px; width:100%; overflow:hidden; }
  .mini-bar-container canvas{ width:100% !important; height:100% !important; display:block; }

  .h-100 { height:100% !important; }
  .donut-container{ position:relative; height:320px; width:100%; }
  @media (max-width:768px){
    #map, #mapSkeleton { height:420px!important; }
    .card .card-title { font: size 0.4rem; }
    .bar-chart-container{ height:260px; }
    .donut-container{ height:280px; }
  }
  .donut-container canvas{ width:100% !important; height:100% !important; display:block; }

  /* Filtros del Mapa (chips de arriba) */
  .map-filters .mf-pill{
    border:1px solid #d5e1f0; background:#fff; color:#223; border-radius:999px;
    padding:6px 12px; font:600 13px/1 system-ui,-apple-system,"Segoe UI",Roboto,Arial;
    display:inline-flex; align-items:center; gap:8px; cursor:pointer;
    box-shadow:0 2px 6px rgba(0,0,0,.06);
  }
  .map-filters .mf-pill:hover{ background:#f8fbff; }
  .map-filters .mf-pill.active{ box-shadow:0 0 0 3px rgba(30,102,197,.12); }
  .map-filters .mf-badge{ min-width:24px; padding:2px 6px; text-align:center; border-radius:999px; background:#eef3fb; color:#223; border:1px solid #dae4f5; font-weight:700; }
  .mf-green{ border-top:3px solid #01a806; } .mf-orange{ border-top:3px solid #f39c12; } .mf-yellow{ border-top:3px solid #f1c40f; } .mf-blue{ border-top:3px solid #2980b9; } .mf-all{ border-top:3px solid #6c757d; }

  /* Drawer/Toasts */
  .drawer-section-title{ font-weight:700; margin:10px 0 6px; }
  .drawer-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:8px; }
  .badge-soft{ background:#eef3fb; border:1px solid #dae4f5; color:#223; }
  .toast-item{ min-width: 320px; max-width: 420px; }
  .toast-header .badge { font-size:.72rem; }

  /* Mini KPIs */
  .kpi-mini { border:1px solid #e3eaf5; border-radius:14px; }
  .kpi-mini .card-body { position:relative; }
  .kpi-card{ height: 4.6rem;}
  .kpi-label { font:600 13px/1.1 system-ui,-apple-system,"Segoe UI",Roboto,Arial; color:#74869b; }
  .kpi-value { font:700 26px/1.2 system-ui,-apple-system,"Segoe UI",Roboto,Arial; color:#1f2937; margin-top:4px; }
  .kpi-dot { width:18px; height:18px; border:3px solid #00ff00; border-radius:50%; background:#fff; box-shadow:0 4px 10px rgba(91,132,255,.06); }
  .kpi-icon { width:30px; height:30px; border-radius:8px; background:#e9f5ff; box-shadow:inset 0 0 0 1px #0073ff; }
  .apx-sparkline { height:76px; margin-top:10px; }
  .apx-radial { height:120px; margin-top:4px; }


  .calendar-card { border:1px solid #eef2f7; border-radius:10px; padding:10px; }
  #miniCalendar .fc .fc-toolbar-title{ font-size:.95rem; }
  #miniCalendar .fc .ev-hecho{ --fc-event-bg-color:#01a806; --fc-event-border-color:#01a806; --fc-event-text-color:#fff; }
  #miniCalendar .ev-hecho .fc-event-main{ color:#fff; }
  #miniCalendar .fc .ev-pendiente{ --fc-event-bg-color:#f39c12; --fc-event-border-color:#f39c12; --fc-event-text-color:#000; }
  #miniCalendar .ev-pendiente .fc-event-main{ color:#000; }
  #miniCalendar .fc .ev-proceso{ --fc-event-bg-color:#f1c40f; --fc-event-border-color:#f1c40f; --fc-event-text-color:#000; }
  #miniCalendar .ev-proceso .fc-event-main{ color:#000; }
  #miniCalendar .fc .ev-siguiente{ --fc-event-bg-color:#2980b9; --fc-event-border-color:#2980b9; --fc-event-text-color:#fff; }
  #miniCalendar .ev-siguiente .fc-event-main{ color:#fff; }

  /* *** NUEVO *** Puntos de severidad (Centro de notificaciones) */
  .badge-sev { width:10px; height:10px; border-radius:50%; display:inline-block; }
  .badge-sev.sev-danger  { background:#dc3545; }  /* rojo crítico */
  .badge-sev.sev-warning { background:#f39c12; }  /* ámbar */
  .badge-sev.sev-info    { background:#0dcaf0; }  /* celeste */
</style>

<div style="padding-left: 25px;">
  <h2 class="mb-4">Panel de control</h2>

<!-- KPIs -->
<div class="kpi-row row row-cols-1 row-cols-sm-2 row-cols-md-3 g-1 justify-content-center text-center mb-4">

  <!-- KPI 1: Funcionabilidad de cámaras -->
  <div class="col d-flex">
    <div id="kpiCamFunc" class="card kpi-card shadow-sm flex-fill" style="cursor:pointer;">
      <div class= "row">
         <div class="col-1"></div>
        <div class="col-3">
          <i class="fas fa-video text-primary mt-3" ></i>
           <h5 class="card-title " ><?= $func_cam_pct ?>%</h5>
        </div>
        <div class="col-7" style="padding:0px;">
          <br> 
          <small><?= $cam_activas?> Cámaras activas de <?= $cam_total ?> en total</small>
           <?php $na_count = count($cam_no_activas); ?>
           <?php if ($na_count > 0): ?>
           <div class="">
            <span class="badge bg-danger"><?= $na_count ?> no activas</span>
           </div>
           <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
 
  <!-- KPI 2: Sensores -->
  <div class="col d-flex">
    <div id="kpiAlarmaFunc" class="card kpi-card shadow-sm flex-fill" style="cursor:pointer;">
      <div class="row">
        <div class="col-1"></div>
        <div class="col-3 " >
           <i class="fas fa-wifi text-info mt-3"></i>
           <h5 class="card-title"><?=  $func_alm_pct ?>%</h5>
        </div>
        <div class="col-7" style="padding:0px;" >
          <br>
           <small> <?= $alm_activas ?> Alarmas activas de <?= $alm_total ?> en total</small>
            <?php $na1_count = count($alm_no_activas); ?>
            <?php if ($na1_count > 0): ?>
          <div class="mt-1">
            <span class="badge bg-danger"><?= $na1_count ?> no activas</span>
          </div>
         <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
 <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador'])): ?>
  <!-- KPI 3: Usuarios -->
  <div class="col d-flex">
    <div class="card kpi-card shadow-sm flex-fill">
      <div class="row">
        <div class="col-1"></div>
       <div class="col-3">
         <i class="fas fa-user text-success mt-4"></i>
        </div>
         <div class="col-7">
         <h5 class="card-title mt-2" ><?= $usuarios ?></h5>
         <small >Usuarios registrados</small>
        </div>
    </div>
  </div>
 <?php endif; ?>
</div>
</div>


<!-- Mapa + Calendario -->
<div class="card shadow-sm">
  <div class="card-body">
    <div class="row g-4">
      
      <!-- COLUMNA IZQUIERDA: Encabezado, Mapa y KPIs -->
      <div class="col-lg-8 d-flex flex-column gap-3">
        
        <!-- 1. Encabezado del Mapa -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-3">
            <h6 class="card-title mb-0">Mapa de dispositivos</h6>
            <div class="badge text-bg-light" id="breadcrumb">México</div>
            <button class="btn btn-sm btn-outline-info" id="btnTour">Ayuda</button>
            <button class="btn btn-sm btn-outline-secondary position-relative" id="btnAlerts" title="Notificaciones">
              <i class="fas fa-bell"></i>
              <span id="alertsBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
            </button>
          </div>
          
          <div class="d-flex align-items-center gap-2">
            <button id="btnVolver" class="btn btn-sm btn-outline-secondary d-none">← Volver</button>
            <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Técnico'])): ?>
              <button id="toggleEdit" class="btn btn-sm btn-outline-warning">Editar estados</button>
            <?php endif; ?>
          </div>
        </div>

        <!-- 2. Buscador y Filtros -->
        <div class="row g-2 align-items-center">
          <div class="col-md-6 position-relative">
            <input id="searchInput" class="form-control form-control-sm pe-5" type="text" placeholder="Buscar sucursal o determinante...">
            <button id="clearSearch" type="button" class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-1 px-2" style="line-height:1;">✕</button>
            <div id="searchResults" class="list-group position-absolute w-100 d-none" style="z-index:1000; max-height:260px; overflow:auto;"></div>
          </div>
          <div class="col-md-12">
            <div id="mapFilters" class="map-filters d-flex flex-wrap align-items-center gap-2">
              <button type="button" class="mf-pill mf-all active" data-chip="all">Todos <span class="mf-badge" id="cnt-all">0</span></button>
              <button type="button" class="mf-pill mf-green active" data-chip="green">Realizado <span class="mf-badge" id="cnt-green">0</span></button>
              <button type="button" class="mf-pill mf-orange active" data-chip="orange">Pendiente <span class="mf-badge" id="cnt-orange">0</span></button>
              <button type="button" class="mf-pill mf-yellow active" data-chip="yellow">En proceso <span class="mf-badge" id="cnt-yellow">0</span></button>
              <button type="button" class="mf-pill mf-blue active" data-chip="blue">Siguiente <span class="mf-badge" id="cnt-blue">0</span></button>
            </div>
          </div>
        </div>

        <!-- 3. El Mapa -->
        <div id="mapSection">
          <div id="mapSkeleton" style="height:520px;border-radius:10px;background:linear-gradient(90deg,#f3f6fb 25%,#e9eef6 37%,#f3f6fb 63%);background-size:400% 100%;animation:shimmer 1.4s infinite;"></div>
          <div id="map" style="height:520px;border-radius:10px;overflow:hidden;display:none;"></div>
          <div class="mt-2 d-flex justify-content-between align-items-center">
            <small class="text-muted">Tip: clic en un estado para hacer zoom.</small>
            <div id="mapLegendMount"></div>
          </div>
        </div>
        <br>

        <!-- 4. Bloque de KPIs (Ahora debajo del mapa) -->
        <div class="row g-3">
          <div class="col-md-6">
            <div class="card kpi-mini shadow-sm border-0 bg-light">
              <!--div class="card-body p-3">
                <div class="kpi-label small text-uppercase fw-bold">Progreso de mantenimientos 
                  <strong>2026</strong></div>
                <div class="kpi-value h4 mb-0"><span id="kpiMaintHechas">0</span> / <span id="kpiMaintTotal">0</span></div>
                <div id="apxMaintSparkline" class="apx-sparkline"></div>
              </div-->
            </div>
          </div>
          <div class="col-md-6">
            <div class="card kpi-mini shadow-sm border-0 bg-light">
              <!--div class="card-body p-3">
                <div class="kpi-label small text-uppercase fw-bold">Porcentaje completado</div>
                <div class="kpi-value h4 mb-0"><span id="kpiMaintPctText">0%</span></div>
                <div id="apxMaintRadial" class="apx-radial"></div>
              </div-->
            </div>
          </div>
        </div>
      </div> <!-- Fin Columna Izquierda -->

      <!-- COLUMNA DERECHA: Calendario -->
      <div class="col-lg-4">
        <div class="calendar-card border p-3 rounded shadow-sm h-100">
          <div class="mb-3">
            <h6 class="mb-1">Calendario de mantenimientos</h6>
            <div class="d-flex flex-wrap gap-1">
              <span class="badge" style="background:#01a806;">Hecho</span>
              <span class="badge" style="background:#f39c12;">Pendiente</span>
              <span class="badge" style="background:#f1c40f;">En proceso</span>
              <span class="badge" style="background:#2980b9;">Siguiente</span>
            </div>
          </div>
          <div id="miniCalendar"></div>
          <small class="text-muted d-block mt-3 border-top pt-2">
            <i class="fas fa-info-circle me-1"></i> Clic en un evento para enfocar la sucursal.
          </small>
        </div>
      </div> <!-- Fin Columna Derecha -->

    </div> <!-- Fin Row -->
  </div> <!-- Fin Card Body -->
</div> <!-- Fin Card Principal -->

<br>


<!-- Fila de Gráficos: Donut + Sucursales -->
<div class="row g-4 mb-4 align-items-stretch">
  
  <!-- Donut (col-5) -->
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
          <div class="d-flex align-items-center gap-3">
            <h6 class="card-title mb-0">Dispositivos totales</h6>
            <div class="badge text-bg-light">CCTV vs Alarma</div>
          </div>
        </div>
        <div class="donut-container">
          <canvas id="devicesDonutChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Vista por Sucursales (col-7) -->
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
          <div class="d-flex align-items-center gap-3">
            <h6 class="card-title mb-0">Vista por sucursales</h6>
            <div class="badge text-bg-light">Cámaras CCTV</div>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <select id="br-metric" class="form-select form-select-sm" style="min-width:180px;">
              <option value="func_pct" selected>Funcionabilidad (%)</option>
              <option value="cctv_no_act">Cámaras no activas</option>
              <option value="cctv_total">Total de cámaras</option>
            </select>
            <select id="br-topn" class="form-select form-select-sm" style="min-width:100px;">
              <option value="10">Top 10</option>
              <option value="20" selected>Top 20</option>
              <option value="50">Top 50</option>
              <option value="0">Todas</option>
            </select>
            <input id="br-search" class="form-control form-control-sm" type="text" placeholder="Buscar..." style="max-width: 150px;">
          </div>
        </div>
        
        <small class="text-muted d-block mb-2">Tip: haz clic en una barra para abrir la sucursal.</small>
        
        <div class="bar-chart-container">
          <div id="branchBarChart"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Fila de Botones Rápidos (Abajo) -->
<div class="row g-3 justify-content-center mb-4">
  <div class="col-md-4 col-lg-3">
    <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Capturista','Técnico'])): ?>
      <a href="/sisec-ui/views/dispositivos/registro.php" class="btn btn-outline-primary w-100 py-3 rounded shadow-sm">
        <i class="fas fa-qrcode fa-lg me-2"></i> Registro nuevo dispositivo
      </a>
    <?php endif; ?>
  </div>
  <div class="col-md-4 col-lg-3">
    <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador'])): ?>
      <a href="/sisec-ui/views/usuarios/registrar.php" class="btn btn-outline-success w-100 py-3 rounded shadow-sm">
        <i class="fas fa-user-plus fa-lg me-2"></i> Registro rápido de usuario
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Drawer lateral (Offcanvas) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="sucursalDrawer" aria-labelledby="drawerTitle" style="width:420px;max-width:100%;">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="drawerTitle">Sucursal</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body" id="drawerBody"></div>
</div>

<!-- Modal: Cerrar mantenimiento -->
<div class="modal fade" id="modalCerrarMto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" id="formCerrarMto" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Cerrar mantenimiento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="sucursal_id" id="cerrar_sucursal_id">
        <input type="hidden" name="evento_id" id="cerrar_evento_id">
        <div class="mb-3">
          <label class="form-label">Descripción del trabajo realizado</label>
          <textarea class="form-control" name="descripcion" rows="3" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Evidencias (fotos, PDF)</label>
          <input class="form-control" type="file" name="archivos[]" accept=".jpg,.jpeg,.png,.pdf" multiple required>
          <small class="text-muted">Puedes subir varias evidencias (máx. 10 MB cada una).</small>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" type="submit">Marcar como realizado</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Extender fecha -->
<div class="modal fade" id="modalExtenderMto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formExtenderMto" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Extender fecha de mantenimiento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="sucursal_id" id="ext_sucursal_id">
        <input type="hidden" name="evento_id" id="ext_evento_id">
        <div class="mb-3">
          <label class="form-label">Nueva fecha programada</label>
          <input class="form-control" type="date" name="nueva_fecha" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Motivo de la extensión</label>
          <textarea class="form-control" name="motivo" rows="3" required placeholder="Ej. proveedor retrasado, refacciones, acceso restringido, etc."></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Evidencia (opcional)</label>
          <input class="form-control" type="file" name="evidencia" accept=".jpg,.jpeg,.png,.pdf">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar extensión</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal: Evidencias -->
<div class="modal fade" id="evidenciasModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          Evidencias del evento <span id="evModalId" class="text-muted"></span>
        </h5>
        <a id="evAbrirCarpeta" href="#" class="btn btn-sm btn-outline-secondary ms-2" target="_blank" rel="noopener">
          Abrir carpeta
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- Controles -->
        <div class="d-flex align-items-center gap-2 mb-3">
          <input id="evSearch" class="form-control form-control-sm" type="search" placeholder="Buscar archivo…">
          <span class="ms-auto small text-muted" id="evCount"></span>
        </div>

        <!-- Sección CIERRE -->
        <div class="mb-2 d-flex align-items-center gap-2">
          <h6 class="mb-0">Evidencia por cierre</h6>
          <span id="evBadgeCierre" class="badge text-bg-secondary">0</span>
        </div>
        <div id="evGridCierre" class="row g-3"></div>
        <div id="evEmptyCierre" class="text-center text-muted py-3 d-none">
          <i class="bi bi-folder-x" style="font-size:1.2rem;"></i>
          <div class="mt-1">Sin archivos de cierre.</div>
        </div>

        <hr class="my-3">

        <!-- Sección EXTENDER -->
        <div class="mb-2 d-flex align-items-center gap-2">
          <h6 class="mb-0">Evidencia por extensión</h6>
          <span id="evBadgeExt" class="badge text-bg-secondary">0</span>
        </div>
        <div id="evGridExt" class="row g-3"></div>
        <div id="evEmptyExt" class="text-center text-muted py-3 d-none">
          <i class="bi bi-folder-x" style="font-size:1.2rem;"></i>
          <div class="mt-1">Sin archivos de extensión.</div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>



<!--  *** NUEVO *** Modal: Centro de notificaciones -->
<div class="modal fade" id="modalAlerts" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          Centro de notificaciones
          <small class="text-muted ms-2">Mantenimientos por vencer / vencidos</small>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex gap-2 flex-wrap mb-3">
          <input id="alerts-search" class="form-control" placeholder="Buscar por sucursal, estado, estatus..." />
          <select id="alerts-filter" class="form-select" style="max-width:220px;">
            <option value="">Todos</option>
            <option value="danger">Críticos (≤1 día)</option>
            <option value="warning">Próximos (≤3 días)</option>
            <option value="info">Programados</option>
          </select>
          <button id="alerts-refresh" class="btn btn-outline-secondary">Actualizar</button>
        </div>
        <div class="table-responsive" style="max-height:60vh;">
          <table class="table table-sm align-middle">
            <thead class="table-light" style="position:sticky; top:0; z-index:1">
              <tr>
                <th style="width:1%;">Sev.</th>
                <th>Sucursal</th>
                <th>Rango</th>
                <th>Estatus</th>
                <th>Días</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="alerts-tbody"></tbody>
          </table>
        </div>
        <div id="alerts-empty" class="text-muted d-none">Sin notificaciones almacenadas.</div>
      </div>
    </div>
  </div>
</div>
 <?php 
 $Label_cam = 'Cámaras con estatus Inactivo';
 $Label_alam = 'Sensores con estatus Inactivo'
 ?>
<!-- Modal: Cámaras no activas -->
<div class="modal fade" id="modalCamsNoActivas" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          Cámaras con estatus Inactivo
          <span class="badge bg-secondary ms-2" id="na-count">0</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador'])): ?>
        <div class="d-flex gap-2 flex-wrap mb-3">
          <input id="na-search" type="text" class="form-control" placeholder="Buscar por sucursal, determinante, municipio, equipo o estatus…">
          <?php endif; ?>
          <button id="na-export" class="btn btn-outline-secondary">Exportar CSV</button>
        </div>

        <div class="table-responsive" style="max-height:60vh;">
          <table class="table table-sm align-middle" id="na-table">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
              <tr>
                <th>Determinante</th>
                <th>Sucursal</th>
                <th>Ubicación</th>
                <th>Equipo</th>
                <th>Estatus</th>
                <th style="width: 1%;">Acciones</th>
              </tr>
            </thead>
            <tbody id="na-tbody">
              <!-- filas por JS -->
            </tbody>
          </table>
        </div>

        <div id="na-empty" class="text-muted d-none"></div>
      </div>
    </div>
  </div>
</div>


<!-- Toasts (alertas por vencer) -->
<!-- <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
  <div id="toastArea"></div>
</div> -->

<?php if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } ?>
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js/minified/introjs.min.css">
<script src="https://cdn.jsdelivr.net/npm/intro.js/minified/intro.min.js"></script>

<script>
(function(){
  const BASE = '/sisec-ui';

  // Elementos del modal (nueva estructura)
  const modalEl        = document.getElementById('evidenciasModal');
  const evModalId      = document.getElementById('evModalId');
  const evSearch       = document.getElementById('evSearch');
  const evCount        = document.getElementById('evCount');
  const evAbrirCarpeta = document.getElementById('evAbrirCarpeta');

  const gridCierre = document.getElementById('evGridCierre');
  const gridExt    = document.getElementById('evGridExt');
  const emptyCierre= document.getElementById('evEmptyCierre');
  const emptyExt   = document.getElementById('evEmptyExt');
  const badgeCierre= document.getElementById('evBadgeCierre');
  const badgeExt   = document.getElementById('evBadgeExt');

  let currentEventId = null;
  let filesCierre = [];
  let filesExt    = [];

  // === Helpers ===
  function makeCard(f){
    const col = document.createElement('div');
    col.className = 'col-12 col-sm-6 col-md-4 col-lg-3';

    const card = document.createElement('div');
    card.className = 'card h-100 shadow-sm';

    // preview
    let preview;
    if (f.kind === 'image') {
      preview = document.createElement('img');
      preview.src = f.url;
      preview.alt = f.name;
      preview.className = 'card-img-top';
      preview.loading = 'lazy';
      preview.style.objectFit = 'cover';
      preview.style.height = '180px';
    } else if (f.kind === 'pdf') {
      preview = document.createElement('div');
      preview.className = 'ratio ratio-4x3';
      const iframe = document.createElement('iframe');
      iframe.src = f.url;
      iframe.title = f.name;
      iframe.loading = 'lazy';
      preview.appendChild(iframe);
    } else {
      preview = document.createElement('div');
      preview.className = 'd-flex align-items-center justify-content-center bg-light';
      preview.style.height = '180px';
      preview.innerHTML = '<div class="text-muted small">Archivo</div>';
    }
    card.appendChild(preview);

    const body = document.createElement('div');
    body.className = 'card-body d-flex flex-column';

    const title = document.createElement('div');
    title.className = 'fw-semibold text-truncate';
    title.title = f.name || '';
    title.textContent = f.name || '(sin nombre)';

    const meta = document.createElement('div');
    meta.className = 'text-muted small mt-1';
    meta.textContent = `${f.mime || '—'} · ${f.mtime || ''}`.trim();

    const actions = document.createElement('div');
    actions.className = 'mt-auto d-flex gap-2';

    const aVer = document.createElement('a');
    aVer.className = 'btn btn-sm btn-primary';
    aVer.href = f.url;
    aVer.target = '_blank';
    aVer.rel = 'noopener';
    aVer.textContent = (f.kind === 'image' ? 'Ver' : 'Abrir');

    const aDesc = document.createElement('a');
    aDesc.className = 'btn btn-sm btn-outline-secondary';
    aDesc.href = f.url;
    aDesc.download = f.name || '';
    aDesc.textContent = 'Descargar';

    actions.appendChild(aVer);
    actions.appendChild(aDesc);

    body.appendChild(title);
    body.appendChild(meta);
    body.appendChild(actions);

    card.appendChild(body);
    col.appendChild(card);
    return col;
  }

  function renderSection(gridEl, emptyEl, badgeEl, files){
    gridEl.innerHTML = '';
    if (!files.length){
      emptyEl.classList.remove('d-none');
      badgeEl.textContent = '0';
      return;
    }
    emptyEl.classList.add('d-none');
    badgeEl.textContent = String(files.length);
    files.forEach(f => gridEl.appendChild(makeCard(f)));
  }

  function renderAll(){
    renderSection(gridCierre, emptyCierre, badgeCierre, filesCierre);
    renderSection(gridExt,    emptyExt,    badgeExt,    filesExt);

    const total = filesCierre.length + filesExt.length;
    evCount.textContent = total ? `${total} archivo(s)` : '';
  }

  // === API ===
  async function fetchEvidencias(eventoId){
    const url = `${BASE}/views/api/mto_evidencias_listar.php?evento_id=${encodeURIComponent(eventoId)}`;
    const res = await fetch(url, { cache:'no-store', credentials:'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    if (data?.ok !== true) throw new Error(data?.error || 'Error API');

    // Acepta dos formatos:
    // 1) { ok:true, files:[...] }  (sin categoría)
    // 2) { ok:true, cierre:[...], extender:[...] }
    if (Array.isArray(data.files)) {
      // Intenta inferir categoría si viene en la propiedad .type o .categoria
      const arr = data.files.map(f => ({
        ...f,
        kind: f.kind || (/\.(png|jpe?g|gif|webp)$/i.test(f.name||'') ? 'image' : /\.pdf$/i.test(f.name||'') ? 'pdf' : 'file')
      }));
      // Si no hay categoría, muestra todo en "cierre"
      return { cierre: arr, extender: [] };
    }
    const cierre  = Array.isArray(data.cierre)   ? data.cierre  : [];
    const extender= Array.isArray(data.extender) ? data.extender: [];
    // Normaliza kind por si no llega
    const norm = f => ({ ...f, kind: f.kind || (/\.(png|jpe?g|gif|webp)$/i.test(f.name||'') ? 'image' : /\.pdf$/i.test(f.name||'') ? 'pdf' : 'file') });
    return { cierre: cierre.map(norm), extender: extender.map(norm) };
  }

  // === Filtro local por búsqueda ===
  function applyFilter(){
    const q = (evSearch.value || '').trim().toLowerCase();
    if (!q){ renderAll(); return; }
    const byText = f => (f.name || '').toLowerCase().includes(q);
    renderSection(gridCierre, emptyCierre, badgeCierre, filesCierre.filter(byText));
    renderSection(gridExt,    emptyExt,    badgeExt,    filesExt.filter(byText));
    const total = filesCierre.filter(byText).length + filesExt.filter(byText).length;
    evCount.textContent = total ? `${total} archivo(s)` : '';
  }
  evSearch.addEventListener('input', applyFilter);

  // === API pública ===
  window.openEvidenciasModal = async function(eventoId){
    try{
      currentEventId = eventoId;
      evModalId.textContent = `#${eventoId}`;
      evAbrirCarpeta.href = `${BASE}/public/mto_evidencias/${encodeURIComponent(eventoId)}/`;

      // Estado de carga
      evSearch.value = '';
      evCount.textContent = 'Cargando…';
      filesCierre = [];
      filesExt    = [];
      renderAll();

      // Cargar
      const data = await fetchEvidencias(eventoId);
      filesCierre = data.cierre || [];
      filesExt    = data.extender || [];
      renderAll();

      // Abrir modal
      const m = bootstrap.Modal.getOrCreateInstance(modalEl);
      m.show();
    } catch(e){
      console.error(e);
      alert('No se pudieron cargar las evidencias.');
    }
  };
})();
</script>


<script>
// ===== Datos desde PHP =====
const CCTV_NON_ACTIVE = <?php echo json_encode($cam_no_activas, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const ALARMA_NON_ACTIVE = <?php echo json_encode($alm_no_activas, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const BRANCH_STATS = <?php echo json_encode($branch_stats, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const DEV_TOTALS = { cctv: <?= (int)$cctv_total ?>, alarma: <?= (int)$alarma_total ?> };
const NEXT_MAINTENANCE_FALLBACK_DAYS = <?= (int)$NEXT_MAINTENANCE_FALLBACK_DAYS ?>;
const metricasPorEstadoRaw = <?php echo json_encode($porEstado, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const sucursales = <?php echo json_encode($sucursales, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const mantenimiento = <?php echo json_encode($mantenimiento_lista); ?>;
// *** NUEVO *** Endpoints de alertas (ajusta si usas otros)
const ALERTS_UPSERT_URL = '/sisec-ui/views/api/mto_alerts_upsert.php';
const ALERTS_LIST_URL   = '/sisec-ui/views/api/mto_alerts_list.php';

// ===== Utils =====
function toISO10(d){ return (d||'').slice(0,10); }
function todayISO(){ return new Date().toISOString().slice(0,10); }
function addDaysISO(iso, n){ const d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate()+n); return d.toISOString().slice(0,10); }
function daysBetween(aISO,bISO){ const a=new Date(aISO+'T00:00:00'); const b=new Date(bISO+'T00:00:00'); return Math.round((b-a)/(1000*60*60*24)); }
function inRangeInclusive(dayISO, startISO, endISOIncl){ return (startISO <= dayISO) && (dayISO <= endISOIncl); }
const normalizeKey = s => (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^A-Za-z0-9\s]/g,'').trim().replace(/\s+/g,' ').toLowerCase();
const normalizeText= s => (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();

// ===== Índice calendario para el mapa =====
const eventsBySucursal = new Map();

// ===== Normalización de status (calendario) =====
function normStatusSlug(s){
  const t = (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
  if (/(hecho|realizado|completado|finalizado|cerrado|ok|listo)/.test(t)) return 'hecho';
  if (/(proceso|en curso|ejecucion|trabajando|hoy)/.test(t))              return 'proceso';
  if (/(sig|siguiente|prox\.?|pr[oó]x\.?|proximo|pr[oó]ximo|programado)/.test(t)) return 'siguiente';
  if (/(pend|pendiente|por hacer|to do|programar|sin fecha)/.test(t))             return 'pendiente';
  if (/^ok$/.test(t))    return 'hecho';
  if (/^proc?$/.test(t)) return 'proceso';
  if (/^pend?$/.test(t)) return 'pendiente';
  if (/^sig$/.test(t))   return 'siguiente';
  return 'pendiente';
}

// ===== Derivar color desde eventos (ajustado para priorizar "hecho") =====
function chipFromEvents(sucursalId){
  const currentYear = new Date().getFullYear().toString();

  // Mantenemos toda tu lógica, pero filtramos para que solo entren eventos de este año
  const list = (eventsBySucursal.get(String(sucursalId)) || [])
    .filter(ev => {
      const startYear = String(ev.start || '').substring(0, 4);
      const endYear = String(ev.end || ev.start || '').substring(0, 4);
      return startYear === currentYear || endYear === currentYear;
    })
    .map(ev => ({
      id: String(ev.id || ''),
      start: toISO10(ev.start),
      end: toISO10(ev.end || ev.start),
      slug: normStatusSlug(ev.slug || ev.label || ev.title || '')
    }));

  if (!list.length) return null;

  // --- De aquí hacia abajo es EXACTAMENTE tu código original ---
  list.sort((a,b) => a.start.localeCompare(b.start));
  const hoy = todayISO();

  // ✅ PRIORIDAD ABSOLUTA: si hay algún evento marcado como "hecho", pintamos verde
  const hasHecho = list.some(ev => ev.slug === 'hecho');
  if (hasHecho) return 'green';

  // 1) Si HOY está dentro de alguna ventana => EN PROCESO (amarillo)
  for (const ev of list){
    const endIncl = (ev.end === ev.start) ? ev.end : addDaysISO(ev.end, -1);
    if (inRangeInclusive(hoy, ev.start, endIncl)) return 'yellow';
  }

  // 2) Hay eventos pasados => VERDE
  if (list.some(ev => {
    const endIncl = (ev.end === ev.start) ? ev.end : addDaysISO(ev.end, -1);
    return endIncl < hoy;
  })) return 'green';

  // 3) Todos son futuros
  const futuros = list.filter(ev => ev.start > hoy);
  if (futuros.length){
    if (futuros.some(ev => ev.slug === 'siguiente')) return 'blue';
    const prox = futuros[0];
    if (prox.slug === 'proceso')   return 'yellow';
    if (prox.slug === 'hecho')     return 'green';
    if (prox.slug === 'pendiente') return 'orange';
    return 'orange';
  }

  return 'orange';
}



// ===== Lógica base/fallback de color =====
function baseChipClassForSucursal(s) {
  const man = (s.status_manual || '').toLowerCase();
  if (man === 'hecho')     return 'green';
  if (man === 'pendiente') return 'orange';
  if (man === 'proceso')   return 'yellow';
  if (man === 'siguiente') return 'blue';

  const disp = Number(s.disp_count || 0);
  const hecho = Number(s.mto_hecho || 0);
  const pendiente = Number(s.mto_pendiente || 0);
  const proceso = Number(s.mto_proceso || 0);
  const siguiente = Number(s.mto_siguiente || 0);
  if (pendiente > 0) return 'orange';
  if (proceso   > 0) return 'yellow';
  if (siguiente > 0) return 'blue';

  if (disp > 0) {
    if (!s.last_fecha) return 'blue';
    const last = new Date(String(s.last_fecha) + 'T00:00:00');
    const now  = new Date();
    const diff = Math.floor((now - last)/(1000*60*60*24));
    if (diff >= NEXT_MAINTENANCE_FALLBACK_DAYS) return 'blue';
    return (hecho > 0 || diff < NEXT_MAINTENANCE_FALLBACK_DAYS) ? 'green' : 'blue';
  }
  return 'orange';
}

// ===== Color final =====
function chipClassForSucursal(s){
  const fromCal = chipFromEvents(s.sucursal_id);
  if (fromCal) return fromCal;
  const man = (s.status_manual || '').toLowerCase();
  if (man) return ({hecho:'green', pendiente:'orange', proceso:'yellow', siguiente:'blue'}[man] || baseChipClassForSucursal(s));
  return baseChipClassForSucursal(s);
}

// ===== DOM & prefs =====
const breadcrumbEl = document.getElementById('breadcrumb');
const btnVolver    = document.getElementById('btnVolver');
const searchInput  = document.getElementById('searchInput');
const clearSearch  = document.getElementById('clearSearch');
const searchResults= document.getElementById('searchResults');
const mapSection   = document.getElementById('mapSection');
const toggleEditBtn= document.getElementById('toggleEdit');
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const metricasPorEstado = {}; for (const [k,v] of Object.entries(metricasPorEstadoRaw || {})) metricasPorEstado[normalizeKey(k)] = v;

let nivel='pais', estadoActual=null, estadoActualKey=null;
let editMode = false;

// ===== Leaflet =====
const mexicoBounds = L.latLngBounds([14.0,-119.0], [33.5,-86.0]);
const map = L.map('map', { preferCanvas:true, maxBounds:mexicoBounds, maxBoundsViscosity:1.0, worldCopyJump:false, inertia:false, minZoom:4, maxZoom:18 });
const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18, attribution:'&copy; OpenStreetMap', noWrap:true, bounds:mexicoBounds});
const cluster = L.markerClusterGroup({
  disableClusteringAtZoom: 13,
  maxClusterRadius: 55,
  iconCreateFunction: cl => {
    const markers = cl.getAllChildMarkers();
    const counts = {green:0, orange:0, yellow:0, blue:0};
    for (const m of markers){ const chip = m?.options?._chip || 'green'; counts[chip] = (counts[chip]||0)+1; }
    const dominant = Object.entries(counts).sort((a,b)=>b[1]-a[1])[0][0] || 'green';
    const n = markers.length; const size = n<10?'small':(n<100?'medium':'large');
    return L.divIcon({ html:`<div><span>${n}</span></div>`, className:`marker-cluster marker-cluster-${size} marker-cluster-${dominant}`, iconSize:L.point(40,40) });
  }
});

let estadosLayer=null, legendContainer=null; const activeChips = new Set(['green','orange','yellow','blue']); let currentQuery='';

function breadcrumbSet(){ const parts=['México']; if (estadoActual) parts.push(estadoActual); breadcrumbEl.textContent = parts.join(' > '); }
function colorScale(v,max){ const t=max? v/max : 0; const a = 0.12 + 0.68*t; return `rgba(30,102,197,${a})`; }

function scopeSucursales(){ let base=(sucursales||[]).filter(s=>s.lat!=null&&s.lng!=null); if(estadoActualKey) base=base.filter(s=>normalizeKey(s.estado)===estadoActualKey); if(currentQuery){ const qn=normalizeText(currentQuery); base=base.filter(s=> normalizeText([s.sucursal||'',s.determinante||'',s.municipio||'',s.ciudad||'',s.estado||''].join(' | ')).includes(qn)); } return base; }

const cntAll = document.getElementById('cnt-all'), cntGreen = document.getElementById('cnt-green'), cntOrange = document.getElementById('cnt-orange'), cntYellow = document.getElementById('cnt-yellow'), cntBlue = document.getElementById('cnt-blue');
function updateFilterCounters(){ const base=scopeSucursales(); const counts={green:0,orange:0,yellow:0,blue:0}; for(const s of base) counts[chipClassForSucursal(s)]++; const total=base.length; cntAll.textContent=String(total); cntGreen.textContent=String(counts.green); cntOrange.textContent=String(counts.orange); cntYellow.textContent=String(counts.yellow); cntBlue.textContent=String(counts.blue); }

const markersById = new Map();
function buildIconFor(s){ const det = (s.determinante && String(s.determinante).trim()!=='') ? String(s.determinante).trim() : '—'; const colorClass = chipClassForSucursal(s); const pendingClass = (colorClass==='orange' && Number(s.disp_count||0)===0)?'pending':''; return L.divIcon({ className:'det-icon', html:`<div class="det-chip ${colorClass} ${pendingClass}">${det}</div>`, iconSize:[64,28], iconAnchor:[32,14], popupAnchor:[0,-14] }); }
function addMarkerFor(s){
  if(s.lat==null||s.lng==null) return;
  const icon=buildIconFor(s);
  const m=L.marker([s.lat,s.lng],{icon});
  m.options._chip = chipClassForSucursal(s);

  const detBadge = s.determinante ? ` <span class="badge bg-light text-dark ms-1">#${s.determinante}</span>` : '';
  const linea = `<small>${s.municipio}, ${s.ciudad} · ${s.estado}</small>`;
  const label = (()=>{ const c=chipClassForSucursal(s); if(c==='orange') return 'Mantenimiento pendiente'; if(c==='yellow') return 'Mantenimiento en proceso'; if(c==='blue') return 'Siguiente mantenimiento'; return 'Mantenimiento hecho'; })();
  const manualHint = s.status_manual ? `<span class=\"badge rounded-pill text-bg-warning ms-2\">• Manual</span>` : '';
  const editor = `
    <div class="mt-2 ${editMode?'':'d-none'}" data-editor="status">
      <div class="btn-group btn-group-sm w-100" role="group">
        <button class="btn btn-outline-success"   data-set="hecho">Hecho</button>
        <button class="btn btn-outline-warning"   data-set="pendiente">Pendiente</button>
        <button class="btn btn-outline-secondary" data-set="proceso">En proceso</button>
        <button class="btn btn-outline-info"      data-set="siguiente">Siguiente</button>
        <button class="btn btn-outline-dark"      data-set="auto" title="Quitar override">Auto</button>
      </div>
      <small class="text-muted d-block mt-1">Este cambio solo afecta el color/estado visible de la sucursal.</small>
    </div>`;
  m.bindPopup(`<b>${s.sucursal}</b>${detBadge}${manualHint}<br>${linea}<hr style="margin:.5rem 0"/><div>Total disp.: <b>${s.total}</b> (CCTV: ${s.cctv} · Alarma: ${s.alarma})</div><div class="mt-1"><span class="badge bg-secondary">${label}</span></div>${editor}<div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="/sisec-ui/views/dispositivos/listar.php?sucursal_id=${encodeURIComponent(s.sucursal_id)}#filtros">Ver dispositivos</a></div>`);

  // Abrir Drawer al click (drill-down sin salir)
  m.on('click', () => openDrawerSucursal(s));

  m.on('popupopen',()=>{ const p=m.getPopup()?.getElement(); if(!p) return; const wrap=p.querySelector('[data-editor="status"]'); if(!wrap) return; wrap.addEventListener('click',async(ev)=>{ const btn=ev.target.closest('button[data-set]'); if(!btn) return; const newState=btn.getAttribute('data-set'); await setManualStatus(s,newState,m); });});

  markersById.set(String(s.sucursal_id), m);
  cluster.addLayer(m);
}

async function setManualStatus(sucursalRecord, newState, marker){
  if(!CSRF){ alert('No se encontró token CSRF. Refresca la página.'); return; }
  try{
    const body=new URLSearchParams();
    body.set('csrf',CSRF);
    body.set('sucursal_id', String(sucursalRecord.sucursal_id));
    body.set('estado', newState);
    const resp = await fetch('/sisec-ui/views/api/sucursales_set_status.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body: body.toString() });
    const data=await resp.json().catch(()=>null);
    if(!resp.ok || !data || !data.ok){ const serverMsg = data?.error ? `\nDetalle: ${data.error}` : ''; throw new Error(`HTTP ${resp.status}${serverMsg}`); }
    const local=(sucursales||[]).find(x=>String(x.sucursal_id)===String(sucursalRecord.sucursal_id));
    if(local){ local.status_manual = (newState==='auto')? null : newState; }
    const rec = local || sucursalRecord; const newIcon=buildIconFor(rec); marker.setIcon(newIcon); marker.options._chip = chipClassForSucursal(rec);
    renderSucursales(); updateFilterCounters(); updateMaintenanceProgress();
  } catch(e){ alert('No se pudo guardar el estado.' + (e?.message? `\n${e.message}` : '')); }
}

function renderSucursales(){
  const filtered = scopeSucursales().filter(s => activeChips.has(chipClassForSucursal(s)));
  cluster.clearLayers(); markersById.clear(); filtered.forEach(addMarkerFor);
  if(filtered.length){ const group=L.featureGroup([...markersById.values()]); try{ map.fitBounds(group.getBounds(), { padding:[20,20], maxZoom: Math.max(map.getZoom(), 12) }); }catch(_){ } }
}

function refreshDynamicMapStyles(){
  const base=scopeSucursales(); let anyChanged=false;
  for(const s of base){
    const id=String(s.sucursal_id);
    const m=markersById.get(id);
    if(!m) continue;
    const before=m.options._chip;
    const after=chipClassForSucursal(s);
    if(before!==after){ m.setIcon(buildIconFor(s)); m.options._chip=after; anyChanged=true; }
  }
  if(anyChanged){
    updateFilterCounters(); updateMaintenanceProgress();
    try{ if(cluster && typeof cluster.refreshClusters==='function'){ cluster.refreshClusters(); } }catch(_){}
  }
  // Llamar siempre al final (por si no hubo cambios “visibles”)
  updateMaintenanceProgress();
}

function msUntilNextMidnight(){ const now=new Date(); const next=new Date(now); next.setHours(24,0,0,0); return Math.max(1000, next - now); }
function scheduleDailyRepaint(){
  setTimeout(refreshDynamicMapStyles, 5000);
  setTimeout(()=>{ refreshDynamicMapStyles(); setInterval(refreshDynamicMapStyles, 24*60*60*1000); }, msUntilNextMidnight());
  setInterval(refreshDynamicMapStyles, 60*60*1000);
  document.addEventListener('visibilitychange',()=>{ if(!document.hidden) refreshDynamicMapStyles(); });
}

// ===== Chips UI arriba
const filtersEl = document.getElementById('mapFilters');
function setActiveChips(arr){ activeChips.clear(); arr.forEach(c=>activeChips.add(c)); renderSucursales(); updateFilterCounters(); syncChipsUI(); syncURL(); }
function syncChipsUI(){
  const rootTop=document.getElementById('mapFilters');
  if(rootTop){ rootTop.querySelectorAll('.mf-pill').forEach(el=>{ const k=el.getAttribute('data-chip'); if(k==='all'){ el.classList.toggle('active', activeChips.size===4); } else { el.classList.toggle('active', activeChips.has(k)); } }); }
  if(legendContainer){ legendContainer.innerHTML = renderLegendContent(activeChips); }
}
filtersEl?.addEventListener('click',(e)=>{
  const pill=e.target.closest('.mf-pill'); if(!pill) return;
  const type=pill.getAttribute('data-chip');
  if(type==='all'){ setActiveChips(['green','orange','yellow','blue']); return; }
  const multi = e.ctrlKey||e.metaKey||e.shiftKey;
  if(multi){
    if(activeChips.has(type)) activeChips.delete(type); else activeChips.add(type);
    if(activeChips.size===0) setActiveChips(['green','orange','yellow','blue']); else { renderSucursales(); updateFilterCounters(); syncChipsUI(); syncURL(); }
    return;
  }
  if(activeChips.size===1 && activeChips.has(type)) setActiveChips(['green','orange','yellow','blue']); else setActiveChips([type]);
});

// ===== Leyenda clicable (debajo del mapa)
function renderLegendContent(activeSet){
  const isOn=k=>activeSet.has(k)?'active':'';
  return `
    <div class="legend legend--minimal">
      <div class="legend-chips">
        <button type="button" class="mf-pill mf-green ${isOn('green')}" data-chip="green" title="Mantenimiento hecho"><span class="det-chip green">Mto. realizado</span></button>
        <button type="button" class="mf-pill mf-orange ${isOn('orange')}" data-chip="orange" title="Mantenimiento pendiente"><span class="det-chip orange">Mto. pendiente</span></button>
        <button type="button" class="mf-pill mf-yellow ${isOn('yellow')}" data-chip="yellow" title="Mantenimiento en proceso"><span class="det-chip yellow">En proceso</span></button>
        <button type="button" class="mf-pill mf-blue ${isOn('blue')}" data-chip="blue" title="Siguiente mantenimiento"><span class="det-chip blue">Sig. mantenimiento</span></button>
      </div>
    </div>`;
}
function mountLegendBelow(){
  const mount = document.getElementById('mapLegendMount');
  if (!mount) return;
  mount.innerHTML = '';
  const wrap = document.createElement('div');
  wrap.innerHTML = renderLegendContent(activeChips);
  wrap.addEventListener('click',(ev)=>{
    const pill=ev.target.closest('.mf-pill'); if(!pill) return;
    const k=pill.getAttribute('data-chip');
    if(k==='all'){ setActiveChips(['green','orange','yellow','blue']); return; }
    const multi=ev.ctrlKey||ev.metaKey||ev.shiftKey;
    if(multi){
      if(activeChips.has(k)) activeChips.delete(k); else activeChips.add(k);
      if(activeChips.size===0) setActiveChips(['green','orange','yellow','blue']); else { renderSucursales(); updateFilterCounters(); syncChipsUI(); syncURL(); }
      return;
    }
    if(activeChips.size===1 && activeChips.has(k)) setActiveChips(['green','orange','yellow','blue']); else setActiveChips([k]);
  });
  legendContainer = wrap;
  mount.appendChild(wrap);
}

// ===== Choropleth (Total fijo)
function updateChoroplethStyle(){
  if(!estadosLayer) return;
  const vals = Object.values(metricasPorEstado).map(m => (m.total ?? 0));
  const max  = Math.max(1, ...vals, 1);
  estadosLayer.setStyle(f=>{
    const raw=f.properties.name||f.properties.NOMGEO||f.properties.estado||'Estado';
    const key=normalizeKey(raw);
    const rec=metricasPorEstado[key];
    const v = rec ? (rec.total ?? 0) : 0;
    return { color:'#e0e7f1', weight:1, fillColor:colorScale(v,max), fillOpacity:1 };
  });
}

// ===== Búsqueda
const index = (sucursales||[]).map(s=>{ const k=[s.sucursal||'', s.determinante||'', s.municipio||'', s.ciudad||'', s.estado||''].join(' | '); return { id:String(s.sucursal_id), display:s, norm:normalizeText(k) }; });
function doSearch(q){
  const qn=normalizeText(q);
  if(!qn){ searchResults.classList.add('d-none'); searchResults.innerHTML=''; return; }
  const results=index.filter(it=>it.norm.includes(qn)).slice(0,8);
  searchResults.innerHTML = results.length ? results.map(r=>{
    const s=r.display;
    const det=s.determinante? `<span class="match-code">#${s.determinante}</span>`:'';
    return `<a class="list-group-item list-group-item-action" data-id="${r.id}">
              <div class="d-flex justify-content-between align-items-center">
                <div><b>${s.sucursal}</b> ${det}</div>
                <small class="text-muted">${s.municipio}, ${s.estado}</small>
              </div>
            </a>`;
  }).join('') : `<div class="list-group-item small text-muted">Sin resultados</div>`;
  searchResults.classList.remove('d-none');
}
function focusSucursalById(id){
  const marker=markersById.get(String(id));
  if(!marker){
    const rec=(sucursales||[]).find(s=>String(s.sucursal_id)===String(id));
    if(rec){ estadoActual=rec.estado; estadoActualKey=normalizeKey(rec.estado); btnVolver.classList.remove('d-none'); breadcrumbSet(); renderSucursales(); setTimeout(()=>focusSucursalById(id), 50); }
    return;
  }
  cluster.zoomToShowLayer(marker,()=>{ map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16)); marker.openPopup(); const el=marker.getElement(); el?.querySelector('.det-chip')?.classList.add('glow'); setTimeout(()=> el?.querySelector('.det-chip')?.classList.remove('glow'), 2000); });
}
function debounce(fn,ms=200){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }
searchInput.addEventListener('input', debounce(e=>{ const val=typeof e==='string'? e : e.target.value; currentQuery=val||''; doSearch(val); renderSucursales(); updateFilterCounters(); syncURL(); },150));
clearSearch.addEventListener('click',()=>{ currentQuery=''; searchInput.value=''; searchResults.classList.add('d-none'); renderSucursales(); updateFilterCounters(); syncURL(); });
document.addEventListener('click',(e)=>{ if(!searchResults.contains(e.target) && e.target!==searchInput) searchResults.classList.add('d-none'); });
searchResults.addEventListener('click',(e)=>{ const item=e.target.closest('.list-group-item'); if(!item) return; const id=item.getAttribute('data-id'); focusSucursalById(id); searchResults.classList.add('d-none'); });

// ===== Navegación / edición
btnVolver.addEventListener('click',()=>{ if(nivel==='estado'){ estadoActual=null; estadoActualKey=null; nivel='pais'; map.setView([23.6345,-102.5528],5); btnVolver.classList.add('d-none'); } breadcrumbSet(); renderSucursales(); updateFilterCounters(); syncURL(); });
toggleEditBtn?.addEventListener('click',()=>{ editMode=!editMode; toggleEditBtn.classList.toggle('btn-outline-warning', !editMode); toggleEditBtn.classList.toggle('btn-warning', editMode); toggleEditBtn.textContent = editMode? 'Salir de edición':'Editar estados'; cluster.eachLayer(m=>{ if(m.isPopupOpen && m.isPopupOpen()){ m.closePopup(); setTimeout(()=>m.openPopup(),0); } }); });

// ===== Estados / Init mapa (versión robusta)
function renderEstados(){
  const skel = document.getElementById('mapSkeleton');
  const mapEl = document.getElementById('map');

  // Mostrar el mapa YA, independientemente del GeoJSON
  if (skel) skel.style.display = 'none';
  if (mapEl) mapEl.style.display = 'block';

  // Inicializar mapa base si aún no está armado
  try {
    map.setView([23.6345, -102.5528], 5);
    if (!osm._map) { osm.addTo(map); }
    if (!map.hasLayer(cluster)) { map.addLayer(cluster); }
  } catch (e) {
    console.error('Error iniciando Leaflet:', e);
  }

  // Asegurar tamaño correcto (por si estaba oculto)
  setTimeout(() => { try { map.invalidateSize(false); } catch(_){} }, 200);

  // Pintar marcadores y KPIs inmediatamente
  mountLegendBelow();
  renderSucursales();
  updateFilterCounters();
  updateMaintenanceProgress();

  // Cargar GeoJSON en segundo plano (opcional)
  const GEOJSON_URL = '/sisec-ui/assets/geo/mexico_estados.geojson';
  fetch(GEOJSON_URL)
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(geo => {
      if (estadosLayer) try { map.removeLayer(estadosLayer); } catch(_){}
      const vals = Object.values(metricasPorEstado).map(m => (m.total ?? 0));
      const max  = Math.max(1, ...vals, 1);
      estadosLayer = L.geoJSON(geo, {
        style: f => {
          const raw = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
          const key = normalizeKey(raw);
          const rec = metricasPorEstado[key];
          const v   = rec ? (rec.total ?? 0) : 0;
          return { color:'#e0e7f1', weight:1, fillColor:colorScale(v,max), fillOpacity:1 };
        },
        onEachFeature:(f,layer)=>{
          const raw = f.properties.name || f.properties.NOMGEO || f.properties.estado || 'Estado';
          const key = normalizeKey(raw);
          const rec = metricasPorEstado[key] || { total:0, cctv:0, alarma:0, label:raw };
          layer.bindTooltip(
            `<b>${rec.label}</b><br>Total: ${rec.total}<br>CCTV: ${rec.cctv}<br>Alarma: ${rec.alarma}`,
            { sticky:true }
          );
          layer.on('click', () => {
            estadoActual    = rec.label;
            estadoActualKey = key;
            nivel           = 'estado';
            breadcrumbSet();
            map.fitBounds(layer.getBounds(), { padding:[20,20], maxZoom:10 });
            btnVolver.classList.remove('d-none');
            renderSucursales();
            updateFilterCounters();
            syncURL();
          });
        }
      }).addTo(map);

      // Aplicar estilo por si cambian datos
      updateChoroplethStyle();
    })
    .catch(err => {
      console.warn('GeoJSON no cargó, el mapa seguirá sin coropleta:', err);
      // No hacemos nada más: el mapa ya está visible con marcadores.
    });
}


// ===== Drawer (drill-down)
function renderDrawerEventos(sid){
  const list = eventsBySucursal.get(String(sid)) || [];
  if (!list.length) return '<div class="text-muted">Sin eventos programados.</div>';
  const sort = [...list].sort((a,b) => (a.start||'').localeCompare(b.start||'')); // ya son ISO10
  return `
    <ul class="list-group">
      ${sort.map(ev => {
        const fi = toISO10(ev.start), ff = toISO10(ev.end||ev.start);
        const slug = ev.slug||'pendiente';
        const label = {hecho:'Hecho', pendiente:'Pendiente', proceso:'En proceso', siguiente:'Siguiente'}[slug] || 'Pendiente';
        const cls   = {hecho:'success',pendiente:'warning',proceso:'secondary',siguiente:'info'}[slug] || 'warning';
        const evId  = ev.id || '';
        return `
          <li class="list-group-item d-flex justify-content-between align-items-start">
            <div class="ms-0 me-auto">
              <div class="fw-bold">${ev.title || 'Mantenimiento'}</div>
              <small class="text-muted">${fi}${ff && ff!==fi ? ' al '+ff : ''}</small>
            </div>
            <span class="badge text-bg-${cls} rounded-pill">${label}</span>
            <div class="ms-2 d-flex flex-column gap-1">
              <button class="btn btn-sm btn-outline-secondary" onclick="openEvidenciasModal('${evId}')">Evidencias</button>
              ${slug!=='hecho' ? `<button class="btn btn-sm btn-success" onclick="uiOpenCerrar('${sid}','${evId}')">Cerrar</button>` : ''}
              ${slug!=='hecho' ? `<button class="btn btn-sm btn-outline-primary" onclick="uiOpenExtender('${sid}','${evId}')">Extender</button>` : ''}
            </div>
          </li>`;
      }).join('')}
    </ul>`;
}
function openDrawerSucursal(s){
  const body = document.getElementById('drawerBody');
  document.getElementById('drawerTitle').textContent = s.sucursal;
  body.innerHTML = `
    <div class="drawer-block">
      <div class="drawer-section-title">Resumen</div>
      <div class="drawer-grid">
        <div class="badge badge-soft w-100 text-start p-2">CCTV: <b>${s.cctv}</b></div>
        <div class="badge badge-soft w-100 text-start p-2">Alarma: <b>${s.alarma}</b></div>
        <div class="badge badge-soft w-100 text-start p-2">Total: <b>${s.total}</b></div>
        <div class="badge badge-soft w-100 text-start p-2">Determinante: <b>${s.determinante || '—'}</b></div>
      </div>
      <small class="text-muted d-block mt-2">${s.municipio}, ${s.ciudad} · ${s.estado}</small>
    </div>
    <hr class="my-3"/>
    <div class="drawer-block">
      <div class="drawer-section-title">Mantenimientos</div>
      ${renderDrawerEventos(s.sucursal_id)}
    </div>
    <hr class="my-3"/>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary" href="/sisec-ui/views/dispositivos/listar.php?sucursal_id=${encodeURIComponent(s.sucursal_id)}#filtros">Ver dispositivos</a>
      ${s.status_manual
        ? `<button class="btn btn-outline-dark" onclick="quickAuto('${s.sucursal_id}')">Quitar override</button>`
        : `<button class="btn btn-outline-warning" onclick="quickSetManual('${s.sucursal_id}','pendiente')">Marcar pendiente</button>`
      }
    </div>`;
  new bootstrap.Offcanvas('#sucursalDrawer').show();
}
function quickSetManual(sid, state){ const s=(sucursales||[]).find(x=>String(x.sucursal_id)===String(sid)); const m=markersById.get(String(sid)); if(s && m) setManualStatus(s, state, m); }
function quickAuto(sid){ const s=(sucursales||[]).find(x=>String(x.sucursal_id)===String(sid)); const m=markersById.get(String(sid)); if(s && m) setManualStatus(s, 'auto', m); }

// ===== Mini calendario (FullCalendar)
let fcMini=null;
/**
 * Función auxiliar para obtener el slug correcto basado en el texto del label.
 * Centralizamos esto aquí para que si el día de mañana cambia un nombre, 
 * solo lo edites en un solo lugar.
 */
function getNormalizedSlug(ev) {
  const label = (ev.extendedProps?.status_label || '').toLowerCase();
  let slug = ev.extendedProps?.status_slug || 'pendiente';

  if (label.includes('siguiente')) return 'siguiente';
  if (label.includes('hecho') || label.includes('finalizado')) return 'hecho';
  if (label.includes('proceso')) return 'proceso';
  if (label.includes('pendiente')) return 'pendiente';
  
  return slug; // Si no encuentra coincidencia, devuelve el original
}

async function fetchCalendarEvents(startISO, endISO) {
  const p = new URLSearchParams();
  p.set('start', startISO);
  p.set('end', endISO);
  ['hecho', 'pendiente', 'proceso', 'siguiente'].forEach(s => p.append('status[]', s));

  let j;
  try {
    const res = await fetch('/sisec-ui/views/api/mantenimiento_events.php?' + p.toString(), { credentials: 'same-origin' });
    j = await res.json();
    if (!j || j.ok !== true) throw new Error('Respuesta inválida del servidor');
  } catch (e) {
    console.error('fetchCalendarEvents error:', e);
    return [];
  }

  // 1. Indexar para el mapa usando el Slug NORMALIZADO
  for (const ev of (j.events || [])) {
    const sid = String(ev.extendedProps?.sucursal_id || ev.sucursal_id || '');
    const start = ev.start;
    const end = ev.end || ev.start;
    
    // USAMOS LA FUNCIÓN NORMALIZADORA
    const slug = getNormalizedSlug(ev);

    if (sid) {
      const recForMap = { 
        id: ev.id, 
        start, 
        end, 
        slug, // <--- Ahora sí, el mapa tendrá el color correcto
        label: ev.extendedProps?.status_label || '', 
        title: ev.title || '' 
      };
      const arr = eventsBySucursal.get(sid) || [];
      const idx = arr.findIndex(r => String(r.id) === String(recForMap.id));
      if (idx >= 0) arr[idx] = recForMap; else arr.push(recForMap);
      eventsBySucursal.set(sid, arr);
    }
  }

  // 2. Devolver a FullCalendar (con colores basados en el slug normalizado)
  return (j.events || []).map(ev => {
    const slug = getNormalizedSlug(ev); // <--- Normalización para el calendario
    
    const COLORS = {
      hecho:     { bg: '#01a806', text: '#fff', border: '#01a806' },
      pendiente: { bg: '#f39c12', text: '#000', border: '#f39c12' },
      proceso:   { bg: '#f1c40f', text: '#000', border: '#f1c40f' },
      siguiente: { bg: '#2980b9', text: '#fff', border: '#2980b9' },
    };
    
    const c = COLORS[slug] || COLORS.pendiente;
    
    return {
      id: ev.id, 
      title: ev.title, 
      start: ev.start, 
      end: ev.end, 
      allDay: true,
      backgroundColor: c.bg, 
      borderColor: c.border, 
      textColor: c.text,
      classNames: ['ev-' + slug],
      extendedProps: { 
        ...(ev.extendedProps || {}), 
        status_slug_norm: slug // Guardamos el bueno para usarlo en eventDidMount
      }
    };
  });
}

// 🆕 Recarga explícita del cache del mapa
async function fetchCalendarEventsReload() {
  try {
    const today = todayISO();
    const past = addDaysISO(today, -90);
    const future = addDaysISO(today, 180);
    const qs = new URLSearchParams();
    qs.set('start', past);
    qs.set('end', future);
    ['hecho', 'pendiente', 'proceso', 'siguiente'].forEach(s => qs.append('status[]', s));

    const res = await fetch('/sisec-ui/views/api/mantenimiento_events.php?' + qs.toString(), {
      credentials: 'same-origin',
      cache: 'no-store'
    });
    const j = await res.json();
    if (!res.ok || !j || j.ok !== true) throw new Error(j?.error || 'Respuesta inválida');

    eventsBySucursal.clear();
    for (const ev of (j.events || [])) {
      const sid = String(ev.extendedProps?.sucursal_id || ev.sucursal_id || '');
      const start = ev.start;
      const end = ev.end || ev.start;
      
      // TAMBIÉN USAMOS LA NORMALIZADORA AQUÍ
      const slug = getNormalizedSlug(ev);

      if (sid) {
        const recForMap = {
          id: ev.id,
          start,
          end,
          slug, // <--- Los contadores y filtros estarán correctos
          label: ev.extendedProps?.status_label || '',
          title: ev.title || ''
        };
        const arr = eventsBySucursal.get(sid) || [];
        arr.push(recForMap);
        eventsBySucursal.set(sid, arr);
      }
    }
  } catch (e) {
    console.warn('fetchCalendarEventsReload() error:', e);
  }
}

function initMiniCalendar(){
  const calEl = document.getElementById('miniCalendar');
  if (!calEl) return;
  fcMini = new FullCalendar.Calendar(calEl, {
    locale: 'es', initialView: 'dayGridMonth', height: 'auto',
    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
    eventDidMount: (info) => {
      const slug = info.event.extendedProps?.status_slug_norm;
      if (slug) info.el.classList.add('ev-' + slug);
    },
    events: async (info, success, failure) => {
      try {
        const visibleStart = info.startStr.slice(0, 10);
        const visibleEnd   = info.endStr.slice(0, 10);
        const wideStart    = addDaysISO(visibleStart, -30);
        const wideEnd      = addDaysISO(visibleEnd, 180);
        const list         = await fetchCalendarEvents(wideStart, wideEnd);
        success(list);
      } catch (e) { failure(e); }
    },
   eventClick: info => {
     const sid = info.event.extendedProps?.sucursal_id;
     if (sid) focusSucursalById(sid);
   },

    // 🔁 Re-render completo cuando haya nuevos eventos o cambie el mes
    eventsSet: () => {
      renderSucursales();
      updateFilterCounters();
      refreshDynamicMapStyles();
      showDeadlineToasts();
      updateMaintenanceProgress(); // 🔁 recalcula cuando el calendario termina de cargar eventos
    },
    datesSet: () => {
      setTimeout(() => {
        renderSucursales();
        updateFilterCounters();
        refreshDynamicMapStyles();
        showDeadlineToasts();
        updateMaintenanceProgress(); // 🔁 recalcula cuando el calendario termina de cargar eventos
      }, 0);
    }
  });
  fcMini.render();
}

// ===== Donut + Mini KPIs
let devicesDonut=null; 
function initDevicesDonutChart(){
  const ctx = document.getElementById('devicesDonutChart')?.getContext('2d');
  if(!ctx) return;

  const cctv   = Number(DEV_TOTALS.cctv || 0);
  const alarma = Number(DEV_TOTALS.alarma || 0);

  const data = {
    labels: ['CCTV','Alarma'],
    datasets: [{
      data: [cctv, alarma],
      backgroundColor: ['#572364', '#D71F85'],
      hoverOffset: 6
    }]
  };
  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom' },
      tooltip: {
        callbacks: {
          label(ctx){
            const v = ctx.parsed || 0;
            const total = (cctv + alarma) || 1;
            const pct = Math.round((v * 100) / total);
            return ` ${ctx.label}: ${v} (${pct}%)`;
          }
        }
      }
    },
    cutout: '62%'
  };

  if (window.devicesDonut) window.devicesDonut.destroy();
  window.devicesDonut = new Chart(ctx, { type: 'doughnut', data, options });
}


let apxMaintSpark=null, apxMaintRadial=null;

function computeMaintenanceCounts() {
  // Verificamos que el mapa exista y tenga datos para evitar errores
  if (typeof eventsBySucursal === 'undefined' || eventsBySucursal.size === 0) {
    return { total: 0, hechas: 0, faltan: 0, pct: 0 };
  }

  const allEvents = [];
  try {
    eventsBySucursal.forEach(arr => {
      if (Array.isArray(arr)) allEvents.push(...arr);
    });
  } catch (e) {
    console.warn("Error procesando eventsBySucursal:", e);
  }

  let hechas = 0;
  for (const ev of allEvents) {
    // Usamos el slug que ya normalizamos en las funciones anteriores
    if (ev.slug === 'hecho') {
      hechas++;
    }
  }

  const total = allEvents.length;
  const faltan = total - hechas;
  const pct = total > 0 ? Math.round((hechas * 100) / total) : 0;

  return { total, hechas, faltan, pct };
}


function goToListado(kind) {
  // 1. La ruta hacia tu vista de listado
  let url = '/sisec-ui/views/sucursales/mantenimiento_listado.php';
  const params = new URLSearchParams();

  /**
   * 2. Filtro de Estado:
   * 'hechas' -> Solo las que tienen el status_label de "Hecho"
   * 'nohechas' -> Las que están en "Proceso" o "Pendiente"
   * 'todas' -> Los 16 registros del año actual
   */
  if (kind === 'hechas') {
    params.set('estado', 'hechas');
  } else if (kind === 'nohechas') {
    params.set('estado', 'nohechas');
  } else {
    params.set('estado', 'todas');
  }

  // 3. Filtro Temporal: 
  // Obligamos al listado a mostrar solo lo de este año para coincidir con el KPI
  params.set('anio', new Date().getFullYear());

  // 4. Redirección
  url += '?' + params.toString();
  window.location.href = url;
}

function initApexMiniCards() {
  // Si ApexCharts no está cargado, salimos discretamente
  if (typeof ApexCharts === 'undefined') return;

  try {
    const data = computeMaintenanceCounts();

    // Actualizar Textos
    const hEl = document.getElementById('kpiMaintHechas');
    const tEl = document.getElementById('kpiMaintTotal');
    const pEl = document.getElementById('kpiMaintPctText');

    if (hEl) hEl.textContent = data.hechas;
    if (tEl) tEl.textContent = data.total;
    if (pEl) pEl.textContent = data.pct + '%';

    // 1. Configuración Barra (Sparkline)
    const sparkOpts = {
      chart: { 
        type: 'bar', 
        height: 76, 
        sparkline: { enabled: true },
        animations: { enabled: true },
        events: {
          dataPointSelection: (e, ctx, opts) => {
            const i = opts.dataPointIndex;
            if (i === 1) goToListado('hechas');
            else if (i === 2) goToListado('nohechas');
            else goToListado('todas');
          },
          // Recuperamos el cursor de puntero para que el usuario sepa que es clicable
          dataPointMouseEnter: () => { document.body.style.cursor = 'pointer'; },
          dataPointMouseLeave: () => { document.body.style.cursor = ''; }
        }
      },
      series: [{ 
        name: 'Mantenimientos', 
        data: [data.total, data.hechas, data.faltan] 
      }],
      plotOptions: { 
        bar: { 
          columnWidth: '60%', 
          distributed: true, 
          borderRadius: 4 
        } 
      },
      colors: ['#0073FF', '#01A806', '#F39C12'],
      // --- TOOLTIP REACTIVADO Y CONFIGURADO ---
      tooltip: {
        enabled: true,
        theme: 'dark', // Se ve mejor en dashboards
        x: { show: false }, // Ocultamos el eje X que no es necesario en sparklines
        y: {
          title: {
            formatter: (seriesName, opts) => {
              // Asignamos nombre a cada barra según su posición
              const labels = ['Total Anual', 'Completadas', 'Pendientes'];
              return labels[opts.dataPointIndex] + ':';
            }
          }
        }
      }
    };


    // Render/Update Barra
    if (window.apxMaintSpark && typeof window.apxMaintSpark.updateOptions === 'function') {
      window.apxMaintSpark.updateOptions(sparkOpts);
    } else {
      const el = document.querySelector('#apxMaintSparkline');
      if (el) {
        window.apxMaintSpark = new ApexCharts(el, sparkOpts);
        window.apxMaintSpark.render();
      }
    }

    // 2. Configuración Radial
    const radialOpts = {
      chart: { type: 'radialBar', height: 120, sparkline: { enabled: true } },
      series: [data.pct],
      colors: ['#01a806'],
      plotOptions: { radialBar: { 
        hollow: { size: '60%' },
        dataLabels: {
        show: true, // Queremos controlar las etiquetas
        name: { show: false }, // <--- ESTO QUITA EL "series-1"
        value: { show: false } // <--- ESTO QUITA EL NÚMERO DENTRO
      }
     } }
    };

    // Render/Update Radial
    if (window.apxMaintRadial && typeof window.apxMaintRadial.updateOptions === 'function') {
      window.apxMaintRadial.updateOptions(radialOpts);
    } else {
      const el = document.querySelector('#apxMaintRadial');
      if (el) {
        window.apxMaintRadial = new ApexCharts(el, radialOpts);
        window.apxMaintRadial.render();
      }
    }

  } catch (err) {
    console.error("Error en initApexMiniCards:", err);
  }
}
function updateMaintenanceProgress(){  
initApexMiniCards(); }

// ===== Alertas por vencer
const DEADLINE_SOON_DAYS = 3;

// *** ACTUALIZADO *** helpers con “rojo” a 1 día
function daysLeftTo(startISO, endISO) {
  const today = todayISO();
  const target = (startISO > today) ? startISO : endISO;
  return daysBetween(today, target);
}
function severityFromDaysLeft(d) {
  if (d <= 1) return 'danger';   // rojo
  if (d <= 3) return 'warning';  // ámbar
  return 'info';
}

// *** ACTUALIZADO *** Persistencia (usa tu endpoint)
async function persistAlerts(alerts){
  try{
    if(!Array.isArray(alerts) || !alerts.length) return;
    const res = await fetch(ALERTS_UPSERT_URL, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ alerts })
    });
    const j = await res.json();
    if (!j || j.ok !== true) console.warn('No se pudo persistir alertas', j?.error);
  } catch(e) { console.warn('persistAlerts error', e); }
}

function findUpcomingDeadlines(){
  const today = todayISO();
  const soonCut = addDaysISO(today, DEADLINE_SOON_DAYS);
  const out = [];
  for (const s of (sucursales||[])) {
    const sid = String(s.sucursal_id);
    const list = eventsBySucursal.get(sid) || [];
    const near = list.filter(ev => {
      const fi = toISO10(ev.start);
      const ff = toISO10(ev.end || ev.start);
      const start = fi; const end = ff;
      const slug  = ev.slug || 'pendiente';
      if (slug === 'hecho') return false;
      return (start <= soonCut && end >= today);
    });
    if (near.length) out.push({ sid, sucursal: s, eventos: near });
  }
  return out;
}

// *** ACTUALIZADO *** Toasts + badge rojo + persistencia
function showDeadlineToasts(){
  const list = findUpcomingDeadlines();
  const area = document.getElementById('toastArea'); if (!area) return;
  area.innerHTML = '';

  let criticalCount = 0;
  const toPersist = [];

  list.forEach(block => {
    const s = block.sucursal;
    const items = block.eventos.map(ev => {
      const fi = toISO10(ev.start); const ff = toISO10(ev.end || ev.start);
      const when = fi === ff ? fi : `${fi} → ${ff}`;
      const dLeft = daysLeftTo(fi, ff);
      const sev   = severityFromDaysLeft(dLeft);
      if (sev === 'danger') criticalCount++;

      // Apilar para persistir
      toPersist.push({
        sid: String(s.sucursal_id),
        evento_id: String(ev.id || ''),
        start: fi, end: ff,
        status_slug: ev.slug || 'pendiente',
        days_left: dLeft,
        severity: sev,
        sucursal: s.sucursal || ''
      });

      return `<li>
        <span class="badge-sev sev-${sev} me-1"></span>
        ${ev.title || 'Mantenimiento'} <small class="text-muted">(${when})</small> 
        <span class="ms-1 badge ${sev==='danger'?'bg-danger':(sev==='warning'?'bg-warning text-dark':'bg-info text-dark')}">
          ${dLeft} día${dLeft===1?'':'s'}
        </span>
        <a href="#" onclick="uiOpenCerrar('${s.sucursal_id}','${ev.id}')" class="ms-2">Cerrar</a> · 
        <a href="#" onclick="uiOpenExtender('${s.sucursal_id}','${ev.id}')" class="ms-1">Extender</a>
      </li>`;
    }).join('');
    const toastId = `toast-${block.sid}-${Math.random().toString(36).slice(2)}`;
    const div = document.createElement('div');
    div.className = 'toast align-items-center show toast-item';
    div.id = toastId;
    div.role = 'alert';
    div.innerHTML = `
      <div class="toast-header">
        <span class="badge text-bg-warning me-2">Por vencer</span>
        <strong class="me-auto">${s.sucursal}</strong>
        <button type="button" class="btn-close" onclick="document.getElementById('${toastId}').remove()"></button>
      </div>
      <div class="toast-body">
        <ul class="mb-0">${items}</ul>
      </div>`;
    area.appendChild(div);
  });

  if (toPersist.length) persistAlerts(toPersist);

  const badge = document.getElementById('alertsBadge');
  if (badge){
    if (criticalCount > 0){
      badge.textContent = String(criticalCount);
      badge.classList.remove('d-none');
    } else {
      badge.classList.add('d-none');
    }
  }
}

// *** NUEVO *** Centro de notificaciones (histórico)
async function fetchAlertsHistory({ q = '', severity = '' } = {}){
  const p = new URLSearchParams();
  if (q) p.set('q', q);
  if (severity) p.set('severity', severity);
  try{
    const res = await fetch(ALERTS_LIST_URL + (p.toString() ? ('?' + p.toString()) : ''), { credentials:'same-origin' });
    const j = await res.json().catch(()=>null);
    if (!j || j.ok !== true) throw new Error(j?.error || 'Respuesta inválida');
    return Array.isArray(j.alerts) ? j.alerts : [];
  }catch(e){
    console.warn('fetchAlertsHistory error', e);
    return [];
  }
}
function renderAlertsTable(rows){
  const tbody = document.getElementById('alerts-tbody');
  const empty = document.getElementById('alerts-empty');
  if (!tbody || !empty) return;

  if (!rows.length){
    tbody.innerHTML = '';
    empty.classList.remove('d-none');
    return;
  }
  empty.classList.add('d-none');

tbody.innerHTML = rows.map(r => {
  const fi = toISO10(r.start || '');
  const ff = toISO10(r.end || r.start || '');
  const d  = Number(r.days_left ?? daysLeftTo(fi, ff));
  const sev = r.severity || severityFromDaysLeft(d);
  const label = (r.status_slug || 'pendiente');
  const when = fi === ff ? fi : `${fi} → ${ff}`;
  const sid = r.sid || r.sucursal_id || '';

  // NUEVO: validar evento_id para (des)habilitar botones
  const eventoId = r.evento_id || '';
  const hasEvt = !!(eventoId && String(eventoId).trim() !== '');
  const disAttr = hasEvt ? '' : 'disabled title="Sin ID de evento"';

  return `<tr>
    <td><span class="badge-sev sev-${sev}"></span></td>
    <td>
      <div class="fw-bold">${escapeHtml(r.sucursal || '')}</div>
      <div><small class="text-muted">#${escapeHtml(String(sid))}</small></div>
    </td>
    <td><small>${when}</small></td>
    <td><span class="badge ${sev==='danger'?'bg-danger':(sev==='warning'?'bg-warning text-dark':'bg-info text-dark')}">
      ${escapeHtml(label)}</span>
    </td>
    <td><span class="badge ${sev==='danger'?'bg-danger':(sev==='warning'?'bg-warning text-dark':'bg-info text-dark')}">
      ${d} día${d===1?'':'s'}</span>
    </td>
    <td>
      <button class="btn btn-sm btn-success me-1" ${disAttr}
              onclick="uiOpenCerrar('${sid}','${eventoId}')">Cerrar</button>
      <button class="btn btn-sm btn-outline-primary" ${disAttr}
              onclick="uiOpenExtender('${sid}','${eventoId}')">Extender</button>
    </td>
  </tr>`;
}).join('');
}

document.getElementById('btnAlerts')?.addEventListener('click', async () => {
  new bootstrap.Modal('#modalAlerts').show();
  const q = document.getElementById('alerts-search')?.value || '';
  const severity = document.getElementById('alerts-filter')?.value || '';
  const rows = await fetchAlertsHistory({ q, severity });
  renderAlertsTable(rows);
});
document.getElementById('alerts-refresh')?.addEventListener('click', async () => {
  const q = document.getElementById('alerts-search')?.value || '';
  const severity = document.getElementById('alerts-filter')?.value || '';
  const rows = await fetchAlertsHistory({ q, severity });
  renderAlertsTable(rows);
});
document.getElementById('alerts-search')?.addEventListener('input', async (e) => {
  const severity = document.getElementById('alerts-filter')?.value || '';
  const rows = await fetchAlertsHistory({ q: e.target.value, severity });
  renderAlertsTable(rows);
});
document.getElementById('alerts-filter')?.addEventListener('change', async (e) => {
  const q = document.getElementById('alerts-search')?.value || '';
  const rows = await fetchAlertsHistory({ q, severity: e.target.value });
  renderAlertsTable(rows);
});

// Cierra #modalAlerts (si está abierto) y luego ejecuta la apertura del modal objetivo
function openAfterHidingAlerts(openFn){
  const alertsEl = document.getElementById('modalAlerts');
  if (!alertsEl) { openFn(); return; }

  const alerts = bootstrap.Modal.getInstance(alertsEl) || new bootstrap.Modal(alertsEl);

  // ¿Está visible?
  if (alertsEl.classList.contains('show')) {
    const onHidden = () => {
      alertsEl.removeEventListener('hidden.bs.modal', onHidden);
      // Abrimos el siguiente modal ya sin backdrop duplicado
      setTimeout(openFn, 0);
    };
    alertsEl.addEventListener('hidden.bs.modal', onHidden);
    alerts.hide();
  } else {
    openFn();
  }
}

function uiOpenExtender(sid, eventoId){
  if (!eventoId) {
    alert('No hay ID de evento para extender. Abre desde el calendario o desde una notificación reciente.');
    return;
  }
  openAfterHidingAlerts(() => {
    document.getElementById('ext_sucursal_id').value = String(sid || '');
    document.getElementById('ext_evento_id').value   = String(eventoId || '');
    new bootstrap.Modal('#modalExtenderMto', { backdrop: true }).show();
  });
}

function uiOpenCerrar(sid, eventoId){
  if (!eventoId) {
    alert('No hay ID de evento para cerrar. Abre desde el calendario o desde una notificación reciente.');
    return;
  }
  openAfterHidingAlerts(() => {
    document.getElementById('cerrar_sucursal_id').value = String(sid || '');
    document.getElementById('cerrar_evento_id').value   = String(eventoId || '');
    new bootstrap.Modal('#modalCerrarMto', { backdrop: true }).show();
  });
}



// ===== Formularios Ajax
document.getElementById('formCerrarMto')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.currentTarget; const fd = new FormData(form);
  try{
    const res = await fetch('/sisec-ui/views/api/mantenimiento_cerrar.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const j = await res.json();
    if (!j || j.ok !== true) throw new Error(j?.error || 'No se pudo cerrar');
// Cerrar
if (typeof fcMini?.refetchEvents === 'function') await fcMini.refetchEvents();
await fetchCalendarEventsReload();     // 🟢 cache del mapa actualizado
renderSucursales();
updateFilterCounters();
refreshDynamicMapStyles();
showDeadlineToasts();
updateMaintenanceProgress(); // 🔁 fuerza recálculo del KPI
    bootstrap.Modal.getInstance(document.getElementById('modalCerrarMto'))?.hide();
  }catch(err){ alert('Error al cerrar mantenimiento: ' + (err?.message || '')); }
});

document.getElementById('formExtenderMto')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.currentTarget; const fd = new FormData(form);
  try {
    const res = await fetch('/sisec-ui/views/api/mantenimiento_extender.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    let j = null;
    try { j = await res.json(); } catch(_) {}

    if (!res.ok || !j || j.ok !== true) {
      const svrErr = (j && (j.detail || j.error)) ? `\nDetalle: ${j.detail || j.error}` : '';
      throw new Error((j?.error || `HTTP ${res.status}`) + svrErr);
    } 
// Extender
if (typeof fcMini?.refetchEvents === 'function') await fcMini.refetchEvents();
await fetchCalendarEventsReload();     // 🟢 cache del mapa actualizado
renderSucursales();
updateFilterCounters();
refreshDynamicMapStyles();
showDeadlineToasts();
updateMaintenanceProgress(); // 🔁 fuerza recálculo del KPI
    bootstrap.Modal.getInstance(document.getElementById('modalExtenderMto'))?.hide();
  } catch (err) {
    alert('Error al extender mantenimiento: ' + (err?.message || 'Desconocido'));
  }
});

// ===== Tour (AYUDA).js
document.getElementById('btnTour')?.addEventListener('click', () => {
  introJs().setOptions({
    nextLabel:'Siguiente', prevLabel:'Anterior', doneLabel:'Listo',
    steps:[
      { element: document.querySelector('#searchInput'), intro:'Busca sucursal, determinante o ubicación' },
      { element: document.querySelector('#map'), intro:'Mapa con marcadores y clústers. Clic para ver detalle en el panel lateral.' },
      { element: document.querySelector('#mapFilters'), intro:'Filtra por estado de mantenimiento.' },
      { element: document.querySelector('#miniCalendar'), intro:'Eventos programados (clic para centrar en el mapa).' },
    ]
  }).start();
});

// ===== Compartir vista (URL)
function syncURL(){
  const params = new URLSearchParams();
  if (estadoActual) params.set('zona', estadoActual);
  if (currentQuery) params.set('q', currentQuery);
  if (activeChips.size < 4) params.set('chips', [...activeChips].join(','));
  history.replaceState(null, '', '?' + params.toString());
}
(function restoreFromURL(){
  const url = new URL(window.location.href);
  const q = url.searchParams.get('q') || '';
  const zona = url.searchParams.get('zona') || '';
  const chips = (url.searchParams.get('chips') || '').split(',').filter(Boolean);
  if (q){ searchInput.value = q; currentQuery=q; }
  if (zona){ estadoActual=zona; estadoActualKey=normalizeKey(zona); btnVolver.classList.remove('d-none'); }
  if (chips.length){ setActiveChips(chips); } else { syncChipsUI(); }
})();

// ===== Atajos
document.addEventListener('keydown', (e) => {
  if (e.key === '/' && !e.ctrlKey && !e.metaKey) { e.preventDefault(); document.getElementById('searchInput')?.focus(); }
  if (e.key === 'Escape') { clearSearch?.click(); }
});

// ===== Modal “No Activas”: render & filtros =====
function naRowHTML(r){
  const det = (r.determinante && String(r.determinante).trim() !== '') ? `#${r.determinante}` : '—';
  const ubic = `${r.municipio}, ${r.estado}`;
  const link = `/sisec-ui/views/dispositivos/listar.php?sucursal_id=${encodeURIComponent(r.sucursal_id)}#filtros`;
  return `<tr>
    <td>${det}</td>
    <td>${escapeHtml(r.sucursal || '')}</td>
    <td><small class="text-muted">${escapeHtml(ubic)}</small></td>
    <td>${escapeHtml(r.equipo || '')}</td>
    <td><span class="badge rounded-pill text-bg-warning">${escapeHtml(r.estatus || '')}</span></td>
    <td><a class="btn btn-sm btn-outline-primary" href="${link}">Ver sucursal</a></td>
  </tr>`;
}
function escapeHtml(s){ return String(s)
  .replaceAll('&','&amp;').replaceAll('<','&lt;')
  .replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;"); }

function renderNonActiveTable(list){
  const tbody = document.getElementById('na-tbody');
  const empty = document.getElementById('na-empty');
  const count = document.getElementById('na-count');
  if(!tbody || !empty || !count) return;

  tbody.innerHTML = list.map(naRowHTML).join('');
  count.textContent = String(list.length);
  empty.classList.toggle('d-none', list.length > 0);
  const msg = list.length === 0 ? "No hay registros para mostrar." : "";
empty.textContent = msg;
}

let CURRENT_MODAL_DATA = []; 

function openNonActiveModal(dataList, title) {
  CURRENT_MODAL_DATA = dataList; // Guardamos la lista actual (CCTV o Alarmas)
  
  // Cambiamos el título del modal dinámicamente
  const modalTitle = document.querySelector('#modalCamsNoActivas .modal-title');
  if(modalTitle) modalTitle.firstChild.textContent = title + ' ';

  renderNonActiveTable(CURRENT_MODAL_DATA);
  new bootstrap.Modal('#modalCamsNoActivas').show();
}
function filterNonActive(query) {
  const q = (query||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
  if(!q) return CURRENT_MODAL_DATA.slice();
  
  return CURRENT_MODAL_DATA.filter(r => {
    const haystack = [
      r.determinante||'', r.sucursal||'', r.municipio||'',
      r.ciudad||'', r.estado||'', r.equipo||'', r.estatus||''
    ].join(' | ').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
    return haystack.includes(q);
  });
}
function exportNonActiveCSV(rows){
  const sep = ',';
  const headers = ['Determinante','Sucursal','Municipio','Ciudad','Estado','Equipo','Estatus','SucursalID'];
  const csv = [headers.join(sep)]
    .concat(rows.map(r => [
      (r.determinante||''),
      (r.sucursal||''),
      (r.municipio||''),
      (r.ciudad||''),
      (r.estado||''),
      (r.equipo||''),
      (r.estatus||''),
      String(r.sucursal_id||'')
    ].map(v => `"${String(v).replaceAll('"','""')}"`).join(sep)))
    .join('\r\n');

  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `camaras_no_activas_${new Date().toISOString().slice(0,10)}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
}

// KPI Cámaras
document.getElementById('kpiCamFunc')?.addEventListener('click', () => {
  const lista = Array.isArray(CCTV_NON_ACTIVE) ? CCTV_NON_ACTIVE : [];
  openNonActiveModal(lista, '<?= $Label_cam ?>');
});

// KPI Alarmas (Asegúrate de que el ID coincida con tu HTML)
document.getElementById('kpiAlarmaFunc')?.addEventListener('click', () => {
  // Supongamos que tienes una constante ALARMA_NON_ACTIVE similar a la de CCTV
  const lista = Array.isArray(ALARMA_NON_ACTIVE) ? ALARMA_NON_ACTIVE : [];
  openNonActiveModal(lista, '<?= $Label_alam ?>');
});

document.getElementById('na-search')?.addEventListener('input', (e) => {
  const filtered = filterNonActive(e.target.value);
  renderNonActiveTable(filtered);
});

document.getElementById('na-export')?.addEventListener('click', () => {
  const q = document.getElementById('na-search')?.value || '';
  const rows = filterNonActive(q);
  exportNonActiveCSV(rows);
});

// ===== Vista por Sucursales (ApexCharts) =====
let apxBranchChart = null;

function nfSearch(s){ return (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(); }
function ellipsizeMid(s, max=24){
  const t = String(s||'');
  if (t.length <= max) return t;
  const half = Math.floor((max-1)/2);
  return t.slice(0, half) + '…' + t.slice(-half);
}
function labelSucursal(r){
  const det = r.determinante ? `#${r.determinante} · ` : '';
  return `${det}${r.sucursal || ''}`;
}

function buildBranchRows(metric='func_pct', topN=20, q=''){
  const query = nfSearch(q);
  let base = Array.isArray(BRANCH_STATS) ? BRANCH_STATS.slice() : [];
  if (estadoActualKey) base = base.filter(r => normalizeKey(r.estado) === estadoActualKey);
  if (query) {
    base = base.filter(r => nfSearch([r.sucursal,r.estado,r.ciudad,r.municipio,r.determinante].join(' | ')).includes(query));
  }
  base.sort((a,b) => (b[metric] ?? 0) - (a[metric] ?? 0) || (a.sucursal||'').localeCompare(b.sucursal||''));
  if (topN && Number(topN) > 0) base = base.slice(0, Number(topN));
  return base;
}

function fmtValue(metric, v){
  return (metric === 'func_pct') ? `${v}%` : `${v}`;
}
function colorForMetric(metric, v){
  if (metric === 'func_pct') {
    if (v >= 95) return '#01A806';
    if (v >= 80) return '#F39C12';
    return '#D71F85';
  }
  return metric === 'cctv_no_act' ? '#F39C12' : '#572364';
}

// --- helper de scroll suave hacia el mapa ---
function scrollToMapSection() {
  return new Promise(resolve => {
    const el = document.getElementById('mapSection') || document.getElementById('map');
    if (!el) { resolve(); return; }
    // Altura aproximada de tu navbar; ajusta si hace falta
    const NAV_OFFSET = 68;
    const rect = el.getBoundingClientRect();
    const top = window.pageYOffset + rect.top - NAV_OFFSET;

    // Asegura que el mapa esté visible (por si el skeleton sigue activo)
    const skel = document.getElementById('mapSkeleton');
    const mapEl = document.getElementById('map');
    if (skel) skel.style.display = 'none';
    if (mapEl) mapEl.style.display = 'block';

    window.scrollTo({ top, behavior: 'smooth' });
    // Da tiempo a que termine el scroll antes de resolver
    setTimeout(resolve, 350);
  });
}

function renderBranchChart(){
  const metricSel = document.getElementById('br-metric');
  const topnSel   = document.getElementById('br-topn');
  const searchInp = document.getElementById('br-search');
  const metric    = (metricSel?.value || 'func_pct');
  const topN      = Number(topnSel?.value || 20);
  const q         = searchInp?.value || '';

  const rows = buildBranchRows(metric, topN, q);

  const labelsFull = rows.map(r => labelSucursal(r));
  const labelsY    = labelsFull.map(s => ellipsizeMid(s, 28));
  const data       = rows.map(r => r[metric] ?? 0);
  const series = [{ name: metric === 'func_pct' ? 'Funcionabilidad' : (metric === 'cctv_no_act' ? 'No activas' : 'Total cámaras'), data }];

  const colors = data.map(v => colorForMetric(metric, v));

  const chartHeight = Math.min(900, Math.max(320, 60 + rows.length * 28));
  const container = document.querySelector('#branchBarChart')?.parentElement;
  if (container) container.style.height = chartHeight + 'px';

  const opts = {
    chart: {
      type: 'bar',
      height: chartHeight,
      toolbar: { show: false },
      animations: { enabled: true },
      events: {
        dataPointSelection: async function(_e, _ctx, cfg){
          const i = cfg?.dataPointIndex ?? -1;
          const r = rows[i];
        if (!r) return;
        // 1) sube al mapa
        await scrollToMapSection();
        // 2) centra y abre popup en el marcador
        focusSucursalById(String(r.sucursal_id));
        },
        dataPointMouseEnter: () => { document.body.style.cursor = 'pointer'; },
        dataPointMouseLeave: () => { document.body.style.cursor = ''; },
      }
    },
    series,
    plotOptions: {
      bar: {
        horizontal: true,
        barHeight: '60%',
        borderRadius: 3,
        distributed: true
      }
    },
    colors,
    dataLabels: { enabled: false },
    xaxis: {
      categories: labelsY,
      labels: {
        formatter: (value) => fmtValue(metric, value),
        style: { fontSize: '11px' }
      },
      max: metric === 'func_pct' ? 100 : undefined
    },
    yaxis: {
      labels: { style: { fontSize: '11px' } },
    },
    tooltip: {
      y: { formatter: (val) => fmtValue(metric, val) },
      custom: function({ series, seriesIndex, dataPointIndex, w }){
        const v = series[seriesIndex][dataPointIndex];
        const r = rows[dataPointIndex];
        if (!r) return '';
        const full = labelsFull[dataPointIndex];
        const loc  = `${r.municipio}, ${r.estado}`;
        return `
          <div class="apex-tooltip" style="padding:8px 10px;">
            <div><b>${full}</b></div>
            <div><small class="text-muted">${loc}</small></div>
            <div style="margin-top:4px;">
              <div>${metric === 'func_pct' ? 'Funcionabilidad' : (metric === 'cctv_no_act' ? 'No activas' : 'Total cámaras')}: <b>${fmtValue(metric, v)}</b></div>
              <div>Activas: <b>${r.cctv_activas}</b> / Total: <b>${r.cctv_total}</b></div>
            </div>
          </div>`;
      }
    },
    grid: { borderColor: '#eef2f7' },
    legend: { show: false },
    noData: { text: 'Sin datos' }
  };

  if (apxBranchChart) {
    apxBranchChart.updateOptions(opts, true, true);
  } else {
    const el = document.getElementById('branchBarChart');
    if (!el) return;
    apxBranchChart = new ApexCharts(el, opts);
    apxBranchChart.render();
  }
}

// Listeners
document.getElementById('br-metric')?.addEventListener('change', renderBranchChart);
document.getElementById('br-topn')?.addEventListener('change', renderBranchChart);
document.getElementById('br-search')?.addEventListener('input', () => {
  clearTimeout(window.__br_search_t);
  window.__br_search_t = setTimeout(renderBranchChart, 200);
});

// Mantén la integración con tu flujo: re-render cuando cambia el mapa/ámbito
const __renderSucursalesPrev = renderSucursales;
renderSucursales = function(){
  __renderSucursalesPrev.apply(this, arguments);
  renderBranchChart();
};

// ===== Init
(function(){ const skel=document.getElementById('mapSkeleton'); const mapEl=document.getElementById('map'); skel.style.display='block'; mapEl.style.display='none'; renderEstados(); })();
breadcrumbSet();
document.addEventListener('DOMContentLoaded',()=>{ updateFilterCounters(); updateMaintenanceProgress(); initDevicesDonutChart(); initMiniCalendar(); scheduleDailyRepaint(); renderBranchChart(); });
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Inicio";
$pageHeader = "CESISS";
$activePage = "inicio";
include __DIR__ . '/../../layout.php';