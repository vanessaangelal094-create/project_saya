
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
date_default_timezone_set('Asia/Makassar');

/* =========================
   FILTER
========================= */
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

/* =========================
   DAFTAR LOKASI
========================= */
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

/* =========================
   DATA BBM
========================= */
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

/* =========================
   DATA PELUMAS DALAM LITER
========================= */
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

/* =========================
   DATA PENGGANTIAN BERDASARKAN JENIS
   Menghitung jumlah catatan per jenis,
   bukan jumlah liter.
========================= */
$type_sql = "
    SELECT
        l.jenis_pelumas,
        COUNT(*) AS jumlah_penggantian
    FROM lubricant l
    INNER JOIN equipment e ON e.id = l.equipment_id
    WHERE l.tanggal >= ? AND l.tanggal < ?
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

/* =========================
   REKAP HARIAN DAN PER UNIT
========================= */
$daily = [];
$table_records = [];

for ($day = 1; $day <= $days_in_month; $day++) {
    $date = sprintf(
        '%s-%s-%02d',
        $selected_year,
        $selected_month,
        $day
    );

    $daily[$date] = [
        'fuel' => 0,
        'lubricant' => 0
    ];
}

/* Rekap BBM */
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

/* Rekap pelumas */
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

/* =========================
   REKAP KONSUMSI PER ALAT
========================= */
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

/* Urutkan BBM dari terbesar ke terkecil */
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

/* Urutkan pelumas dari terbesar ke terkecil */
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

/* =========================
   DATA GRAFIK HARIAN
========================= */
$chart_labels = [];
$chart_fuel = [];
$chart_lubricant = [];

foreach ($daily as $date => $data) {
    $chart_labels[] = date('d', strtotime($date));
    $chart_fuel[] = round($data['fuel'], 2);
    $chart_lubricant[] = round($data['lubricant'], 2);
}

$unit_count = count(array_unique(array_column($table_records, 'equipment_code')));

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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #263445;
}

.sidebar {
    width: 245px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #172b4d;
    padding: 25px 16px;
    color: #fff;
    overflow-y: auto;
}

.brand {
    font-size: 18px;
    font-weight: 700;
    padding: 8px 12px 28px;
    line-height: 1.5;
    border-bottom: 1px solid #344762;
    margin-bottom: 20px;
}

.brand small {
    display: block;
    font-size: 11px;
    font-weight: 400;
    color: #b9c7dc;
}

.nav-title {
    font-size: 10px;
    color: #9eafc8;
    padding: 12px;
    letter-spacing: 1px;
}

.sidebar a {
    display: block;
    color: #dce5f2;
    text-decoration: none;
    padding: 12px;
    margin: 4px 0;
    border-radius: 7px;
    font-size: 13px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #29476f;
    color: #fff;
}

