<?php
// /sisec-ui/views/sucursales/mantenimiento_listado.php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Prevencion','Administrador','Técnico','Distrital','Mantenimientos']);
include __DIR__ . '/../../includes/db.php';
ob_start();

/* ====== Config ====== */
$COLLATION = 'utf8mb4_general_ci';
$STATUS_HECHO     = 'Mantenimiento hecho';
$STATUS_PENDIENTE = 'Mantenimiento pendiente';
$STATUS_PROCESO   = 'Mantenimiento en proceso';
$STATUS_SIGUIENTE = 'Siguiente mantenimiento';
$NEXT_MAINTENANCE_FALLBACK_DAYS = 180; // Mantener consistente con tu panel

/* ====== Filtros ====== */
// Compatibilidad con ?status=hechas|nohechas (legacy)
$legacyMode  = strtolower($_GET['status'] ?? '');
// Nuevo parámetro unificado
$estadoParam = strtolower($_GET['estado'] ?? 'todas'); // todas | realizado | no_realizadas | pendiente | proceso | siguiente

// Mapear legacy a nuevos valores
if (in_array($legacyMode, ['hechas','nohechas'], true)) {
  $estadoParam = ($legacyMode === 'hechas') ? 'realizado' : 'no_realizadas';
}
// Aceptar también si llegan los antiguos directamente en ?estado=
if ($estadoParam === 'hechas')    $estadoParam = 'realizado';
if ($estadoParam === 'nohechas')  $estadoParam = 'no_realizadas';

// Zona por región
$zonaParam = trim($_GET['zona'] ?? 'todas'); // 'todas' o el nombre exacto de la región

/* ====== Prefijos categoría (usados para cctv/alarma) ====== */
$PREFIJOS_CCTV   = ['racks%','camara%','dvr%','nvr%','servidor%','monitor%','biometrico%','videoportero%','videotelefono%','ups%'];
$PREFIJOS_ALARMA = ['sensor%','dh%','pir%','cm%','oh%','estrobo%','rep%','drc%','estacion%','teclado%','sirena%','boton%'];

function esc_like_prefixes(string $col, string $collation, array $prefixes): string {
  $likes = [];
  foreach ($prefixes as $p) {
    $p = str_replace("'", "''", $p);
    $likes[] = "$col COLLATE $collation LIKE '$p'";
  }
  return '(' . implode(' OR ', $likes) . ')';
}
$whereCCTV   = esc_like_prefixes('e.nom_equipo', $COLLATION, $PREFIJOS_CCTV);
$whereAlarma = esc_like_prefixes('e.nom_equipo', $COLLATION, $PREFIJOS_ALARMA);
$CASE_CCTV   = "CASE WHEN $whereCCTV THEN 1 ELSE 0 END";
$CASE_ALARMA = "CASE WHEN $whereAlarma THEN 1 ELSE 0 END";

/* ====== Orden personalizado de regiones (ajusta a tus nombres) ====== */
$orderCaseRegion = "CASE
  WHEN r.nom_region = 'Centro'    THEN 1
  WHEN r.nom_region = 'Norte'     THEN 2
  WHEN r.nom_region = 'Occidente' THEN 3
  WHEN r.nom_region = 'Oriente'   THEN 4
  WHEN r.nom_region = 'Poniente'  THEN 5
  WHEN r.nom_region = 'Sur'       THEN 6
  ELSE 99
END";

/* ====== Query principal (agregado por sucursal) ====== */
$stHecho     = $conn->real_escape_string($STATUS_HECHO);
$stPendiente = $conn->real_escape_string($STATUS_PENDIENTE);
$stProceso   = $conn->real_escape_string($STATUS_PROCESO);
$stSig       = $conn->real_escape_string($STATUS_SIGUIENTE);

$sql = "
  SELECT
    r.nom_region           AS region,
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
  INNERJOIN ciudades   c       ON m.ciudad_id    = c.id
  INNER JOIN regiones   r       ON c.region_id    = r.id
  LEFT  JOIN determinantes dtr  ON dtr.sucursal_id = s.id
  LEFT  JOIN dispositivos disp  ON disp.sucursal   = s.id
  LEFT  JOIN equipos      e     ON disp.equipo     = e.id
  LEFT  JOIN status       st    ON disp.estado     = st.id
  GROUP BY
    r.nom_region, c.nom_ciudad, m.nom_municipio,
    s.id, s.nom_sucursal, dtr.nom_determinante, s.lat, s.lng, s.status_manual
  ORDER BY $orderCaseRegion, c.nom_ciudad, s.nom_sucursal, dtr.nom_determinante
