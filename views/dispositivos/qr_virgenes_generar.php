<?php
// /sisec-ui/views/dispositivos/qr_virgenes_generar.php
// se estaran generando los qr virgenes en este script
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

verificarAutenticacion();
verificarRol(['Superadmin','Técnico', 'Capturista']);

date_default_timezone_set('America/Mexico_City');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---------- helpers ----------
function uuidv4(): string {
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function app_base_url(): string {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return ($https ? 'https://' : 'http://') . $host;
}
$baseClaimUrl = app_base_url() . '/sisec-ui/views/dispositivos/qr_claim.php?t=';

$feedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $cantidad = max(1, min(500, (int)($_POST['cantidad'] ?? 0)));
  $uid = (int)($_SESSION['usuario_id'] ?? 0);

  // === NUEVO: asegurar carpeta
  $dirQR = __DIR__ . "/../../public/qr_virgenes/";
  if (!is_dir($dirQR)) mkdir($dirQR, 0777, true);

  $stmt = $conn->prepare("INSERT INTO qr_pool (token, creado_por) VALUES (?, ?)");

  for ($i=0; $i<$cantidad; $i++) {
    $tok = uuidv4();
    $stmt->bind_param('si', $tok, $uid);
    $stmt->execute();

    // === NUEVO: generar archivo físico
    $qrPng = file_get_contents(
      app_base_url() . "/sisec-ui/views/dispositivos/qr_png.php?text=" 
      . urlencode($baseClaimUrl.$tok) 
      . "&s=220"
    );

    file_put_contents($dirQR . $tok . ".png", $qrPng);
  }

  $stmt->close();
  $feedback = "Se generaron {$cantidad} QR vírgenes.";
}

$userId= $_SESSION['usuario_id'] ?? null; 

if (!$userId) {
    die("Error: Sesión no válida.");
}                         

// QR vírgenes
/* $resVirgenes = $conn->query("
  SELECT id, token 
  FROM qr_pool 
  WHERE dispositivo_id IS NULL 
    AND creado_por = $userId
  ORDER BY id DESC 
  LIMIT 300;
");
$qrVirgenes = $resVirgenes->fetch_all(MYSQLI_ASSOC); */

$stmtV = $conn->prepare("SELECT id, token FROM qr_pool WHERE dispositivo_id IS NULL AND creado_por = ? ORDER BY id DESC LIMIT 300");
$stmtV->bind_param("i", $userId);
$stmtV->execute();
$qrVirgenes = $stmtV->get_result()->fetch_all(MYSQLI_ASSOC);

// QR reclamados
$resReclamados = $conn->query("
  SELECT id, token, dispositivo_id, claimed_at 
  FROM qr_pool 
  WHERE dispositivo_id IS NOT NULL 
  ORDER BY claimed_at DESC 
  LIMIT 300
");
$qrReclamados = $resReclamados->fetch_all(MYSQLI_ASSOC);

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Generar QR vírgenes</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link rel="shortcut icon" href="/sisec-ui/public/img/QRCESISS.png">
  <style>
    body{background:#f7fbfd}
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px}
    .label{background:#fff;border:1px dashed #cfe7ee;border-radius:.75rem;padding:.6rem;text-align:center}
    .code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.78rem;color:#0f3c45}
    @media print{body{background:#fff}.noprint{display:none!important}.grid{grid-template-columns:repeat(3,1fr);gap:8px}.label{border:1px solid #000;padding:.4rem}}
    .back-home {display: inline-flex;align-items: center;gap: .4rem;text-decoration: none;color: var(--accent);font-size: .9rem;font-weight: 500;transition: color .2s ease;}
    .back-home:hover {color: #116fc1ff;}
    /* ---------- Título ---------- */
    :root{--brand:#3C92A6;--brand-2:#3C92A6;--ink:#0f3c45;}
    h2{font-weight:800; letter-spacing:.2px; color:var(--ink);margin-bottom:.75rem!important;}
    h2::after{content:""; display:block; width:78px; height:4px; border-radius:99px;margin-top:.5rem; background:linear-gradient(90deg,var(--brand),var(--brand-2));}
  </style>
</head>
<body class="container py-4">
  <h2 style="margin:4px 0 16px; font-weight:800;">QR vírgenes</h2>
  <a href="/sisec-ui/index.php" class="back-home"><i class="fa-solid fa-house"></i>Volver al inicio</a>
  <br><br>
  <form method="post" class="row g-2 align-items-center noprint">
    <div class="col-auto">  
      <label class="col-form-label">Cantidad</label>
    </div>
    <div class="col-auto">
      <input type="number" name="cantidad" min="1" max="5" value="5" class="form-control" required>
    </div>
    <div class="col-auto">
      <button class="btn btn-primary">Generar</button>
    </div>
    <div class="col-auto">
      <a href="/sisec-ui/views/dispositivos/qr_virgenes_imagen.php" class="btn btn-outline-secondary noprint">Descargar lámina JPG</a>
    </div>
  </form>
  <?php if ($feedback): ?>
    <div class="alert alert-success my-3 noprint"><?= htmlspecialchars($feedback) ?></div>
  <?php endif; ?>
  <ul class="nav nav-tabs mt-4" id="qrTabs" role="tablist">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#virgenes">QR vírgenes (<?= count($qrVirgenes) ?>)</button>
    </li>
    <!-- <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reclamados">QR reclamados (<?= count($qrReclamados) ?>)</button>
    </li> -->
  </ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="virgenes">
      <div class="grid mt-3">
        <?php foreach ($qrVirgenes as $row): ?>
          <?php $claimUrl = $baseClaimUrl . urlencode($row['token']); ?>
          <div class="label">
            <img src="/sisec-ui/views/dispositivos/qr_png.php?text=<?= urlencode($claimUrl) ?>&s=220" width="160">
            <div class="small text-secondary mt-2">VIRGEN</div>
            <div class="code">#<?= (int)$row['id'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="tab-pane fade" id="reclamados">
      <div class=" mt-5">      
      <h5  class= "text-center"> Carga no disponible </h5> </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>