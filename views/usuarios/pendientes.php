<?php
// /sisec-ui/views/usuarios/pendientes.php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Administrador','Superadmin']);

require_once __DIR__ . '/../../includes/conexion.php';
date_default_timezone_set('America/Mexico_City');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$base = '/sisec-ui';

/* ====== Filtros ====== */
$q       = trim($_GET['q']   ?? '');
$fromRaw = trim($_GET['from']?? '');
$toRaw   = trim($_GET['to']  ?? '');
$pp      = (int)($_GET['pp'] ?? 10);
$pp      = max(5, min(50, $pp)); // 5..50
$p       = (int)($_GET['p']  ?? 1);
$p       = max(1, $p);

$conds = ["esta_aprobado = 0"];
$types = '';
$params= [];

/* Búsqueda por nombre/email/cargo/empresa */
if ($q !== '') {
  $like = "%$q%";
  $conds[] = "(nombre LIKE ? OR email LIKE ? OR cargo LIKE ? OR empresa LIKE ?)";
  $types  .= 'ssss';
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

/* Rango de fechas sobre creado_el (inclusive) */
if ($fromRaw !== '') {
  // normaliza a inicio del día
  $from = $fromRaw . ' 00:00:00';
  $conds[] = "creado_el >= ?";
  $types  .= 's';
  $params[] = $from;
}
if ($toRaw !== '') {
  // normaliza a fin del día
  $to = $toRaw . ' 23:59:59';
  $conds[] = "creado_el <= ?";
  $types  .= 's';
  $params[] = $to;
}

$where = 'WHERE ' . implode(' AND ', $conds);

/* ====== Total para paginación ====== */
$sqlCount = "SELECT COUNT(*) AS total FROM usuarios $where";
$stmtC = $conexion->prepare($sqlCount);
if ($types !== '') $stmtC->bind_param($types, ...$params);
$stmtC->execute();
$total = (int)($stmtC->get_result()->fetch_assoc()['total'] ?? 0);

$pages = max(1, (int)ceil($total / $pp));
$p = min($p, $pages); // si se borran filas y quedas en página alta
$offset = ($p - 1) * $pp;

/* ====== Datos página ====== */
$sql = "
  SELECT id, nombre, email, cargo, empresa, rol, foto, creado_el
  FROM usuarios
  $where
  ORDER BY creado_el DESC
  LIMIT ? OFFSET ?
";
$typesSel = $types . 'ii';
$paramsSel = $params;
$limitVal  = (int)$pp;
$offsetVal = (int)$offset;
$paramsSel[] = $limitVal;
$paramsSel[] = $offsetVal;

$stmt = $conexion->prepare($sql);
if ($typesSel !== '') $stmt->bind_param($typesSel, ...$paramsSel);
$stmt->execute();
$pendientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Helper para mantener querystring en los links de paginación */
function qs_keep(array $keep, array $override = []): string {
  $q = array_merge($keep, $override);
  return http_build_query(array_filter($q, fn($v)=>$v!=='' && $v!==null));
}
$qsBase = ['q'=>$q, 'from'=>$fromRaw, 'to'=>$toRaw, 'pp'=>$pp];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Solicitudes pendientes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    .avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #e5e7eb; }
    .table td, .table th { vertical-align: middle; }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="m-0">Solicitudes pendientes</h3>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/views/inicio/index.php">← Volver</a>
  </div>

  <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success"><?= h($_GET['msg']) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= h($_GET['error']) ?></div>
  <?php endif; ?>

  <!-- Filtros -->
  <form class="card card-body mb-3 shadow-sm">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Buscar</label>
        <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Nombre, correo, cargo o empresa">
      </div>
      <div class="col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" name="from" value="<?= h($fromRaw) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" name="to" value="<?= h($toRaw) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Por página</label>
        <select name="pp" class="form-select">
          <?php foreach ([5,10,20,30,50] as $opt): ?>
            <option value="<?= $opt ?>" <?= $pp===$opt?'selected':'' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 d-flex gap-2 mt-2">
        <button class="btn btn-primary">Filtrar</button>
        <a class="btn btn-outline-secondary" href="<?= basename(__FILE__) ?>">Borrar filtros</a>
      </div>
    </div>
  </form>

  <?php if (empty($pendientes)): ?>
    <div class="alert alert-info">No hay solicitudes pendientes<?= $total===0?'':' en esta búsqueda' ?>.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover bg-white border shadow-sm">
        <thead class="table-light">
          <tr>
            <th style="width:56px;">Foto</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Cargo</th>
            <th>Empresa</th>
            <th>Solicitado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pendientes as $u): ?>
          <tr>
            <td>
              <?php if (!empty($u['foto'])): ?>
                <img class="avatar" src="<?= $base . '/uploads/usuarios/' . h($u['foto']) ?>" alt="foto"/>
              <?php else: ?>
                <div class="avatar bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center">
                  <span class="text-secondary">—</span>
                </div>
              <?php endif; ?>
            </td>
            <td><strong><?= h($u['nombre']) ?></strong></td>
            <td><?= h($u['email']) ?></td>
            <td><?= h($u['cargo']) ?></td>
            <td><?= h($u['empresa']) ?></td>
            <td><?= h(date('Y-m-d H:i', strtotime($u['creado_el'] ?? ''))) ?></td>
            <td class="text-end">
              <button class="btn btn-primary btn-sm me-2"
                      data-bs-toggle="modal"
                      data-bs-target="#approveModal"
                      data-user-id="<?= (int)$u['id'] ?>"
                      data-user-name="<?= h($u['nombre']) ?>">
                Aprobar
              </button>

              <form class="d-inline" method="post" action="<?= $base ?>/controllers/usuarios_aprobar.php"
                    onsubmit="return confirm('¿Eliminar la solicitud de <?= h($u['nombre']) ?>? Esta acción no se puede deshacer.');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-outline-danger btn-sm">Rechazar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <nav aria-label="Paginación">
      <ul class="pagination">
        <?php
          $prev = max(1, $p-1);
          $next = min($pages, $p+1);
        ?>
        <li class="page-item <?= $p<=1?'disabled':'' ?>">
          <a class="page-link" href="?<?= qs_keep($qsBase, ['p'=>$prev]) ?>">«</a>
        </li>
        <?php
          // Ventana de páginas ±2
          $start = max(1, $p-2);
          $end   = min($pages, $p+2);
          if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="?'.qs_keep($qsBase, ['p'=>1]).'">1</a></li>';
            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
          }
          for ($i=$start; $i<=$end; $i++) {
            $active = $i===$p ? 'active' : '';
            echo '<li class="page-item '.$active.'"><a class="page-link" href="?'.qs_keep($qsBase, ['p'=>$i]).'">'.$i.'</a></li>';
          }
          if ($end < $pages) {
            if ($end < $pages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            echo '<li class="page-item"><a class="page-link" href="?'.qs_keep($qsBase, ['p'=>$pages]).'">'.$pages.'</a></li>';
          }
        ?>
        <li class="page-item <?= $p>=$pages?'disabled':'' ?>">
          <a class="page-link" href="?<?= qs_keep($qsBase, ['p'=>$next]) ?>">»</a>
        </li>
      </ul>
      <div class="text-muted small">
        Mostrando <?= count($pendientes) ?> de <?= $total ?> solicitud(es). Página <?= $p ?> de <?= $pages ?>.
      </div>
    </nav>
  <?php endif; ?>
</div>

<!-- Modal Aprobar (igual que antes) -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= $base ?>/controllers/usuarios_aprobar.php">
      <div class="modal-header">
        <h5 class="modal-title">Aprobar acceso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="accion" value="aprobar">
        <input type="hidden" name="id" id="approveUserId" value="">
        <div class="mb-3">
          <label class="form-label">Usuario</label>
          <input type="text" class="form-control" id="approveUserName" value="" readonly>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Rol a asignar</label>
            <select class="form-select" name="rol" id="rolSelect" required>
              <option value="">Selecciona un rol</option>
              <option>Administrador</option>
              <option>Capturista</option>
              <option>Técnico</option>
              <option>Distrital</option>
              <option>Prevencion</option>
              <option>Mantenimientos</option>
              <option>Monitorista</option>
            </select>
            <div class="form-text">
              * No se permite aprobar como <b>Superadmin</b> desde esta pantalla.
            </div>
          </div>

          <!-- Ámbito (se muestra según rol) -->
          <div class="col-md-3 scope d-none" id="wrapRegion">
            <label class="form-label">Región</label>
            <select class="form-select" name="region" id="regionSel">
              <option value="">—</option>
            </select>
          </div>
          <div class="col-md-3 scope d-none" id="wrapCiudad">
            <label class="form-label">Ciudad</label>
            <select class="form-select" name="ciudad" id="ciudadSel">
              <option value="">—</option>
            </select>
          </div>
          <div class="col-md-3 scope d-none" id="wrapMunicipio">
            <label class="form-label">Municipio</label>
            <select class="form-select" name="municipio" id="muniSel">
              <option value="">—</option>
            </select>
          </div>
          <div class="col-md-3 scope d-none" id="wrapSucursal">
            <label class="form-label">Sucursal</label>
            <select class="form-select" name="sucursal" id="sucSel">
              <option value="">—</option>
            </select>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Aprobar</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const base = '<?= $base ?>';
  const approveModal = document.getElementById('approveModal');
  const userIdInput  = document.getElementById('approveUserId');
  const userNameInp  = document.getElementById('approveUserName');

  const rolSel      = document.getElementById('rolSelect');
  const wrapRegion  = document.getElementById('wrapRegion');
  const wrapCiudad  = document.getElementById('wrapCiudad');
  const wrapMuni    = document.getElementById('wrapMunicipio');
  const wrapSuc     = document.getElementById('wrapSucursal');

  const regionSel = document.getElementById('regionSel');
  const ciudadSel = document.getElementById('ciudadSel');
  const muniSel   = document.getElementById('muniSel');
  const sucSel    = document.getElementById('sucSel');

  // Al abrir modal, inyectar datos
  approveModal.addEventListener('show.bs.modal', (ev)=>{
    const btn = ev.relatedTarget;
    userIdInput.value = btn.getAttribute('data-user-id') || '';
    userNameInp.value = btn.getAttribute('data-user-name') || '';
    rolSel.value = '';
    hideAllScope();
    clearSelects();
  });

  // Lógica de visibilidad por rol
  rolSel.addEventListener('change', ()=>{
    const rol = rolSel.value;
    hideAllScope();
    if (rol === 'Distrital') {
      show(wrapRegion);
      loadRegiones();
    } else if (rol === 'Prevencion' || rol === 'Monitorista') {
      show(wrapRegion); show(wrapCiudad); show(wrapMuni); show(wrapSuc);
      loadRegiones();
    } // Otros roles: sin ámbito
  });

  // Carga cascada
  regionSel.addEventListener('change', ()=>{
    if (!regionSel.value) { resetBelow('ciudad'); return; }
    loadCiudades(regionSel.value);
  });
  ciudadSel.addEventListener('change', ()=>{
    if (!ciudadSel.value) { resetBelow('municipio'); return; }
    loadMunicipios(ciudadSel.value);
  });
  muniSel.addEventListener('change', ()=>{
    if (!muniSel.value) { resetBelow('sucursal'); return; }
    loadSucursales(muniSel.value);
  });

  function hideAllScope(){ [wrapRegion,wrapCiudad,wrapMuni,wrapSuc].forEach(hide); }
  function hide(el){ el.classList.add('d-none'); }
  function show(el){ el.classList.remove('d-none'); }

  function clearSelects(){
    regionSel.innerHTML = '<option value="">—</option>';
    ciudadSel.innerHTML = '<option value="">—</option>';
    muniSel.innerHTML   = '<option value="">—</option>';
    sucSel.innerHTML    = '<option value="">—</option>';
  }

  function resetBelow(level){
    if (level === 'ciudad'){
      ciudadSel.innerHTML = '<option value="">—</option>';
      muniSel.innerHTML   = '<option value="">—</option>';
      sucSel.innerHTML    = '<option value="">—</option>';
    } else if (level === 'municipio'){
      muniSel.innerHTML = '<option value="">—</option>';
      sucSel.innerHTML  = '<option value="">—</option>';
    } else if (level === 'sucursal'){
      sucSel.innerHTML = '<option value="">—</option>';
    }
  }

  async function loadRegiones(){
    regionSel.innerHTML = '<option value="">Cargando...</option>';
    const res = await fetch(base + '/controllers/UserController.php?accion=regiones');
    const data = await res.json();
    regionSel.innerHTML = '<option value="">—</option>';
    data.forEach(r => regionSel.innerHTML += `<option value="${r.id}">${r.nombre}</option>`);
  }
  async function loadCiudades(regionId){
    ciudadSel.innerHTML = '<option value="">Cargando...</option>';
    const res = await fetch(base + '/controllers/UserController.php?accion=ciudades&region=' + regionId);
    const data = await res.json();
    ciudadSel.innerHTML = '<option value="">—</option>';
    data.forEach(c => ciudadSel.innerHTML += `<option value="${c.id}">${c.nombre}</option>`);
    muniSel.innerHTML = '<option value="">—</option>';
    sucSel.innerHTML  = '<option value="">—</option>';
  }
  async function loadMunicipios(ciudadId){
    muniSel.innerHTML = '<option value="">Cargando...</option>';
    const res = await fetch(base + '/controllers/UserController.php?accion=municipios&ciudad=' + ciudadId);
    const data = await res.json();
    muniSel.innerHTML = '<option value="">—</option>';
    data.forEach(m => muniSel.innerHTML += `<option value="${m.id}">${m.nombre}</option>`);
    sucSel.innerHTML = '<option value="">—</option>';
  }
  async function loadSucursales(muniId){
    sucSel.innerHTML = '<option value="">Cargando...</option>';
    const res = await fetch(base + '/controllers/UserController.php?accion=sucursales&municipio=' + muniId);
    const data = await res.json();
    sucSel.innerHTML = '<option value="">—</option>';
    data.forEach(s => sucSel.innerHTML += `<option value="${s.id}">${s.nombre}</option>`);
  }
})();
</script>
</body>
</html>
