<?php
require_once __DIR__ . '/../../includes/auth.php';
verificarAutenticacion();
verificarRol(['Superadmin','Administrador']);
require_once __DIR__ . '/../../includes/db.php';

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$q      = trim($_GET['q'] ?? '');
$filtro = $_GET['filtro'] ?? 'todos';

$conds  = [];
$params = [];
$types  = '';

// filtro no vistas
if ($filtro === 'novistas') {
  $conds[] = 'n.visto = 0';
}

// búsqueda por usuario
if ($q !== '') {
  $conds[]  = 'u.nombre LIKE ?';
  $types   .= 's';
  $params[] = "%$q%";
}

$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$sqlUsuarios = "
  SELECT DISTINCT u.id, u.nombre
  FROM notificaciones n
  JOIN usuarios u ON u.id = n.usuario_id
  $where
  ORDER BY u.nombre
";

$stmtUsers = $conn->prepare($sqlUsuarios);
if ($types) {
  $stmtUsers->bind_param($types, ...$params);
}
$stmtUsers->execute();
$usuarios = $stmtUsers->get_result();

$excel = new Spreadsheet();
$excel->removeSheetByIndex(0);

while ($u = $usuarios->fetch_assoc()) {

  $sheet = $excel->createSheet();
  $sheet->setTitle(substr($u['nombre'], 0, 30));

  // Encabezados
  $sheet->fromArray(
    ['Fecha', 'Acción', 'Mensaje', 'Dispositivo / Modelo'],
    null,
    'A1'
  );

  // ====== DATOS ======
  $sqlMovs = "
    SELECT 
      n.fecha,
      n.mensaje,
      m.num_modelos AS modelo
    FROM notificaciones n
    LEFT JOIN dispositivos d ON d.id = n.dispositivo_id
    LEFT JOIN modelos m ON m.id = d.modelo
    WHERE n.usuario_id = ?
    ORDER BY n.fecha
  ";

  $stmtMov = $conn->prepare($sqlMovs);
  $stmtMov->bind_param('i', $u['id']);
  $stmtMov->execute();
  $movs = $stmtMov->get_result();

  $row = 2;
  while ($m = $movs->fetch_assoc()) {

    $accion = 'OTRO';
    $msg = strtolower($m['mensaje']);

    if (str_contains($msg, 'inició sesión'))      $accion = 'LOGIN';
    elseif (str_contains($msg, 'cerró sesión'))  $accion = 'LOGOUT';
    elseif (str_contains($msg, 'registr'))       $accion = 'REGISTRO';
    elseif (str_contains($msg, 'edit'))          $accion = 'EDICIÓN';
    elseif (str_contains($msg, 'elimin'))        $accion = 'ELIMINACIÓN';

    $sheet->setCellValue("A$row", $m['fecha']);
    $sheet->setCellValue("B$row", $accion);
    $sheet->setCellValue("C$row", $m['mensaje']);
    $sheet->setCellValue("D$row", $m['modelo'] ?? '-');

    $row++;
  }

  // ====== ESTILOS ======
  $lastRow = $sheet->getHighestRow();

  $sheet->freezePane('A2');
  $sheet->setAutoFilter("A1:D$lastRow");

  // Encabezados
  $sheet->getStyle('A1:D1')->applyFromArray([
    'font' => [
      'bold'  => true,
      'color' => ['rgb' => 'FFFFFF'],
    ],
    'alignment' => [
      'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'fill' => [
      'fillType'   => Fill::FILL_SOLID,
      'startColor' => ['rgb' => '1F4E78'],
    ],
  ]);

  // Bordes
  $sheet->getStyle("A1:D$lastRow")->applyFromArray([
    'borders' => [
      'allBorders' => [
        'borderStyle' => Border::BORDER_THIN,
      ],
    ],
  ]);

  // Zebra
  for ($i = 2; $i <= $lastRow; $i++) {
    if ($i % 2 === 0) {
      $sheet->getStyle("A$i:D$i")->applyFromArray([
        'fill' => [
          'fillType'   => Fill::FILL_SOLID,
          'startColor' => ['rgb' => 'F3F6F9'],
        ],
      ]);
    }
  }

  // Formato fecha
  $sheet->getStyle("A2:A$lastRow")
    ->getNumberFormat()
    ->setFormatCode(NumberFormat::FORMAT_DATE_DATETIME);

  // Auto size
  foreach (range('A', 'D') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
  }

  // Wrap texto mensaje
  $sheet->getStyle("C2:C$lastRow")
    ->getAlignment()
    ->setWrapText(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename=actividad_por_usuario.xlsx');

$writer = new Xlsx($excel);
$writer->save('php://output');
exit;