.main {
    margin-left: 245px;
    padding: 28px;
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
.kpi {
    background: #fff;
    border: 1px solid #e5eaf0;
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

.kpi {
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

.average-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.average-card {
    border: 1px solid #e5eaf0;
    border-radius: 9px;
    padding: 16px;
    min-height: 100px;
    background: #fff;
}

.average-label {
    font-size: 12px;
    color: #758195;
    margin-bottom: 12px;
}

.average-value {
    font-size: 16px;
    font-weight: 700;
    color: #22344d;
}

.chart-note {
    font-size: 11px;
    color: #8b97a8;
    margin-top: 10px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
    font-size: 12px;
}

thead {
    background: #f5f7fa;
}

th, td {
    padding: 13px 12px;
    border-bottom: 1px solid #e9edf2;
    text-align: left;
}

th {
    color: #667387;
    font-weight: 600;
}

td {
    color: #36465a;
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

@media (max-width: 700px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .main {
        margin-left: 0;
        padding: 16px;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .kpi-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .kpi {
        padding: 14px;
    }

    .kpi-value {
        font-size: 20px;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        PT BIRINGKASSI RAYA
        <small>Heavy Equipment Monitoring</small>
    </div>

    <div class="nav-title">MENU UTAMA</div>
    <a href="../index.php">Dashboard</a>
    <a href="../equipment/index.php">Equipment Master</a>
    <a href="../maintenance/index.php">Maintenance</a>
    <a href="../breakdown/index.php">Breakdown & Downtime</a>
    <a href="../sparepart/index.php">Spare Part</a>
    <a href="../lubricant/index.php">Lubricant</a>
    <a href="../fuel/index.php">Fuel Performance</a>
    <a href="index.php" class="active">Analisis Operasional</a>
</aside>

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
                        <?= $selected_month === $number ? 'selected' : '' ?>>
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

    <section class="kpi-grid">
        <div class="kpi">
            <div class="kpi-label">Total Penggunaan BBM</div>
            <div class="kpi-value">
                <?= number_format($total_fuel, 1, ',', '.') ?>
                <span style="font-size:13px;font-weight:500">Liter</span>
            </div>
            <div class="kpi-note">Akumulasi bulan terpilih</div>
        </div>

        <div class="kpi">
            <div class="kpi-label">Total Penggunaan Pelumas</div>
            <div class="kpi-value">
                <?= number_format($total_lubricant, 1, ',', '.') ?>
                <span style="font-size:13px;font-weight:500">Liter</span>
            </div>
            <div class="kpi-note">Pelumas dengan satuan liter</div>
        </div>

        <div class="kpi">
            <div class="kpi-label">Unit dengan Catatan Penggunaan</div>
            <div class="kpi-value"><?= number_format($unit_count) ?></div>
            <div class="kpi-note">Unit unik yang tercatat pada bulan terpilih</div>
        </div>

        <div class="kpi">
            <div class="kpi-label">HM Harian</div>
            <div class="kpi-value" style="font-size:20px">Belum tersedia</div>
            <div class="kpi-note">Menunggu pencatatan HM harian</div>
        </div>
    </section>

    <!-- BARIS 1: BBM PER ALAT DAN CONTOH RATA-RATA PEMAKAIAN -->
    <section class="chart-grid">
        <div class="panel">
            <div class="panel-heading">
                <h2>Konsumsi BBM per Alat</h2>
                <span>Liter</span>
            </div>
            <div class="chart-wrap">
                <canvas id="equipmentFuelChart"></canvas>
            </div>
        </div>

<div class="panel">
    <div class="panel-heading">
        <h2>Rata-rata Pemakaian</h2>
        <span>Data HM</span>
    </div>

    <div class="average-grid">
        <div class="average-card">
            <div class="average-label">HM Awal</div>
            <div class="average-value">Belum tersedia</div>
        </div>

        <div class="average-card">
            <div class="average-label">HM Akhir</div>
            <div class="average-value">Belum tersedia</div>
        </div>

        <div class="average-card">
            <div class="average-label">Total Jam Operasi</div>
            <div class="average-value">— jam</div>
        </div>

        <div class="average-card">
            <div class="average-label">Rata-rata Pemakaian</div>
            <div class="average-value">— jam/hari</div>
        </div>
    </div>

    <p class="chart-note">
        Perhitungan akan tersedia setelah data HM awal dan akhir
        serta hari operasi tercatat tersedia.
    </p>
</div>
    

    <!-- BARIS 2: PELUMAS PER ALAT DAN PENGGANTIAN BERDASARKAN JENIS -->
    <section class="chart-grid">
        <div class="panel">
            <div class="panel-heading">
                <h2>Konsumsi Pelumas per Alat</h2>
                <span>Liter</span>
            </div>
            <div class="chart-wrap">
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
                Dihitung dari jumlah catatan setiap jenis pelumas pada periode terpilih.
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
                        <th>HM Tercatat</th>
                        <th class="text-right">BBM (L)</th>
                        <th class="text-right">Pelumas (L)</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($table_records)): ?>
                    <tr>
                        <td colspan="7" class="empty">
                            Tidak ada data konsumsi untuk filter yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($table_records as $record): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($record['tanggal'])) ?></td>
                            <td><strong><?= htmlspecialchars($record['equipment_code']) ?></strong></td>
                            <td><?= htmlspecialchars($record['equipment_name']) ?></td>
                            <td>
                                <?= ($record['hm_km'] !== null && $record['hm_km'] !== '')
                                    ? htmlspecialchars((string) $record['hm_km'])
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

/* Grafik aktual: konsumsi BBM per alat */
new Chart(document.getElementById('equipmentFuelChart'), {
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

/* Grafik aktual: konsumsi pelumas per alat */
new Chart(document.getElementById('equipmentLubricantChart'), {
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

/* Grafik aktual: jumlah catatan per jenis pelumas.
   Tidak difilter hanya ke satuan liter karena menghitung catatan penggantian,
   bukan volume. */
new Chart(document.getElementById('lubricantTypeChart'), {
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
</script>

</body>
</html>
