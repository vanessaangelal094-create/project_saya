<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

date_default_timezone_set('Asia/Makassar');


/* =========================================================
   FUNGSI BANTU
========================================================= */

function import_h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function import_normalize($value)
{
    return preg_replace(
        '/[^A-Z0-9]/',
        '',
        strtoupper(trim((string)$value))
    );
}


function import_number($value)
{
    if ($value === null || trim((string)$value) === '') {
        return null;
    }

    if (is_numeric($value)) {
        return (float)$value;
    }

    $value = trim((string)$value);

    $value = str_replace(
        ["\xc2\xa0", ' '],
        '',
        $value
    );

    if (
        strpos($value, ',') !== false &&
        strpos($value, '.') !== false
    ) {
        if (strrpos($value, ',') > strrpos($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
    } elseif (strpos($value, ',') !== false) {
        $value = str_replace(',', '.', $value);
    }

    return is_numeric($value) ? (float)$value : null;
}


function import_excel_date($value)
{
    if ($value === null || trim((string)$value) === '') {
        return null;
    }

    // Nomor seri tanggal Excel.
    if (is_numeric($value) && (float)$value >= 1000) {
        $days = (int)floor((float)$value);

        $date = new DateTimeImmutable('1899-12-30');

        return $date
            ->modify('+' . $days . ' days')
            ->format('Y-m-d');
    }

    $value = trim((string)$value);

    $formats = [
        'Y-m-d',
        'd/m/Y',
        'd-m-Y',
        'j/n/Y',
        'd/m/y'
    ];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat(
            '!' . $format,
            $value
        );

        if ($date instanceof DateTimeImmutable) {
            $dateErrors = DateTimeImmutable::getLastErrors();

            if (
                $dateErrors === false ||
                (
                    $dateErrors['warning_count'] === 0 &&
                    $dateErrors['error_count'] === 0
                )
            ) {
                return $date->format('Y-m-d');
            }
        }
    }

    return null;
}


/* =========================================================
   MEMBACA STRUKTUR XLSX
========================================================= */

function import_xml($xmlText)
{
    $dom = new DOMDocument();

    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;

    if (!$dom->loadXML($xmlText, LIBXML_NONET)) {
        throw new RuntimeException(
            'Struktur XML pada file Excel tidak dapat dibaca.'
        );
    }

    $xpath = new DOMXPath($dom);

    $xpath->registerNamespace(
        'x',
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
    );

    $xpath->registerNamespace(
        'p',
        'http://schemas.openxmlformats.org/package/2006/relationships'
    );

    $xpath->registerNamespace(
        'r',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
    );

    return [$dom, $xpath];
}


function import_shared_strings(ZipArchive $zip)
{
    $xmlText = $zip->getFromName('xl/sharedStrings.xml');

    if ($xmlText === false) {
        return [];
    }

    [, $xpath] = import_xml($xmlText);

    $strings = [];

    $items = $xpath->query('//x:sst/x:si');

    foreach ($items as $item) {
        $text = '';

        foreach ($xpath->query('.//x:t', $item) as $textNode) {
            $text .= $textNode->textContent;
        }

        $strings[] = $text;
    }

    return $strings;
}


function import_sheet_map(ZipArchive $zip)
{
    $workbookText = $zip->getFromName('xl/workbook.xml');

    $relationsText = $zip->getFromName(
        'xl/_rels/workbook.xml.rels'
    );

    if (
        $workbookText === false ||
        $relationsText === false
    ) {
        throw new RuntimeException(
            'Struktur workbook Excel tidak ditemukan.'
        );
    }

    [, $workbookXPath] = import_xml($workbookText);

    [, $relationsXPath] = import_xml($relationsText);

    $relations = [];

    foreach (
        $relationsXPath->query('//p:Relationship')
        as $relation
    ) {
        $relations[$relation->getAttribute('Id')] =
            $relation->getAttribute('Target');
    }

    $sheets = [];

    foreach (
        $workbookXPath->query('//x:sheets/x:sheet')
        as $sheet
    ) {
        $name = $sheet->getAttribute('name');

        $relationshipId = $sheet->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'id'
        );

        if (!isset($relations[$relationshipId])) {
            continue;
        }

        $target = str_replace(
            '\\',
            '/',
            $relations[$relationshipId]
        );

        if (substr($target, 0, 1) === '/') {
            $path = ltrim($target, '/');
        } elseif (strpos($target, 'xl/') === 0) {
            $path = $target;
        } else {
            $path = 'xl/' . ltrim($target, '/');
        }

        $sheets[import_normalize($name)] = [
            'name' => $name,
            'path' => $path
        ];
    }

    return $sheets;
}


