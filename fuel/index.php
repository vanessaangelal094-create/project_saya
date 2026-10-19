<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$selected_location = trim($_GET['location'] ?? '');
$selected_equipment = (int)($_GET['equipment_id'] ?? 0);

$equipment_options = [];
$locations = [];
$fuel_data = [];

$total_liter = 0;
$this_month_liter = 0;
$average_liter = 0;
$error_message = '';

/* =========================
   AMBIL PILIHAN LOKASI
========================= */
$location_query = $conn->query("
    SELECT DISTINCT location
    FROM equipment
    WHERE location IS NOT NULL
      AND TRIM(location) <> ''
    ORDER BY location ASC
");

if ($location_query) {
    while ($row = $location_query->fetch_assoc()) {
        $locations[] = $row['location'];
    }
}

/* =========================
   AMBIL PILIHAN EQUIPMENT
========================= */
$equipment_sql = "
    SELECT id, equipment_code, equipment_name, location
    FROM equipment
";

if ($selected_location !== '') {
    $equipment_sql .= " WHERE location = ? ";
}

$equipment_sql .= " ORDER BY equipment_code ASC";

$equipment_stmt = $conn->prepare($equipment_sql);

if ($equipment_stmt) {
    if ($selected_location !== '') {
        $equipment_stmt->bind_param("s", $selected_location);
    }

    if ($equipment_stmt->execute()) {
        $equipment_result = $equipment_stmt->get_result();

        while ($equipment_row = $equipment_result->fetch_assoc()) {
            $equipment_options[] = $equipment_row;
        }
    }

    $equipment_stmt->close();
}

/* Pastikan equipment sesuai lokasi yang dipilih */
if ($selected_equipment > 0) {
    $equipment_valid = false;

    foreach ($equipment_options as $equipment_option) {
        if ((int)$equipment_option['id'] === $selected_equipment) {
            $equipment_valid = true;
            break;
        }
    }

    if (!$equipment_valid) {
        $selected_equipment = 0;
    }
}

/* =========================
   AMBIL DATA BBM
========================= */
$sql = "
    SELECT
        f.id,
        f.tanggal,
        f.jumlah_liter,
        f.hm_km,
        f.keterangan,
        e.equipment_code,
        e.equipment_name,
        e.location
    FROM fuel_usage f
    INNER JOIN equipment e ON e.id = f.equipment_id
";

$where_conditions = [];
$bind_types = '';
$bind_values = [];

if ($selected_location !== '') {
    $where_conditions[] = "e.location = ?";
    $bind_types .= 's';
    $bind_values[] = $selected_location;
}

if ($selected_equipment > 0) {
    $where_conditions[] = "e.id = ?";
    $bind_types .= 'i';
    $bind_values[] = $selected_equipment;
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY f.tanggal DESC, f.id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if ($bind_types !== '') {
        $stmt->bind_param($bind_types, ...$bind_values);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $fuel_data[] = $row;
        }
    } else {
        $error_message = "Data BBM gagal dibaca dari database.";
    }

    $stmt->close();
} else {
    $error_message = "Query data BBM gagal diproses.";
}

/* =========================
   HITUNG RINGKASAN
========================= */
$current_month = date('Y-m');

$monthly_usage = [];
$equipment_usage = [];

foreach ($fuel_data as $item) {
    $liter = (float)$item['jumlah_liter'];
    $total_liter += $liter;

    if (!empty($item['tanggal'])) {
        $month_key = date('Y-m', strtotime($item['tanggal']));

        if ($month_key === $current_month) {
            $this_month_liter += $liter;
        }

        if (!isset($monthly_usage[$month_key])) {
            $monthly_usage[$month_key] = 0;
        }

        $monthly_usage[$month_key] += $liter;
    }

    $unit = trim(
        ($item['equipment_code'] ?? '') . ' - ' .
        ($item['equipment_name'] ?? '')
    );

    if (!isset($equipment_usage[$unit])) {
        $equipment_usage[$unit] = 0;
    }

    $equipment_usage[$unit] += $liter;
}

$average_liter = count($fuel_data) > 0
    ? $total_liter / count($fuel_data)
    : 0;

/* =========================
   FILTER PERIODE GRAFIK
========================= */
$periode = $_GET['periode'] ?? 'Harian';

if (!in_array($periode, ['Harian', 'Bulanan', 'Khusus'], true)) {
    $periode = 'Harian';
}

$tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-t');

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir) ||
    $tanggal_mulai > $tanggal_akhir
) {
    $tanggal_mulai = date('Y-m-01');
    $tanggal_akhir = date('Y-m-t');
}

$grafik_harian = $periode !== 'Bulanan';

if ($periode === 'Harian') {
    $tanggal_mulai = date('Y-m-01');
    $tanggal_akhir = date('Y-m-t');
}

