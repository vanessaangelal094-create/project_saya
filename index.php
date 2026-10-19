<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

require_once "config/database.php";


/* =========================
   FILTER LOKASI DASHBOARD
========================= */

$selected_location = trim($_GET['location'] ?? '');


/* =========================
   DATA PILIHAN LOKASI
========================= */

$location_result = $conn->query("
    SELECT DISTINCT location
    FROM equipment
    WHERE location IS NOT NULL
    AND location != ''
    ORDER BY location ASC
");


/* =========================
   KONDISI LOKASI
========================= */

$location_condition = "";
$location_param = null;

if ($selected_location !== '') {
    $location_condition = " AND location = ?";
    $location_param = $selected_location;
}


/* =========================
   FUNGSI KPI
========================= */

function getDashboardCount($conn, $sql, $location_param = null)
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    if ($location_param !== null) {
        $stmt->bind_param("s", $location_param);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();

    return (int)($row['total'] ?? 0);
}


/* =========================
   DATA KPI DASHBOARD
========================= */

$total_equipment = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM equipment
     WHERE 1=1 $location_condition",
    $location_param
);

$equipment_operasi = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM equipment
     WHERE status = 'Running'
     $location_condition",
    $location_param
);

$equipment_standby = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM equipment
     WHERE status = 'Standby'
     $location_condition",
    $location_param
);

$dalam_maintenance = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM equipment
     WHERE status = 'Maintenance'
     $location_condition",
    $location_param
);

$breakdown = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM equipment
     WHERE status = 'Breakdown'
     $location_condition",
    $location_param
);

