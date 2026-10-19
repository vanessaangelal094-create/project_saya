<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once "../config/database.php";
date_default_timezone_set('Asia/Makassar');
/* =========================
   FUNGSI BANTUAN
========================= */
function breakdown_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function breakdown_tanggal($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }
    $timestamp = strtotime($tanggal);
    if ($timestamp === false) {
        return '-';
    }
    return date('d-m-Y H:i', $timestamp);
}
function breakdown_downtime($menit)
{
    if ($menit === null || $menit === '') {
        return '-';
    }
    $menit = max(0, (int)$menit);
    $jam = intdiv($menit, 60);
    $sisa_menit = $menit % 60;
    if ($jam > 0) {
        return $jam . ' jam ' . $sisa_menit . ' menit';
    }
    return $sisa_menit . ' menit';
}
function breakdown_status_class($status)
{
    switch ($status) {
        case 'Dilaporkan':
            return 'status-dilaporkan';
        case 'Dalam Penanganan':
            return 'status-penanganan';
        case 'Menunggu Spare Part':
            return 'status-menunggu';
        case 'Selesai':
            return 'status-selesai';
        default:
            return 'status-default';
    }
}
/* =========================
   FILTER
========================= */
$selected_location = trim($_GET['lokasi'] ?? '');
$selected_status = trim($_GET['status'] ?? '');
$status_options = [
    'Dilaporkan',
    'Dalam Penanganan',
    'Menunggu Spare Part',
    'Selesai'
];
if (!in_array($selected_status, $status_options, true)) {
    $selected_status = '';
}
/* =========================
   DAFTAR LOKASI
========================= */
$locations = [];
$location_sql = "
    SELECT DISTINCT location
    FROM equipment
    WHERE location IS NOT NULL
      AND TRIM(location) <> ''
    ORDER BY location ASC
";
$location_result = $conn->query($location_sql);
if ($location_result) {
    while ($location_row = $location_result->fetch_assoc()) {
        $locations[] = $location_row['location'];
    }
}
/* =========================
   RINGKASAN BREAKDOWN
========================= */
$stats = [
    'total' => 0,
    'dilaporkan' => 0,
    'penanganan' => 0,
    'menunggu' => 0,
    'selesai' => 0
];
$stats_sql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE
            WHEN b.status = 'Dilaporkan' THEN 1
            ELSE 0
        END) AS dilaporkan,
        SUM(CASE
            WHEN b.status = 'Dalam Penanganan' THEN 1
            ELSE 0
        END) AS penanganan,
        SUM(CASE
            WHEN b.status = 'Menunggu Spare Part' THEN 1
            ELSE 0
        END) AS menunggu,
        SUM(CASE
            WHEN b.status = 'Selesai' THEN 1
            ELSE 0
        END) AS selesai
    FROM breakdown_reports b
    INNER JOIN equipment e ON b.equipment_id = e.id
";
if ($selected_location !== '') {
    $stats_sql .= " WHERE e.location = ?";
}
$stats_stmt = $conn->prepare($stats_sql);
if ($stats_stmt) {
    if ($selected_location !== '') {
        $stats_stmt->bind_param("s", $selected_location);
    }
    if ($stats_stmt->execute()) {
        $stats_result = $stats_stmt->get_result();
        if ($stats_result) {
            $stats_data = $stats_result->fetch_assoc();
            if ($stats_data) {
                $stats['total'] = (int)($stats_data['total'] ?? 0);
                $stats['dilaporkan'] = (int)($stats_data['dilaporkan'] ?? 0);
                $stats['penanganan'] = (int)($stats_data['penanganan'] ?? 0);
                $stats['menunggu'] = (int)($stats_data['menunggu'] ?? 0);
                $stats['selesai'] = (int)($stats_data['selesai'] ?? 0);
            }
        }
    }
    $stats_stmt->close();
}
/* =========================
   DATA LAPORAN BREAKDOWN
========================= */
$reports = [];
$reports_sql = "
    SELECT
        b.id,
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
        e.location,
        CASE
            WHEN b.tanggal_selesai IS NOT NULL
            THEN TIMESTAMPDIFF(
                MINUTE,
                b.tanggal_kejadian,
                b.tanggal_selesai
            )
            ELSE TIMESTAMPDIFF(
                MINUTE,
                b.tanggal_kejadian,
                NOW()
            )
        END AS downtime_menit
    FROM breakdown_reports b
    INNER JOIN equipment e ON b.equipment_id = e.id
    WHERE 1 = 1