if ($periode === 'Bulanan') {
    $tanggal_mulai = date('Y-m-01', strtotime('-8 months'));
    $tanggal_akhir = date('Y-m-t');
}

$grafik_usage = [];
$trend_labels = [];
$trend_values = [];

$start = strtotime($tanggal_mulai);
$end = strtotime($tanggal_akhir);

if ($grafik_harian) {
    for ($d = $start; $d <= $end; $d = strtotime('+1 day', $d)) {
        $key = date('Y-m-d', $d);
        $grafik_usage[$key] = 0;
        $trend_labels[] = date('d M', $d);
    }
} else {
    $start = strtotime(date('Y-m-01', $start));
    $end = strtotime(date('Y-m-01', $end));

    for ($d = $start; $d <= $end; $d = strtotime('+1 month', $d)) {
        $key = date('Y-m', $d);
        $grafik_usage[$key] = 0;
        $trend_labels[] = date('M Y', $d);
    }
}

foreach ($fuel_data as $item) {
    if (empty($item['tanggal'])) {
        continue;
    }

    $tanggal = date('Y-m-d', strtotime($item['tanggal']));

    if ($tanggal < $tanggal_mulai || $tanggal > $tanggal_akhir) {
        continue;
    }

    $key = $grafik_harian
        ? $tanggal
        : date('Y-m', strtotime($tanggal));

    if (isset($grafik_usage[$key])) {
        $grafik_usage[$key] += (float)$item['jumlah_liter'];
    }
}

$trend_values = array_map(
    fn($value) => round($value, 2),
    array_values($grafik_usage)
);

// Urutkan pemakaian BBM dari terbesar ke terkecil
if ((int)$selected_equipment <= 0) {
    arsort($equipment_usage, SORT_NUMERIC);

    // Tampilkan hanya 10 unit dengan pemakaian terbesar
    $equipment_usage = array_slice(
        $equipment_usage,
        0,
        10,
        true
    );
}

$equipment_labels = array_keys($equipment_usage);
$equipment_values = array_values($equipment_usage);