";
// Fix de INNERJOIN (sin espacio)
$sql = str_replace('INNERJOIN','INNER JOIN',$sql);

/* ====== Ejecutar y tipar ====== */
$list = [];
$regionesUnicas = [];
if ($res = $conn->query($sql)) {
  while ($row = $res->fetch_assoc()) {
    $row['cctv']          = (int)($row['cctv'] ?? 0);
    $row['alarma']        = (int)($row['alarma'] ?? 0);
    $row['disp_count']    = (int)($row['disp_count'] ?? 0);
    $row['mto_hecho']     = (int)($row['mto_hecho'] ?? 0);
    $row['mto_pendiente'] = (int)($row['mto_pendiente'] ?? 0);
    $row['mto_proceso']   = (int)($row['mto_proceso'] ?? 0);
    $row['mto_siguiente'] = (int)($row['mto_siguiente'] ?? 0);

    $regionName = trim((string)($row['region'] ?? ''));
    if ($regionName !== '' && !isset($regionesUnicas[$regionName])) {
      $regionesUnicas[$regionName] = true;
    }

    $list[] = $row;
  }
  $res->free();
}

/* ====== Lógica de estado calculado (chip) ====== */
function chip_class_for(array $s, int $fallbackDays): string {
  $man = strtolower(trim($s['status_manual'] ?? ''));
  if ($man === 'hecho')     return 'green';
  if ($man === 'pendiente') return 'orange';
  if ($man === 'proceso')   return 'yellow';
  if ($man === 'siguiente') return 'blue';

  $disp       = (int)($s['disp_count'] ?? 0);
  $hecho      = (int)($s['mto_hecho'] ?? 0);
  $pendiente  = (int)($s['mto_pendiente'] ?? 0);
  $proceso    = (int)($s['mto_proceso'] ?? 0);
  $siguiente  = (int)($s['mto_siguiente'] ?? 0);

  if ($pendiente > 0) return 'orange';
  if ($proceso   > 0) return 'yellow';
  if ($siguiente > 0) return 'blue';

  if ($disp > 0) {
    if (empty($s['last_fecha'])) return 'blue';
    $last = strtotime($s['last_fecha'].' 00:00:00');
    $diff = floor((time() - $last) / 86400);
    if ($diff >= $fallbackDays) return 'blue';
    return ($hecho > 0 || $diff < $fallbackDays) ? 'green' : 'blue';
  }
  return 'orange';
}

/* ====== Aplicar filtros de Zona y Estado ====== */
$filtered = array_values(array_filter($list, function($s) use ($NEXT_MAINTENANCE_FALLBACK_DAYS, $estadoParam, $zonaParam){
  // ZONA (región)
  if ($zonaParam !== 'todas') {
    $region = strtolower(trim($s['region'] ?? ''));
    if ($region !== strtolower($zonaParam)) return false;
  }

  // ESTADO
  $chip = chip_class_for($s, $NEXT_MAINTENANCE_FALLBACK_DAYS);
  switch ($estadoParam) {
    case 'realizado':     return $chip === 'green';
    case 'no_realizadas': return $chip !== 'green';
    case 'pendiente':     return $chip === 'orange';
    case 'proceso':       return $chip === 'yellow';
    case 'siguiente':     return $chip === 'blue';
    case 'todas':
    default:              return true;
  }
}));

/* ====== Título dinámico ====== */
$estadoToTitle = [
  'todas'         => 'Todas las sucursales',
  'realizado'     => 'Sucursales con MTO. REALIZADO',
  'no_realizadas' => 'Sucursales NO REALIZADAS (pendiente / en proceso / siguiente)',
  'pendiente'     => 'Sucursales con mantenimiento PENDIENTE',
  'proceso'       => 'Sucursales con mantenimiento EN PROCESO',
  'siguiente'     => 'Sucursales con SIGUIENTE mantenimiento'
];
$zonaTitle = ($zonaParam === 'todas') ? '' : " • Zona: ".htmlspecialchars($zonaParam, ENT_QUOTES, 'UTF-8');
$title = ($estadoToTitle[$estadoParam] ?? 'Listado de sucursales') . $zonaTitle;

/* ====== Opciones de zona (regiones únicas) ====== */
$zonas = array_keys($regionesUnicas);
natcasesort($zonas);
$zonas = array_values($zonas);

/* ====== Helpers UI ====== */
function selected($a, $b) { return ($a === $b) ? 'selected' : ''; }

?>
<h2 class="mb-3"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>