";
$report_types = '';
$report_params = [];
if ($selected_location !== '') {
    $reports_sql .= " AND e.location = ?";
    $report_types .= 's';
    $report_params[] = $selected_location;
}
if ($selected_status !== '') {
    $reports_sql .= " AND b.status = ?";
    $report_types .= 's';
    $report_params[] = $selected_status;
}
$reports_sql .= " ORDER BY b.tanggal_kejadian DESC, b.id DESC";
$reports_stmt = $conn->prepare($reports_sql);
if ($reports_stmt) {
    if (!empty($report_params)) {
        $reports_stmt->bind_param($report_types, ...$report_params);
    }
    if ($reports_stmt->execute()) {
        $reports_result = $reports_stmt->get_result();
        if ($reports_result) {
            while ($report_row = $reports_result->fetch_assoc()) {
                $reports[] = $report_row;
            }
        }
    }
    $reports_stmt->close();
}
/* =========================
   PESAN FILTER AKTIF
========================= */
$filter_aktif = ($selected_location !== '' || $selected_status !== '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Breakdown &amp; Failure | Monitoring Alat Berat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #253247;
            font-size: 14px;
        }
        /* =========================
           SIDEBAR - STANDARD
           JANGAN DIUBAH
        ========================= */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background: #0759bd;
            color: white;
            padding: 25px 15px;
            overflow-y: auto;
            z-index: 1000;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 35px;
            padding-left: 10px;
        }
        .logo img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }
        .logo-text {
            font-size: 13px;
            font-weight: bold;
            line-height: 1.3;
        }
        .menu {
            list-style: none;
        }
        .menu li {
            margin-bottom: 7px;
        }
        .menu a {
            display: block;
            padding: 12px 14px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            transition: 0.2s;
        }
        .menu a:hover,
        .menu a.active {
            background: rgba(255, 255, 255, 0.18);
        }
        .menu-arrow {
            margin-left: 6px;
            font-size: 11px;
        }
        .submenu {
            list-style: none;
            padding-left: 13px;
            margin-top: 5px;
        }
        .submenu li {
            margin-bottom: 4px;
        }
        .submenu a {
            font-size: 12px;
            padding: 10px 12px;
        }
        /* =========================
           KONTEN UTAMA
        ========================= */
        .main-content {
            margin-left: 240px;
            padding: 30px;
            min-height: 100vh;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }
        .page-title {
            font-size: 25px;
            font-weight: 700;
            color: #172b4d;
            margin-bottom: 8px;
        }
        .page-description {
            color: #78869b;
            font-size: 13px;
            line-height: 1.6;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 17px;
            background: #0759bd;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #064a9c;
        }
        /* =========================
           KARTU RINGKASAN
        ========================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e6ebf2;
            min-height: 125px;
        }
        .stat-label {
            font-size: 12px;
            color: #78869b;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .stat-value {
            font-size: 29px;
            font-weight: 700;
            color: #172b4d;
        }
        .stat-card.total {
            border-top: 4px solid #0759bd;
        }
        .stat-card.dilaporkan {
            border-top: 4px solid #e3a008;
        }
        .stat-card.penanganan {
            border-top: 4px solid #2684ff;
        }
        .stat-card.menunggu {
            border-top: 4px solid #e87518;
        }
        .stat-card.selesai {
            border-top: 4px solid #19945b;
        }
        /* =========================
           PANEL DAN FILTER
        ========================= */
        .panel {
            background: #ffffff;
            border: 1px solid #e6ebf2;
            border-radius: 12px;
            margin-bottom: 22px;
            overflow: hidden;
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 20px 22px;
            border-bottom: 1px solid #edf0f5;
        }
        .panel-title {
            font-size: 16px;
            font-weight: 700;
            color: #172b4d;
        }
        .panel-subtitle {
            color: #8995a8;
            font-size: 12px;
            margin-top: 6px;
            line-height: 1.5;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 13px;
            padding: 20px 22px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 200px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #526176;
        }
        .filter-group select {
            min-width: 200px;
            padding: 11px 12px;
            border: 1px solid #d9e0e9;
            border-radius: 7px;
            background: #ffffff;
            color: #253247;
            font-size: 13px;
            outline: none;
        }
        .filter-group select:focus {
            border-color: #0759bd;
        }
        .btn-filter,
        .btn-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-filter {
            background: #0759bd;
            color: #ffffff;
            border: 1px solid #0759bd;
        }
        .btn-reset {
            background: #ffffff;
            color: #526176;
            border: 1px solid #d9e0e9;
        }
        .result-info {
            padding: 0 22px 18px;
            color: #78869b;
            font-size: 12px;
        }
        /* =========================
           TABEL LAPORAN
        ========================= */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }
        thead {
            background: #f7f9fc;
        }
        th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #718096;
            font-weight: 700;
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid #e8edf3;
        }
        td {
            padding: 16px 14px;
            font-size: 12px;
            color: #40516a;
            border-bottom: 1px solid #edf0f5;
            vertical-align: middle;
        }
        tbody tr:hover {
            background: #fbfcfe;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        .unit-code {
            font-weight: 700;
            color: #0759bd;
        }
        .unit-name {
            font-weight: 600;
            color: #253247;
            margin-bottom: 5px;
        }
        .muted-text {
            color: #8995a8;
            font-size: 11px;
        }
        .damage-name {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
            color: #253247;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-dilaporkan {
            background: #fff5d9;
            color: #986800;
        }
        .status-penanganan {
            background: #e8f2ff;
            color: #0759bd;
        }
        .status-menunggu {
            background: #fff0e4;
            color: #b65411;
        }
        .status-selesai {
            background: #e3f7ed;
            color: #167347;
        }
        .status-default {
            background: #edf0f5;
            color: #526176;
        }
        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }
        .empty-icon {
            font-size: 35px;
            margin-bottom: 15px;
        }
        .empty-title {
            font-size: 16px;
            font-weight: 700;
            color: #34445b;
            margin-bottom: 8px;
        }
        .empty-description {
            color: #8995a8;
            font-size: 13px;
            line-height: 1.7;
            max-width: 480px;
            margin: 0 auto 20px;
        }
        .footer-note {
            margin-top: 18px;
            color: #8995a8;
            font-size: 11px;
            line-height: 1.7;
        }
        /* =========================
           RESPONSIVE
        ========================= */
        @media (max-width: 1250px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .page-title {
                font-size: 22px;
            }
        }
        @media (max-width: 620px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
            .main-content {
                margin-left: 0;
                padding: 18px;
            }
            .page-header {
                flex-direction: column;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .stat-card {
                padding: 15px;
                min-height: 105px;
            }
            .stat-value {
                font-size: 24px;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group,
            .filter-group select {
                width: 100%;
                min-width: 0;
            }
            .btn-filter,
            .btn-reset {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- =========================
         SIDEBAR
    ========================= -->
    <aside class="sidebar">
        <div class="logo">
            <img src="../assets/logo.png" alt="Logo PT Biringkassi Raya">
            <div class="logo-text">
                PT BIRINGKASSI RAYA
            </div>
        </div>
        <ul class="menu">
            <li>
                <a href="../index.php">Dashboard</a>
            </li>
            <li>
                <a href="../equipment/index.php">Equipment Master</a>
            </li>
            <li>
                <a href="../equipment/index.php">Status Unit</a>
            </li>
            <li>
                <a href="../maintenance/index.php">Jadwal Pemeliharaan</a>
            </li>
            <li>
                <a href="../maintenance/history/index.php">Histori Pemeliharaan</a>
            </li>
            <li>
                <a href="index.php" class="active">Breakdown &amp; Failure</a>
            </li>
            <li class="menu-dropdown">
                <a href="javascript:void(0);" onclick="
                    var submenu = document.getElementById('spareSubmenu');
                    var arrow = document.getElementById('spareArrow');
                    if (submenu.style.display === 'none' || submenu.style.display === '') {
                        submenu.style.display = 'block';
                        arrow.textContent = '▼';
                    } else {
                        submenu.style.display = 'none';
                        arrow.textContent = '▶';
                    }
                ">
                    Spare Part &amp; Lubricant
                    <span id="spareArrow" class="menu-arrow">▶</span>
                </a>
                <ul id="spareSubmenu" class="submenu" style="display: none;">
                    <li>
                        <a href="../sparepart/index.php">Spare Part</a>
                    </li>
                    <li>
                        <a href="../lubricant/index.php">Lubricant</a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="../fuel/index.php">Fuel Performance</a>
            </li>
            <li>
                <a href="#">Biaya Pemeliharaan</a>
            </li>
            <li>
                <a href="#">Kinerja &amp; Evaluasi</a>
            </li>
            <li>
                <a href="#">Laporan</a>
            </li>
        </ul>
    </aside>
    <!-- KONTEN UTAMA -->
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Breakdown &amp; Failure</h1>
                <p class="page-description">
                    Monitoring laporan kerusakan, proses perbaikan, dan downtime alat berat.
                </p>
            </div>
            <a href="tambah.php" class="btn-primary">
                <span>+</span>
                <span>Tambah Laporan</span>
            </a>
        </div>
        <!-- KARTU RINGKASAN -->
        <section class="stats-grid">
            <div class="stat-card total">
                <div class="stat-label">Total Laporan Breakdown</div>
                <div class="stat-value">
                    <?= number_format($stats['total'], 0, ',', '.') ?>
                </div>
            </div>
            <div class="stat-card dilaporkan">
                <div class="stat-label">Dilaporkan</div>
                <div class="stat-value">
                    <?= number_format($stats['dilaporkan'], 0, ',', '.') ?>
                </div>
            </div>
            <div class="stat-card penanganan">
                <div class="stat-label">Dalam Penanganan</div>
                <div class="stat-value">
                    <?= number_format($stats['penanganan'], 0, ',', '.') ?>
                </div>
            </div>
            <div class="stat-card menunggu">
                <div class="stat-label">Menunggu Spare Part</div>
                <div class="stat-value">
                    <?= number_format($stats['menunggu'], 0, ',', '.') ?>
                </div>
            </div>
            <div class="stat-card selesai">
                <div class="stat-label">Selesai</div>
                <div class="stat-value">
                    <?= number_format($stats['selesai'], 0, ',', '.') ?>
                </div>
            </div>
        </section>
        <!-- FILTER -->
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Filter Laporan</h2>
                    <p class="panel-subtitle">
                        Saring laporan berdasarkan lokasi unit dan status penanganan.
                    </p>
                </div>
            </div>
            <form action="index.php" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="lokasi">Lokasi / Unit Kerja</label>
                    <select name="lokasi" id="lokasi">
                        <option value="">Semua Lokasi</option>
                        <?php foreach ($locations as $location): ?>
                            <option
                                value="<?= breakdown_h($location) ?>"
                                <?= $selected_location === $location ? 'selected' : '' ?>
                            >
                                <?= breakdown_h($location) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status">Status Penanganan</label>
                    <select name="status" id="status">
                        <option value="">Semua Status</option>
                        <?php foreach ($status_options as $status_option): ?>
                            <option
                                value="<?= breakdown_h($status_option) ?>"
                                <?= $selected_status === $status_option ? 'selected' : '' ?>
                            >
                                <?= breakdown_h($status_option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">
                    Terapkan Filter
                </button>
                <a href="index.php" class="btn-reset">
                    Reset
                </a>
            </form>
        </section>
                <!-- TABEL LAPORAN -->
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Riwayat Breakdown &amp; Failure</h2>
                    <p class="panel-subtitle">
                        Daftar kerusakan dan status penanganan setiap unit alat berat.
                    </p>
                </div>
            </div>
            <?php if (count($reports) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Tanggal Kejadian</th>
                                <th>Unit Alat Berat</th>
                                <th>Lokasi</th>
                                <th>HM</th>
                                <th>Jenis Kerusakan</th>
                                <th>PIC / Teknisi</th>
                                <th>Downtime</th>
                                <th>Status</th>
                                <th>Tanggal Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $index => $report): ?>
                                <tr>
                                    <td>
                                        <?= $index + 1 ?>
                                    </td>
                                    <td>
                                        <?= breakdown_h(
                                            breakdown_tanggal($report['tanggal_kejadian'])
                                        ) ?>
                                    </td>
                                    <td>
                                        <div class="unit-code">
                                            <?= breakdown_h($report['equipment_code']) ?>
                                        </div>
                                        <div class="unit-name">
                                            <?= breakdown_h($report['equipment_name']) ?>
                                        </div>
                                        <?php if (!empty($report['equipment_type'])): ?>
                                            <div class="muted-text">
                                                <?= breakdown_h($report['equipment_type']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= breakdown_h($report['location'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <?= $report['hm_km'] !== null
                                            ? number_format((float)$report['hm_km'], 2, ',', '.')
                                            : '-' ?>
                                    </td>
                                    <td>
                                        <div
                                            class="damage-name"
                                            title="<?= breakdown_h($report['jenis_kerusakan']) ?>"
                                        >
                                            <?= breakdown_h($report['jenis_kerusakan']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= breakdown_h($report['pic_teknisi'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <?= breakdown_h(
                                            breakdown_downtime($report['downtime_menit'])
                                        ) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= breakdown_status_class($report['status']) ?>">
                                            <?= breakdown_h($report['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= breakdown_h(
                                            breakdown_tanggal($report['tanggal_selesai'])
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="result-info">
                    Menampilkan <?= count($reports) ?> laporan breakdown.
                    <?php if ($filter_aktif): ?>
                        Filter sedang diterapkan.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3 class="empty-title">
                        Belum Ada Laporan Breakdown
                    </h3>
                    <p class="empty-description">
                        Belum terdapat laporan kerusakan yang sesuai dengan filter.
                        Kamu dapat menambahkan laporan ketika data kerusakan alat berat
                        sudah tersedia.
                    </p>
                    <a href="tambah.php" class="btn-primary">
                        + Tambah Laporan
                    </a>
                </div>
            <?php endif; ?>
        </section>
        <p class="footer-note">
            Catatan: Downtime dihitung sejak waktu kejadian hingga waktu selesai perbaikan.
            Untuk laporan yang belum selesai, perhitungan berlangsung hingga waktu saat ini.
        </p>
    </main>
</body>
</html>