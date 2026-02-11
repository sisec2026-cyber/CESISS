<?php 
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Mantenimientos','Invitado','Técnico','Capturista','Distrital','Prevencion','Monitorista']);
include __DIR__ . '/../../includes/db.php';

$rolesStatusEdit = ['Superadmin','Administrador','Prevencion']; // solo estos pueden cambiar status

/* HELPERS */
function esCamara(?string $nomEquipo): bool {
  if (!$nomEquipo) return false;
  $s = mb_strtolower($nomEquipo, 'UTF-8');
  // Palabras comunes para cámaras: camara/cámara, ptz, bullet, dome, ip cam
  return (bool)preg_match('/\b(c[aá]mara|ptz|bullet|dome|ip\s*cam)\b/u', $s);
}
/* Fecha en español */
function fmtDateEs(?string $s, bool $largo = false): string {
  if (!$s || $s === '0000-00-00') return '';
  $ts = strtotime($s);
  if (!$ts) return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

  if (class_exists('IntlDateFormatter')) {
    $pattern = $largo ? "d 'de' MMMM 'de' y" : "dd/MMM/y"; // ej: 12/ene/2025
    $fmt = new IntlDateFormatter(
      'es_MX',
      IntlDateFormatter::NONE,
      IntlDateFormatter::NONE,
      date_default_timezone_get(),
      IntlDateFormatter::GREGORIAN,
      $pattern
    );
    $out = $fmt->format($ts);
    if (!$largo) {
      // Abrevia el mes para verse como 01/Ene/2026
      $out = preg_replace_callback('/\/([[:alpha:]]{3})\//u', function($m){
        return '/' . mb_convert_case($m[1], MB_CASE_TITLE, 'UTF-8') . '/';
      }, $out);
    }
    return $out;
  }

  $mesCorto = [1=>'ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  $mesLargo = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  $d = (int)date('d', $ts);
  $m = (int)date('n', $ts);
  $y = date('Y', $ts);

  if ($largo) {
    return sprintf('%d de %s de %s', $d, $mesLargo[$m], $y);  // 12 de enero de 2025
  }
  return sprintf('%02d/%s/%s', $d, $mesCorto[$m], $y);        // 12/ene/2025
}

function fmtDate(?string $s): string {
  return fmtDateEs($s, false); // usa corto: 12/ene/2025
}

/* “Instalación”: chip “Pendiente” si no hay fecha */
function renderInstalacion(?string $s): string {
  if (!$s || $s === '0000-00-00') {
    return '<span class="chip off" title="Fecha pendiente">Pendiente</span>';
  }
  return fmtDateEs($s, false);
}

/* Muestra — cuando el valor viene vacío */
function dashIfEmpty($v): string {
  return (isset($v) && trim((string)$v) !== '')
    ? htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')
    : '<span class="muted">—</span>';
}

/* ALCANCE POR USUARIO */
$userId = $_SESSION['usuario_id'] ?? null;
$filtroRegion = $filtroCiudad = $filtroMunicipio = $filtroSucursal = null;

if ($userId) {
  $qUser = $conn->prepare("SELECT region, ciudad, municipio, sucursal FROM usuarios WHERE id = ?");
  $qUser->bind_param("i", $userId);
  $qUser->execute();
  $userFilter = $qUser->get_result()->fetch_assoc() ?: [];
  $filtroRegion    = !empty($userFilter['region'])    ? (int)$userFilter['region']    : null;
  $filtroCiudad    = !empty($userFilter['ciudad'])    ? (int)$userFilter['ciudad']    : null;
  $filtroMunicipio = !empty($userFilter['municipio']) ? (int)$userFilter['municipio'] : null;
  $filtroSucursal  = !empty($userFilter['sucursal'])  ? (int)$userFilter['sucursal']  : null;
  $qUser->close();
}

function buildUserScopeWhere(&$types, &$params, $fRegion, $fCiudad, $fMunicipio, $fSucursal) {
  $extra = [];
  if ($fSucursal) {
    $extra[] = "d.sucursal = ?";
    $types  .= "i";
    $params[] = $fSucursal;
  } elseif ($fMunicipio) {
    $extra[] = "m.id = ?";
    $types  .= "i";
    $params[] = $fMunicipio;
  } elseif ($fCiudad) {
    $extra[] = "c.id = ?";
    $types  .= "i";
    $params[] = $fCiudad;
  } elseif ($fRegion) {
    $extra[] = "c.region_id = ?";
    $types  .= "i";
    $params[] = $fRegion;
  }
  return $extra;
}

/* QS Y BUSCADOR (para export, prellenado y compatibilidad) */
$qsCiudad    = isset($_GET['ciudad_id'])    ? (int)$_GET['ciudad_id']    : 0;
$qsMunicipio = isset($_GET['municipio_id']) ? (int)$_GET['municipio_id'] : 0;
$qsSucursal  = isset($_GET['sucursal_id'])  ? (int)$_GET['sucursal_id']  : 0;
$searchParam = isset($_GET['search']) ? trim($_GET['search']) : ''; // input de búsqueda en vivo

/* WHERE (alcance + filtros seleccionados) */
$typesScope = "";
$paramsScope = [];
$scope = buildUserScopeWhere($typesScope, $paramsScope, $filtroRegion, $filtroCiudad, $filtroMunicipio, $filtroSucursal);

$typesSel = "";  $paramsSel = [];  $sel = [];
if ($qsCiudad)    { $sel[] = "c.id = ?";        $typesSel .= "i"; $paramsSel[] = $qsCiudad; }
if ($qsMunicipio) { $sel[] = "m.id = ?";        $typesSel .= "i"; $paramsSel[] = $qsMunicipio; }
if ($qsSucursal)  { $sel[] = "d.sucursal = ?";  $typesSel .= "i"; $paramsSel[] = $qsSucursal; }

$whereParts = [];
if ($scope) $whereParts[] = implode(" AND ", $scope);
if ($sel)   $whereParts[] = implode(" AND ", $sel);
$whereBase = $whereParts ? ('WHERE ' . implode(" AND ", $whereParts)) : '';

$selectBase = "d.id,
  /* FECHAS */
  d.fecha_instalacion  AS fecha_inst,
  d.fecha               AS fecha_mto,     -- <— SI USAS d.fecha: reemplaza por  d.fecha AS fecha_mto
  /* CAMPOS COMUNES */
  d.imagen,
  d.sucursal,
  s.nom_sucursal,
  m.nom_municipio,
  c.nom_ciudad,
  det.nom_determinante,
  COALESCE(eq.nom_equipo, d.equipo) AS nom_equipo,
  mo.num_modelos,
  es.status_equipo,
  /* SOLO ALARMA */
  d.zona_alarma,
  d.tipo_sensor,
  /* SOLO CCTV */
  d.servidor,
  d.tiene_analitica,
  d.analiticas";

$joinsBase = "LEFT JOIN sucursales    s   ON d.sucursal = s.id
  LEFT JOIN determinantes det ON det.sucursal_id = s.id
  LEFT JOIN municipios    m   ON s.municipio_id = m.id
  LEFT JOIN ciudades      c   ON m.ciudad_id = c.id
  LEFT JOIN equipos       eq  ON d.equipo = eq.id
  LEFT JOIN modelos       mo  ON d.modelo = mo.id
  LEFT JOIN status        es  ON d.estado = es.id";
/* ================= ALARMAS ================= */

/* Palabras clave para reconocer ALARMAS por nombre */
$alarmaKeywords = ['SENSOR', 'MOVIMIENTO', 'PIR', 'MAGNETICO', 'MAGNÉTICO', 'CONTACTO','PUERTA', 'VENTANA','TECLADO', 'tarjeta de comunicacion',
'KEYPAD','SIRENA', 'ESTROBO','PANEL', 'CONTROL','EXPANSORA', 'MODULO', 'MÓDULO','BOTON', 'PÁNICO', 'PANICO', 'DH', 'dh', 'EXPANSOR',
'ESTACION MANUAL', 'EM','em', 'CM', 'DRC', 'RECEPTORA', 'REPETIDORA','REPETIDOR', 'ESTROBOS', 'OH', 'RELEVADOR', 'WEIGAND','FUENTE DE PODER',
'GP23', 'ELECTRO IMAN', 'ELECTROIMAMN', 'LIBERADOR', 'BATERIA', 'TRANSFORMADOR', 'TAMPER', 'RONDIN', 'IMPACTO', 'RATONERA','ratonera','Transmisor','Trasmisor', 'PIR 360'];

/* LIKE dinámicos sobre nombre de equipo */
$alarmaLikeClauses = [];
foreach ($alarmaKeywords as $kw) {
  $alarmaLikeClauses[] = "UPPER(COALESCE(eq.nom_equipo, d.equipo)) LIKE ?";
}

/* Filtro final de ALARMA */
$alarmaFilterSql = "d.alarma_id IS NOT NULL";
if ($alarmaLikeClauses) {
  $alarmaFilterSql .= " OR (
    d.cctv_id IS NULL
    AND (" . implode(" OR ", $alarmaLikeClauses) . ")
  )";
}

