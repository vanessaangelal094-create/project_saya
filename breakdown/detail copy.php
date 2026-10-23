<?php

$view = $_GET['view'] ?? 'list';

$routes = [
    'tambah'   => 'tambah.php',
    'detail'   => 'detail.php',
    'riwayat'  => 'riwayat.php',
    'import'   => 'import_riwayat.php'
];

if (isset($routes[$view])) {
    require __DIR__ . '/' . $routes[$view];
    exit;
}

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
date_default_timezone_set('Asia/Makassar');

function h($value) {
    return htmlspecialchars((string)($value ?? '-'), ENT_QUOTES, 'UTF-8');
}

function detail_tanggal($value) {
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $time = strtotime($value);

    if ($time === false) {
        return h($value);
    }

    if (date('H:i:s', $time) === '00:00:00') {
        return date('d/m/Y', $time);
    }

    return date('d/m/Y H:i', $time);
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID laporan tidak valid.');
}

/* Mengambil laporan dan identitas unit */
$sql = "
    SELECT
        b.id,
        b.equipment_id,
        b.tanggal_kejadian,
        b.hm_km,
        b.jenis_kerusakan,
        b.deskripsi_kerusakan,
        b.tindakan_perbaikan,
        b.pic_teknisi,
        b.tanggal_selesai,
        b.status,
        b.keterangan,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type,
        e.spesifikasi,
        e.brand_model,
        e.serial_number,
        e.year AS equipment_year,
        e.location
    FROM breakdown_reports b
    LEFT JOIN equipment e ON b.equipment_id = e.id
    WHERE b.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit('Gagal mengambil data laporan. Periksa struktur tabel database.');
}

$stmt->bind_param("i", $id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    http_response_code(404);
    exit('Laporan breakdown tidak ditemukan.');
}

/* Mengambil kronologi laporan */
$history = [];

