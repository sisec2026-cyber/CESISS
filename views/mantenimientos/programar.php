<?php
// /sisec-ui/views/mantenimientos/programar.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Técnico','Prevencion','Distrital']);
require_once __DIR__ . '/../../includes/db.php';

/* =========================
   CONFIG
========================= */
$STATUS_HECHO     = 'Mantenimiento hecho';
$STATUS_PENDIENTE = 'Mantenimiento pendiente';
$STATUS_PROCESO   = 'Mantenimiento en proceso';
$STATUS_SIGUIENTE = 'Siguiente mantenimiento';
$STATUS_ALLOWED = [$STATUS_HECHO,$STATUS_PENDIENTE,$STATUS_PROCESO,$STATUS_SIGUIENTE];

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];
$feedback = null;
$feedback_type = 'success';

// Utilidad: formatear "04/ago/25"
function fmt_es_corta(string $iso): string {
  if (!$iso) return '';
  $ts = strtotime($iso);
  $mes = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'][(int)date('n',$ts)-1] ?? '';
  return date('d',$ts).'/'.$mes.'/'.substr(date('Y',$ts),2);
}

/* POST create/update/delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($CSRF, $csrf)) {
    $feedback = 'Token CSRF inválido. Refresca la página.';
    $feedback_type = 'danger';
  } else {
    $action = $_POST['action'] ?? '';
    try {
      if ($action === 'create' || $action === 'update') {
        $id            = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $sucursal_id   = (int)($_POST['sucursal_id'] ?? 0);
        $fecha_inicio  = trim((string)($_POST['fecha_inicio'] ?? ''));
        $fecha_fin     = trim((string)($_POST['fecha_fin'] ?? ''));
        $status        = trim((string)($_POST['status_label'] ?? ''));
        $notas         = trim((string)($_POST['notas'] ?? ''));

        if ($sucursal_id <= 0) throw new Exception('Debes seleccionar una sucursal del listado.');
        if (!$fecha_inicio || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) throw new Exception('Fecha inicio inválida.');
        if ($fecha_fin && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) throw new Exception('Fecha fin inválida.');
        if (!$fecha_fin) $fecha_fin = $fecha_inicio;
        if ($fecha_fin < $fecha_inicio) throw new Exception('La fecha fin no puede ser menor que la de inicio.');
        if (!in_array($status, $STATUS_ALLOWED, true)) throw new Exception('Estado inválido.');

        // --- Validar superposición/duplicados en la misma sucursal // Traslape: NOT( nuevo_fin < inicio_existente OR nuevo_inicio > fin_existente )
        $overlapSql = "SELECT id, fecha_inicio, fecha_fin, status_label
          FROM mantenimiento_eventos
          WHERE sucursal_id = ?
            AND NOT (? < fecha_inicio OR ? > fecha_fin)";
        $types = 'iss';
        $params = [$sucursal_id, $fecha_inicio, $fecha_fin];

        if ($action === 'update') {
          if ($id <= 0) throw new Exception('ID inválido para editar.');
          $overlapSql .= " AND id <> ?";
          $types .= 'i';
          $params[] = $id;
        }

        $stOv = $conn->prepare($overlapSql);
        if (!$stOv) throw new Exception('Error de BD (prepare overlap).');
        $stOv->bind_param($types, ...$params);
        $stOv->execute();
        $resOv = $stOv->get_result();
        if ($rowOv = $resOv->fetch_assoc()) {
          $yaFi = $rowOv['fecha_inicio'];
          $yaFf = $rowOv['fecha_fin'];
          $yaSt = $rowOv['status_label'];
          throw new Exception('Ya existe un evento para esta sucursal en ese rango: '.
            fmt_es_corta($yaFi).' – '.fmt_es_corta($yaFf).' · '.$yaSt);
        }

        // Detección de columnas de rango (compatibilidad)
        $hasRangeCols = $conn->query("SHOW COLUMNS FROM mantenimiento_eventos LIKE 'fecha_inicio'")->num_rows > 0;
        if ($action === 'create') {
          if ($hasRangeCols) {
            $st = $conn->prepare("INSERT INTO mantenimiento_eventos (sucursal_id, fecha_inicio, fecha_fin, status_label, notas) VALUES (?,?,?,?,?)");
            if (!$st) throw new Exception('Error de BD (prepare create range).');
            $st->bind_param('issss', $sucursal_id, $fecha_inicio, $fecha_fin, $status, $notas);
          } else {
            // legacy
            $st = $conn->prepare("INSERT INTO mantenimiento_eventos (sucursal_id, fecha, status_label, notas) VALUES (?,?,?,?)");
            if (!$st) throw new Exception('Error de BD (prepare create legacy).');
            $st->bind_param('isss', $sucursal_id, $fecha_inicio, $status, $notas);
          }
          if (!$st->execute()) throw new Exception('No se pudo guardar el evento.');
          $feedback = 'Evento creado correctamente.';
        } else {
          // UPDATE
          if ($hasRangeCols) {
            $st = $conn->prepare("UPDATE mantenimiento_eventos SET sucursal_id=?, fecha_inicio=?, fecha_fin=?, status_label=?, notas=? WHERE id=?");
            if (!$st) throw new Exception('Error de BD (prepare update range).');
            $st->bind_param('issssi', $sucursal_id, $fecha_inicio, $fecha_fin, $status, $notas, $id);
          } else {
            // legacy
            $st = $conn->prepare("UPDATE mantenimiento_eventos SET sucursal_id=?, fecha=?, status_label=?, notas=? WHERE id=?");
            if (!$st) throw new Exception('Error de BD (prepare update legacy).');
            $st->bind_param('isssi', $sucursal_id, $fecha_inicio, $status, $notas, $id);
          }
          if (!$st->execute()) throw new Exception('No se pudo actualizar el evento.');
          $feedback = 'Evento actualizado correctamente.';
        }
      } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID inválido.');
        $st = $conn->prepare("DELETE FROM mantenimiento_eventos WHERE id=?");
        if (!$st) throw new Exception('Error de BD (prepare delete).');
        $st->bind_param('i', $id);
        if (!$st->execute()) throw new Exception('No se pudo eliminar.');
        $feedback = 'Evento eliminado.';
      }
    } catch (Throwable $e) {
      $feedback = $e->getMessage();
      $feedback_type = 'danger';
    }
  }
}

/* Sucursales (datalist) */
$sucursales = [];
$res = $conn->query("SELECT s.id, s.nom_sucursal, d.nom_determinante AS determinante, m.nom_municipio AS municipio, c.nom_ciudad AS ciudad, r.nom_region AS region
  FROM sucursales s
  LEFT JOIN determinantes d ON d.sucursal_id = s.id
  LEFT JOIN municipios m ON s.municipio_id = m.id
  LEFT JOIN ciudades  c ON m.ciudad_id    = c.id
  LEFT JOIN regiones  r ON c.region_id    = r.id
  ORDER BY r.nom_region, c.nom_ciudad, m.nom_municipio, s.nom_sucursal");
if ($res) while ($row = $res->fetch_assoc()) $sucursales[] = $row;

/* Próximos (solo PROCESO/SIGUIENTE) */
$hoy = date('Y-m-d');
$hasta = date('Y-m-d', strtotime('+180 days'));
$eventos = [];
$hasRangeCols = $conn->query("SHOW COLUMNS FROM mantenimiento_eventos LIKE 'fecha_inicio'")->num_rows > 0;
if ($hasRangeCols) {
  $st2 = $conn->prepare("SELECT me.id, me.sucursal_id, me.fecha_inicio, me.fecha_fin, me.status_label, me.notas,
           s.nom_sucursal, d.nom_determinante AS determinante,
           m.nom_municipio AS municipio, c.nom_ciudad AS ciudad, r.nom_region AS region
    FROM mantenimiento_eventos me
    JOIN sucursales s ON s.id = me.sucursal_id
    LEFT JOIN determinantes d ON d.sucursal_id = s.id
    LEFT JOIN municipios m ON s.municipio_id = m.id
    LEFT JOIN ciudades  c ON m.ciudad_id    = c.id
    LEFT JOIN regiones  r ON c.region_id    = r.id
    WHERE me.status_label IN (?,?)
    AND me.fecha_fin >= ?
    AND me.fecha_inicio <= ?
    ORDER BY me.fecha_inicio ASC, s.nom_sucursal ASC");
  $st2->bind_param('ssss', $STATUS_PROCESO, $STATUS_SIGUIENTE, $hoy, $hasta);
} else {
  $st2 = $conn->prepare("SELECT me.id, me.sucursal_id, me.fecha AS fecha_inicio, me.fecha AS fecha_fin, me.status_label, me.notas,
           s.nom_sucursal, d.nom_determinante AS determinante,
           m.nom_municipio AS municipio, c.nom_ciudad AS ciudad, r.nom_region AS region
    FROM mantenimiento_eventos me
    JOIN sucursales s ON s.id = me.sucursal_id
    LEFT JOIN determinantes d ON d.sucursal_id = s.id
    LEFT JOIN municipios m ON s.municipio_id = m.id
    LEFT JOIN ciudades  c ON m.ciudad_id    = c.id
    LEFT JOIN regiones  r ON c.region_id    = r.id
    WHERE me.status_label IN (?,?)
    AND me.fecha >= ?
    AND me.fecha <= ?
    ORDER BY me.fecha ASC, s.nom_sucursal ASC");
  $st2->bind_param('ssss', $STATUS_PROCESO, $STATUS_SIGUIENTE, $hoy, $hasta);
}
$st2->execute();
$res2 = $st2->get_result();
while ($row = $res2->fetch_assoc()) $eventos[] = $row;

ob_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>CESISS - Programar Mantenimientos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($CSRF,ENT_QUOTES,'UTF-8') ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="shortcut icon" href="/sisec-ui/public/img/QRCESISS.png">
  <style>
    .fc .fc-toolbar-title{ font-size:1.05rem; }
    .table-fixed{ table-layout:fixed; }
    .table-fixed td{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .status-badge{ font-weight:700; }
    /* Colores base (coinciden con el mapa) + fases temporales */
    .fc .ev-hecho{--fc-event-bg-color:#01a806; --fc-event-border-color:#01a806; --fc-event-text-color:#fff;}
    .fc .ev-pendiente{--fc-event-bg-color:#f39c12; --fc-event-border-color:#f39c12; --fc-event-text-color:#000;}
    .fc .ev-proceso{--fc-event-bg-color:#f1c40f; --fc-event-border-color:#f1c40f; --fc-event-text-color:#000;}
    .fc .ev-siguiente{--fc-event-bg-color:#2980b9; --fc-event-border-color:#2980b9; --fc-event-text-color:#fff;}
    .fc .phase-ongoing .fc-event-main { outline: 2px solid rgba(0,0,0,.18); }
    .fc .phase-soon-7  { --fc-event-bg-color:#ff9f43; --fc-event-border-color:#ff9f43; --fc-event-text-color:#000; }
    .fc .phase-soon-3  { --fc-event-bg-color:#dc3545; --fc-event-border-color:#dc3545; --fc-event-text-color:#fff; }
    .fc .phase-overdue { --fc-event-bg-color:#dc3545; --fc-event-border-color:#dc3545; --fc-event-text-color:#fff; }
    :root{--brand:#3C92A6; --brand-2:#24A3C1; --ink:#10343b; --muted:#486973; --bg:#F7FBFD;--surface:#FFFFFF; --border:#DDEEF3; --border-strong:#BFE2EB;--chip:#EAF7FB; --ring:0 0 0 .22rem rgba(36,163,193,.25); --ring-strong:0 0 0 .28rem rgba(36,163,193,.33);--shadow-sm:0 6px 18px rgba(20,78,90,.08);--radius-xl:1rem; --radius-2xl:1.25rem;}
    h2{ font-weight:800; letter-spacing:.2px; color:var(--ink); margin-bottom:.75rem!important; }
    h2::after{ content:""; display:block; width:78px; height:4px; border-radius:99px; margin-top:.5rem;background:linear-gradient(90deg,var(--brand),var(--brand-2)); }
    .back-home {display: inline-flex;align-items: center;gap: .4rem;text-decoration: none;color: var(--accent);font-size: .9rem;font-weight: 500;transition: color .2s ease;}
    .back-home:hover {color: #116fc1ff;}
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <h2 class="mb-3">Programación de mantenimientos</h2>
  <p class="text-muted mb-4">Asigna manualmente rangos de fechas por sucursal y estado. Lo que registres aquí alimenta el calendario y el mapa.</p>
  <a href="/sisec-ui/index.php" class="back-home"><i class="fa-solid fa-house"></i>Volver al inicio</a>
  <?php if ($feedback): ?>
    <div class="alert alert-<?= htmlspecialchars($feedback_type) ?>"><?= htmlspecialchars($feedback) ?></div>
  <?php endif; ?>
  <div class="row g-4" style="margin-top: -10px;">
    <!-- Formulario -->
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-3">Nuevo / Editar evento</h6>
          <form method="post" id="formME" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
            <input type="hidden" name="action" value="create" id="f_action">
            <input type="hidden" name="id" id="f_id">
            <div class="mb-3">
              <label class="form-label">Sucursal</label>
              <input class="form-control" list="dl_sucursales" id="f_sucursal_text" placeholder="Escribe para buscar…" autocomplete="off" required>
              <datalist id="dl_sucursales">
                <?php foreach ($sucursales as $s):
                  $label = $s['nom_sucursal'];
                  if (!empty($s['determinante'])) $label .= " (#".$s['determinante'].")";
                  $meta = ($s['municipio']??'').", ".($s['ciudad']??'')." · ".($s['region']??'');
                ?>
                  <option data-id="<?= (int)$s['id'] ?>" value="<?= htmlspecialchars($label) ?>" label="<?= htmlspecialchars($meta) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <input type="hidden" name="sucursal_id" id="f_sucursal_id" required>
              <div class="form-text">Selecciona una opción del listado (datalist).</div>
            </div>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">Inicio</label>
                <input type="date" name="fecha_inicio" id="f_fecha_inicio" class="form-control" required>
              </div>
              <div class="col-6">
                <label class="form-label">Fin</label>
                <input type="date" name="fecha_fin" id="f_fecha_fin" class="form-control">
              </div>
            </div>
            <div class="mt-2">
              <label class="form-label">Estado</label>
              <select name="status_label" id="f_status" class="form-select" required>
                <option value="<?= htmlspecialchars($STATUS_HECHO) ?>"><?= htmlspecialchars($STATUS_HECHO) ?></option>
                <option value="<?= htmlspecialchars($STATUS_PENDIENTE) ?>"><?= htmlspecialchars($STATUS_PENDIENTE) ?></option>
                <option value="<?= htmlspecialchars($STATUS_PROCESO) ?>"><?= htmlspecialchars($STATUS_PROCESO) ?></option>
                <option value="<?= htmlspecialchars($STATUS_SIGUIENTE) ?>"><?= htmlspecialchars($STATUS_SIGUIENTE) ?></option>
              </select>
            </div>
            <div class="mt-3">
              <label class="form-label">Notas (opcional)</label>
              <textarea name="notas" id="f_notas" rows="2" class="form-control" placeholder="Observaciones…"></textarea>
            </div>
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-primary" type="submit" id="btnGuardar">Guardar</button>
              <button class="btn btn-outline-secondary" type="button" id="btnLimpiar">Limpiar</button>
              <!-- Botón eliminar (solo visible en modo "update") -->
              <button class="btn btn-outline-danger ms-auto d-none" type="button" id="btnEliminar">Eliminar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- Calendario y lista -->
    <div class="col-lg-7">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h6 class="fw-bold mb-0">Calendario (todos los estados)</h6>
            <div class="d-flex align-items-center gap-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="fcOnlyUpcoming">
                <label class="form-check-label" for="fcOnlyUpcoming">Mostrar solo próximos en la vista (opcional)</label>
              </div>
              <div class="d-flex gap-2 small">
                <span class="badge" style="background:#01a806;color:#fff;">Hecho</span>
                <span class="badge" style="background:#f39c12;color:#000;">Pendiente</span>
                <span class="badge" style="background:#f1c40f;color:#000;">En proceso</span>
                <span class="badge" style="background:#2980b9;color:#fff;">Siguiente</span>
                <span class="badge" style="background:#dc3545;color:#fff;">Urgente/vencido</span>
              </div>
            </div>
          </div>
          <div id="calendar"></div>
          <small class="text-muted d-block mt-2">Clic en un evento para precargarlo en el formulario (editar/duplicar). También puedes arrastrar o redimensionar para reprogramar.</small>
        </div>
      </div>
        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="fw-bold mb-3">Próximos eventos (En proceso / Siguiente)</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle table-fixed">
                <thead>
                  <tr>
                    <th style="width:180px;">Rango</th>
                    <th>Sucursal</th>
                    <th style="width:160px;">Estado</th>
                    <th style="width:140px;">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (!$eventos): ?>
                  <tr><td colspan="4" class="text-muted">Sin próximos eventos.</td></tr>
                <?php else: foreach ($eventos as $ev):
                  $name = $ev['nom_sucursal'];
                  if (!empty($ev['determinante'])) $name .= " (#".$ev['determinante'].")";
                  $fi = $ev['fecha_inicio']; $ff = $ev['fecha_fin'] ?: $fi;
                  $rangoTxt = fmt_es_corta($fi) . ' – ' . fmt_es_corta($ff);
                  $bg = match ($ev['status_label']) {
                    $STATUS_HECHO     => '#01a806',
                    $STATUS_PENDIENTE => '#f39c12',
                    $STATUS_PROCESO   => '#f1c40f',
                    $STATUS_SIGUIENTE => '#2980b9',
                    default           => '#6c757d'
                  };
                  $txt = ($bg === '#01a806' || $bg === '#2980b9') ? '#fff' : '#000';
                ?>
                  <tr>
                    <td><?= htmlspecialchars($rangoTxt) ?></td>
                    <td title="<?= htmlspecialchars(($ev['municipio']??'').', '.($ev['ciudad']??'').' · '.($ev['region']??'')) ?>">
                      <?= htmlspecialchars($name) ?>
                    </td>
                    <td>
                      <span class="badge status-badge" style="background:<?= $bg ?>;color:<?= $txt ?>;">
                        <?= htmlspecialchars($ev['status_label']) ?>
                      </span>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary btn-edit" data-id="<?= (int)$ev['id'] ?>" data-sid="<?= (int)$ev['sucursal_id'] ?>" data-fi="<?= htmlspecialchars($fi) ?>" data-ff="<?= htmlspecialchars($ff) ?>" data-status="<?= htmlspecialchars($ev['status_label']) ?>" data-n="<?= htmlspecialchars($ev['nom_sucursal']) ?>" data-det="<?= htmlspecialchars($ev['determinante'] ?? '') ?>" data-notas="<?= htmlspecialchars($ev['notas'] ?? '') ?>">Editar</button>
                        <form method="post" onsubmit="return confirm('¿Eliminar este evento?')">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                          <button class="btn btn-outline-danger">Eliminar</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="/sisec-ui/views/index.php">Ir al panel / mapa</a>
          </div>
        </div>
      </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
  // --- Datalist: resolver ID de sucursal desde el texto visible
  const dl = document.getElementById('dl_sucursales');
  const inputText = document.getElementById('f_sucursal_text');
  const inputId   = document.getElementById('f_sucursal_id');
  function resolveSucursalIdByText(txt){
    for (const opt of dl.options) {
      if ((opt.value||'').trim() === (txt||'').trim()) {
        return opt.getAttribute('data-id') || '';
      }
    }
    return '';
  }
  inputText.addEventListener('change', () => {
    inputId.value = resolveSucursalIdByText(inputText.value);
    if (!inputId.value) { alert('Selecciona una sucursal del listado.'); inputText.focus(); }
  });
  document.getElementById('formME').addEventListener('submit', (e)=>{
    if (!inputId.value) {
      e.preventDefault();
      inputId.value = resolveSucursalIdByText(inputText.value);
      if (!inputId.value) { alert('Selecciona una sucursal del listado.'); inputText.focus(); }
    }
    // Normaliza fin vacío = inicio
    const fi = document.getElementById('f_fecha_inicio').value;
    const ff = document.getElementById('f_fecha_fin').value;
    if (!ff) document.getElementById('f_fecha_fin').value = fi;
  });
  // --- Edición rápida desde la tabla
  document.querySelectorAll('.btn-edit').forEach(b=>{
    b.addEventListener('click', ()=>{
      setFormMode('update');
      document.getElementById('f_id').value = b.dataset.id;
      document.getElementById('f_fecha_inicio').value = b.dataset.fi;
      document.getElementById('f_fecha_fin').value = b.dataset.ff;
      document.getElementById('f_status').value = b.dataset.status;
      document.getElementById('f_notas').value = b.dataset.notas || '';
      const label = b.dataset.n + (b.dataset.det ? ' (#'+b.dataset.det+')' : '');
      document.getElementById('f_sucursal_text').value = label;
      document.getElementById('f_sucursal_id').value = b.dataset.sid;
      window.scrollTo({top:0, behavior:'smooth'});
    });
  });
  // --- Helpers UI
  function setFormMode(mode){ // 'create' | 'update'
    const isUpdate = mode === 'update';
    document.getElementById('f_action').value = isUpdate ? 'update' : 'create';
    document.getElementById('btnEliminar').classList.toggle('d-none', !isUpdate);
    document.getElementById('btnGuardar').textContent = isUpdate ? 'Actualizar' : 'Guardar';
  }
  function preloadFormFromEvent(ev){
    // ID (necesario para update/delete)
    const evId = ev.id || ev.extendedProps?.event_id || '';
    document.getElementById('f_id').value = evId;
    // Sucursal
    const sid   = ev.extendedProps?.sucursal_id || '';
    const label = ev.title || '';
    document.getElementById('f_sucursal_text').value = label;
    document.getElementById('f_sucursal_id').value   = sid;
    // Fechas inclusivas
    const fi = ev.extendedProps?.fecha_inicio || (ev.start ? ev.start.toISOString().slice(0,10) : '');
    let ff   = ev.extendedProps?.fecha_fin;
    if (!ff){
      if (ev.end){ // end exclusivo en FullCalendar -> ajustamos un día
        const endExc = new Date(ev.end.getTime() - 86400000);
        ff = endExc.toISOString().slice(0,10);
      } else {
        ff = fi;
      }
    }
    document.getElementById('f_fecha_inicio').value = fi;
    document.getElementById('f_fecha_fin').value    = ff;
    // Estado (mapear por slug si viene)
    const mapStatus = {
      'hecho':     '<?= addslashes($STATUS_HECHO) ?>',
      'pendiente': '<?= addslashes($STATUS_PENDIENTE) ?>',
      'proceso':   '<?= addslashes($STATUS_PROCESO) ?>',
      'siguiente': '<?= addslashes($STATUS_SIGUIENTE) ?>'
    };
    const slug = (ev.extendedProps?.status_slug || '').toLowerCase();
    if (mapStatus[slug]) {
      document.getElementById('f_status').value = mapStatus[slug];
    }
    // Notas
    document.getElementById('f_notas').value = ev.extendedProps?.notas || '';
  }
  // --- Limpiar formulario a "create"
  document.getElementById('btnLimpiar').addEventListener('click', ()=>{
    setFormMode('create');
    document.getElementById('f_id').value = '';
    document.getElementById('f_sucursal_text').value = '';
    document.getElementById('f_sucursal_id').value = '';
    document.getElementById('f_fecha_inicio').value = '';
    document.getElementById('f_fecha_fin').value = '';
    document.getElementById('f_status').selectedIndex = 0;
    document.getElementById('f_notas').value = '';
  });
  // --- Botón Eliminar desde el formulario
  document.getElementById('btnEliminar').addEventListener('click', ()=>{
    const id = document.getElementById('f_id').value;
    if (!id) { showAlert('No hay evento seleccionado.', 'danger'); return; }
    if (!confirm('¿Eliminar este evento?')) return;
    document.getElementById('f_action').value = 'delete';
    document.getElementById('formME').submit();
  });
  // --- Helpers calendario (drag & drop)
  function isoDate(dateObj){
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth()+1).padStart(2,'0');
    const d = String(dateObj.getDate()).padStart(2,'0');
    return `${y}-${m}-${d}`;
  }
  function minusOneDay(isoYMD){
    const d = new Date(isoYMD + 'T00:00:00');
    d.setDate(d.getDate()-1);
    return isoDate(d);
  }
  function showAlert(msg, type='success'){
    const el = document.createElement('div');
    el.className = `alert alert-${type}`;
    el.textContent = msg;
    document.querySelector('.container .row')?.prepend(el);
    setTimeout(()=> el.remove(), 2800);
  }
  const calEl = document.getElementById('calendar');
  const onlyUpcomingChk = document.getElementById('fcOnlyUpcoming');
  function buildParams(fetchInfo){
    const p = new URLSearchParams();
    const startISO = fetchInfo.startStr.slice(0,10);
    const endISO   = fetchInfo.endStr.slice(0,10);
    // siempre pasar el rango de la vista
    p.set('start', startISO);
    p.set('end', endISO);
    // todos los estados
    ['hecho','pendiente','proceso','siguiente'].forEach(s => p.append('status[]', s));
    // opcional: si activas "solo próximos", podrías agregar un flag y filtrar en tu endpoint
    // if (onlyUpcomingChk.checked) p.set('upcoming', '1');
    return p;
  }
  const fc = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,listMonth' },
    // Edición directa
    editable: true,
    eventDurationEditable: true,
    droppable: false,
    eventOverlap: true,
    events: function(fetchInfo, success, failure){
      const p = buildParams(fetchInfo);
      fetch('/sisec-ui/views/api/mantenimiento_events.php?'+p.toString(), {credentials:'same-origin'})
        .then(r=>r.json())
        .then(j=>{
          if (!j || j.ok !== true) throw new Error('Respuesta inválida del servidor.');
          success(j.events || []);
        })
        .catch(failure);
    },
    eventDrop: function(info){ updateEventDates(info, false); },
    eventResize: function(info){ updateEventDates(info, true); },
    eventClick: (info) => {
      const ev = info.event;
      setFormMode('update');
      preloadFormFromEvent(ev);
      window.scrollTo({top:0, behavior:'smooth'});
    }
  });
  fc.render();
  setFormMode('create');
  onlyUpcomingChk.addEventListener('change', ()=> fc.refetchEvents());
  function updateEventDates(info, isResize){
    const ev = info.event;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const id  = ev.id || ev.extendedProps?.event_id;
    if (!id) { info.revert(); showAlert('No se pudo identificar el evento.', 'danger'); return; }
    const startISO = ev.start ? isoDate(ev.start) : (ev.extendedProps?.fecha_inicio || '');
    let endISO;
    if (ev.end) {
      const endExc = isoDate(ev.end); // exclusivo
      endISO = minusOneDay(endExc);   // inclusivo
    } else {
      endISO = startISO;
    }
    const body = new URLSearchParams();
    body.set('csrf', csrf);
    body.set('id', id);
    body.set('start', startISO);
    body.set('end', endISO);
    fetch('/sisec-ui/views/api/mantenimiento_events_update.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      credentials: 'same-origin',
      body: body.toString()
    })
    .then(r => r.json())
    .then(j => {
      if (!j || j.ok !== true) throw new Error(j?.error || 'Error al guardar');
      ev.setExtendedProp('fecha_inicio', startISO);
      ev.setExtendedProp('fecha_fin', endISO);
      showAlert('Fechas actualizadas.');
    })
    .catch(err => {
      console.error(err);
      info.revert();
      showAlert('No se pudo actualizar: ' + (err?.message || 'Error'), 'danger');
    });
  }
</script>
</body>
</html>
<?php
echo ob_get_clean();