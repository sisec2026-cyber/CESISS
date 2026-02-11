<?php
// /sisec-ui/views/dispositivos/qr_scan.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Técnico']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Escanear QR</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <style>
    body{background:#f7fbfd}
    #reader{width:100%;max-width:520px;margin:auto}
  </style>
</head>
<body class="container py-4">
  <h4 class="mb-3">Escanear QR</h4>
  <div id="reader" class="shadow rounded bg-white p-2"></div>
  <p class="text-muted mt-3">Apunta al QR virgen. Al detectarlo, se abrirá el formulario de asignación.</p>

  <script>
  const reader = new Html5Qrcode("reader");
  const config = { fps: 12, qrbox: 260, aspectRatio: 1.0 };
  function onScanSuccess(decodedText){
    try{
      const u = new URL(decodedText, location.origin);
      if (u.pathname.endsWith('/qr_claim.php') && u.searchParams.get('t')) {
        reader.stop().then(()=>location.href = u.href);
        return;
      }
    }catch(_){}
    if (decodedText.startsWith('http')) location.href = decodedText;
  }
  reader.start({ facingMode: "environment" }, config, onScanSuccess);
  </script>
</body>
</html>