/* ALARMAS: dispositivos con alarma_id */
$resultAlarmas = false;

$sqlAlarmas = "SELECT $selectBase
  FROM dispositivos d
  $joinsBase
  $whereBase
  " . ($whereBase ? " AND " : " WHERE ") . "
  ( $alarmaFilterSql )
  ORDER BY COALESCE(eq.nom_equipo, d.equipo) ASC";
$typesAlarmas  = $typesScope . $typesSel . str_repeat('s', count($alarmaKeywords));
$paramsAlarmas = array_merge($paramsScope, $paramsSel);
foreach ($alarmaKeywords as $kw) {
  $paramsAlarmas[] = '%' . $kw . '%';
}

if ($scope || $qsCiudad || $qsMunicipio || $qsSucursal) {

$stmtA = $conn->prepare($sqlAlarmas);
if ($typesAlarmas) {
  $stmtA->bind_param($typesAlarmas, ...$paramsAlarmas);
}
$stmtA->execute();
$resultAlarmas = $stmtA->get_result();

}

/* CCTV: incluiye TODO lo relacionado con CCTV, coincidencias por cctv_id o bien, por nombre del equipo (DVR/NVR/Servidor/etc) cuando alarma_id es NULL */
$resultCCTV = false;

/* Palabras clave para reconocer equipos de CCTV por nombre */
$cctvKeywords = ['CAM', 'CÁMARA', 'CAMARA', 'PTZ', 'BULLET', 'DOME','NVR', 'DVR', 'SERVIDOR', 'SERVER', 'GRABADOR','STORAGE', 'ENCODER', 'DECODIFICADOR', 'DECODER','SWITCH POE', 'POE', 'MONITOR', 'HDD', 'DISCO', 'UPS', 'ESTACIÓN DE TRABAJO', 'estación de trabajo', 'GABINETE', 'gabinete','VIDEO PORTERO', 'VIDEOPORTERO', 'video portero', 'telefono', 'SWITCH', 'MOUSE', 'WORKSTATION','AXTV', 'AX-TV', 'BALLOON', 'BALLOONS', 'EXTENSORES', 'RACK', 'RC', 'FUENTE', 'PLUG', 'JACK', 'RJ45', 'TRANSCEPTOR', 'Visual Tools', 'videoportero', 'VIDEO PORTERO', 'VIDEOPORTERO', 'JOYSTICK', 'LICENCIAS', 'BIOMETRICO', 'CONTROL DE ACCESO', 'VMS'];

/* Build OR de LIKEs sobre eq.nom_equipo */
$cctvLikeClauses = [];
foreach ($cctvKeywords as $kw) {
  $cctvLikeClauses[] = "UPPER(COALESCE(eq.nom_equipo, d.equipo)) LIKE ?";
}

/* Filtro ampliado para CCTV */
$cctvFilterSql = "d.cctv_id IS NOT NULL";
if ($cctvLikeClauses) {
  $cctvFilterSql .= " OR (d.alarma_id IS NULL AND (" . implode(" OR ", $cctvLikeClauses) . "))";
}

/* Query final CCTV */
$sqlCCTV = "SELECT $selectBase
  FROM dispositivos d
  $joinsBase
  $whereBase
  " . ($whereBase ? " AND " : " WHERE ") . " ( $cctvFilterSql )
  ORDER BY
  /* 1️⃣ NVR y DVR primero */
  CASE
    WHEN UPPER(COALESCE(eq.nom_equipo, d.equipo)) REGEXP '\\b(NVR|DVR)\\b' THEN 0
    ELSE 1
  END,

  /* 2️⃣ Número real del equipo */
  CAST(
    REGEXP_SUBSTR(
      UPPER(COALESCE(eq.nom_equipo, d.equipo)),
      '[0-9]+'
    ) AS UNSIGNED
  ),

  /* 3️⃣ Respaldo por texto */
  COALESCE(eq.nom_equipo, d.equipo)";