<div class="card shadow-sm">
  <div class="card-body">
    <!-- ===== Filtros ===== -->
    <form class="row g-2 align-items-end mb-3" method="get">
      <div class="col-12 col-md-4">
        <label class="form-label">Zona (Región)</label>
        <select name="zona" class="form-select" onchange="this.form.submit()">
          <option value="todas" <?= selected('todas', $zonaParam) ?>>Todas</option>
          <?php foreach ($zonas as $z): ?>
            <option value="<?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?>" <?= selected(strtolower($z), strtolower($zonaParam)) ?>>
              <?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select" onchange="this.form.submit()">
          <option value="todas"         <?= selected('todas', $estadoParam) ?>>Todas</option>
          <option value="realizado"     <?= selected('realizado', $estadoParam) ?>>Mto. realizado</option>
          <option value="no_realizadas" <?= selected('no_realizadas', $estadoParam) ?>>No realizadas</option>
          <option value="pendiente"     <?= selected('pendiente', $estadoParam) ?>>Pendientes</option>
          <option value="proceso"       <?= selected('proceso', $estadoParam) ?>>En proceso</option>
          <option value="siguiente"     <?= selected('siguiente', $estadoParam) ?>>Siguiente</option>
        </select>
      </div>
      <div class="col-12 col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">Aplicar</button>
        <a class="btn btn-outline-secondary" href="/sisec-ui/views/sucursales/mantenimiento_listado.php">Limpiar</a>
      </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <span class="badge text-bg-light">Total listadas: <b><?= count($filtered) ?></b></span>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="/sisec-ui/views/index.php">← Volver al panel</a>
        <!-- Accesos rápidos -->
        <a class="btn btn-sm btn-outline-success"
           href="?estado=realizado<?= $zonaParam!=='todas' ? '&zona='.urlencode($zonaParam) : '' ?>">
           Mto. realizado
        </a>
        <a class="btn btn-sm btn-outline-primary"
           href="?estado=no_realizadas<?= $zonaParam!=='todas' ? '&zona='.urlencode($zonaParam) : '' ?>">
           No realizadas
        </a>
        <a class="btn btn-sm btn-outline-warning"
           href="?estado=pendiente<?= $zonaParam!=='todas' ? '&zona='.urlencode($zonaParam) : '' ?>">
           Pendientes
        </a>
        <a class="btn btn-sm btn-outline-dark"
           href="?estado=proceso<?= $zonaParam!=='todas' ? '&zona='.urlencode($zonaParam) : '' ?>">
           En proceso
        </a>
        <a class="btn btn-sm btn-outline-info"
           href="?estado=siguiente<?= $zonaParam!=='todas' ? '&zona='.urlencode($zonaParam) : '' ?>">
           Siguiente
        </a>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th style="min-width:120px;">Región</th>
            <th style="min-width:120px;">Ciudad</th>
            <th style="min-width:160px;">Sucursal</th>
            <th style="min-width:100px;">Determinante</th>
            <th class="text-center">Total</th>
            <th class="text-center">CCTV</th>
            <th class="text-center">Alarma</th>
            <th style="min-width:160px;">Estado calculado</th>
            <th style="min-width:160px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filtered as $row):
            $total = (int)$row['cctv'] + (int)$row['alarma'];
            $chip  = chip_class_for($row, $NEXT_MAINTENANCE_FALLBACK_DAYS);
            $label = [
              'green'  => 'Mto. realizado',
              'orange' => 'Mto. pendiente',
              'yellow' => 'Mto. en proceso',
              'blue'   => 'Siguiente mantenimiento'
            ][$chip] ?? '—';
            $pillClass = [
              'green'  => 'success',
              'orange' => 'warning',
              'yellow' => 'secondary',
              'blue'   => 'info'
            ][$chip] ?? 'secondary';
          ?>
          <tr>
            <td><?= htmlspecialchars($row['region'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['ciudad'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['sucursal'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['determinante'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-center"><?= (int)$total ?></td>
            <td class="text-center"><?= (int)$row['cctv'] ?></td>
            <td class="text-center"><?= (int)$row['alarma'] ?></td>
            <td>
              <span class="badge text-bg-<?= $pillClass ?>"><?= $label ?></span>
              <?php if(!empty($row['status_manual'])): ?>
                <span class="badge text-bg-light ms-1">• Manual</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-sm btn-outline-primary"
                 href="/sisec-ui/views/dispositivos/listar.php?sucursal_id=<?= (int)$row['sucursal_id'] ?>#filtros">
                 Ver dispositivos
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!count($filtered)): ?>
          <tr><td colspan="9" class="text-muted text-center">Sin registros</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';