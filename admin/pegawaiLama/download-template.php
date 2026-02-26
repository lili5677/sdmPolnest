<?php
session_start();
require_once '../../config/database.php';

// Cek user login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Cek PhpSpreadsheet
if (!file_exists('../../vendor/autoload.php')) {
    //  fallback ke CSV biasa
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_pegawai.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'nama_lengkap',
        'email', 
        'jenis_pegawai',
        'jabatan',
        'jenis_kepegawaian',
        'status_aktif',
        'unit_kerja',
        'tanggal_mulai_kerja',
        'masa_kontrak_mulai',
        'masa_kontrak_selesai'
    ]);
    
    fputcsv($output, [
        'Contoh: Ahmad Fauzi',
        'Contoh: ahmad@example.com',
        'dosen/staff/tendik',
        'Contoh: Dosen TI',
        'kontrak/tetap',
        'aktif/tidak_aktif',
        'Contoh: Fakultas Teknik',
        'Format: 2024-01-15',
        'Jika kontrak: 2024-01-01',
        'Jika kontrak: 2025-01-01'
    ]);
    
    fclose($output);
    exit();
}

require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Buat spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Import Pegawai');

// header
$headers = [
    'nama_lengkap',
    'email',
    'jenis_pegawai',
    'jabatan',
    'jenis_kepegawaian',
    'status_aktif',
    'unit_kerja',
    'tanggal_mulai_kerja',
    'masa_kontrak_mulai',
    'masa_kontrak_selesai'
];

$sheet->fromArray($headers, null, 'A1');

$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1e40af']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(25);

// contoh/petunjuk
$examples = [
    'Contoh: Ahmad Fauzi',
    'Contoh: ahmad@example.com',
    'dosen/staff/tendik',
    'Contoh: Dosen TI',
    'kontrak/tetap',
    'aktif/tidak_aktif',
    'Contoh: Fakultas Teknik',
    'Format: 2024-01-15',
    'Jika kontrak: 2024-01-01',
    'Jika kontrak: 2025-01-01'
];

$sheet->fromArray($examples, null, 'A2');

$exampleStyle = [
    'font' => [
        'italic' => true,
        'color' => ['rgb' => '6b7280'],
        'size' => 10
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'f3f4f6']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'e5e7eb']
        ]
    ]
];

$sheet->getStyle('A2:J2')->applyFromArray($exampleStyle);
$sheet->getRowDimension(2)->setRowHeight(20);

$columnWidths = [
    'A' => 25,  // nama_lengkap
    'B' => 30,  // email
    'C' => 18,  // jenis_pegawai
    'D' => 25,  // jabatan
    'E' => 20,  // jenis_kepegawaian
    'F' => 18,  // status_aktif
    'G' => 25,  // unit_kerja
    'H' => 22,  // tanggal_mulai_kerja
    'I' => 23,  // masa_kontrak_mulai
    'J' => 23   // masa_kontrak_selesai
];

foreach ($columnWidths as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}

// border
$emptyRowStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'e5e7eb']
        ]
    ]
];

for ($row = 3; $row <= 12; $row++) {
    $sheet->getStyle("A$row:J$row")->applyFromArray($emptyRowStyle);
    $sheet->getRowDimension($row)->setRowHeight(18);
}

$sheet->freezePane('A2');

// notes
$noteRow = 14;
$sheet->setCellValue("A$noteRow", "CATATAN PENTING:");
$sheet->setCellValue("A" . ($noteRow + 1), "1. Hapus baris 2 (baris contoh) sebelum upload!");
$sheet->setCellValue("A" . ($noteRow + 2), "2. Kolom wajib diisi: nama_lengkap, email, jenis_pegawai");
$sheet->setCellValue("A" . ($noteRow + 3), "3. Jika jenis_kepegawaian = 'kontrak', wajib isi masa_kontrak_mulai dan masa_kontrak_selesai");
$sheet->setCellValue("A" . ($noteRow + 4), "4. Format tanggal: YYYY-MM-DD (contoh: 2024-01-15)");
$sheet->setCellValue("A" . ($noteRow + 5), "5. Jenis pegawai: dosen, staff, atau tendik");
$sheet->setCellValue("A" . ($noteRow + 6), "6. Jenis kepegawaian: kontrak atau tetap");
$sheet->setCellValue("A" . ($noteRow + 7), "7. Status aktif: aktif atau tidak_aktif");

$noteStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'dc2626'],
        'size' => 10
    ]
];

$sheet->getStyle("A$noteRow")->applyFromArray($noteStyle);

$sheet->getStyle("A" . ($noteRow + 1) . ":A" . ($noteRow + 7))->getFont()->setSize(9);
$sheet->getStyle("A" . ($noteRow + 1) . ":A" . ($noteRow + 7))->getFont()->getColor()->setRGB('374151');

// Merge cells untuk notes
$sheet->mergeCells("A" . ($noteRow + 1) . ":J" . ($noteRow + 1));
$sheet->mergeCells("A" . ($noteRow + 2) . ":J" . ($noteRow + 2));
$sheet->mergeCells("A" . ($noteRow + 3) . ":J" . ($noteRow + 3));
$sheet->mergeCells("A" . ($noteRow + 4) . ":J" . ($noteRow + 4));
$sheet->mergeCells("A" . ($noteRow + 5) . ":J" . ($noteRow + 5));
$sheet->mergeCells("A" . ($noteRow + 6) . ":J" . ($noteRow + 6));
$sheet->mergeCells("A" . ($noteRow + 7) . ":J" . ($noteRow + 7));

// download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template_Import_Pegawai_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

exit();
?>