/* Tipos y parámetros (alcance + filtros + patrones LIKE) */
$typesCCTV  = $typesScope . $typesSel . str_repeat('s', count($cctvKeywords));
$paramsCCTV = array_merge($paramsScope, $paramsSel);
foreach ($cctvKeywords as $kw) { $paramsCCTV[] = '%' . $kw . '%'; }

/* Ejecutar */
if ($scope || $qsCiudad || $qsMunicipio || $qsSucursal) {
  $stmtC = $conn->prepare($sqlCCTV);
  if ($typesCCTV) $stmtC->bind_param($typesCCTV, ...$paramsCCTV);
  $stmtC->execute();
  $resultCCTV = $stmtC->get_result();
}

/* DETERMINANTE + MARCAS (si hay sucursal en QS) */
$determinanteTxt = '';
$marcasTexto = '';
if ($qsSucursal) {
  $qd = $conn->prepare("SELECT nom_determinante FROM determinantes WHERE sucursal_id = ? LIMIT 1");
  $qd->bind_param('i', $qsSucursal);
  $qd->execute();
  $determinanteTxt = ($qd->get_result()->fetch_assoc()['nom_determinante'] ?? '');
  $qd->close();

  $qb = $conn->prepare("SELECT GROUP_CONCAT(DISTINCT ma.nom_marca ORDER BY ma.nom_marca SEPARATOR ', ') AS marcas
    FROM dispositivos d
    JOIN marcas ma ON d.marca_id = ma.id_marcas
    WHERE d.sucursal = ?");
  $qb->bind_param('i', $qsSucursal);
  $qb->execute();
  $marcasTexto = ($qb->get_result()->fetch_assoc()['marcas'] ?? '');
  $qb->close();
}

ob_start();
?>

<!-- ======================== ESTILOS ======================== -->
<style>
  :root{
    --ink:#0f172a; --sub:#475569; --card:#ffffff; --bg:#f6f7fb;
    --brand:#3C92A6; --soft:#e8f4f7; --line:#e6eaf0;
    --warn:#fff7e6; --warn-h:#ffecc7; --info:#e9f1ff; --info-h:#dce7ff;
    --ok:#e8fff1; --ok-b:#c8f2da; --muted:#98a0aa;
  }
  body{ background:var(--bg); color:var(--ink); }
  .page-wrap{ padding:12px 20px; }
  .card-lite{ background:var(--card); border:1px solid var(--line); border-radius:16px; box-shadow:0 4px 18px rgba(12,21,31,.04); padding:16px; }
  .sec-title{ display:flex; align-items:center; gap:.6rem; font-weight:700; margin:.25rem 0 .8rem; }
  .sec-title i{ color:var(--brand); }
  .controls .form-select, .controls .form-control{ height:44px; border-radius:12px; border:1px solid var(--line); }
  #ciudad,#municipio,#sucursal{ max-width:260px; }
  @media (max-width: 992px){ #ciudad,#municipio,#sucursal{ max-width:100%; } }
  .pill-det{ display:inline-block; font-size:.75rem; padding:.1rem .45rem; border-radius:9999px; background:var(--soft); color:var(--brand); border:1px solid #cde7ed; margin-left:.35rem; }
  .mini-badge{ font-size:.75rem; margin-left:.35rem; }
  .muted{ color:var(--muted); }
  .toolbar{ display:flex; gap:10px; align-items:center; justify-content:space-between; margin-bottom:12px; }
  .toolbar .search-inline{ display:none; gap:10px; }
  .table{ margin-bottom:0; }
  thead th{ font-weight:700; border-bottom:1px solid var(--line); }
  .table-warning thead th{ background:var(--warn); }
  .table-warning tbody tr:hover{ background:var(--warn-h); }
  .table-primary thead th{ background:var(--info); }
  .table-primary tbody tr:hover{ background:var(--info-h); }
  .table td, .table th { white-space: nowrap; }
  .table td img { max-width: 100%; height: auto; }
  @media (max-width: 768px){.btn, .form-control { font-size: .9rem; padding: 6px 10px; }.table td, .table th { font-size: .85rem; white-space: normal; }}
  /* Paginación */
  .pager{ display:flex; align-items:center; justify-content:space-between; padding-top:8px; gap:10px; }
  .pager .info{ font-size:.9rem; color:var(--sub); }
  .pager .controls{ display:flex; gap:6px; align-items:center; }
  .pager .btn{ border-radius:10px; }
  .pager .pagesize{ height:36px; border-radius:10px; border:1px solid var(--line); padding:0 8px; }
  .empty-row td{ color:#98a0aa; }
  /* Analíticas: chips bonitos */
  .chips{ display:flex; gap:6px; flex-wrap:wrap; justify-content:center; }
  .chip{ display:inline-flex; align-items:center; gap:6px; padding:.2rem .55rem; border-radius:999px; border:1px solid var(--line); background:#fff; font-size:.78rem; }
  .chip i{ opacity:.8; }
  .chip.ok{ background:var(--ok); border-color:var(--ok-b); }
  .chip.off{ background:#f5f5f5; border-color:#e5e7eb; color:#6b7280; }
  /* ---------- Título ---------- */
  h2{font-weight:800; letter-spacing:.2px; color:var(--ink);margin-bottom:.75rem!important;}
  h2::after{content:""; display:block; width:78px; height:4px; border-radius:99px;margin-top:.5rem; background:linear-gradient(90deg,var(--brand),var(--brand-2));}
  /* Botones de tabla */
  .btn-ver, .btn-editar, .btn-eliminar {border-radius: 8px;transition: all 0.2s ease-in-out;}
  .btn-ver:hover {background-color: #0dcaf0;color: #fff;box-shadow: 0 0 10px rgba(13, 202, 240, 0.5);}
  .btn-editar:hover {background-color: #ffc107;color: #212529;box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);}
  .btn-eliminar:hover {background-color: #dc3545;color: #fff;box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);}
  /* Botones superiores */
  .btn-exportar {background-color: #198754;color: white;border: none;border-radius: 999px;padding: 8px 16px;font-weight: 800;transition: all 0.2s;}
  .btn-exportar:hover {background-color: #157347;box-shadow: 0 0 12px rgba(25, 135, 84, 0.5);}
  .btn-registrar {background-color: #0d6efd;color: white;border-radius: 999px;padding: 8px 16px;font-weight: 800;transition: all 0.2s;}
  .btn-registrar:hover {background-color: #0b5ed7;box-shadow: 0 0 12px rgba(13, 110, 253, 0.5);}
  /* ===== MODAL PROFESIONAL CESISS ===== */
  #statusTicketModal .modal-content {border-radius: 12px;box-shadow: 0 8px 24px rgba(0,0,0,0.15);border: none;overflow: hidden;font-family: "Segoe UI", Roboto, Arial, sans-serif;}
  /* Header */
  #statusTicketModal .modal-header {background: linear-gradient(135deg, #3C92A6, #24a3c1);color: #ffffff;border-bottom: none;padding: 20px 24px;}
  #statusTicketModal .modal-header .modal-title {font-size: 1.25rem;font-weight: 600;}
  /* Close button */
  #statusTicketModal .btn-close {border-radius: 50%;width: 28px;height: 28px;opacity: 1;transition: all 0.2s ease;}
  /* Body */
  #statusTicketModal .modal-body {padding: 24px;background: #f8fafc;}
  #statusTicketModal .form-label {font-weight: 500;color: #333333;margin-bottom: 6px;}
  #statusTicketModal .form-select,#statusTicketModal .form-control {border-radius: 8px;border: 1px solid #d1d5db;padding: 10px 14px;font-size: 0.95rem;transition: border-color 0.2s ease, box-shadow 0.2s ease;}
  #statusTicketModal .form-select:focus,
  #statusTicketModal .form-control:focus {border-color: #3C92A6;box-shadow: 0 0 0 3px rgba(60,146,166,0.2);outline: none;}
  /* Textarea placeholder */
  #statusTicketModal textarea::placeholder {color: #9ca3af;font-style: italic;}
  /* Footer */
  #statusTicketModal .modal-footer {padding: 16px 24px;background: #f1f5f9;border-top: none;justify-content: flex-end;}
  #statusTicketModal .btn-primary {background: linear-gradient(135deg, #3C92A6, #24a3c1);border: none;border-radius: 8px;padding: 10px 22px;font-weight: 600;font-size: 0.95rem;transition: all 0.25s ease;}
  #statusTicketModal .btn-primary:hover {filter: brightness(1.1);transform: translateY(-1px);box-shadow: 0 4px 12px rgba(0,0,0,0.15);}
  #statusTicketModal .btn-secondary {border-radius: 8px;padding: 10px 22px;font-weight: 500;background: #e5e7eb;color: #374151;border: none;transition: all 0.2s ease;}
  #statusTicketModal .btn-secondary:hover {background: #d1d5db;}
  /* Responsive */
  @media(max-width:576px){ #statusTicketModal .modal-body {padding: 18px;} #statusTicketModal .modal-header, #statusTicketModal .modal-footer {padding: 14px 18px;}}
</style>

<div style="padding-left: 25px;">
  <h2 style="margin:4px 0 16px; font-weight:800;">Listado de dispositivos</h2>
  <div class="toolbar">
    <form id="formBusqueda" class="search-inline" autocomplete="off">
      <input type="text" id="search" name="search" class="form-control" style="width:300px" placeholder="Búsqueda (equipo, modelo, zona, analítica)" value="<?= htmlspecialchars($searchParam, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </form>
    <button id="btnExportarPDF" class="btn btn-danger shadow-sm" style="display:none;">
      <i class="fas fa-file-pdf me-2"></i> Exportar Carpeta
    </button>
    <div class="d-flex flex-wrap gap-2">
      <button id="btnExportar" class="btn btn-success shadow-sm btn-exportar" style="display:none;">
        <i class="fas fa-file-excel me-2"></i> Exportar
      </button>
      <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Capturista','Técnico'])): ?>
        <a href="registro.php" class="btn btn-primary shadow-sm btn-registrar">
          <i class="fas fa-plus me-2"></i> Registrar nuevo dispositivo
        </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-lite controls mb-3">
    <div class="row g-3 align-items-end">
      <div class="col-lg-4">
        <label class="form-label">Ciudad</label>
        <select id="ciudad" class="form-select" <?= $filtroCiudad ? 'disabled' : '' ?>>
          <option value="">-- Selecciona una ciudad --</option>
          <?php
            $qCiudades = "SELECT id, nom_ciudad FROM ciudades";
            $w = [];
            if ($filtroRegion) { $w[] = "region_id = " . (int)$filtroRegion; }
            if ($filtroCiudad) { $w[] = "id = " . (int)$filtroCiudad; }
            if ($w) $qCiudades .= " WHERE " . implode(" AND ", $w);
            $qCiudades .= " ORDER BY nom_ciudad";
            $ciudades = $conn->query($qCiudades);
            while ($row = $ciudades->fetch_assoc()):
          ?>
            <option value="<?= $row['id'] ?>" <?= ($filtroCiudad == $row['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($row['nom_ciudad'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-lg-4">
        <label class="form-label">Municipio</label>
        <select id="municipio" class="form-select" <?= $filtroMunicipio ? 'disabled' : '' ?>>
          <option value="">-- Selecciona un municipio --</option>
        </select>
      </div>
      <div class="col-lg-4">
        <label class="form-label">Sucursal 
          <span id="badgeDeterminante" class="badge bg-secondary mini-badge" style="display:<?= $determinanteTxt ? 'inline-block':'none' ?>;">
            <?= $determinanteTxt ? 'Determinante: ' . htmlspecialchars($determinanteTxt, ENT_QUOTES, 'UTF-8') : '' ?>
          </span>
        </label>
        <select id="sucursal" class="form-select" <?= $filtroSucursal ? 'disabled' : '' ?>>
          <option value="">-- Selecciona una sucursal --</option>
        </select>
      </div>
      <div>
        <small id="marcasResumen" class="muted d-block mt-1" style="display:<?= $marcasTexto ? 'block' : 'none' ?>;">
          <?= $marcasTexto ? ('Marcas en esta tienda: ' . htmlspecialchars($marcasTexto, ENT_QUOTES, 'UTF-8')) : '' ?>
        </small>
      </div>
    </div>
  </div>

  <!-- =================== ALARMAS =================== -->
  <h5 class="sec-title"><i class="fa-solid fa-bell"></i> Dispositivos de Alarma</h5>
  <div class="table-responsive mb-2 card-lite">
    <table class="table table-hover table-bordered text-center align-middle table-warning">
      <thead>
        <tr>
          <th>Equipo</th>
          <th>Instalación</th>
          <th>Mantenimiento</th>
          <th>Modelo</th>
          <th>Status</th>
          <th>Determinante</th>
          <th>Zona</th>
          <th>Tipo sensor</th>
          <th>Imagen</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody-alarma">
        <?php if ($resultAlarmas && $resultAlarmas->num_rows > 0): ?>
          <?php while ($r = $resultAlarmas->fetch_assoc()): ?>
            <tr>
              <td><?= dashIfEmpty($r['nom_equipo'] ?? null) ?></td>
              <td><?= renderInstalacion($r['fecha_inst'] ?? null) ?></td>
              <td><?= (!empty($r['fecha_mto']) && $r['fecha_mto'] !== '0000-00-00') ? fmtDate($r['fecha_mto']) : '<span class="muted">—</span>' ?></td>
              <td><?= dashIfEmpty($r['num_modelos'] ?? null) ?></td>
              <!-- Status -->
              <td>
                <?php if (in_array($_SESSION['usuario_rol'], $rolesStatusEdit)): ?>
                  <span class="status-label" 
                        data-id="<?= (int)$r['id'] ?>" 
                        data-status="<?= (int)($r['estado'] ?? 1) ?>" 
                        style="cursor:pointer; color:var(--brand); font-weight:600;">
                    <?= htmlspecialchars($r['status_equipo'] ?? 'Activo', ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php else: ?>
                  <span style="color:#555; font-weight:600;"><?= htmlspecialchars($r['status_equipo'] ?? 'Activo', ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['nom_determinante'])): ?>
                    <span class="pill-det">Det. <?= htmlspecialchars($r['nom_determinante'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span data-bs-toggle="tooltip" title="<?= htmlspecialchars(($r['nom_municipio'] ?? '') . ', ' . ($r['nom_ciudad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                      <i class="fas fa-info-circle text-muted"></i>
                    </span>
                  <?php endif; ?>
              </td>
              <td><?= dashIfEmpty($r['zona_alarma'] ?? null) ?></td>
              <td><?= dashIfEmpty($r['tipo_sensor'] ?? null) ?></td>
              <td>
                <?php if (!empty($r['imagen'])): ?>
                  <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($r['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagen" style="max-height:50px; object-fit:contain;">
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="device.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-info btn-ver" title="Ver dispositivo">
                  <i class="fas fa-eye"></i>
                </a>

                <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Capturista','Técnico'])): ?>
                  <a href="editar.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-warning btn-editar" title="Editar dispositivo">
                    <i class="fa-regular fa-pen-to-square"></i>
                  </a>
                  <button class="btn btn-sm btn-outline-danger btn-eliminar" 
                          data-bs-toggle="modal" 
                          data-bs-target="#confirmDeleteModal" 
                          data-id="<?= (int)$r['id'] ?>"
                          title="Eliminar dispositivo">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                <?php endif; ?>
              </td>

            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr class="empty-row" data-empty="1"><td colspan="10" class="text-center text-muted">Selecciona filtros para ver dispositivos de alarma</td></tr>
        <?php endif; ?>
        <!-- Modal cambio de Status y Ticket -->
          <div class="modal fade" id="statusTicketModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <form id="formStatusTicket">
                  <div class="modal-header">
                    <h5 class="modal-title">Actualizar status y enviar correo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal">X</button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="dispositivo_id" id="dispositivo_id">
                    <input type="hidden" name="status_actual" id="status_actual">
                    <div class="mb-3">
                      <label class="form-label">Nuevo Status</label>
                      <select name="status_nuevo" id="status_nuevo" class="form-select" required>
                        <?php
                          $qStatus = $conn->query("SELECT id, status_equipo FROM status ORDER BY status_equipo");
                          while ($st = $qStatus->fetch_assoc()):
                        ?>
                          <option value="<?= (int)$st['id'] ?>"><?= htmlspecialchars($st['status_equipo'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endwhile; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Descripción / Motivo del cambio de status</label>
                      <textarea name="descripcion_ticket" class="form-control" rows="3" placeholder="Escribe aquí el motivo..." required></textarea>
                      <div class="mb-3">
                        <label class="form-label">Tomar foto</label>
                        <div id="camera-container" style="text-align:center;">
                          <video id="video" width="100%" autoplay style="border-radius:8px; border:1px solid #ccc;"></video>
                          <canvas id="canvas" style="display:none;"></canvas>
                          <button type="button" id="capture" class="btn btn-sm btn-outline-primary mt-2">Capturar</button>
                        </div>
                        <input type="hidden" name="foto_base64" id="foto_base64">
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar y enviar correo</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
      </tbody>
    </table>
    <div id="pager-alarma" class="pager">
      <div class="info"></div>
      <div class="controls">
        <label class="muted me-1">Mostrar</label>
        <select class="pagesize" data-for="alarma">
          <option>10</option><option>25</option><option>50</option><option>100</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" data-act="prev" data-for="alarma">Anterior</button>
        <button class="btn btn-outline-secondary btn-sm" data-act="next" data-for="alarma">Siguiente</button>
      </div>
    </div>
  </div>

  <!-- =================== CCTV =================== -->
  <h5 class="sec-title mt-4"><i class="fa-solid fa-video"></i>Dispositivos de CCTV</h5>
  <div class="table-responsive mb-2 card-lite">
    <table class="table table-hover table-bordered text-center align-middle table-primary">
      <thead>
        <tr>
          <th>Equipo</th>
          <th>Instalación</th>
          <th>Mantenimiento</th>
          <th>Modelo</th>
          <th>Status</th>
          <th>Determinante</th>
          <th>Servidor/NVR</th>
          <th>Analítica <small class="muted">(solo cámaras)</small></th>
          <th>Imagen</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody-cctv">
        <?php if ($resultCCTV && $resultCCTV->num_rows > 0): ?>
          <?php while ($r = $resultCCTV->fetch_assoc()): ?>
            <tr>
              <td><?= dashIfEmpty($r['nom_equipo'] ?? null) ?></td>
              <td><?= renderInstalacion($r['fecha_inst'] ?? null) ?></td>
              <td><?= (!empty($r['fecha_mto']) && $r['fecha_mto'] !== '0000-00-00') ? fmtDate($r['fecha_mto']) : '<span class="muted">—</span>' ?></td>
              <td><?= dashIfEmpty($r['num_modelos'] ?? null) ?></td>
              <!-- Status -->
              <td>
                <?php if (in_array($_SESSION['usuario_rol'], $rolesStatusEdit)): ?>
                  <span class="status-label" data-id="<?= (int)$r['id'] ?>" data-status="<?= (int)($r['estado'] ?? 1) ?>" style="cursor:pointer; color:var(--brand); font-weight:600;">
                    <?= htmlspecialchars($r['status_equipo'] ?? 'Activo', ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php else: ?>
                  <span style="color:#555; font-weight:600;"><?= htmlspecialchars($r['status_equipo'] ?? 'Activo', ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['nom_determinante'])): ?>
                  <span class="pill-det">Det. <?= htmlspecialchars($r['nom_determinante'], ENT_QUOTES, 'UTF-8') ?></span>
                  <span data-bs-toggle="tooltip" title="<?= htmlspecialchars(($r['nom_municipio'] ?? '') . ', ' . ($r['nom_ciudad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fas fa-info-circle text-muted"></i>
                  </span>
                <?php endif; ?>
              </td>
              <td><?= dashIfEmpty($r['servidor'] ?? null) ?></td>
              <td>
                <?php if (esCamara($r['nom_equipo'] ?? '')): ?>
                  <?php
                    $tiene = isset($r['tiene_analitica']) ? (int)$r['tiene_analitica'] : 0;
                    $alist = trim((string)($r['analiticas'] ?? ''));
                    if ($tiene && $alist !== ''):
                      $chips = array_filter(array_map('trim', explode(',', $alist)));
                  ?>
                  <div class="chips">
                    <?php foreach ($chips as $chip): ?>
                      <span class="chip ok" title="Analítica activa">
                        <i class="fa-solid fa-bolt"></i><?= htmlspecialchars($chip, ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                  <?php else: ?>
                    <span class="chip off" title="Sin analítica configurada">
                      <i class="fa-regular fa-circle"></i>Sin analítica (configurable)
                    </span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['imagen'])): ?>
                  <img src="/sisec-ui/public/uploads/<?= htmlspecialchars($r['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagen" style="max-height:50px; object-fit:contain;">
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="device.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-info btn-ver" title="Ver dispositivo">
                  <i class="fas fa-eye"></i>
                </a>
                <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Mantenimientos','Capturista','Técnico'])): ?>
                  <a href="editar.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-warning btn-editar" title="Editar dispositivo">
                    <i class="fa-regular fa-pen-to-square"></i>
                  </a>
                  <button class="btn btn-sm btn-outline-danger btn-eliminar" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?= (int)$r['id'] ?>" title="Eliminar dispositivo"><i class="fas fa-trash-alt"></i></button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row" data-empty="1"><td colspan="10" class="text-center text-muted">Selecciona filtros para ver dispositivos de CCTV</td></tr>
          <?php endif; ?>
          <!-- Modal cambio de Status y Ticket -->
          <div class="modal fade" id="statusTicketModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <form id="formStatusTicket">
                  <div class="modal-header">
                    <h5 class="modal-title">Actualizar status y enviar correo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal">X</button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="dispositivo_id" id="dispositivo_id">
                    <input type="hidden" name="status_actual" id="status_actual">
                    <div class="mb-3">
                      <label class="form-label">Nuevo Status</label>
                      <select name="status_nuevo" id="status_nuevo" class="form-select" required>
                        <?php
                          $qStatus = $conn->query("SELECT id, status_equipo FROM status ORDER BY status_equipo");
                          while ($st = $qStatus->fetch_assoc()):
                        ?>
                          <option value="<?= (int)$st['id'] ?>"><?= htmlspecialchars($st['status_equipo'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endwhile; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Descripción / Motivo del cambio de status</label>
                      <textarea name="descripcion_ticket" class="form-control" rows="3" placeholder="Escribe aquí el motivo..." required></textarea>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar y enviar correo</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
      </tbody>
    </table>
    <div id="pager-cctv" class="pager">
      <div class="info"></div>
      <div class="controls">
        <label class="muted me-1">Mostrar</label>
        <select class="pagesize" data-for="cctv">
          <option>10</option><option>25</option><option>50</option><option>100</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" data-act="prev" data-for="cctv">Anterior</button>
        <button class="btn btn-outline-secondary btn-sm" data-act="next" data-for="cctv">Siguiente</button>
      </div>
    </div>
  </div>

  <?php if (in_array($_SESSION['usuario_rol'], ['Superadmin','Administrador','Mantenimientos','Invitado','Técnico','Capturista','Distrital','Prevencion','Monitorista'])): ?>
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar eliminación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">¿Estás segura(o) de que deseas eliminar este dispositivo?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" id="deleteLink" class="btn btn-danger">Eliminar</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <script> //Script para el modal de ticket cambio status
    document.querySelectorAll('.status-label').forEach(el => {
      el.addEventListener('click', () => {
        const id = el.dataset.id;
        const status = el.dataset.status;

        document.getElementById('dispositivo_id').value = id;
        document.getElementById('status_actual').value = status;
        document.getElementById('status_nuevo').value = status; // preselecciona el actual

        new bootstrap.Modal(document.getElementById('statusTicketModal')).show();
      });
    });

    // Enviar form AJAX
    document.getElementById('formStatusTicket').addEventListener('submit', function(e){
      e.preventDefault();
      const formData = new FormData(this);

      fetch('guardar_status_ticket.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(res => {
        if(res.ok){
          alert('Status actualizado y ticket generado');
          location.reload(); // recarga para mostrar status actualizado
        } else {
          alert('Error: ' + res.error);
        }
      })
      .catch(err => {
        console.error(err);
        alert('Error al guardar status y ticket');
      });
    });
  </script>
</div>

<!-- ======================== JS ======================== -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const video = document.getElementById("video");
  const canvas = document.getElementById("canvas");
  const captureBtn = document.getElementById("capture");
  const fotoInput = document.getElementById("foto_base64");

  // Inicia la cámara cuando se abre el modal
  const modal = document.getElementById('statusTicketModal');
  modal.addEventListener('shown.bs.modal', async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: true });
      video.srcObject = stream;
    } catch (err) {
      alert("No se pudo acceder a la cámara: " + err.message);
    }
  });

  // Captura la imagen
  captureBtn.addEventListener("click", () => {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext("2d").drawImage(video, 0, 0);
    const dataURL = canvas.toDataURL("image/jpeg");
    fotoInput.value = dataURL;
    alert("Foto capturada correctamente.");
  });

  // Detiene la cámara al cerrar el modal
  modal.addEventListener('hidden.bs.modal', () => {
    const stream = video.srcObject;
    if (stream) stream.getTracks().forEach(track => track.stop());
  });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const formBusqueda    = document.getElementById('formBusqueda');
  const searchInput     = document.getElementById('search');
  const btnExportar     = document.getElementById('btnExportar');

  const ciudadSelect    = document.getElementById('ciudad');
  const municipioSelect = document.getElementById('municipio');
  const sucursalSelect  = document.getElementById('sucursal');

  const badgeDet        = document.getElementById('badgeDeterminante');
  const marcasResumen   = document.getElementById('marcasResumen');

  function initTooltips(scope=document) {
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    const list = [].slice.call(scope.querySelectorAll('[data-bs-toggle="tooltip"]'));
    list.forEach(el => new bootstrap.Tooltip(el));
  }

  function buildParams() {
    const p = new URLSearchParams();
    if (ciudadSelect.value)    p.set('ciudad_id', ciudadSelect.value);
    if (municipioSelect.value) p.set('municipio_id', municipioSelect.value);
    if (sucursalSelect.value)  p.set('sucursal_id', sucursalSelect.value);
    if (searchInput && searchInput.value.trim()) p.set('search', searchInput.value.trim());
    return p;
  }

  function toggleUIBySelection() {
    const ready = ciudadSelect.value && municipioSelect.value && sucursalSelect.value;
    document.querySelector('.search-inline').style.display = ready ? 'flex' : 'none';
    btnExportar.style.display  = ready ? 'inline-block' : 'none';
    btnExportarPDF.style.display = ready ? 'inline-block' : 'none';
    if (searchInput) {
      searchInput.disabled = !ready;
      const btn = document.querySelector('#formBusqueda button'); if (btn) btn.disabled = !ready;
      if (!ready) searchInput.value = '';
    }
  }

  // Exportar PDF (incluye búsqueda actual)
  btnExportarPDF.addEventListener('click', function() {
    const params = buildParams();
    window.open(`exportar_carpeta.php?${params.toString()}`, '_blank');
  });

  function getCurrentListUrl() {
    const base = window.location.pathname;
    const qs = buildParams().toString();
    return qs ? `${base}?${qs}` : base;
  }

  // Helpers MAY/MIN para JSON
  const pick = (o, keys) => keys.reduce((v,k)=> v ?? o?.[k], undefined);
  const asID = (o) => pick(o, ['id','ID','Id']);
  const asNomMunicipio = (o) => pick(o, ['nom_municipio','nomMunicipio','municipio']);
  const asNomSucursal  = (o) => pick(o, ['nom_sucursal','nomSucursal','sucursal']);

  function loadMunicipios(ciudadId) {
    municipioSelect.innerHTML = '<option value="">-- Selecciona un municipio --</option>';
    sucursalSelect.innerHTML  = '<option value="">-- Selecciona una sucursal --</option>';
    sucursalSelect.disabled   = true;
    badgeDet.style.display = 'none'; if (marcasResumen){marcasResumen.style.display='none';}

    if (!ciudadId) { municipioSelect.disabled = true; return Promise.resolve(); }
    municipioSelect.disabled = false;

    return fetch(`obtener_municipios.php?ciudad_id=${encodeURIComponent(ciudadId)}`)
      .then(r => r.json())
      .then(data => {
        data.forEach(raw => {
          const id  = asID(raw);
          const nom = asNomMunicipio(raw);
          if (!id) return;
          municipioSelect.innerHTML += `<option value="${id}">${nom ?? id}</option>`;
        });
        <?php if ($filtroMunicipio): ?>
          municipioSelect.value = String(<?= (int)$filtroMunicipio ?>);
          municipioSelect.disabled = true;
        <?php endif; ?>
      });
  }

  function loadSucursales(municipioId) {
    sucursalSelect.innerHTML = '<option value="">-- Selecciona una sucursal --</option>';
    if (!municipioId) {
      sucursalSelect.disabled = true;
      badgeDet.style.display = 'none'; if (marcasResumen){marcasResumen.style.display='none';}
      return Promise.resolve();
    }
    sucursalSelect.disabled = false;

    return fetch(`obtener_sucursales.php?municipio_id=${encodeURIComponent(municipioId)}`)
      .then(r => r.json())
      .then(data => {
        data.forEach(raw => {
          const id  = asID(raw);
          const nom = asNomSucursal(raw);
          if (!id) return;
          sucursalSelect.innerHTML += `<option value="${id}">${nom ?? id}</option>`;
        });
        <?php if ($filtroSucursal): ?>
          sucursalSelect.value = String(<?= (int)$filtroSucursal ?>);
          sucursalSelect.disabled = true;
        <?php endif; ?>
      });
  }

  function navigateWithFilters(){ window.location.href = getCurrentListUrl(); }

  // --------- Paginación + búsqueda en vivo ----------
  function norm(s){ return (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(); }

  class TablePager {
    constructor(tbody, pagerEl, initialPageSize=10){
      this.tbody   = tbody;
      this.pagerEl = pagerEl;
      this.infoEl  = pagerEl.querySelector('.info');
      this.sizeSel = pagerEl.querySelector('.pagesize');
      this.btnPrev = pagerEl.querySelector('[data-act="prev"]');
      this.btnNext = pagerEl.querySelector('[data-act="next"]');
      this.pageSize = parseInt(this.sizeSel?.value || initialPageSize, 10);
      this.page = 1;
      this.rowsAll = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.dataset.empty);
      this.emptyRow = tbody.querySelector('.empty-row') || null;
      this.query = '';
      this.rowsFiltered = this.rowsAll.slice();

      // Cache texto por fila
      this.rowsAll.forEach(r => { r.dataset.text = norm(r.innerText); });

      // Eventos
      if (this.sizeSel) this.sizeSel.addEventListener('change', () => {
        this.pageSize = parseInt(this.sizeSel.value, 10); this.page = 1; this.render();
      });
      if (this.btnPrev) this.btnPrev.addEventListener('click', () => { if (this.page > 1){ this.page--; this.render(); }});
      if (this.btnNext) this.btnNext.addEventListener('click', () => { if (this.page < this.totalPages()){ this.page++; this.render(); }});

      this.render(); // inicio
    }
    totalPages(){ return Math.max(1, Math.ceil(this.rowsFiltered.length / this.pageSize)); }
    setQuery(q){
      this.query = q;
      if (!q){ this.rowsFiltered = this.rowsAll.slice(); }
      else { this.rowsFiltered = this.rowsAll.filter(r => r.dataset.text.includes(q)); }
      this.page = 1;
      this.render();
    }
    render(){
      // Ocultar todas
      this.rowsAll.forEach(r => r.style.display='none');

      // Mostrar rango actual
      const total = this.rowsFiltered.length;
      if (total === 0){
        if (this.emptyRow){ this.emptyRow.style.display='table-row'; this.emptyRow.querySelector('td').innerText = 'Sin resultados'; }
        this.infoEl.textContent = '0 de 0';
        this.btnPrev?.setAttribute('disabled','');
        this.btnNext?.setAttribute('disabled','');
        return;
      } else if (this.emptyRow){ this.emptyRow.style.display='none'; }

      const pgs = this.totalPages();
      if (this.page > pgs) this.page = pgs;
      const start = (this.page - 1) * this.pageSize;
      const end   = Math.min(start + this.pageSize, total);
      for (let i=start; i<end; i++){ this.rowsFiltered[i].style.display = ''; }

      // Info
      this.infoEl.textContent = `Mostrando ${start+1}–${end} de ${total}`;
      // Botones
      if (this.page <= 1) this.btnPrev?.setAttribute('disabled',''); else this.btnPrev?.removeAttribute('disabled');
      if (this.page >= pgs) this.btnNext?.setAttribute('disabled',''); else this.btnNext?.removeAttribute('disabled');
    }
  }

  // Instanciar paginadores
  const pagerA = new TablePager(document.getElementById('tbody-alarma'), document.getElementById('pager-alarma'), 10);
  const pagerC = new TablePager(document.getElementById('tbody-cctv'),   document.getElementById('pager-cctv'),   10);

  // Búsqueda en tiempo real (incluye texto de chips de analítica)
  function applyLiveSearch(){
    const q = norm(searchInput?.value.trim() || '');
    pagerA.setQuery(q);
    pagerC.setQuery(q);
  }

  // INIT URL (soporta caso “solo sucursal”)
  (function initFromURL() {
    const url = new URLSearchParams(window.location.search);
    let targetCiudad    = url.get('ciudad_id')    || (<?= $filtroCiudad ? 'String('.(int)$filtroCiudad.')' : '""' ?>);
    let targetMunicipio = url.get('municipio_id') || (<?= $filtroMunicipio ? 'String('.(int)$filtroMunicipio.')' : '""' ?>);
    let targetSucursal  = url.get('sucursal_id')  || (<?= $filtroSucursal ? 'String('.(int)$filtroSucursal.')' : '""' ?>);
    const soloSucursal  = !!targetSucursal && !targetCiudad && !targetMunicipio;

    if (targetCiudad) ciudadSelect.value = String(targetCiudad);

    const chain = Promise.resolve()
      .then(() => {
        if (soloSucursal) {
          return fetch(`obtener_ruta_sucursal.php?sucursal_id=${encodeURIComponent(targetSucursal)}`)
            .then(r => r.json())
            .then(info => {
              if (info && !info.error) {
                targetCiudad    = String(info.ciudad_id ?? info.ciudad ?? '');
                targetMunicipio = String(info.municipio_id ?? info.municipio ?? '');
                if (targetCiudad) ciudadSelect.value = targetCiudad;
              }
            }).catch(()=>{});
        }
      })
      .then(() => targetCiudad ? loadMunicipios(targetCiudad) : null)
      .then(() => {
        if (targetMunicipio) {
          municipioSelect.value = String(targetMunicipio);
          return loadSucursales(targetMunicipio);
        }
      })
      .then(() => {
        if (targetSucursal) { sucursalSelect.value = String(targetSucursal); }
      })
      .then(() => { toggleUIBySelection(); initTooltips(document); applyLiveSearch(); });
  })();

  // Eventos selects
  ciudadSelect.addEventListener('change', () => { loadMunicipios(ciudadSelect.value).then(() => { toggleUIBySelection(); }); });
  municipioSelect.addEventListener('change', () => { loadSucursales(municipioSelect.value).then(() => { toggleUIBySelection(); }); });
  sucursalSelect.addEventListener('change', () => { toggleUIBySelection(); window.location.href = getCurrentListUrl(); });

  // Búsqueda en vivo
  formBusqueda.addEventListener('submit', (e)=>{ e.preventDefault(); applyLiveSearch(); });
  searchInput.addEventListener('input',  ()=>{ applyLiveSearch(); });

  // Exportar (incluye búsqueda actual)
  btnExportar.addEventListener('click', function() {
    const params = buildParams();
    window.open(`exportar_lista_excel.php?${params.toString()}`, '_blank');
  });

  // Eliminar con return_url
  const deleteModal = document.getElementById('confirmDeleteModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', function (event) {
      const button     = event.relatedTarget;
      const deviceId   = button.getAttribute('data-id');
      const deleteLink = deleteModal.querySelector('#deleteLink');
      deleteLink.href  = `eliminar.php?id=${encodeURIComponent(deviceId)}&return_url=${encodeURIComponent(getCurrentListUrl())}`;
    });
  }

  // Alertas autohide
  const alerts = document.querySelectorAll('.alert[data-autohide="true"]');
  alerts.forEach(function(el) {
    setTimeout(function() {
      if (window.bootstrap && bootstrap.Alert) bootstrap.Alert.getOrCreateInstance(el).close();
      else el.remove();
    }, 4500);
  });
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Listado de dispositivos";
$pageHeader = "Listado de dispositivos";
$activePage = "dispositivos";
include __DIR__ . '/../../layout.php';
?>