$history_stmt = $conn->prepare("
    SELECT id, urutan, tanggal_riwayat, uraian, lampiran
    FROM breakdown_history
    WHERE breakdown_id = ?
    ORDER BY urutan ASC, id ASC
");

if ($history_stmt) {
    $history_stmt->bind_param("i", $id);
    $history_stmt->execute();

    $history_result = $history_stmt->get_result();

    while ($item = $history_result->fetch_assoc()) {
        $history[] = $item;
    }

    $history_stmt->close();
}

/* Penentuan tampilan status */
$status = $report['status'] ?? 'Dilaporkan';
$status_class = 'status-reported';

if ($status === 'Menunggu Spare Part') {
    $status_class = 'status-waiting';
} elseif ($status === 'Dalam Penanganan') {
    $status_class = 'status-progress';
} elseif ($status === 'Selesai') {
    $status_class = 'status-completed';
}

/* Identitas unit */
$nama_unit = trim(
    ($report['equipment_name'] ?? '') . ' ' .
    ($report['spesifikasi'] ?? '')
);

if ($nama_unit === '') {
    $nama_unit = $report['equipment_type'] ?? '-';
}

$nomor_laporan = 'BR-' . str_pad(
    (string)$report['id'],
    4,
    '0',
    STR_PAD_LEFT
);

$riwayat_terakhir = !empty($history)
    ? $history[count($history) - 1]
    : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Breakdown | Monitoring Alat Berat</title>

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>
        .bd-page {
            max-width: 1480px;
            margin: 0 auto;
            padding-bottom: 36px;
            color: #1e293b;
        }

        .bd-page * {
            box-sizing: border-box;
        }

        .bd-breadcrumb {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            align-items: center;
            margin: 0 0 20px;
            color: #94a3b8;
            font-size: 12px;
        }

        .bd-breadcrumb a {
            color: #64748b;
            text-decoration: none;
        }

        .bd-breadcrumb a:hover {
            color: #6248df;
        }

        .bd-page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .bd-page-title {
            margin: 0 0 7px;
            color: #172554;
            font-size: 27px;
            font-weight: 750;
            line-height: 1.3;
        }

        .bd-page-subtitle {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
        }

        .bd-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border: 1px solid #dbe3ed;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
            white-space: nowrap;
        }

        .bd-back:hover {
            background: #f8fafc;
        }

        /* Header identitas unit */
        .bd-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 22px;
            padding: 25px 27px;
            margin-bottom: 19px;
            border: 1px solid #dfe6f1;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .025);
        }

        .bd-hero-main {
            display: flex;
            align-items: flex-start;
            gap: 17px;
            min-width: 0;
        }

        .bd-unit-symbol {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 54px;
            width: 54px;
            height: 54px;
            border-radius: 11px;
            background: #eeebff;
            color: #6447df;
            font-size: 23px;
            font-weight: 800;
        }

        .bd-unit-eyebrow {
            margin: 0 0 6px;
            color: #64748b;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .bd-unit-title {
            margin: 0;
            color: #172554;
            font-size: 21px;
            font-weight: 750;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .bd-unit-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 17px;
            margin-top: 9px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

        .bd-hero-status {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 7px;
            min-width: 170px;
        }

        .bd-meta-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 650;
        }

        .bd-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.5;
        }

        .bd-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-waiting {
            background: #fff3df;
            color: #a64b08;
        }

        .status-progress {
            background: #e8f1ff;
            color: #1d4ed8;
        }

        .status-completed {
            background: #e3f8eb;
            color: #167344;
        }

        .status-reported {
            background: #edf2f7;
            color: #475569;
        }

        /* Ringkasan angka */
        .bd-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .bd-summary-item {
            min-width: 0;
            padding: 17px 19px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }

        .bd-summary-label {
            margin-bottom: 8px;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
        }

        .bd-summary-value {
            color: #1e293b;
            font-size: 17px;
            font-weight: 750;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        /* Kartu konten */
        .bd-card {
            margin-bottom: 20px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            background: #fff;
        }

        .bd-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding: 18px 22px;
            border-bottom: 1px solid #e8edf4;
        }

        .bd-heading-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .bd-section-number {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 29px;
            width: 29px;
            height: 29px;
            border-radius: 7px;
            background: #f0edff;
            color: #6447df;
            font-size: 12px;
            font-weight: 800;
        }

        .bd-card-title {
            margin: 2px 0 0;
            color: #1e293b;
            font-size: 16px;
            font-weight: 750;
            line-height: 1.5;
        }

        .bd-card-caption {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        }

        .bd-card-body {
            padding: 19px 22px 22px;
        }

        .bd-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 32px;
        }

        .bd-field {
            min-width: 0;
            padding: 13px 0;
            border-bottom: 1px solid #edf1f6;
        }

        .bd-field-full {
            grid-column: 1 / -1;
        }

        .bd-field-label {
            display: block;
            margin-bottom: 6px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
        }

        .bd-field-value {
            color: #1e293b;
            font-size: 13px;
            font-weight: 550;
            line-height: 1.8;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .bd-value-muted {
            color: #94a3b8;
            font-weight: 450;
        }

        .bd-issue {
            margin-bottom: 17px;
            padding: 17px 18px;
            border: 1px solid #fed7aa;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            background: #fffbf5;
        }

        .bd-issue-label {
            margin-bottom: 7px;
            color: #9a560d;
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .bd-issue-text {
            margin: 0;
            color: #78350f;
            font-size: 14px;
            font-weight: 650;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        /* Timeline */
        .bd-history-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .bd-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 27px;
            height: 25px;
            padding: 0 8px;
            margin-left: 6px;
            border-radius: 6px;
            background: #efedff;
            color: #6041d8;
            font-size: 11px;
            font-weight: 800;
        }

        .bd-manage-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 12px;
            border: 1px solid #dbe3ed;
            border-radius: 7px;
            color: #475569;
            background: #fff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 650;
        }

        .bd-manage-link:hover {
            background: #f8fafc;
        }

        .bd-history-body {
            padding: 23px 22px 25px;
        }

        .bd-history-list {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .bd-history-item {
            position: relative;
            display: grid;
            grid-template-columns: 20px 165px minmax(0, 1fr);
            gap: 0 16px;
            padding: 0 0 22px;
        }

        .bd-history-item:last-child {
            padding-bottom: 0;
        }

        .bd-history-track {
            position: relative;
            display: flex;
            justify-content: center;
        }

        .bd-history-track::after {
            position: absolute;
            top: 11px;
            bottom: -2px;
            left: 9px;
            width: 2px;
            background: #e2e8f0;
            content: "";
        }

        .bd-history-item:last-child .bd-history-track::after {
            display: none;
        }

        .bd-history-dot {
            position: relative;
            z-index: 1;
            width: 10px;
            height: 10px;
            margin-top: 5px;
            border: 2px solid #fff;
            border-radius: 50%;
            background: #8168ef;
            box-shadow: 0 0 0 2px #e7e1ff;
        }

        .bd-history-item:last-child .bd-history-dot {
            background: #d97706;
            box-shadow: 0 0 0 2px #ffebc8;
        }

        .bd-history-date {
            padding-top: 1px;
            color: #475569;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .bd-history-content {
            min-width: 0;
            padding: 13px 15px 14px;
            border: 1px solid #e7ebf2;
            border-radius: 9px;
            background: #fff;
        }

        .bd-history-item:last-child .bd-history-content {
            border-color: #f3d5a8;
            background: #fffdf8;
        }

        .bd-history-title {
            margin: 0 0 7px;
            color: #334155;
            font-size: 12px;
            font-weight: 750;
        }

        .bd-history-description {
            margin: 0;
            color: #475569;
            font-size: 12px;
            line-height: 1.9;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .bd-attachments {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 11px;
        }

        .bd-attachment {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border: 1px solid #dbeafe;
            border-radius: 5px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 10px;
            font-weight: 750;
        }

        .bd-empty {
            padding: 23px 18px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.8;
            background: #fafcff;
        }

        .bd-empty a {
            color: #6248df;
            font-weight: 750;
            text-decoration: none;
        }

        .bd-last-update {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr);
            gap: 13px;
            padding: 19px 22px;
            border: 1px solid #f3d5a8;
            border-radius: 10px;
            background: #fffaf1;
            margin-bottom: 21px;
        }

        .bd-last-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 9px;
            background: #ffedca;
            color: #a65b08;
            font-size: 18px;
            font-weight: 800;
        }

        .bd-last-label {
            margin: 0 0 6px;
            color: #9a560d;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .bd-last-date {
            margin: 0 0 7px;
            color: #78350f;
            font-size: 13px;
            font-weight: 750;
        }

        .bd-last-text {
            margin: 0;
            color: #78350f;
            font-size: 12px;
            line-height: 1.8;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .bd-bottom-actions {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 5px;
        }

        @media (max-width: 950px) {
            .bd-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .bd-history-item {
                grid-template-columns: 20px 135px minmax(0, 1fr);
                gap: 0 12px;
            }
        }

        @media (max-width: 650px) {
            .bd-page-title {
                font-size: 22px;
            }

            .bd-hero {
                padding: 18px;
            }

            .bd-hero-status {
                padding-left: 71px;
                min-width: 0;
            }

            .bd-summary {
                grid-template-columns: 1fr;
            }

            .bd-card-header,
            .bd-card-body,
            .bd-history-body {
                padding-left: 16px;
                padding-right: 16px;
            }

            .bd-fields {
                grid-template-columns: 1fr;
            }

            .bd-field-full {
                grid-column: auto;
            }

            .bd-history-item {
                grid-template-columns: 18px minmax(0, 1fr);
                gap: 0 10px;
            }

            .bd-history-track {
                grid-column: 1;
                grid-row: 1 / span 2;
            }

            .bd-history-date {
                grid-column: 2;
                padding-bottom: 8px;
            }

            .bd-history-content {
                grid-column: 2;
            }
        }
    </style>
</head>

<body>

<?php
$base = '../';
$active = 'breakdown';
include $base . 'includes/sidebar.php';
?>

<main class="main">
    <div class="bd-page">

        <div class="bd-breadcrumb">
            <a href="../index.php">Home</a>
            <span>›</span>
            <a href="index.php">Breakdown &amp; Failure</a>
            <span>›</span>
            <span>Detail Laporan</span>
        </div>

        <div class="bd-page-header">
            <div>
                <h1 class="bd-page-title">Detail Laporan Breakdown</h1>
                <p class="bd-page-subtitle">
                    Informasi kerusakan, status penanganan, dan riwayat pekerjaan alat berat.
                </p>
            </div>

            <a href="index.php" class="bd-back">
                &larr; Kembali ke Daftar
            </a>
        </div>

        <!-- Header identitas unit dan status -->
        <section class="bd-hero">
            <div class="bd-hero-main">
                <div class="bd-unit-symbol">BH</div>

                <div>
                    <p class="bd-unit-eyebrow">Unit yang Mengalami Breakdown</p>

                    <h2 class="bd-unit-title">
                        <?= h($nama_unit) ?>
                    </h2>

                    <div class="bd-unit-meta">
                        <span>ID Unit: <strong><?= h($report['equipment_code']) ?></strong></span>
                        <span>No. Laporan: <strong><?= h($nomor_laporan) ?></strong></span>
                        <span>Lokasi: <strong><?= h($report['location'] ?? '-') ?></strong></span>
                    </div>
                </div>
            </div>

            <div class="bd-hero-status">
                <span class="bd-meta-label">STATUS PENANGANAN</span>
                <span class="bd-status <?= h($status_class) ?>">
                    <span class="bd-status-dot"></span>
                    <?= h($status) ?>
                </span>
            </div>
        </section>

        <!-- Ringkasan singkat -->
        <section class="bd-summary">

            <div class="bd-summary-item">
                <div class="bd-summary-label">Tanggal Kejadian</div>
                <div class="bd-summary-value">
                    <?= h(detail_tanggal($report['tanggal_kejadian'])) ?>
                </div>
            </div>

            <div class="bd-summary-item">
                <div class="bd-summary-label">Total Catatan Penanganan</div>
                <div class="bd-summary-value">
                    <?= count($history) ?> catatan
                </div>
            </div>

            <div class="bd-summary-item">
                <div class="bd-summary-label">Tanggal Selesai</div>
                <div class="bd-summary-value">
                    <?= !empty($report['tanggal_selesai'])
                        ? h(detail_tanggal($report['tanggal_selesai']))
                        : 'Belum selesai' ?>
                </div>
            </div>

        </section>

        <!-- Bagian 1: kerusakan -->
        <section class="bd-card">
            <div class="bd-card-header">
                <div class="bd-heading-group">
                    <span class="bd-section-number">01</span>
                    <div>
                        <h2 class="bd-card-title">Informasi Kerusakan</h2>
                        <p class="bd-card-caption">
                            Ringkasan awal kejadian yang dilaporkan.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bd-card-body">

                <div class="bd-issue">
                    <div class="bd-issue-label">Jenis Kerusakan</div>
                    <p class="bd-issue-text">
                        <?= h($report['jenis_kerusakan']) ?>
                    </p>
                </div>

                <div class="bd-fields">

                    <div class="bd-field">
                        <span class="bd-field-label">Tanggal Kejadian</span>
                        <div class="bd-field-value">
                            <?= h(detail_tanggal($report['tanggal_kejadian'])) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">HM/KM Saat Kejadian</span>
                        <div class="bd-field-value">
                            <?php if ($report['hm_km'] !== null && $report['hm_km'] !== ''): ?>
                                <?= h(number_format((float)$report['hm_km'], 1, ',', '.')) ?>
                            <?php else: ?>
                                <span class="bd-value-muted">Tidak tersedia</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bd-field bd-field-full">
                        <span class="bd-field-label">Deskripsi Kerusakan Awal</span>
                        <div class="bd-field-value">
                            <?= h($report['deskripsi_kerusakan']) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">PIC / Teknisi</span>
                        <div class="bd-field-value">
                            <?= !empty($report['pic_teknisi'])
                                ? h($report['pic_teknisi'])
                                : '<span class="bd-value-muted">Tidak dicantumkan</span>' ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Tanggal Selesai</span>
                        <div class="bd-field-value">
                            <?= !empty($report['tanggal_selesai'])
                                ? h(detail_tanggal($report['tanggal_selesai']))
                                : '<span class="bd-value-muted">Belum selesai</span>' ?>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Bagian 2: informasi unit -->
        <section class="bd-card">
            <div class="bd-card-header">
                <div class="bd-heading-group">
                    <span class="bd-section-number">02</span>
                    <div>
                        <h2 class="bd-card-title">Identitas Unit</h2>
                        <p class="bd-card-caption">
                            Data unit yang terhubung dengan laporan ini.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bd-card-body">
                <div class="bd-fields">

                    <div class="bd-field">
                        <span class="bd-field-label">ID Unit</span>
                        <div class="bd-field-value">
                            <?= h($report['equipment_code']) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Nama Alat</span>
                        <div class="bd-field-value">
                            <?= h($report['equipment_name']) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Jenis Alat</span>
                        <div class="bd-field-value">
                            <?= h($report['equipment_type']) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Spesifikasi</span>
                        <div class="bd-field-value">
                            <?= h($report['spesifikasi']) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Merek / Model</span>
                        <div class="bd-field-value">
                            <?= h($report['brand_model']) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Nomor Seri</span>
                        <div class="bd-field-value">
                            <?= h($report['serial_number']) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Tahun Unit</span>
                        <div class="bd-field-value">
                            <?= h($report['equipment_year']) ?>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Lokasi / Unit Kerja</span>
                        <div class="bd-field-value">
                            <?= h($report['location']) ?>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Bagian 3: kronologi -->
        <section class="bd-card">
            <div class="bd-card-header">
                <div class="bd-history-heading">
                    <div class="bd-heading-group">
                        <span class="bd-section-number">03</span>
                        <div>
                            <h2 class="bd-card-title">
                                Kronologi Penanganan
                                <span class="bd-count"><?= count($history) ?></span>
                            </h2>
                            <p class="bd-card-caption">
                                Urutan aktivitas berdasarkan tanggal/periode dalam catatan.
                            </p>
                        </div>
                    </div>

                    <a href="riwayat.php?id=<?= (int)$report['id'] ?>"
                       class="bd-manage-link">
                        Kelola Riwayat
                    </a>
                </div>
            </div>

            <div class="bd-history-body">

                <?php if (empty($history)): ?>

                    <div class="bd-empty">
                        <strong>Belum ada riwayat penanganan.</strong><br>
                        Catatan kronologi belum tersimpan untuk laporan ini.
                        <a href="import_riwayat.php">Buka Import Riwayat</a>
                        jika data Excel belum dimasukkan.
                    </div>

                <?php else: ?>

                    <div class="bd-history-list">
                        <?php foreach ($history as $item): ?>
                            <article class="bd-history-item">

                                <div class="bd-history-track">
                                    <span class="bd-history-dot"></span>
                                </div>

                                <div class="bd-history-date">
                                    <?= h($item['tanggal_riwayat']) ?>
                                </div>

                                <div class="bd-history-content">
                                    <h3 class="bd-history-title">
                                        Catatan Penanganan <?= (int)$item['urutan'] ?>
                                    </h3>

                                    <p class="bd-history-description">
                                        <?= h($item['uraian']) ?>
                                    </p>

                                    <?php if (!empty($item['lampiran'])): ?>
                                        <div class="bd-attachments">
                                            <span class="bd-attachment">
                                                <?= h($item['lampiran']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </article>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

            </div>
        </section>

        <!-- Bagian 4: kondisi terakhir -->
        <section class="bd-card">
            <div class="bd-card-header">
                <div class="bd-heading-group">
                    <span class="bd-section-number">04</span>
                    <div>
                        <h2 class="bd-card-title">Kondisi Terakhir</h2>
                        <p class="bd-card-caption">
                            Status dan keterangan terkini pada laporan.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bd-card-body">

                <?php if ($riwayat_terakhir): ?>
                    <div class="bd-last-update">
                        <div class="bd-last-icon">!</div>
                        <div>
                            <p class="bd-last-label">Catatan Kronologi Terakhir</p>

                            <p class="bd-last-date">
                                <?= h($riwayat_terakhir['tanggal_riwayat']) ?>
                            </p>

                            <p class="bd-last-text">
                                <?= h($riwayat_terakhir['uraian']) ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bd-fields">

                    <div class="bd-field">
                        <span class="bd-field-label">Status Saat Ini</span>
                        <div class="bd-field-value">
                            <span class="bd-status <?= h($status_class) ?>">
                                <span class="bd-status-dot"></span>
                                <?= h($status) ?>
                            </span>
                        </div>
                    </div>

                    <div class="bd-field">
                        <span class="bd-field-label">Tanggal Selesai Keseluruhan</span>
                        <div class="bd-field-value">
                            <?= !empty($report['tanggal_selesai'])
                                ? h(detail_tanggal($report['tanggal_selesai']))
                                : 'Belum dinyatakan selesai' ?>
                        </div>
                    </div>

                    <div class="bd-field bd-field-full">
                        <span class="bd-field-label">Keterangan Laporan</span>
                        <div class="bd-field-value">
                            <?php if (!empty($report['keterangan'])): ?>
                                <?= h($report['keterangan']) ?>
                            <?php else: ?>
                                <span class="bd-value-muted">Tidak ada keterangan tambahan.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bd-field bd-field-full">
                        <span class="bd-field-label">Ringkasan Tindakan Perbaikan</span>
                        <div class="bd-field-value">
                            <?php if (!empty($report['tindakan_perbaikan'])): ?>
                                <?= h($report['tindakan_perbaikan']) ?>
                            <?php else: ?>
                                <span class="bd-value-muted">Tidak ada tindakan yang dicatat pada laporan utama.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <div class="bd-bottom-actions">
            <a href="index.php" class="bd-back">
                &larr; Kembali ke Breakdown &amp; Failure
            </a>
        </div>

    </div>
</main>

</body>
</html>
