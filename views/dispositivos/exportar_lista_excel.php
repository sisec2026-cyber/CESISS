<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador','Capturista','Técnico','Distrital','Prevencion','Monitorista','Mantenimientos']);
require __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../includes/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/* ===== CONFIGURACIÓN BASE ===== */
@ini_set('memory_limit', '1024M');
@ini_set('max_execution_time', '180');

/* ===== PARÁMETROS ===== */
$ciudad    = isset($_GET['ciudad_id']) ? (int) $_GET['ciudad_id'] : 0;
$municipio = isset($_GET['municipio_id']) ? (int) $_GET['municipio_id'] : 0;
$sucursal  = isset($_GET['sucursal_id']) ? (int) $_GET['sucursal_id'] : 0;

/* ===== NOMBRE SUCURSAL ===== */
$nombreSucursalTitulo = '';
if ($sucursal > 0) {
  $stmtSucursal = $conn->prepare("SELECT nom_sucursal FROM sucursales WHERE id = ?");
  $stmtSucursal->bind_param("i", $sucursal);
  $stmtSucursal->execute();
  $resSuc = $stmtSucursal->get_result();
  if ($resSuc && $row = $resSuc->fetch_assoc()) {
    $nombreSucursalTitulo = $row['nom_sucursal'];
  }
  $stmtSucursal->close();
}

/* ===== FILTRO BASE ===== */
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($ciudad > 0)    { $where .= " AND c.id = ?"; $params[] = $ciudad;    $types .= "i"; }
if ($municipio > 0) { $where .= " AND m.id = ?"; $params[] = $municipio; $types .= "i"; }
if ($sucursal > 0)  { $where .= " AND s.id = ?"; $params[] = $sucursal;  $types .= "i"; }

/* ===== SELECT BASE ===== */
$selectBase = "d.id,
  d.fecha,
  s.nom_sucursal,
  m.nom_municipio,
  c.nom_ciudad,
  eq.nom_equipo,
  mo.num_modelos,
  es.status_equipo,
  d.area,
  d.observaciones,
  d.serie,
  d.mac,
  d.vms,
  d.servidor,
  d.switch,
  d.puerto,
  d.zona_alarma,
  d.tipo_sensor,
  d.tiene_analitica,
  d.analiticas";

$joinsBase = "LEFT JOIN sucursales s ON d.sucursal = s.id
  LEFT JOIN municipios m ON s.municipio_id = m.id
  LEFT JOIN ciudades c ON m.ciudad_id = c.id
  LEFT JOIN equipos eq ON d.equipo = eq.id
  LEFT JOIN modelos mo ON d.modelo = mo.id
  LEFT JOIN status es ON d.estado = es.id";

/* ===== CONSULTA: ALARMAS ===== */
$sqlAlarmas = "SELECT $selectBase
  FROM dispositivos d
  $joinsBase
  $where
  AND d.alarma_id IS NOT NULL
  ORDER BY d.id ASC";

$stmtA = $conn->prepare($sqlAlarmas);
if (!empty($params)) $stmtA->bind_param($types, ...$params);
$stmtA->execute();
$resultAlarmas = $stmtA->get_result();

/* ===== CONSULTA: CCTV ===== */
$cctvKeywords = ['CAM', 'CÁMARA', 'CAMARA', 'PTZ', 'BULLET', 'DOME','NVR', 'DVR', 'SERVIDOR', 'SERVER', 'GRABADOR',
  'STORAGE', 'ENCODER', 'DECODIFICADOR', 'DECODER','SWITCH POE', 'POE', 'MONITOR', 'HDD', 'DISCO'];
$cctvLike = [];
foreach ($cctvKeywords as $kw) { $cctvLike[] = "UPPER(eq.nom_equipo) LIKE ?"; }

$cctvSql = "SELECT $selectBase
  FROM dispositivos d
  $joinsBase
  $where
  AND (d.cctv_id IS NOT NULL OR (d.alarma_id IS NULL AND (" . implode(" OR ", $cctvLike) . ")))
  ORDER BY d.id ASC";

$typesCCTV = $types . str_repeat('s', count($cctvKeywords));
$paramsCCTV = $params;
foreach ($cctvKeywords as $kw) $paramsCCTV[] = '%' . $kw . '%';

$stmtC = $conn->prepare($cctvSql);
$stmtC->bind_param($typesCCTV, ...$paramsCCTV);
$stmtC->execute();
$resultCCTV = $stmtC->get_result();

/* ===== CREAR EXCEL ===== */
$spreadsheet = new Spreadsheet();

// Protección del archivo
$spreadsheet->getActiveSheet()->getProtection()->setSheet(true);
$spreadsheet->getActiveSheet()->getProtection()->setPassword('CESISS2025');
$spreadsheet->getSecurity()->setLockWindows(true);
$spreadsheet->getSecurity()->setLockStructure(true);

/* HOJA 1: ALARMAS */
$sheetA = $spreadsheet->getActiveSheet();
$sheetA->setTitle('Alarmas');

$headersA = [
  'Equipo', 'Fecha Mant.', 'Modelo', 'Status', 'Zona', 'Tipo de sensor', 'Observaciones'];