function fuel_h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Performance - PT Biringkassi Raya</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f8fc;
            color: #1f2937;
            font-size: 14px;
        }

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
            padding: 0;
            margin: 0;
        }

        .menu li { margin-bottom: 7px; }

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

        .submenu {
            list-style: none;
            padding-left: 18px;
            margin: 0;
        }

        .menu-arrow { float: right; }

        .main {
            margin-left: 240px;
            padding: 28px 32px 45px;
        }

        .header { margin-bottom: 22px; }

        .header h1 {
            margin: 0 0 7px;
            font-size: 30px;
            color: #0759bd;
        }

        .header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .filter-box,
        .summary-card,
        .box {
            background: white;
            border: 1px solid #dce8f3;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        .filter-box {
            padding: 18px;
            margin-bottom: 20px;
        }

        .filter-title {
            font-weight: bold;
            margin-bottom: 12px;
        }

        .filter-form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: bold;
            color: #475569;
        }

        .filter-form select {
            width: 280px;
            max-width: 100%;
            height: 40px;
            padding: 0 12px;
            border: 1px solid #d9e2ec;
            border-radius: 8px;
            background: white;
            font-size: 13px;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 40px;
            padding: 0 17px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-filter {
            background: #0759bd;
            color: white;
            border: none;
        }

        .btn-reset {
            background: white;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .summary-card { padding: 20px; }

        .summary-title {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .summary-value {
            font-size: 27px;
            font-weight: bold;
            color: #0759bd;
            overflow-wrap: anywhere;
        }

        .summary-unit {
            font-size: 13px;
            font-weight: normal;
            color: #64748b;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .box {
            padding: 20px;
            min-width: 0;
        }

        .box-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .box-title h2 {
            margin: 0;
            font-size: 15px;
            color: #0759bd;
        }

        .box-title span {
            font-size: 11px;
            color: #64748b;
        }

        .chart-area {
            position: relative;
            height: 280px;
            width: 100%;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th {
            text-align: left;
            padding: 12px 10px;
            background: #eef5fc;
            color: #4b6380;
            font-weight: bold;
            white-space: nowrap;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
            color: #334155;
            vertical-align: top;
        }

        .empty-data {
            text-align: center;
            padding: 30px 15px;
            color: #64748b;
        }

        .error-message {
            padding: 12px 15px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .period-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: end;
            margin-bottom: 18px;
        }

        .period-form label {
            font-size: 12px;
            color: #475569;
        }

        .period-form select,
        .period-form input {
            padding: 9px;
            border: 1px solid #d9e2ec;
            border-radius: 7px;
            margin-top: 5px;
            background: white;
        }

        @media (max-width: 1100px) {
            .summary-grid { grid-template-columns: 1fr; }
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 18px;
            }

            .header h1 { font-size: 24px; }

            .filter-form select { width: 100%; }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">
        <img src="../assets/logo.png" alt="Logo PT Biringkassi Raya">
        <div class="logo-text">PT BIRINGKASSI RAYA</div>
    </div>

    <ul class="menu">
        <li><a href="../index.php">Dashboard</a></li>
        <li><a href="../equipment/index.php">Equipment Master</a></li>
        <li><a href="#">Status Unit</a></li>
        <li><a href="../maintenance/index.php">Jadwal Pemeliharaan</a></li>
        <li><a href="../maintenance/history/index.php">Histori Pemeliharaan</a></li>
        <li><a href="../breakdown/index.php">Breakdown &amp; Failure</a></li>

        <li>
            <a href="javascript:void(0)" onclick="toggleSubmenu()">
                Spare Part &amp; Lubricant
                <span id="menuArrow" class="menu-arrow">▶</span>
            </a>

            <ul id="spareSubmenu" class="submenu" style="display:none;">
                <li><a href="../sparepart/index.php">Spare Part</a></li>
                <li><a href="../lubricant/index.php">Lubricant</a></li>
            </ul>
        </li>

        <li><a href="index.php" class="active">Fuel Performance</a></li>
        <li><a href="#">Biaya Pemeliharaan</a></li>
        <li><a href="#">Kinerja &amp; Evaluasi</a></li>
        <li><a href="#">Laporan</a></li>
    </ul>
</aside>

<main class="main">

    <div class="header">
        <h1>Fuel Performance</h1>
        <p>Monitoring penggunaan bahan bakar alat berat berdasarkan lokasi dan equipment.</p>
    </div>
    <a href="tambah.php" class="btn btn-filter"
   style="display:inline-flex; margin-bottom:20px;">
    + Tambah Data BBM
</a>

    <?php if ($error_message !== ''): ?>
        <div class="error-message">
            <?= fuel_h($error_message); ?>
            Periksa tabel fuel_usage dan koneksi database.
        </div>
    <?php endif; ?>

    <div class="filter-box">
        <div class="filter-title">🔎 Filter Lokasi dan Equipment</div>

        <form method="GET" action="index.php" class="filter-form" id="fuelFilterForm">
            <div class="filter-group">
               <label for="location">Lokasi Equipment</label>

<select name="location" id="location">
    <option value="">Semua Lokasi</option>

    <?php foreach ($locations as $location): ?>
        <option
            value="<?= fuel_h($location); ?>"
            <?= $selected_location === $location ? 'selected' : ''; ?>
        >
            <?= fuel_h($location); ?>
        </option>
    <?php endforeach; ?>
</select>
            </div>

            <div class="filter-group">
                <label for="equipment_id">Pilih Equipment</label>
                <select name="equipment_id" id="equipment_id">
                    <option value="0">Semua Equipment</option>

                    <?php foreach ($equipment_options as $equipment): ?>
                        <option
                            value="<?= (int)$equipment['id']; ?>"
                            <?= $selected_equipment === (int)$equipment['id'] ? 'selected' : ''; ?>
                        >
                            <?= fuel_h($equipment['equipment_code'] . ' - ' . $equipment['equipment_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-filter">Tampilkan</button>
            <a href="index.php" class="btn btn-reset">Reset</a>
        </form>
    </div>

    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-title">Total Pemakaian BBM</div>
            <div class="summary-value">
                <?= number_format($total_liter, 2, ',', '.'); ?>
                <span class="summary-unit">Liter</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-title">Rata-rata Pemakaian per Catatan</div>
            <div class="summary-value">
                <?= number_format($average_liter, 2, ',', '.'); ?>
                <span class="summary-unit">Liter</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-title">Pemakaian Bulan Ini</div>
            <div class="summary-value">
                <?= number_format($this_month_liter, 2, ',', '.'); ?>
                <span class="summary-unit">Liter</span>
            </div>
        </div>
    </section>

    <section class="dashboard-grid">
        <div class="box">
            <div class="box-title">
                <h2>📈 Tren Pemakaian BBM</h2>
                <span>
                    <?= $periode === 'Bulanan' ? '9 bulan terakhir' : 'Pemakaian per hari'; ?>
                </span>
            </div>

            <form method="GET" action="index.php" class="period-form">
                <input type="hidden" name="location" value="<?= fuel_h($selected_location); ?>">
                <input type="hidden" name="equipment_id" value="<?= (int)$selected_equipment; ?>">

                <div>
                    <label for="periode">Periode Grafik</label><br>
                    <select id="periode" name="periode" onchange="this.form.submit()">
                        <option value="Harian" <?= $periode === 'Harian' ? 'selected' : ''; ?>>Harian</option>
                        <option value="Bulanan" <?= $periode === 'Bulanan' ? 'selected' : ''; ?>>Bulanan</option>
                        <option value="Khusus" <?= $periode === 'Khusus' ? 'selected' : ''; ?>>Periode Khusus</option>
                    </select>
                </div>

                <?php if ($periode === 'Khusus'): ?>
                    <div>
                        <label for="tanggal_mulai">Tanggal Mulai</label><br>
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                               value="<?= fuel_h($tanggal_mulai); ?>" required>
                    </div>

                    <div>
                        <label for="tanggal_akhir">Tanggal Akhir</label><br>
                        <input type="date" id="tanggal_akhir" name="tanggal_akhir"
                               value="<?= fuel_h($tanggal_akhir); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-filter">Terapkan</button>
                <?php endif; ?>
            </form>

            <div class="chart-area">
                <canvas id="fuelTrendChart"></canvas>
            </div>
        </div>

        <div class="box">
            <div class="box-title">
                <h2>⛽ Pemakaian BBM per Equipment</h2>
                <span><?= $selected_equipment > 0 ? 'Equipment terpilih' : '10 unit teratas'; ?></span>
            </div>

            <div class="chart-area">
                <canvas id="fuelEquipmentChart"></canvas>
            </div>
        </div>
    </section>

    <section class="box">
        <div class="box-title">
            <h2>📋 Riwayat Pemakaian BBM</h2>
            <span><?= number_format(count($fuel_data), 0, ',', '.'); ?> catatan</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>ID Unit</th>
                        <th>Equipment</th>
                        <th>Lokasi</th>
                        <th>HM</th>
                        <th>BBM (Liter)</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($fuel_data) > 0): ?>
                        <?php foreach ($fuel_data as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td>
                                    <?= !empty($item['tanggal'])
                                        ? date('d-m-Y', strtotime($item['tanggal']))
                                        : '-'; ?>
                                </td>
                                <td><strong><?= fuel_h($item['equipment_code']); ?></strong></td>
                                <td><?= fuel_h($item['equipment_name']); ?></td>
                                <td><?= fuel_h($item['location']); ?></td>
                                <td>
                                    <?= $item['hm_km'] !== null && $item['hm_km'] !== ''
                                        ? number_format((float)$item['hm_km'], 2, ',', '.')
                                        : '-'; ?>
                                </td>
                                <td><?= number_format((float)$item['jumlah_liter'], 2, ',', '.'); ?></td>
                                <td><?= fuel_h($item['keterangan'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-data">
                                Data pemakaian BBM belum tersedia untuk filter ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function toggleSubmenu() {
    const submenu = document.getElementById('spareSubmenu');
    const arrow = document.getElementById('menuArrow');

    if (submenu.style.display === 'none') {
        submenu.style.display = 'block';
        arrow.textContent = '▼';
    } else {
        submenu.style.display = 'none';
        arrow.textContent = '▶';
    }
}

/* Data grafik dari PHP */
const trendLabels = <?= json_encode($trend_labels); ?>;
const trendValues = <?= json_encode($trend_values); ?>;

const equipmentLabels = <?= json_encode($equipment_labels); ?>;
const equipmentValues = <?= json_encode($equipment_values); ?>;

/* Grafik tren pemakaian BBM */
const trendCanvas = document.getElementById('fuelTrendChart');

if (trendCanvas) {
    new Chart(trendCanvas, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Pemakaian BBM (Liter)',
                data: trendValues,
                borderColor: '#0759bd',
                backgroundColor: 'rgba(7, 89, 189, 0.12)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' +
                                Number(context.parsed.y).toLocaleString('id-ID') +
                                ' Liter';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: true,
                        maxTicksLimit: 15,
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Liter'
                    }
                }
            }
        }
    });
}

/* Grafik pemakaian BBM per equipment */
const equipmentCanvas = document.getElementById('fuelEquipmentChart');

if (equipmentCanvas) {
    new Chart(equipmentCanvas, {
        type: 'bar',
        data: {
            labels: equipmentLabels.map(function(label) {
    return label.split(' - ')[0];
}),
            datasets: [{
                label: 'Total Pemakaian (Liter)',
                data: equipmentValues,
                backgroundColor: '#0759bd',
                borderRadius: 5,
                maxBarThickness: 45
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Pemakaian: ' +
                                Number(context.parsed.y).toLocaleString('id-ID') +
                                ' Liter';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: true,
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Liter'
                    }
                }
            }
        }
    });
}
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.filter-form');
    const lokasi = document.getElementById('location');
    const equipment = document.getElementById('equipment_id');

    if (!form || !lokasi || !equipment) return;

    lokasi.addEventListener('change', function () {
        equipment.value = '0';
        form.submit();
    });

    equipment.addEventListener('change', function () {
        form.submit();
    });
});
</script>

</body>
</html>