// Data status pemeliharaan untuk grafik Dashboard
$maintenance_status_query = $conn->query("
    SELECT
        SUM(status = 'Selesai') AS selesai,
        SUM(status IN ('Segera Diservis', 'Overdue')) AS belum_selesai
    FROM maintenance_schedule
");

$maintenance_status = $maintenance_status_query
    ? $maintenance_status_query->fetch_assoc()
    : [
        'selesai' => 0,
        'belum_selesai' => 0
    ];

$maintenance_selesai = (int) ($maintenance_status['selesai'] ?? 0);
$maintenance_belum_selesai = (int) ($maintenance_status['belum_selesai'] ?? 0);


/* =========================
   DATA GRAFIK BBM & PELUMAS
   JANUARI - SEPTEMBER 2026
========================= */

$dashboard_chart_labels = [];

$dashboard_fuel_by_month = [];
$dashboard_lubricant_by_month = [];

for ($month = 1; $month <= 9; $month++) {
    $month_key = '2026-' . str_pad($month, 2, '0', STR_PAD_LEFT);

    $dashboard_chart_labels[] = date(
        'M',
        strtotime($month_key . '-01')
    );

    $dashboard_fuel_by_month[$month_key] = 0;
    $dashboard_lubricant_by_month[$month_key] = 0;
}

/* Data BBM dari database */
$fuel_chart_sql = "
    SELECT
        DATE_FORMAT(f.tanggal, '%Y-%m') AS bulan,
        SUM(f.jumlah_liter) AS total
    FROM fuel_usage f
    INNER JOIN equipment e ON e.id = f.equipment_id
    WHERE f.tanggal >= '2026-01-01'
      AND f.tanggal < '2026-10-01'
";

if ($selected_location !== '') {
    $fuel_chart_sql .= " AND e.location = ?";
}

$fuel_chart_sql .= "
    GROUP BY DATE_FORMAT(f.tanggal, '%Y-%m')
";

$fuel_chart_stmt = $conn->prepare($fuel_chart_sql);

if ($fuel_chart_stmt) {
    if ($selected_location !== '') {
        $fuel_chart_stmt->bind_param('s', $selected_location);
    }

    if ($fuel_chart_stmt->execute()) {
        $fuel_chart_result = $fuel_chart_stmt->get_result();

        while ($row = $fuel_chart_result->fetch_assoc()) {
            $bulan = $row['bulan'];

            if (isset($dashboard_fuel_by_month[$bulan])) {
                $dashboard_fuel_by_month[$bulan] = (float) $row['total'];
            }
        }
    }

    $fuel_chart_stmt->close();
}

/* Data jumlah catatan pelumas dari database */
$lubricant_chart_sql = "
    SELECT
        DATE_FORMAT(l.tanggal, '%Y-%m') AS bulan,
        SUM(l.jumlah) AS total
    FROM lubricant l
    INNER JOIN equipment e ON e.id = l.equipment_id
    WHERE l.tanggal >= '2026-01-01'
      AND l.tanggal < '2026-10-01'
      AND LOWER(TRIM(l.satuan)) IN ('liter', 'ltr', 'l')
";

if ($selected_location !== '') {
    $lubricant_chart_sql .= " AND e.location = ?";
}

$lubricant_chart_sql .= "
    GROUP BY DATE_FORMAT(l.tanggal, '%Y-%m')
";

$lubricant_chart_stmt = $conn->prepare($lubricant_chart_sql);

if ($lubricant_chart_stmt) {
    if ($selected_location !== '') {
        $lubricant_chart_stmt->bind_param('s', $selected_location);
    }

    if ($lubricant_chart_stmt->execute()) {
        $lubricant_chart_result = $lubricant_chart_stmt->get_result();

        while ($row = $lubricant_chart_result->fetch_assoc()) {
            $bulan = $row['bulan'];

            if (isset($dashboard_lubricant_by_month[$bulan])) {
                $dashboard_lubricant_by_month[$bulan] = (float) $row['total'];
            }
        }
    }

    $lubricant_chart_stmt->close();
}

/* Array yang akan digunakan grafik */
$dashboard_chart_fuel_values = array_values($dashboard_fuel_by_month);

$dashboard_chart_lubricant_values = array_values($dashboard_lubricant_by_month);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Monitoring Alat Berat</title>

    <!-- Terapkan tema tersimpan sebelum halaman tampil (mencegah kedipan terang) -->
    <script>
        try {
            document.documentElement.setAttribute(
                'data-theme',
                localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'
            );
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="assets/css/app.css">

</head>


<body>

    <?php
    $base   = '';
    $active = 'dashboard';
    include $base . 'includes/sidebar.php';
    ?>


    <main class="main">


        <!-- HEADER -->

        <div class="header">

            <div class="header-left">

                <h1>Dashboard</h1>

                <p>
                    Ringkasan kondisi operasional, pemeliharaan, dan performa alat berat di PT Biringkassi Raya.
                </p>

                <!-- FILTER LOKASI -->
                <div class="location-filter">

                    <label for="location">
                        Lokasi Equipment
                    </label>

                    <form method="GET" action="index.php">

                        <select
                            name="location"
                            id="location"
                            onchange="this.form.submit()"
                        >

                            <option value="">
                                Semua Lokasi
                            </option>

                            <?php while ($location = $location_result->fetch_assoc()): ?>

                                <option
                                    value="<?= htmlspecialchars($location['location']); ?>"
                                    <?= $selected_location === $location['location'] ? 'selected' : ''; ?>
                                >
                                    <?= htmlspecialchars($location['location']); ?>
                                </option>

                            <?php endwhile; ?>

                        </select>

                    </form>

                </div>

            </div>


            <div class="profile">

                <div
                    class="user-info"
                    onclick="toggleProfile()"
                >

                    🔔 &nbsp;

                    <strong>
                        <?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?>
                    </strong>

                    &nbsp;⌄

                </div>


                <div
                    class="profile-menu"
                    id="profileMenu"
                >

                    <div class="profile-name">

                        Login sebagai

                        <strong>
                            <?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?>
                        </strong>

                    </div>


                    <a
                        href="auth/logout.php"
                        class="logout-link"
                    >
                        🚪 Logout
                    </a>

                </div>

            </div>

        </div>



        <!-- =========================
             KPI
        ========================= -->

        <section class="kpi">

            <div
                class="kpi-card"
                onclick="window.location.href='equipment/index.php<?php echo $selected_location !== '' ? '?location=' . urlencode($selected_location) : ''; ?>';"
                style="cursor: pointer;"
            >

                <div class="kpi-icon icon-blue">
                    🚜
                </div>

                <div class="kpi-info">

                    <div class="kpi-title">
                        Total Equipment
                    </div>

                    <div class="kpi-number">
                        <?= $total_equipment; ?>
                    </div>

                </div>

            </div>


            <div
                class="kpi-card"
                onclick="window.location.href='equipment/index.php?location=<?php echo urlencode($selected_location); ?>&status=Running';"
                style="cursor: pointer;"
            >

                <div class="kpi-icon icon-green">
                    ⚙
                </div>

                <div class="kpi-info">

                    <div class="kpi-title">
                        Equipment Operasi
                    </div>

                    <div class="kpi-number">
                        <?= $equipment_operasi; ?>
                    </div>

                </div>

            </div>


            <div
                class="kpi-card"
                onclick="window.location.href='equipment/index.php?location=<?php echo urlencode($selected_location); ?>&status=Standby';"
                style="cursor: pointer;"
            >

                <div class="kpi-icon icon-yellow">
                    ◷
                </div>

                <div class="kpi-info">

                    <div class="kpi-title">
                        Standby
                    </div>

                    <div class="kpi-number">
                        <?= $equipment_standby; ?>
                    </div>

                </div>

            </div>


            <div
                class="kpi-card"
                onclick="<?php
                    if ($selected_location !== '') {
                        echo "window.location.href='equipment/index.php?location=" . urlencode($selected_location) . "&status=Maintenance';";
                    } else {
                        echo "window.location.href='maintenance/index.php';";
                    }
                ?>"
                style="cursor: pointer;"
            >

                <div class="kpi-icon icon-yellow">
                    🔧
                </div>

                <div class="kpi-info">

                    <div class="kpi-title">
                        Dalam Maintenance
                    </div>

                    <div class="kpi-number">
                        <?= $dalam_maintenance; ?>
                    </div>

                </div>

            </div>


            <div
                class="kpi-card"
                onclick="window.location.href='equipment/index.php?location=<?php echo urlencode($selected_location); ?>&status=Breakdown';"
                style="cursor: pointer;"
            >

                <div class="kpi-icon icon-red">
                    ⚠
                </div>

                <div class="kpi-info">

                    <div class="kpi-title">
                        Breakdown
                    </div>

                    <div class="kpi-number kpi-red">
                        <?= $breakdown; ?>
                    </div>

                </div>

            </div>

        </section>



        <!-- =========================
             BBM + PELUMAS
        ========================= -->

        <section class="dashboard-grid">

            <div class="box">

                <div class="box-title">

                    <h2>
                        ⛽ &nbsp; Penggunaan BBM & Pergantian Pelumas
                    </h2>

                    <span>
                        Jan - Sep
                    </span>

                </div>

                <div class="combined-chart">

                    <div class="combined-chart-area">

                        <div class="chart-canvas-wrap">
                            <canvas id="fuelLubricantChart"></canvas>
                        </div>

                    </div>

                    <div class="chart-legend">

                        <div class="legend-item">
                            <div class="legend-box"></div>
                            BBM (Liter)
                        </div>

                        <div class="legend-item">
                            <div class="legend-line"></div>
                            Pelumas (Liter)
                        </div>

                    </div>

                </div>

            </div>


            <div class="box">

                <div class="box-title">
                    <h2>
                        📊 &nbsp; Status Equipment
                    </h2>

                    <span>
                        Kondisi Unit
                    </span>
                </div>

                <div style="height: 250px; position: relative;">
                    <canvas id="statusEquipmentChart"></canvas>
                </div>

            </div>
        </section>

        <section class="dashboard-grid">

            <div class="box">

                <div class="box-title">
                    <h2>🔧 &nbsp; Status Pemeliharaan</h2>

                    <span>
                        Kondisi Jadwal
                    </span>
                </div>

                <div style="height: 250px; position: relative;">
                    <canvas
                        id="maintenanceStatusChart"
                        onclick="window.location.href='maintenance/index.php?status=Selesai';"
                        style="cursor: pointer;"
                    ></canvas>
                </div>

            </div>

        </section>

        <!-- CHART.JS -->

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>

            Chart.defaults.font.family = "Inter, Arial, sans-serif";

            /* ---------- Warna grafik mengikuti tema ---------- */
            const isDark = document.documentElement.getAttribute("data-theme") === "dark";

            const gridColor = isDark ? "#2a2a3d" : "#ececf3";
            const barColor  = isDark ? "#60a5fa" : "#3b82f6";

            Chart.defaults.color       = isDark ? "#a9a9c0" : "#8a8aa0";
            Chart.defaults.borderColor = gridColor;

            /* grafik tidak otomatis berganti warna, jadi muat ulang saat tema diganti */
            window.addEventListener("themechange", function () {
                location.reload();
            });

            const fuelChartCanvas =
                document.getElementById("fuelLubricantChart");

            if (fuelChartCanvas) {

                new Chart(fuelChartCanvas, {

                    data: {

                        labels: <?= json_encode($dashboard_chart_labels, JSON_UNESCAPED_UNICODE); ?>,

                        datasets: [

                            {
                                type: "bar",

                                label: "BBM (Liter)",

                                data: <?= json_encode($dashboard_chart_fuel_values); ?>,

                                backgroundColor: barColor,

                                borderRadius: 6,

                                borderSkipped: false,

                                barPercentage: 0.62,

                                categoryPercentage: 0.72,

                                yAxisID: "y"
                            },

                            {
                                type: "line",

                                label: "Pelumas (Liter)",

                                data: <?= json_encode($dashboard_chart_lubricant_values); ?>,

                                borderColor: "#f59e0b",

                                backgroundColor: "#f59e0b",

                                borderWidth: 3,

                                tension: 0.25,

                                pointRadius: 5,

                                pointHoverRadius: 7,

                                pointBackgroundColor: "#ffffff",

                                pointBorderColor: "#f59e0b",

                                pointBorderWidth: 3,

                                yAxisID: "y1"
                            }

                        ]

                    },

                    options: {

                        responsive: true,

                        maintainAspectRatio: false,

                        interaction: {
                            mode: "index",
                            intersect: false
                        },

                        plugins: {

                            legend: {
                                display: false
                            },

                            tooltip: {

                                backgroundColor: isDark ? "#1f1f31" : "#ffffff",

                                titleColor: isDark ? "#f1f1f8" : "#14142b",

                                bodyColor: isDark ? "#c3c3d8" : "#4a4a63",

                                borderColor: gridColor,

                                borderWidth: 1,

                                padding: 10

                            }

                        },

                        scales: {

                            y: {

                                beginAtZero: true,

                                max: 12000,

                                ticks: {

                                    stepSize: 3000,

                                    font: {
                                        size: 11
                                    },

                                    callback: function(value) {
                                        return value.toLocaleString("id-ID");
                                    }

                                },

                                title: {

                                    display: true,

                                    text: "BBM (Liter)",

                                    color: barColor,

                                    font: {
                                        size: 11,
                                        weight: "bold"
                                    }

                                },

                                grid: {
                                    color: gridColor,
                                    borderDash: [5, 5]
                                }

                            },

                            y1: {

                                position: "right",

                                beginAtZero: true,

                                max: 1200,

                                ticks: {

                                    stepSize: 300,

                                    color: "#d97706",

                                    font: {
                                        size: 11
                                    }

                                },

                                title: {

                                    display: true,

                                    text: "Pelumas (Liter)",

                                    color: "#d97706",

                                    font: {
                                        size: 11,
                                        weight: "bold"
                                    }

                                },

                                grid: {
                                    drawOnChartArea: false
                                }

                            },

                            x: {

                                ticks: {

                                    font: {
                                        size: 11
                                    }

                                },

                                grid: {

                                    color: gridColor,

                                    borderDash: [5, 5]

                                }

                            }

                        }

                    }

                });

            }

        </script>

        <script>

            const statusEquipmentCanvas =
                document.getElementById("statusEquipmentChart");

            if (statusEquipmentCanvas) {

                new Chart(statusEquipmentCanvas, {

                    type: "doughnut",

                    data: {
                        labels: [
                            "Running",
                            "Standby",
                            "Maintenance",
                            "Breakdown"
                        ],

                        datasets: [{
                            data: [
                                <?= $equipment_operasi; ?>,
                                <?= $equipment_standby; ?>,
                                <?= $dalam_maintenance; ?>,
                                <?= $breakdown; ?>
                            ],

                            backgroundColor: [
                                "#22c55e",
                                "#facc15",
                                "#f97316",
                                "#ef4444"
                            ],

                            borderWidth: 2,

                            borderColor: isDark ? "#171724" : "#ffffff"
                        }]
                    },

                    options: {

                        responsive: true,

                        maintainAspectRatio: false,

                        cutout: "62%",

                        plugins: {

                            legend: {
                                position: "bottom",

                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: {
                                        size: 11
                                    }
                                }
                            },

                            tooltip: {
                                callbacks: {
                                    label: function(context) {

                                        const total =
                                            context.dataset.data.reduce(
                                                (a, b) => a + b,
                                                0
                                            );

                                        const value = context.raw;

                                        const percentage =
                                            total > 0
                                                ? ((value / total) * 100).toFixed(1)
                                                : "0.0";

                                        return context.label +
                                            ": " +
                                            value +
                                            " unit (" +
                                            percentage +
                                            "%)";
                                    }
                                }
                            }

                        }

                    }

                });

            }

        </script>

        <script>

            const maintenanceStatusCanvas = document.getElementById("maintenanceStatusChart");

            if (maintenanceStatusCanvas) {

                new Chart(maintenanceStatusCanvas, {
                    type: "bar",

                    data: {
                        labels: [
                            "Selesai",
                            "Segera Diservis",
                        ],

                        datasets: [{
                            label: "Jumlah Jadwal",

                            data: [
    <?= $maintenance_selesai ?>,
    <?= $maintenance_belum_selesai ?>
],

                            backgroundColor: [
                                "#22c55e",
                                "#f59e0b",
                                "#ef4444"
                            ],

                            borderRadius: 6,
                            borderSkipped: false
                        }]
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onClick: function (event, elements) {
                            if (elements.length > 0) {
                                window.location.href = "maintenance/index.php";
                            }
                        },

                        plugins: {
                            legend: {
                                display: false
                            }
                        },

                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });

            }

        </script>

        <!-- =========================
             TOP EQUIPMENT + JADWAL
        ========================= -->

        <section class="dashboard-grid equal">


            <div class="box">

                <div class="box-title">
                    <h2>📊 &nbsp; Top 5 Equipment berdasarkan Konsumsi BBM</h2>

                    <a
                        href="fuel/index.php<?= $selected_location !== '' ? '?location=' . urlencode($selected_location) : ''; ?>"
                        class="see-more"
                    >
                        Lihat Semua
                    </a>
                </div>

                <?php
                /* Ambil 5 unit dengan pemakaian BBM terbesar */
                $top_fuel_sql = "
                    SELECT
                        e.id,
                        e.equipment_code,
                        e.equipment_name,
                        SUM(f.jumlah_liter) AS total_liter,
                        (
                            SELECT f2.hm_km
                            FROM fuel_usage f2
                            WHERE f2.equipment_id = e.id
                              AND f2.hm_km IS NOT NULL
                            ORDER BY f2.tanggal DESC, f2.id DESC
                            LIMIT 1
                        ) AS hm_terakhir
                    FROM fuel_usage f
                        INNER JOIN equipment e
                            ON e.id = f.equipment_id
                ";

                if ($selected_location !== '') {
                    $top_fuel_sql .= " WHERE e.location = ?";
                }

                $top_fuel_sql .= "
                    GROUP BY
                        e.id,
                        e.equipment_code,
                        e.equipment_name
                    ORDER BY total_liter DESC
                    LIMIT 5
                ";

                $top_fuel_stmt = $conn->prepare($top_fuel_sql);
                $top_fuel_result = false;

                if ($top_fuel_stmt) {
                    if ($selected_location !== '') {
                        $top_fuel_stmt->bind_param("s", $selected_location);
                    }

                    if ($top_fuel_stmt->execute()) {
                        $top_fuel_result = $top_fuel_stmt->get_result();
                    }
                }
                ?>

                <div class="table-wrapper">

                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Unit</th>
                                <th>Equipment</th>
                                <th>Konsumsi BBM (Liter)</th>
                                <th>HM Terakhir</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($top_fuel_result && $top_fuel_result->num_rows > 0): ?>

                                <?php $nomor = 1; ?>

                                <?php while ($unit = $top_fuel_result->fetch_assoc()): ?>

                                    <tr>
                                        <td><?= $nomor++; ?></td>

                                        <td>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $unit['equipment_code'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $unit['equipment_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= number_format(
                                                (float)$unit['total_liter'],
                                                2,
                                                ',',
                                                '.'
                                            ); ?>
                                        </td>

                                        <td>
                                            <?php if ($unit['hm_terakhir'] !== null && $unit['hm_terakhir'] !== ''): ?>
                                                <?= number_format((float)$unit['hm_terakhir'], 1, ',', '.'); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                <?php endwhile; ?>

                            <?php elseif ($top_fuel_result !== false): ?>

                                <tr>
                                    <td colspan="5" class="empty">
                                        Belum ada data BBM untuk filter ini.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <tr>
                                    <td colspan="5" class="empty">
                                        Data BBM gagal dibaca. Periksa koneksi database.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>
                    </table>

                </div>

                <?php
                if ($top_fuel_stmt) {
                    $top_fuel_stmt->close();
                }
                ?>

            </div>


            <div class="box">

                <div class="box-title">

                    <h2>
                        📅 &nbsp; Jadwal Maintenance Terdekat
                    </h2>

                    <a href="maintenance/index.php" class="see-more">
                        Lihat Semua
                    </a>

                </div>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>ID Unit</th>
                                <th>Nama Alat</th>
                                <th>Jenis Maintenance</th>
                                <th>Jadwal (HM)</th>
                                <th>Sisa HM</th>
                                <th>Status</th>
                            </tr>

                        </thead>


                        <tbody>
<?php

$maintenance_location_condition = "";

if ($selected_location !== '') {
    $maintenance_location_condition = " AND e.location = ?";
}

$maintenance_sql = "
    SELECT
        ms.id,
        e.equipment_code,
        e.equipment_name,
        ms.jenis_maintenance,
        ms.next_service,
        ms.remaining_hm_km,
        ms.status
    FROM maintenance_schedule ms
    INNER JOIN equipment e
        ON ms.equipment_id = e.id
    WHERE ms.status != 'Selesai'
    $maintenance_location_condition
    ORDER BY ms.remaining_hm_km ASC
    LIMIT 5
";

$maintenance_stmt = $conn->prepare($maintenance_sql);

if ($maintenance_stmt) {

    if ($selected_location !== '') {
        $maintenance_stmt->bind_param("s", $selected_location);
    }

    $maintenance_stmt->execute();

    $maintenance_result = $maintenance_stmt->get_result();

    if ($maintenance_result->num_rows > 0):

        while ($maintenance = $maintenance_result->fetch_assoc()):

            $status_class = 'badge-green';

            if ($maintenance['status'] === 'Segera Diservis') {
                $status_class = 'badge-orange';
            } elseif ($maintenance['status'] === 'Overdue') {
                $status_class = 'badge-red';
            } elseif ($maintenance['status'] === 'Akan Jatuh Tempo') {
                $status_class = 'badge-orange';
            }

?>

<tr>

    <td>
        <strong>
            <?= htmlspecialchars($maintenance['equipment_code']); ?>
        </strong>
    </td>

    <td>
        <?= htmlspecialchars($maintenance['equipment_name']); ?>
    </td>

    <td>
        <?= htmlspecialchars($maintenance['jenis_maintenance']); ?>
    </td>

    <td>
        <?= number_format(
            (float)$maintenance['next_service'],
            0,
            ',',
            '.'
        ); ?>
    </td>

    <td>
        <?= number_format(
            (float)$maintenance['remaining_hm_km'],
            0,
            ',',
            '.'
        ); ?>
    </td>

    <td>
        <span class="badge <?= $status_class; ?>">
            <?= htmlspecialchars($maintenance['status']); ?>
        </span>
    </td>

</tr>

<?php

        endwhile;

    else:

?>

<tr>
    <td colspan="6" class="empty">
        Belum ada data maintenance.
    </td>
</tr>

<?php

    endif;

    $maintenance_stmt->close();

} else {

?>

<tr>
    <td colspan="6" class="empty">
        Data maintenance belum tersedia.
    </td>
</tr>

<?php

}

?>
                        </tbody>

                    </table>

                </div>

            </div>

        </section>

    </main>



    <script>

        function toggleProfile() {

            const menu =
                document.getElementById("profileMenu");

            menu.classList.toggle("show");

        }


        document.addEventListener("click", function(event) {

            const profile =
                document.querySelector(".profile");

            const menu =
                document.getElementById("profileMenu");

            if (!profile.contains(event.target)) {

                menu.classList.remove("show");

            }

        });

    </script>


</body>

</html>