$colA = 'A';
foreach ($headersA as $header) {
  $sheetA->setCellValue($colA.'1', $header);
  $sheetA->getStyle($colA.'1')->getFont()->setBold(true);
  $sheetA->getStyle($colA.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $colA++;
}

$fila = 2;
if ($resultAlarmas->num_rows > 0) {
  while ($r = $resultAlarmas->fetch_assoc()) {
    $sheetA->fromArray([
      $r['nom_equipo'], $r['fecha'], $r['num_modelos'], $r['status_equipo'], $r['zona_alarma'],$r['tipo_sensor'], $r['observaciones']], NULL, 'A' . $fila);
    $fila++;
  }
} else {
  $sheetA->setCellValue('A2', 'No se encontraron dispositivos de alarma');
  $sheetA->mergeCells('A2:M2');
}

/* HOJA 2: CCTV */
$sheetC = $spreadsheet->createSheet();
$sheetC->setTitle('CCTV');

$headersC = ['Equipo', 'Fecha Mant.', 'Modelo', 'Status', 'Servidor/NVR', 'Analítica', 'Observaciones'];
$colC = 'A';
foreach ($headersC as $header) {
  $sheetC->setCellValue($colC.'1', $header);
  $sheetC->getStyle($colC.'1')->getFont()->setBold(true);
  $sheetC->getStyle($colC.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $colC++;
}

$fila = 2;
if ($resultCCTV->num_rows > 0) {
  while ($r = $resultCCTV->fetch_assoc()) {
    $analitica = $r['tiene_analitica'] ? $r['analiticas'] : 'No';
    $sheetC->fromArray([
      $r['nom_equipo'], $r['fecha'], $r['num_modelos'], $r['status_equipo'],
      $r['servidor'], $analitica, $r['observaciones']], NULL, 'A' . $fila);
    $fila++;
  }
} else {
  $sheetC->setCellValue('A2', 'No se encontraron dispositivos de CCTV');
  $sheetC->mergeCells('A2:G2');
}

/* ===== ESTILO Y FORMATO GENERAL ===== */
foreach ([$sheetA, $sheetC] as $sheet) {
  // Encabezados
  $sheet->getStyle('A1:G1')->applyFromArray([
      'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
      'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
      'fill' => [
          'fillType' => Fill::FILL_SOLID,
          'startColor' => ['rgb' => '1B5E20'] // Verde oscuro elegante
      ]
  ]);

  // Bordes y formato del contenido
  $highestRow = $sheet->getHighestRow();
  $sheet->getStyle("A1:G{$highestRow}")->applyFromArray([
      'borders' => [
          'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]
      ],
      'alignment' => [
          'vertical' => Alignment::VERTICAL_CENTER,
          'wrapText' => true
      ]
  ]);
  // Ajuste de columnas
  foreach (range('A', 'M') as $col) {
      $sheet->getColumnDimension($col)->setAutoSize(true);
  }
  // Fila de encabezado más alta
  $sheet->getRowDimension(1)->setRowHeight(25);
}

/* ===== MARCA DE AGUA CENTRADA ===== */
foreach ([$sheetA, $sheetC] as $sheet) {
  $drawing = new Drawing();
  $drawing->setName('Marca de Agua');
  $drawing->setDescription('Marca de Agua');
  $drawing->setPath(__DIR__ . '/../../public/img/QRCESISS_dif.png');
  $drawing->setWidth(420);
  $drawing->setHeight(420);

  // Coordenadas: centrado absoluto
  $highestColumn = $sheet->getHighestColumn();
  $highestRow = $sheet->getHighestRow();

  // Calcula columna central y fila media
  $middleColumnIndex = ceil((ord($highestColumn) - 64) / 2);
  $middleColumn = chr(64 + $middleColumnIndex);
  $middleRow = ceil($highestRow / 2);

  $drawing->setCoordinates($middleColumn . $middleRow);
  $drawing->setOffsetX(-500); // Ajuste fino horizontal
  $drawing->setOffsetY(-200); // Ajuste fino vertical
  $drawing->setWorksheet($sheet);
}

/* ===== EXPORTAR ===== */
$writer = new Xlsx($spreadsheet);
// Protección completa del archivo (estructura)
$spreadsheet->getSecurity()->setLockWindows(true);
$spreadsheet->getSecurity()->setLockStructure(true);
$spreadsheet->getSecurity()->setWorkbookPassword('CESISS2025');

// Protección de todas las hojas
foreach ($spreadsheet->getAllSheets() as $sheet) {
  $protection = $sheet->getProtection();
  $protection->setSheet(true);
  $protection->setSort(false);
  $protection->setInsertRows(false);
  $protection->setFormatCells(false);
  $protection->setInsertColumns(false);
  $protection->setDeleteColumns(false);
  $protection->setDeleteRows(false);
  $protection->setPassword('CESISS2025');
}
$nombreLimpio = !empty($nombreSucursalTitulo)
  ? preg_replace('/[^A-Za-z0-9_-]/', '_', $nombreSucursalTitulo)
  : 'General';
$filename = "Dispositivos_{$nombreLimpio}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;