function import_sheet_rows(
    ZipArchive $zip,
    $path,
    array $sharedStrings
) {
    $xmlText = $zip->getFromName($path);

    if ($xmlText === false) {
        throw new RuntimeException(
            'Isi sheet Excel yang dipilih tidak ditemukan.'
        );
    }

    [, $xpath] = import_xml($xmlText);

    $rows = [];

    foreach (
        $xpath->query('//x:sheetData/x:row')
        as $rowNode
    ) {
        $rowNumber = (int)$rowNode->getAttribute('r');

        if ($rowNumber < 1) {
            continue;
        }

        $cells = [];

        foreach ($xpath->query('./x:c', $rowNode) as $cell) {
            $reference = $cell->getAttribute('r');

            if (
                !preg_match(
                    '/^([A-Z]+)\d+$/i',
                    $reference,
                    $matches
                )
            ) {
                continue;
            }

            $letters = strtoupper($matches[1]);

            $column = 0;

            for ($i = 0; $i < strlen($letters); $i++) {
                $column = (
                    $column * 26
                ) + (ord($letters[$i]) - 64);
            }

            $type = $cell->getAttribute('t');

            $value = '';

            if ($type === 'inlineStr') {
                foreach (
                    $xpath->query('.//x:is//x:t', $cell)
                    as $textNode
                ) {
                    $value .= $textNode->textContent;
                }
            } else {
                $valueNode = $xpath->query(
                    './x:v',
                    $cell
                )->item(0);

                if ($valueNode) {
                    $value = $valueNode->textContent;
                }

                if (
                    $type === 's' &&
                    $value !== '' &&
                    isset($sharedStrings[(int)$value])
                ) {
                    $value = $sharedStrings[(int)$value];
                }
            }

            $cells[$column] = $value;
        }

        $rows[$rowNumber] = $cells;
    }

    ksort($rows);

    return $rows;
}


/* =========================================================
   MENCOCOKKAN ID UNIT
========================================================= */

function import_get_unit_code($name)
{
    $nameKey = import_normalize($name);

    $unitMap = [
        'FORKLIFTFK07' => 'BRF-07',
        'WHEELLOADERWL05' => 'F-05',
        'CRANEZOOMLION' => 'CR-03',
        'DULEVO' => 'BRD-01',
        'SSLLOADERCASESR20002' => 'SR-02',
        'EXCAVATORHITACIBR05' => 'BR-5',
        'EXCAVATORLIUGONGBR09' => 'BR-9',
        'EXCAVATORHITACHIBR08' => 'BR-8',
        'BL570T' => 'T-04',
        'SSL375A' => 'CLG-03',
        'BLSN03' => 'SN-03'
    ];

    foreach ($unitMap as $excelName => $unitCode) {
        if (strpos($nameKey, $excelName) !== false) {
            return import_normalize($unitCode);
        }
    }

    if (preg_match('/\(([A-Z0-9][A-Z0-9.\-]*)\)/i', $name, $matches)) {
        return import_normalize($matches[1]);
    }

    return '';
}

/* =========================================================
   PILIH SHEET EXCEL
========================================================= */

