
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
date_default_timezone_set('Asia/Makassar');

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

    return $timestamp === false ? '-' : date('d-m-Y H:i', $timestamp);
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

/* FILTER */
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

/* DAFTAR LOKASI */
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

/* RINGKASAN BREAKDOWN */
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
        SUM(CASE WHEN b.status = 'Dilaporkan' THEN 1 ELSE 0 END) AS dilaporkan,
        SUM(CASE WHEN b.status = 'Dalam Penanganan' THEN 1 ELSE 0 END) AS penanganan,
        SUM(CASE WHEN b.status = 'Menunggu Spare Part' THEN 1 ELSE 0 END) AS menunggu,
        SUM(CASE WHEN b.status = 'Selesai' THEN 1 ELSE 0 END) AS selesai
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
        $stats_data = $stats_result ? $stats_result->fetch_assoc() : null;

        if ($stats_data) {
            foreach ($stats as $key => $value) {
                $stats[$key] = (int)($stats_data[$key] ?? 0);
            }
        }
    }

    $stats_stmt->close();
}

/* DATA LAPORAN BREAKDOWN */
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

$filter_aktif = ($selected_location !== '' || $selected_status !== '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Breakdown &amp; Failure | Monitoring Alat Berat</title>

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>
        .section-sub {
            margin-top: 2px;
            font-size: 13px;
            color: var(--muted);
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
            padding: 18px 20px;
        }

        .filter-form .filter-group {
            min-width: 210px;
        }

        .unit-name {
            font-weight: 600;
            color: var(--ink);
            margin: 2px 0;
        }

        .damage-name {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
            color: var(--ink);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-dilaporkan {
            background: var(--amber-soft);
            color: var(--amber);
        }

        .status-penanganan {
            background: var(--blue-soft);
            color: var(--blue);
        }

        .status-menunggu {
            background: var(--orange-soft);
            color: var(--orange);
        }

        .status-selesai {
            background: var(--green-soft);
            color: var(--green);
        }

        .status-default {
            background: #eeeef5;
            color: var(--ink-2);
        }

        .btn-detail {
            display: inline-block;
            padding: 7px 12px;
            border: 1px solid var(--blue);
            border-radius: 7px;
            color: var(--blue);
            background: #fff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-detail:hover {
            background: var(--blue-soft);
        }

        .empty-row {
            padding: 30px 20px;
            text-align: center;
            color: var(--muted);
            white-space: normal;
        }

        .result-info {
            padding: 14px 20px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 13px;
        }

        .footer-note {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 650px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-form .filter-group {
                min-width: 0;
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

    <div class="header">
        <div class="header-left">
            <h1>Breakdown &amp; Failure</h1>
            <p>Monitoring laporan kerusakan, proses perbaikan, dan downtime alat berat.</p>
        </div>

        <a href="tambah.php" class="btn-primary">
            + Tambah Laporan
        </a>
    </div>

    <!-- KARTU RINGKASAN -->
    <section class="summary-grid">

        <div class="summary-card">
            <div class="summary-icon icon-blue">📋</div>
            <div>
                <div class="summary-title">Total Laporan Breakdown</div>
                <div class="summary-number">
                    <?= number_format($stats['total'], 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon icon-yellow">📣</div>
            <div>
                <div class="summary-title">Dilaporkan</div>
                <div class="summary-number number-yellow">
                    <?= number_format($stats['dilaporkan'], 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon icon-blue">🔧</div>
            <div>
                <div class="summary-title">Dalam Penanganan</div>
                <div class="summary-number">
                    <?= number_format($stats['penanganan'], 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon icon-orange">⏳</div>
            <div>
                <div class="summary-title">Menunggu Spare Part</div>
                <div class="summary-number number-orange">
                    <?= number_format($stats['menunggu'], 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon icon-green">✓</div>
            <div>
                <div class="summary-title">Selesai</div>
                <div class="summary-number number-green">
                    <?= number_format($stats['selesai'], 0, ',', '.') ?>
                </div>
            </div>
        </div>

    </section>

    <!-- FILTER -->
    <section class="section">
        <div class="section-header">
            <div>
                <h3>Filter Laporan</h3>
                <p class="section-sub">
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

            <a href="index.php" class="btn-reset">Reset</a>
        </form>
    </section>

    <!-- TABEL LAPORAN -->
    <section class="section">
        <div class="section-header">
            <div>
                <h3>Riwayat Breakdown &amp; Failure</h3>
                <p class="section-sub">
                    Daftar kerusakan dan status penanganan setiap unit alat berat.
                </p>
            </div>
        </div>

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
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($reports)): ?>

                        <?php foreach ($reports as $index => $report): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td>
                                    <?= breakdown_h(
                                        breakdown_tanggal($report['tanggal_kejadian'])
                                    ) ?>
                                </td>

                                <td>
                                    <div>
                                        <strong>
                                            <?= breakdown_h($report['equipment_code']) ?>
                                        </strong>
                                    </div>
                                    <div class="unit-name">
                                        <?= breakdown_h($report['equipment_name']) ?>
                                    </div>
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

                                <td>
                                    <a
                                        href="detail.php?id=<?= (int)$report['id'] ?>"
                                        class="btn-detail"
                                    >
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="11" class="empty-row">
                                Belum ada laporan breakdown.
                                Klik Tambah Laporan untuk memasukkan data.
                            </td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="result-info">
            Menampilkan <?= count($reports) ?> laporan breakdown.
            <?php if ($filter_aktif): ?>
                Filter sedang diterapkan.
            <?php endif; ?>
        </div>
    </section>

    <p class="footer-note">
        Catatan: Downtime dihitung sejak waktu kejadian hingga waktu selesai perbaikan.
        Untuk laporan yang belum selesai, perhitungan berlangsung hingga waktu saat ini.
    </p>

</main>
</body>
</html>
