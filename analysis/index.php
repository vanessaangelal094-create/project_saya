<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
date_default_timezone_set('Asia/Makassar');

/* FILTER */
$selected_location = trim($_GET['location'] ?? '');
$selected_month = $_GET['month'] ?? '09';
$selected_year = $_GET['year'] ?? '2026';

if (!preg_match('/^(0[1-9]|1[0-2])$/', $selected_month)) {
    $selected_month = '09';
}
if (!preg_match('/^\d{4}$/', $selected_year)) {
    $selected_year = '2026';
}

$start_date = $selected_year . '-' . $selected_month . '-01';
$end_date = date('Y-m-d', strtotime($start_date . ' +1 month'));
$days_in_month = (int) date('t', strtotime($start_date));

/* DAFTAR LOKASI */
$locations = [];

$location_result = $conn->query("
    SELECT DISTINCT location
    FROM equipment
    WHERE location IS NOT NULL AND location <> ''
    ORDER BY location ASC
");

if ($location_result) {
    while ($row = $location_result->fetch_assoc()) {
        $locations[] = $row['location'];
    }
}

/* DATA BBM */
$fuel_sql = "
    SELECT
        f.tanggal,
        f.equipment_id,
        f.jumlah_liter,
        f.hm_km,
        f.keterangan,
        e.equipment_code,
        e.equipment_name,
        e.location
    FROM fuel_usage f
    INNER JOIN equipment e ON e.id = f.equipment_id
    WHERE f.tanggal >= ? AND f.tanggal < ?
";

if ($selected_location !== '') {
    $fuel_sql .= " AND e.location = ?";
}

$fuel_sql .= " ORDER BY f.tanggal ASC";
$fuel_stmt = $conn->prepare($fuel_sql);

if ($selected_location !== '') {
    $fuel_stmt->bind_param("sss", $start_date, $end_date, $selected_location);
} else {
    $fuel_stmt->bind_param("ss", $start_date, $end_date);
}

$fuel_stmt->execute();
$fuel_result = $fuel_stmt->get_result();

/* DATA PELUMAS DALAM LITER */
$lubricant_sql = "
    SELECT
        l.tanggal,
        l.equipment_id,
        l.jenis_pelumas,
        l.jumlah,
        l.satuan,
        l.hm_km,
        l.keterangan,
        e.equipment_code,
        e.equipment_name,
        e.location
    FROM lubricant l
    INNER JOIN equipment e ON e.id = l.equipment_id
    WHERE l.tanggal >= ? AND l.tanggal < ?
      AND LOWER(TRIM(l.satuan)) IN ('liter', 'ltr', 'l')
";

if ($selected_location !== '') {
    $lubricant_sql .= " AND e.location = ?";
}

$lubricant_sql .= " ORDER BY l.tanggal ASC";
$lubricant_stmt = $conn->prepare($lubricant_sql);

if ($selected_location !== '') {
    $lubricant_stmt->bind_param("sss", $start_date, $end_date, $selected_location);
} else {
    $lubricant_stmt->bind_param("ss", $start_date, $end_date);
}

$lubricant_stmt->execute();
$lubricant_result = $lubricant_stmt->get_result();

/* DATA PENGGANTIAN BERDASARKAN JENIS */
$type_sql = "
    SELECT
        l.jenis_pelumas,
        COUNT(*) AS jumlah_penggantian
    FROM lubricant l
    INNER JOIN equipment e ON e.id = l.equipment_id
    WHERE l.tanggal >= ? AND l.tanggal < ?
      AND LOWER(TRIM(l.satuan)) IN ('liter', 'ltr', 'l')
";

if ($selected_location !== '') {
    $type_sql .= " AND e.location = ?";
}

$type_sql .= "
    GROUP BY l.jenis_pelumas
    ORDER BY jumlah_penggantian DESC
";

$type_stmt = $conn->prepare($type_sql);

if ($selected_location !== '') {
    $type_stmt->bind_param("sss", $start_date, $end_date, $selected_location);
} else {
    $type_stmt->bind_param("ss", $start_date, $end_date);
}

$type_stmt->execute();
$type_result = $type_stmt->get_result();

$type_labels = [];
$type_counts = [];

while ($row = $type_result->fetch_assoc()) {
    $type_labels[] = $row['jenis_pelumas'] ?: 'Tidak diketahui';
    $type_counts[] = (int) $row['jumlah_penggantian'];
}

/* =========================================================
   DATA HM PER UNIT (DIHITUNG OTOMATIS DARI FUEL & LUBRICANT)
   Menggantikan tabel analisis_hm yang belum ada
========================================================= */
$hm_by_unit = [];

// Cek apakah tabel analisis_hm ada di database
$check_table = $conn->query("SHOW TABLES LIKE 'analisis_hm'");
$has_analisis_hm_table = ($check_table && $check_table->num_rows > 0);

if ($has_analisis_hm_table) {
    $hm_result = $conn->query("
        SELECT
            h.id_unit,
            h.tanggal_hm_awal,
            h.tanggal_hm_akhir,
            h.hm_awal,
            h.hm_akhir,
            h.jam_operasi,
            e.id AS equipment_id,
            e.equipment_code,
            e.equipment_name,
            e.location
        FROM analisis_hm h
        LEFT JOIN equipment e
            ON UPPER(TRIM(e.equipment_code)) = UPPER(TRIM(h.id_unit))
        ORDER BY h.tanggal_hm_akhir DESC
    ");
} else {
    // Hitung otomatis dari riwayat hm_km di fuel_usage dan lubricant
    $hm_result = $conn->query("
        SELECT
            e.id AS equipment_id,
            e.equipment_code AS id_unit,
            e.equipment_code,
            e.equipment_name,
            e.location,
            MIN(combined.tanggal) AS tanggal_hm_awal,
            MAX(combined.tanggal) AS tanggal_hm_akhir,
            MIN(combined.hm_km) AS hm_awal,
            MAX(combined.hm_km) AS hm_akhir,
            (MAX(combined.hm_km) - MIN(combined.hm_km)) AS jam_operasi
        FROM (
            SELECT equipment_id, tanggal, hm_km FROM fuel_usage WHERE hm_km IS NOT NULL AND hm_km > 0
            UNION ALL
            SELECT equipment_id, tanggal, hm_km FROM lubricant WHERE hm_km IS NOT NULL AND hm_km > 0
        ) AS combined
        INNER JOIN equipment e ON e.id = combined.equipment_id
        GROUP BY e.id, e.equipment_code, e.equipment_name, e.location
    ");
}

if ($hm_result) {
    while ($hm_row = $hm_result->fetch_assoc()) {
        $hm_key = strtoupper(trim((string) $hm_row['id_unit']));

        if ($hm_key !== '' && !isset($hm_by_unit[$hm_key])) {
            $hm_by_unit[$hm_key] = $hm_row;
        }
    }
}

/* NILAI AWAL GRAFIK RATA-RATA DAN RINGKASAN HM */
$average_fuel_labels = [];
$average_fuel_data = [];
$average_lubricant_labels = [];
$average_lubricant_data = [];

$overall_fuel_liters = 0.0;
$overall_fuel_hours = 0.0;
$overall_lubricant_liters = 0.0;
$overall_lubricant_hours = 0.0;

$hm_units_for_average = [];

/* REKAP HARIAN DAN PER UNIT */
$daily = [];
$table_records = [];

for ($day = 1; $day <= $days_in_month; $day++) {
    $date = sprintf('%s-%s-%02d', $selected_year, $selected_month, $day);

    $daily[$date] = [
        'fuel' => 0,
        'lubricant' => 0
    ];
}

/* REKAP BBM */
$total_fuel = 0;

while ($row = $fuel_result->fetch_assoc()) {
    $date = $row['tanggal'];
    $equipment_id = $row['equipment_id'];
    $amount = (float) $row['jumlah_liter'];
    $key = $date . '_' . $equipment_id;

    $total_fuel += $amount;

    if (isset($daily[$date])) {
        $daily[$date]['fuel'] += $amount;
    }

    if (!isset($table_records[$key])) {
        $table_records[$key] = [
            'tanggal' => $date,
            'equipment_code' => $row['equipment_code'],
            'equipment_name' => $row['equipment_name'],
            'hm_km' => $row['hm_km'],
            'hm_awal' => null,
            'hm_akhir' => null,
            'jam_operasi' => null,
            'fuel' => 0,
            'lubricant' => 0,
            'notes' => []
        ];
    }

    $table_records[$key]['fuel'] += $amount;

    if (
        $table_records[$key]['hm_km'] === null ||
        $table_records[$key]['hm_km'] === ''
    ) {
        $table_records[$key]['hm_km'] = $row['hm_km'];
    }

    if (!empty($row['keterangan'])) {
        $table_records[$key]['notes'][] = $row['keterangan'];
    }
}

/* REKAP PELUMAS */
$total_lubricant = 0;

while ($row = $lubricant_result->fetch_assoc()) {
    $date = $row['tanggal'];
    $equipment_id = $row['equipment_id'];
    $amount = (float) $row['jumlah'];
    $key = $date . '_' . $equipment_id;

    $total_lubricant += $amount;

    if (isset($daily[$date])) {
        $daily[$date]['lubricant'] += $amount;
    }

    if (!isset($table_records[$key])) {
        $table_records[$key] = [
            'tanggal' => $date,
            'equipment_code' => $row['equipment_code'],
            'equipment_name' => $row['equipment_name'],
            'hm_km' => $row['hm_km'],
            'hm_awal' => null,
            'hm_akhir' => null,
            'jam_operasi' => null,
            'fuel' => 0,
            'lubricant' => 0,
            'notes' => []
        ];
    }

    $table_records[$key]['lubricant'] += $amount;

    if (
        $table_records[$key]['hm_km'] === null ||
        $table_records[$key]['hm_km'] === ''
    ) {
        $table_records[$key]['hm_km'] = $row['hm_km'];
    }

    if (!empty($row['keterangan'])) {
        $table_records[$key]['notes'][] =
            $row['jenis_pelumas'] . ': ' . $row['keterangan'];
    }
}

/* HUBUNGKAN HM TERBARU DENGAN BARIS KONSUMSI */
foreach ($table_records as &$record) {
    $unit_key = strtoupper(trim((string) $record['equipment_code']));

    if (isset($hm_by_unit[$unit_key])) {
        $record['hm_awal'] = $hm_by_unit[$unit_key]['hm_awal'];
        $record['hm_akhir'] = $hm_by_unit[$unit_key]['hm_akhir'];
        $record['jam_operasi'] = $hm_by_unit[$unit_key]['jam_operasi'];
    }
}
unset($record);

/* REKAP KONSUMSI PER ALAT */
$equipment_consumption = [];

foreach ($table_records as $record) {
    $code = $record['equipment_code'];

    if (!isset($equipment_consumption[$code])) {
        $equipment_consumption[$code] = [
            'name' => $record['equipment_name'],
            'fuel' => 0,
            'lubricant' => 0
        ];
    }

    $equipment_consumption[$code]['fuel'] += $record['fuel'];
    $equipment_consumption[$code]['lubricant'] += $record['lubricant'];
}

/* HM TERBARU DAN RATA-RATA KONSUMSI PER PERIODE HM */
$hm_seen_units = [];

$fuel_interval_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS record_count,
        COALESCE(SUM(jumlah_liter), 0) AS total_liter
    FROM fuel_usage
    WHERE equipment_id = ?
      AND tanggal >= ?
      AND tanggal < DATE_ADD(?, INTERVAL 1 DAY)
");

$lubricant_interval_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS record_count,
        COALESCE(SUM(jumlah), 0) AS total_liter
    FROM lubricant
    WHERE equipment_id = ?
      AND tanggal >= ?
      AND tanggal < DATE_ADD(?, INTERVAL 1 DAY)
      AND LOWER(TRIM(satuan)) IN ('liter', 'ltr', 'l')
");

if (!empty($hm_by_unit) && $fuel_interval_stmt && $lubricant_interval_stmt) {
    foreach ($hm_by_unit as $unit_key => $hm_interval) {
        if ($unit_key === '' || isset($hm_seen_units[$unit_key])) {
            continue;
        }

        $hm_start_date = $hm_interval['tanggal_hm_awal'] ?? '';
        $hm_end_date = $hm_interval['tanggal_hm_akhir'] ?? '';
        $hm_start_value = $hm_interval['hm_awal'] ?? null;
        $hm_end_value = $hm_interval['hm_akhir'] ?? null;

        // Filter periode bulan yang dipilih
        if ($hm_start_date >= $end_date || $hm_end_date < $start_date) {
            continue;
        }

        if (
            empty($hm_start_date) ||
            empty($hm_end_date) ||
            $hm_start_date > $hm_end_date ||
            !is_numeric($hm_start_value) ||
            !is_numeric($hm_end_value)
        ) {
            continue;
        }

        // Perbaikan syntax pengurangan HM Akhir - HM Awal
        $operating_hours = (float) $hm_end_value - (float) $hm_start_value;

        if ($operating_hours <= 0) {
            continue;
        }

        if (
            $selected_location !== '' &&
            (string) ($hm_interval['location'] ?? '') !== $selected_location
        ) {
            continue;
        }

        $hm_seen_units[$unit_key] = true;

        $unit_label = trim((string) ($hm_interval['equipment_code'] ?? ''));

        if ($unit_label === '') {
            $unit_label = trim((string) $hm_interval['id_unit']);
        }

        $hm_units_for_average[] = [
            'id_unit' => $unit_label,
            'hm_awal' => (float) $hm_start_value,
            'hm_akhir' => (float) $hm_end_value,
            'jam_operasi' => $operating_hours
        ];

        $equipment_id_for_hm = $hm_interval['equipment_id'] ?? null;

        if ($equipment_id_for_hm === null || $equipment_id_for_hm === '') {
            continue;
        }

        $equipment_id_for_hm = (int) $equipment_id_for_hm;

        $fuel_interval_stmt->bind_param(
            "iss",
            $equipment_id_for_hm,
            $hm_start_date,
            $hm_end_date
        );

        $fuel_interval_stmt->execute();
        $fuel_interval_row = $fuel_interval_stmt->get_result()->fetch_assoc();

        $fuel_record_count = (int) ($fuel_interval_row['record_count'] ?? 0);
        $fuel_liters = (float) ($fuel_interval_row['total_liter'] ?? 0);

        $average_fuel_labels[] = $unit_label;

        $average_fuel_data[] = $fuel_record_count > 0
            ? round($fuel_liters / $operating_hours, 4)
            : 0;

        if ($fuel_record_count > 0) {
            $overall_fuel_liters += $fuel_liters;
            $overall_fuel_hours += $operating_hours;
        }

        $lubricant_interval_stmt->bind_param(
            "iss",
            $equipment_id_for_hm,
            $hm_start_date,
            $hm_end_date
        );

        $lubricant_interval_stmt->execute();
        $lubricant_interval_row = $lubricant_interval_stmt->get_result()->fetch_assoc();

        if ((int) ($lubricant_interval_row['record_count'] ?? 0) > 0) {
            $lubricant_liters = (float) $lubricant_interval_row['total_liter'];

            $average_lubricant_labels[] = $unit_label;
            $average_lubricant_data[] = round($lubricant_liters / $operating_hours, 4);

            $overall_lubricant_liters += $lubricant_liters;
            $overall_lubricant_hours += $operating_hours;
        }
    }
}

if ($fuel_interval_stmt) {
    $fuel_interval_stmt->close();
}

if ($lubricant_interval_stmt) {
    $lubricant_interval_stmt->close();
}

$jumlah_hm = count($hm_units_for_average);
$hm_awal_total = 0.0;
$hm_akhir_total = 0.0;
$total_jam_operasi = 0.0;

foreach ($hm_units_for_average as $row) {
    $hm_awal_total += $row['hm_awal'];
    $hm_akhir_total += $row['hm_akhir'];
    $total_jam_operasi += $row['jam_operasi'];
}

$rata_hm_awal = $jumlah_hm > 0 ? $hm_awal_total / $jumlah_hm : 0;
$rata_hm_akhir = $jumlah_hm > 0 ? $hm_akhir_total / $jumlah_hm : 0;
$rata_jam_harian = $jumlah_hm > 0 ? $total_jam_operasi / $jumlah_hm : 0;

$overall_average_fuel = $overall_fuel_hours > 0
    ? $overall_fuel_liters / $overall_fuel_hours
    : 0;

$overall_average_lubricant = $overall_lubricant_hours > 0
    ? $overall_lubricant_liters / $overall_lubricant_hours
    : 0;

if (!empty($average_fuel_data)) {
    array_multisort($average_fuel_data, SORT_DESC, SORT_NUMERIC, $average_fuel_labels);
}

if (!empty($average_lubricant_data)) {
    array_multisort($average_lubricant_data, SORT_DESC, SORT_NUMERIC, $average_lubricant_labels);
}

/* URUTKAN BBM */
$equipment_fuel_sorted = $equipment_consumption;

uasort($equipment_fuel_sorted, function ($a, $b) {
    return $b['fuel'] <=> $a['fuel'];
});

$fuel_equipment_labels = [];
$fuel_equipment_data = [];

foreach ($equipment_fuel_sorted as $code => $data) {
    if (trim((string) $code) === '') {
        continue;
    }

    $fuel_equipment_labels[] = $code;
    $fuel_equipment_data[] = round($data['fuel'], 2);
}

/* URUTKAN PELUMAS */
$equipment_lubricant_sorted = $equipment_consumption;

uasort($equipment_lubricant_sorted, function ($a, $b) {
    return $b['lubricant'] <=> $a['lubricant'];
});

$lubricant_equipment_labels = [];
$lubricant_equipment_data = [];

foreach ($equipment_lubricant_sorted as $code => $data) {
    if (trim((string) $code) === '') {
        continue;
    }

    $lubricant_equipment_labels[] = $code;
    $lubricant_equipment_data[] = round($data['lubricant'], 2);
}

$unit_count = count(
    array_unique(array_column($table_records, 'equipment_code'))
);

usort($table_records, function ($a, $b) {
    if ($a['tanggal'] === $b['tanggal']) {
        return strcmp($a['equipment_code'], $b['equipment_code']);
    }

    return strcmp($b['tanggal'], $a['tanggal']);
});

$month_names = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analisis Operasional & Konsumsi</title>
<script>
    try {
        document.documentElement.setAttribute(
            'data-theme',
            localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'
        );
    } catch (e) {}
</script>
<link rel="stylesheet" href="../assets/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
* {
    box-sizing: border-box;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
}

.page-header h1 {
    font-size: 25px;
    margin: 0 0 8px;
}

.page-header p {
    margin: 0;
    font-size: 13px;
    color: #748195;
}

.back-link {
    color: #245ca6;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.filter-panel,
.panel,
.kpi-box {
    background: var(--bg-card, #fff);
    border: 1px solid var(--border-color, #e5eaf0);
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(28, 45, 70, 0.035);
}

.filter-panel {
    display: flex;
    align-items: end;
    gap: 14px;
    flex-wrap: wrap;
    padding: 18px;
    margin-bottom: 20px;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 145px;
}

.filter-field label {
    font-size: 12px;
    font-weight: 600;
    color: #536176;
}

.filter-field select {
    height: 39px;
    border: 1px solid #d9e0e8;
    border-radius: 7px;
    padding: 0 10px;
    background: #fff;
    color: #263445;
}

.btn {
    border: none;
    background: #245ca6;
    color: #fff;
    height: 39px;
    padding: 0 19px;
    border-radius: 7px;
    font-weight: 600;
    cursor: pointer;
}

.btn:hover {
    background: #194a8a;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.kpi-box {
    padding: 20px;
    min-height: 125px;
}

.kpi-label {
    color: #758195;
    font-size: 12px;
    margin-bottom: 15px;
}

.kpi-value {
    font-size: 25px;
    font-weight: 700;
    color: #22344d;
}

.kpi-note {
    font-size: 11px;
    color: #8b97a8;
    margin-top: 8px;
}

.panel {
    padding: 20px;
    margin-bottom: 20px;
    min-width: 0;
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.panel-heading h2 {
    font-size: 16px;
    margin: 0;
}

.panel-heading span {
    color: #7b8797;
    font-size: 11px;
}

.chart-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.chart-wrap {
    position: relative;
    height: 290px;
}

.chart-note {
    font-size: 11px;
    color: #8b97a8;
    margin-top: 10px;
}

.table-wrap {
    overflow-x: auto;
}

.text-right {
    text-align: right;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #7b8797;
}

@media (max-width: 1100px) {
    .kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .chart-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<?php
$base   = '../';
$active = 'dashboard';
include $base . 'includes/sidebar.php';
?>

<main class="main">
    <header class="page-header">
        <div>
            <h1>Analisis Operasional & Konsumsi</h1>
            <p>Ringkasan penggunaan BBM, pelumas, dan catatan unit alat berat.</p>
        </div>
        <a href="../index.php" class="back-link">← Kembali ke Dashboard</a>
    </header>

    <form method="GET" class="filter-panel">
        <div class="filter-field">
            <label for="location">Lokasi</label>
            <select name="location" id="location">
                <option value="">Semua Lokasi</option>
                <?php foreach ($locations as $location): ?>
                    <option
                        value="<?= htmlspecialchars($location) ?>"
                        <?= $selected_location === $location ? 'selected' : '' ?>>
                        <?= htmlspecialchars($location) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="month">Bulan</label>
            <select name="month" id="month">
                <?php foreach ($month_names as $number => $name): ?>
                    <option
                        value="<?= $number ?>"
                        <?= (string) $selected_month === (string) $number ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="year">Tahun</label>
            <select name="year" id="year">
                <?php for ($year = 2024; $year <= 2030; $year++): ?>
                    <option
                        value="<?= $year ?>"
                        <?= (int) $selected_year === $year ? 'selected' : '' ?>>
                        <?= $year ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <button type="submit" class="btn">Tampilkan Data</button>
    </form>

    <!-- KARTU RINGKASAN UTAMA -->
    <section class="kpi-grid">
        <div class="kpi-box">
            <div class="kpi-label">Total Penggunaan BBM</div>
            <div class="kpi-value">
                <?= number_format($total_fuel, 1, ',', '.') ?>
                <span style="font-size:13px;font-weight:500">Liter</span>
            </div>
            <div class="kpi-note">Akumulasi bulan terpilih</div>
        </div>

        <div class="kpi-box">
            <div class="kpi-label">Total Penggunaan Pelumas</div>
            <div class="kpi-value">
                <?= number_format($total_lubricant, 1, ',', '.') ?>
                <span style="font-size:13px;font-weight:500">Liter</span>
            </div>
            <div class="kpi-note">Pelumas dengan satuan liter</div>
        </div>

        <div class="kpi-box">
            <div class="kpi-label">Unit dengan Catatan Penggunaan</div>
            <div class="kpi-value"><?= number_format($unit_count) ?></div>
            <div class="kpi-note">Unit unik yang tercatat pada bulan terpilih</div>
        </div>

        <div class="kpi-box">
            <div class="kpi-label">Rata-rata Jam Operasi</div>
            <div class="kpi-value" style="font-size:22px">
                <?= $jumlah_hm > 0 ? number_format($rata_jam_harian, 1, ',', '.') . ' HM' : 'Belum tersedia' ?>
            </div>
            <div class="kpi-note">Berdasarkan selisih HM Awal & Akhir</div>
        </div>
    </section>   

    <!-- GRAFIK BBM DAN RATA-RATA KONSUMSI BBM -->
    <section class="chart-grid">
        <div class="panel">
            <div class="panel-heading">
                <h2>Konsumsi BBM per Alat</h2>
                <span>Liter</span>
            </div>
            <div class="chart-wrap"
                 style="height: <?= max(290, count($fuel_equipment_labels) * 27) ?>px;">
                <canvas id="equipmentFuelChart"></canvas>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <h2>Rata-rata Konsumsi BBM per Unit</h2>
                <span>Liter/HM</span>
            </div>
            <p class="chart-note">
                Total BBM pada rentang tanggal HM terbaru yang tercatat,
                dibagi selisih HM Awal dan HM Akhir.
            </p>
            <div class="chart-wrap"
                 style="height: <?= max(260, count($average_fuel_labels) * 27) ?>px;">
                <canvas id="averageFuelChart"></canvas>
            </div>
        </div>
    </section>

    <section class="chart-grid">
        <div class="panel">
            <div class="panel-heading">
                <h2>Konsumsi Pelumas per Alat</h2>
                <span>Liter</span>
            </div>
            <div class="chart-wrap"
                 style="height: <?= max(290, count($lubricant_equipment_labels) * 27) ?>px;">
                <canvas id="equipmentLubricantChart"></canvas>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <h2>Penggantian Berdasarkan Jenis</h2>
                <span>Jumlah catatan</span>
            </div>
            <div class="chart-wrap">
                <canvas id="lubricantTypeChart"></canvas>
            </div>
            <p class="chart-note">
                Dihitung berdasarkan jumlah catatan setiap jenis pelumas pada periode terpilih.
            </p>
        </div>
    </section>

    <!-- RINCIAN DATA AKTUAL -->
    <section class="panel">
        <div class="panel-heading">
            <h2>Rincian Konsumsi per Unit</h2>
            <span><?= $month_names[$selected_month] . ' ' . $selected_year ?></span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>ID Unit</th>
                        <th>Nama Equipment</th>
                        <th class="text-right">HM Awal</th>
                        <th class="text-right">HM Akhir</th>
                        <th class="text-right">Jam Operasi (HM)</th>
                        <th class="text-right">BBM (L)</th>
                        <th class="text-right">Pelumas (L)</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($table_records)): ?>
                    <tr>
                        <td colspan="9" class="empty">
                            Tidak ada data konsumsi untuk filter yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($table_records as $record): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($record['tanggal'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($record['equipment_code']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($record['equipment_name']) ?></td>
                            <td class="text-right">
                                <?= $record['hm_awal'] !== null
                                    ? number_format((float) $record['hm_awal'], 1, ',', '.')
                                    : '—' ?>
                            </td>
                            <td class="text-right">
                                <?= $record['hm_akhir'] !== null
                                    ? number_format((float) $record['hm_akhir'], 1, ',', '.')
                                    : '—' ?>
                            </td>
                            <td class="text-right">
                                <?= $record['jam_operasi'] !== null
                                    ? number_format((float) $record['jam_operasi'], 1, ',', '.')
                                    : '—' ?>
                            </td>
                            <td class="text-right">
                                <?= number_format($record['fuel'], 1, ',', '.') ?>
                            </td>
                            <td class="text-right">
                                <?= number_format($record['lubricant'], 1, ',', '.') ?>
                            </td>
                            <td>
                                <?= !empty($record['notes'])
                                    ? htmlspecialchars(implode('; ', array_unique($record['notes'])))
                                    : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
const equipmentFuelLabels = <?= json_encode($fuel_equipment_labels) ?>;
const equipmentFuelData = <?= json_encode($fuel_equipment_data) ?>;

const averageFuelLabels = <?= json_encode($average_fuel_labels) ?>;
const averageFuelData = <?= json_encode($average_fuel_data) ?>;

const equipmentLubricantLabels = <?= json_encode($lubricant_equipment_labels) ?>;
const equipmentLubricantData = <?= json_encode($lubricant_equipment_data) ?>;

const typeLabels = <?= json_encode($type_labels) ?>;
const typeCounts = <?= json_encode($type_counts) ?>;

const commonEquipmentOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    return context.parsed.x.toLocaleString('id-ID') + ' Liter';
                }
            }
        }
    },
    scales: {
        x: {
            beginAtZero: true,
            title: {
                display: true,
                text: 'Konsumsi (Liter)'
            }
        },
        y: {
            grid: {
                display: false
            }
        }
    }
};

/* GRAFIK KONSUMSI BBM BULANAN */
const fuelEl = document.getElementById('equipmentFuelChart');
if (fuelEl) {
    new Chart(fuelEl, {
        type: 'bar',
        data: {
            labels: equipmentFuelLabels,
            datasets: [{
                label: 'BBM',
                data: equipmentFuelData,
                backgroundColor: '#477fc1',
                borderRadius: 4
            }]
        },
        options: commonEquipmentOptions
    });
}

/* GRAFIK RATA-RATA BBM PER UNIT */
const avgFuelEl = document.getElementById('averageFuelChart');
if (avgFuelEl) {
    new Chart(avgFuelEl, {
        type: 'bar',
        data: {
            labels: averageFuelLabels,
            datasets: [{
                label: 'Rata-rata BBM',
                data: averageFuelData,
                backgroundColor: '#477fc1',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.x.toLocaleString('id-ID', {
                                maximumFractionDigits: 4
                            }) + ' Liter/HM';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Rata-rata (Liter/HM)'
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/* GRAFIK KONSUMSI PELUMAS BULANAN */
const lubEl = document.getElementById('equipmentLubricantChart');
if (lubEl) {
    new Chart(lubEl, {
        type: 'bar',
        data: {
            labels: equipmentLubricantLabels,
            datasets: [{
                label: 'Pelumas',
                data: equipmentLubricantData,
                backgroundColor: '#e5a04b',
                borderRadius: 4
            }]
        },
        options: commonEquipmentOptions
    });
}

/* GRAFIK JUMLAH CATATAN PER JENIS PELUMAS */
const typeEl = document.getElementById('lubricantTypeChart');
if (typeEl) {
    new Chart(typeEl, {
        type: 'doughnut',
        data: {
            labels: typeLabels,
            datasets: [{
                data: typeCounts,
                backgroundColor: [
                    '#36a2eb',
                    '#ff6384',
                    '#ff9f40',
                    '#ffcd56',
                    '#4bc0c0',
                    '#9966ff',
                    '#7c8da6',
                    '#c9a0dc'
                ],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' +
                                context.parsed.toLocaleString('id-ID') +
                                ' catatan';
                        }
                    }
                }
            }
        }
    });
}
</script>
</body>
</html>