$allowed_sheets = [
    'JUNI'       => 'Juni 2026',
    'JULI'       => 'Juli 2026',
    'AGUSTUS'    => 'Agustus 2026',
    'SEPTEMBER'  => 'September 2026',
    'OKTOBER'    => 'Oktober 2026'
];

$selected_sheet = strtoupper(
    trim($_POST['sheet_name'] ?? 'OKTOBER')
);

if (!isset($allowed_sheets[$selected_sheet])) {
    $selected_sheet = 'OKTOBER';
}

$errors = [];
$warnings = [];
$import_summary = null;


/* =========================================================
   PROSES IMPORT EXCEL
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !class_exists('ZipArchive') ||
        !class_exists('DOMDocument')
    ) {
        $errors[] =
            'Pembaca Excel belum aktif. Pastikan extension zip dan dom aktif di php.ini XAMPP, lalu restart Apache.';
    }

    if (empty($_FILES['excel_file'])) {
        $errors[] = 'Pilih file Excel terlebih dahulu.';
    } elseif (
        $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK
    ) {
        $errors[] =
            'File gagal diunggah. Silakan pilih file Excel lagi.';
    } elseif (
        strtolower(
            pathinfo(
                $_FILES['excel_file']['name'],
                PATHINFO_EXTENSION
            )
        ) !== 'xlsx'
    ) {
        $errors[] =
            'File harus berformat .xlsx.';
    } elseif (
        $_FILES['excel_file']['size'] > 10 * 1024 * 1024
    ) {
        $errors[] =
            'Ukuran file maksimal 10 MB.';
    }

    if (empty($errors)) {

        $zip = new ZipArchive();

        $opened = $zip->open(
            $_FILES['excel_file']['tmp_name']
        );

        if ($opened !== true) {

            $errors[] =
                'File Excel tidak dapat dibuka. Coba simpan ulang sebagai Excel Workbook (.xlsx).';

        } else {

            $transaction_started = false;

            try {

                $sheetMap = import_sheet_map($zip);

                $sheetKey = import_normalize(
                    $selected_sheet
                );

                if (!isset($sheetMap[$sheetKey])) {
                    throw new RuntimeException(
                        'Sheet ' .
                        $allowed_sheets[$selected_sheet] .
                        ' tidak ditemukan pada file Excel.'
                    );
                }

                $sharedStrings = import_shared_strings($zip);

                $rows = import_sheet_rows(
                    $zip,
                    $sheetMap[$sheetKey]['path'],
                    $sharedStrings
                );


                /* -----------------------------------------
                   DATA EQUIPMENT MASTER
                ----------------------------------------- */

                $equipmentByCode = [];

                $equipmentByName = [];

                $equipmentResult = $conn->query("
                    SELECT
                        id,
                        equipment_code,
                        equipment_name,
                        hm_current
                    FROM equipment
                ");

                if (!$equipmentResult) {
                    throw new RuntimeException(
                        'Data Equipment Master gagal dibaca: ' .
                        $conn->error
                    );
                }

                while (
                    $equipment = $equipmentResult->fetch_assoc()
                ) {
                    $codeKey = import_normalize(
                        $equipment['equipment_code'] ?? ''
                    );

                    if ($codeKey !== '') {
                        $equipmentByCode[$codeKey] = $equipment;
                    }

                    $nameKey = import_normalize(
                        $equipment['equipment_name'] ?? ''
                    );

                    if ($nameKey !== '') {
                        if (
                            array_key_exists(
                                $nameKey,
                                $equipmentByName
                            )
                        ) {
                            // Nama ganda tidak dipakai untuk
                            // pencocokan otomatis.
                            $equipmentByName[$nameKey] = null;
                        } else {
                            $equipmentByName[$nameKey] =
                                $equipment;
                        }
                    }
                }


                /* -----------------------------------------
                   PERSIAPAN IMPORT
                ----------------------------------------- */

                $jenisMaintenance = 'Lubrication';

                $pekerjaanDasar = 'Penggantian Oli (PMC)';

                $inserted = 0;

                $skipped = 0;

                $seenRows = 0;

                $fallbackHmCount = 0;


                $conn->begin_transaction();

                $transaction_started = true;


                /* -----------------------------------------
                   CEK JADWAL DUPLIKAT
                ----------------------------------------- */

                $duplicateStmt = $conn->prepare("
                    SELECT id
                    FROM maintenance_schedule
                    WHERE equipment_id = ?
                      AND jenis_maintenance = ?
                      AND tanggal_jadwal = ?
                      AND ABS(next_service - ?) < 0.001
                    LIMIT 1
                ");

                if (!$duplicateStmt) {
                    throw new RuntimeException(
                        'Gagal menyiapkan pemeriksaan jadwal duplikat: ' .
                        $conn->error
                    );
                }


                /* -----------------------------------------
                   SIMPAN JADWAL
                ----------------------------------------- */

                $insertStmt = $conn->prepare("
                    INSERT INTO maintenance_schedule
                    (
                        equipment_id,
                        jenis_maintenance,
                        last_service,
                        current_hm_km,
                        interval_pm,
                        next_service,
                        remaining_hm_km,
                        pekerjaan,
                        pic_teknisi,
                        tanggal_jadwal,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$insertStmt) {
                    $duplicateStmt->close();

                    throw new RuntimeException(
                        'Gagal menyiapkan penyimpanan jadwal: ' .
                        $conn->error
                    );
                }


                /* -----------------------------------------
                   PROSES BARIS EXCEL
                ----------------------------------------- */

                foreach ($rows as $rowNumber => $cells) {

                    /*
                     * Struktur sheet 2026:
                     * A = Nomor
                     * B = Nama Alat
                     * C = Tanggal servis terakhir
                     * D = HM servis terakhir
                     * E = Tanggal servis berikutnya
                     * F = HM servis berikutnya
                     * G = Keterangan PMC
                     */

                    $rowNo = $cells[1] ?? '';

                    $unitName = trim(
                        (string)($cells[2] ?? '')
                    );

                    if (
                        !is_numeric($rowNo) ||
                        $unitName === ''
                    ) {
                        continue;
                    }

                    $seenRows++;


                    // HM servis terakhir berasal dari kolom D.
                    $lastHm = import_number(
                        $cells[4] ?? null
                    );

                    // Tanggal servis berikutnya dari kolom E.
                    $nextDate = import_excel_date(
                        $cells[5] ?? null
                    );

                    // Target HM berikutnya dari kolom F.
                    $nextHm = import_number(
                        $cells[6] ?? null
                    );

                    $note = trim(
                        (string)($cells[7] ?? '')
                    );


                    /*
                     * Jadwal harus memiliki tanggal dan HM
                     * berikutnya yang valid.
                     */
                    if (
                        $lastHm === null ||
                        $nextHm === null ||
                        $nextDate === null ||
                        $nextHm <= $lastHm
                    ) {
                        $skipped++;

                        $warnings[] =
                            'Baris Excel ' . $rowNumber .
                            ' (' . $unitName .
                            '): tanggal atau HM servis berikutnya tidak valid, sehingga dilewati.';

                        continue;
                    }


                    /* -------------------------------------
                       COCOKKAN ID UNIT
                    ------------------------------------- */

                    $unitCode = import_get_unit_code(
                        $unitName
                    );

                    $equipment = null;

                    if (
                        $unitCode !== '' &&
                        isset($equipmentByCode[$unitCode])
                    ) {
                        $equipment =
                            $equipmentByCode[$unitCode];
                    }


                    /*
                     * Jika nama dari Excel sama persis dengan
                     * equipment_name, gunakan nama tersebut.
                     */
                    if (!$equipment) {
                        $unitNameKey = import_normalize(
                            $unitName
                        );

                        if (
                            $unitNameKey !== '' &&
                            array_key_exists(
                                $unitNameKey,
                                $equipmentByName
                            )
                        ) {
                            $equipment =
                                $equipmentByName[$unitNameKey];
                        }
                    }


                    if (!$equipment) {
                        $skipped++;

                        $warnings[] =
                            'Baris Excel ' . $rowNumber .
                            ' (' . $unitName .
                            '): unit tidak ditemukan atau ID unit tidak cocok dengan Equipment Master.';

                        continue;
                    }


                    $equipmentId = (int)$equipment['id'];


                    /* -------------------------------------
                       HM DAN STATUS JADWAL
                    ------------------------------------- */

                    /*
                     * HM terkini diambil dari Equipment Master.
                     * Jika kosong, nilai awal menggunakan HM
                     * servis terakhir dari Excel dan diberikan
                     * peringatan setelah proses import.
                     */

                    $currentHm = import_number(
                        $equipment['hm_current'] ?? null
                    );

                    if (
                        $currentHm === null ||
                        $currentHm <= 0
                    ) {
                        $currentHm = $lastHm;

                        $fallbackHmCount++;
                    }


                    $intervalPm = $nextHm - $lastHm;

                    $remainingHm = $nextHm - $currentHm;


                    /*
                     * Mengikuti status yang sudah dipakai
                     * oleh maintenance/add.php.
                     */

                    if ($remainingHm < 0) {
                        $status = 'Overdue';
                    } elseif ($remainingHm <= 100) {
                        $status = 'Segera Diservis';
                    } elseif ($remainingHm <= 250) {
                        $status = 'Akan Jatuh Tempo';
                    } else {
                        $status = 'Normal';
                    }


                    /* -------------------------------------
                       CEK DUPLIKAT
                    ------------------------------------- */

                    $duplicateStmt->bind_param(
                        "issd",
                        $equipmentId,
                        $jenisMaintenance,
                        $nextDate,
                        $nextHm
                    );

                    if (!$duplicateStmt->execute()) {
                        throw new RuntimeException(
                            'Gagal memeriksa jadwal unit ' .
                            $unitName . ': ' .
                            $duplicateStmt->error
                        );
                    }

                    $duplicateResult =
                        $duplicateStmt->get_result();

                    if (
                        $duplicateResult &&
                        $duplicateResult->num_rows > 0
                    ) {
                        $skipped++;

                        $warnings[] =
                            'Baris Excel ' . $rowNumber .
                            ' (' . $unitName .
                            '): jadwal ' .
                            date('d/m/Y', strtotime($nextDate)) .
                            ' dengan target HM ' .
                            number_format(
                                $nextHm,
                                1,
                                ',',
                                '.'
                            ) .
                            ' sudah ada dan tidak diimpor ulang.';

                        continue;
                    }


                    /* -------------------------------------
                       DESKRIPSI PEKERJAAN
                    ------------------------------------- */

                    $pekerjaan = $pekerjaanDasar;

                    if ($note !== '') {
                        $pekerjaan .= ' — ' . $note;
                    }

                    $pic = '';


                    /* -------------------------------------
                       SIMPAN KE DATABASE
                    ------------------------------------- */

                    $insertStmt->bind_param(
                        "isdddddssss",
                        $equipmentId,
                        $jenisMaintenance,
                        $lastHm,
                        $currentHm,
                        $intervalPm,
                        $nextHm,
                        $remainingHm,
                        $pekerjaan,
                        $pic,
                        $nextDate,
                        $status
                    );

                    if (!$insertStmt->execute()) {
                        throw new RuntimeException(
                            'Gagal menyimpan unit ' .
                            $unitName . ': ' .
                            $insertStmt->error
                        );
                    }

                    $inserted++;
                }


                $duplicateStmt->close();

                $insertStmt->close();


                /* -----------------------------------------
                   SELESAIKAN TRANSAKSI
                ----------------------------------------- */

                $conn->commit();

                $transaction_started = false;


                $import_summary = [
                    'sheet' => $allowed_sheets[$selected_sheet],
                    'rows' => $seenRows,
                    'inserted' => $inserted,
                    'skipped' => $skipped
                ];


                if ($seenRows === 0) {
                    $warnings[] =
                        'Tidak ditemukan baris jadwal pada sheet yang dipilih.';
                }


                if ($fallbackHmCount > 0) {
                    $warnings[] =
                        $fallbackHmCount .
                        ' unit menggunakan HM servis terakhir sebagai HM awal karena HM terkini tidak tersedia di Equipment Master. Periksa HM dan status unit tersebut.';
                }

            } catch (Throwable $exception) {

                if ($transaction_started) {
                    $conn->rollback();
                }

                $errors[] = $exception->getMessage();

            }

            $zip->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Import Jadwal PMC | Monitoring Alat Berat</title>

    <link
        rel="stylesheet"
        href="../assets/css/app.css"
    >

    <style>

        .import-page {
            max-width: 1150px;
            margin: 0 auto;
            padding-bottom: 35px;
        }

        .import-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .import-title {
            margin: 0 0 7px;
            color: var(--ink);
            font-size: 26px;
            font-weight: 750;
        }

        .import-subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .import-card {
            margin-bottom: 20px;
            padding: 23px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
        }

        .import-card h2 {
            margin: 0 0 7px;
            font-size: 16px;
            color: var(--ink);
        }

        .import-desc {
            margin: 0 0 19px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .import-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .import-group label {
            display: block;
            margin-bottom: 7px;
            color: var(--ink);
            font-size: 12px;
            font-weight: 650;
        }

        .import-group input,
        .import-group select {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
            color: var(--ink);
            font: inherit;
            font-size: 13px;
        }

        .import-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .import-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }

        .import-message {
            margin-bottom: 17px;
            padding: 13px 15px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.75;
        }

        .import-error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }

        .import-success {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
        }

        .import-warning {
            margin-top: 12px;
            padding: 12px 14px;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 12px;
            line-height: 1.75;
        }

        .import-note-list {
            margin: 10px 0 0;
            padding-left: 18px;
        }

        .import-note-list li {
            margin: 3px 0;
        }

        .import-steps {
            margin: 0;
            padding-left: 20px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.9;
        }

        .import-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--ink);
            text-decoration: none;
            font-size: 12px;
            font-weight: 650;
        }

        .import-link:hover {
            background: var(--surface-hover, #f8fafc);
        }

        @media (max-width: 700px) {

            .import-title {
                font-size: 22px;
            }

            .import-card {
                padding: 16px;
            }

            .import-grid {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>


<body>

<?php

$base = '../';

$active = 'schedule';

include $base . 'includes/sidebar.php';

?>


<main class="main">

    <div class="import-page">


        <!-- HEADER -->

        <div class="import-header">

            <div>

                <div class="breadcrumb">

                    <a href="../index.php">Home</a>

                    &nbsp;›&nbsp;

                    <a href="index.php">
                        Jadwal Pemeliharaan
                    </a>

                    &nbsp;›&nbsp;

                    <span>Import Excel</span>

                </div>

                <h1 class="import-title">
                    Import Jadwal Maintenance PMC
                </h1>

                <p class="import-subtitle">
                    Impor data jadwal pemeliharaan dari Excel ke sistem
                    Monitoring Alat Berat PT Biringkassi Raya.
                </p>

            </div>


            <a
                href="index.php"
                class="import-link"
            >
                &larr; Kembali ke Jadwal
            </a>

        </div>


        <!-- PESAN ERROR -->

        <?php if (!empty($errors)): ?>

            <div class="import-message import-error">

                <strong>
                    Import belum berhasil.
                </strong>

                <ul class="import-note-list">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= import_h($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- RINGKASAN IMPORT -->

        <?php if ($import_summary !== null): ?>

            <div class="import-message import-success">

                <strong>
                    Proses import selesai.
                </strong>

                <br>

                Sheet:
                <?= import_h($import_summary['sheet']) ?>

                <br>

                Baris data terdeteksi:
                <?= (int)$import_summary['rows'] ?>

                <br>

                Jadwal baru tersimpan:
                <?= (int)$import_summary['inserted'] ?>

                <br>

                Baris dilewati atau duplikat:
                <?= (int)$import_summary['skipped'] ?>

            </div>

        <?php endif; ?>


        <!-- PERINGATAN IMPORT -->

        <?php if (!empty($warnings)): ?>

            <div class="import-warning">

                <strong>
                    Catatan dari proses import:
                </strong>

                <ul class="import-note-list">

                    <?php foreach (array_slice($warnings, 0, 20) as $warning): ?>

                        <li>
                            <?= import_h($warning) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

                <?php if (count($warnings) > 20): ?>

                    <div>
                        Masih ada
                        <?= count($warnings) - 20 ?>
                        catatan lainnya.
                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!-- FORM IMPORT -->

        <section class="import-card">

            <h2>
                Unggah File Excel
            </h2>

            <p class="import-desc">
                Pilih file LIST JADWAL PMC.xlsx dan tentukan
                sheet bulan yang ingin dimasukkan ke daftar
                Jadwal Pemeliharaan.
            </p>


            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <div class="import-grid">


                    <div class="import-group">

                        <label for="excel_file">
                            File Excel (.xlsx)
                        </label>

                        <input
                            type="file"
                            name="excel_file"
                            id="excel_file"
                            accept=".xlsx"
                            required
                        >

                        <span class="import-help">
                            Pilih file Excel asli dari komputer.
                            Ukuran maksimal 10 MB.
                        </span>

                    </div>


                    <div class="import-group">

                        <label for="sheet_name">
                            Periode Jadwal
                        </label>

                        <select
                            name="sheet_name"
                            id="sheet_name"
                            required
                        >

                            <?php foreach ($allowed_sheets as $sheetKey => $sheetLabel): ?>

                                <option
                                    value="<?= import_h($sheetKey) ?>"
                                    <?= (
                                        $selected_sheet === $sheetKey
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= import_h($sheetLabel) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <span class="import-help">
                            Pilih bulan yang jadwalnya ingin diimpor.
                            Sheet Oktober 2025 tidak disertakan karena
                            formatnya berbeda.
                        </span>

                    </div>


                </div>


                <div class="import-actions">

                    <button
                        type="submit"
                        class="btn-primary"
                        onclick="return confirm('Import jadwal dari sheet yang dipilih? Jadwal yang sudah ada akan dilewati agar tidak terduplikasi.')"
                    >
                        Import Jadwal
                    </button>

                </div>


            </form>

        </section>


        <!-- KETERANGAN DATA -->

        <section class="import-card">

            <h2>
                Informasi Data yang Diimpor
            </h2>

            <ol class="import-steps">

                <li>
                    Nama unit pada Excel dicocokkan dengan
                    ID Unit di Equipment Master.
                </li>

                <li>
                    HM servis terakhir diambil dari kolom
                    HM pada bagian Pergantian Oli.
                </li>

                <li>
                    Tanggal servis berikutnya disimpan ke
                    kolom tanggal_jadwal.
                </li>

                <li>
                    Target HM servis berikutnya disimpan ke
                    kolom next_service.
                </li>

                <li>
                    Jadwal yang sama akan dilewati jika sudah
                    ada di database.
                </li>

                <li>
                    Baris yang tanggal servis atau target HM-nya
                    tidak lengkap akan dilewati dan ditampilkan
                    dalam catatan import.
                </li>

            </ol>

        </section>


    </div>

</main>

</body>

</html>