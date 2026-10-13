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
        SUM(status = 'Segera Diservis') AS segera_service,
        SUM(status = 'Overdue') AS overdue
    FROM maintenance_schedule
");

$maintenance_status = $maintenance_status_query
    ? $maintenance_status_query->fetch_assoc()
    : [
        'selesai' => 0,
        'segera_service' => 0,
        'overdue' => 0
    ];

$maintenance_selesai = (int) ($maintenance_status['selesai'] ?? 0);
$maintenance_segera = (int) ($maintenance_status['segera_service'] ?? 0);
$maintenance_overdue = (int) ($maintenance_status['overdue'] ?? 0);

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
                $dashboard_fuel_by_month[$bulan] =
                    (float) $row['total'];
            }
        }
    }

    $fuel_chart_stmt->close();
}

/* Data jumlah catatan pelumas dari database */
$lubricant_chart_sql = "
    SELECT
        DATE_FORMAT(l.tanggal, '%Y-%m') AS bulan,
        COUNT(*) AS total
    FROM lubricant l
    INNER JOIN equipment e ON e.id = l.equipment_id
    WHERE l.tanggal >= '2026-01-01'
      AND l.tanggal < '2026-10-01'
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
                $dashboard_lubricant_by_month[$bulan] =
                    (int) $row['total'];
            }
        }
    }

    $lubricant_chart_stmt->close();
}

/* Array yang akan digunakan grafik */
$dashboard_chart_fuel_values =
    array_values($dashboard_fuel_by_month);

$dashboard_chart_lubricant_values =
    array_values($dashboard_lubricant_by_month);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Monitoring Alat Berat</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f8fc;
            color: #1f2937;
            font-size: 15px;
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


        /* =========================
           MAIN
        ========================= */

        .main {
            margin-left: 240px;

            padding: 28px 32px 45px;
        }


        /* =========================
           HEADER
        ========================= */

        .header {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 22px;
        }

        .header-left h1 {
            font-size: 32px;

            color: #0759bd;

            margin-bottom: 5px;
        }

        .header-left p {
            color: #64748b;

            font-size: 14px;
        }

        .profile {
            position: relative;
        }

        .user-info {
            background: white;

            border: 1px solid #dce5ef;

            padding: 11px 16px;

            border-radius: 8px;

            cursor: pointer;

            font-size: 13px;

            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }

        .user-info strong {
            color: #0759bd;
        }

        .profile-menu {
            display: none;

            position: absolute;

            right: 0;

            top: 48px;

            width: 190px;

            background: white;

            border: 1px solid #dce5ef;

            border-radius: 9px;

            box-shadow: 0 8px 25px rgba(0,0,0,0.12);

            overflow: hidden;

            z-index: 1000;
        }

        .profile-menu.show {
            display: block;
        }

        .profile-name {
            padding: 13px 15px;

            font-size: 12px;

            color: #64748b;

            border-bottom: 1px solid #edf1f5;
        }

        .profile-name strong {
            display: block;

            margin-top: 4px;

            color: #1f2937;

            font-size: 13px;
        }

        .logout-link {
            display: block;

            padding: 12px 15px;

            color: #dc2626;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;
        }

        .logout-link:hover {
            background: #fef2f2;
        }


        /* =========================
           KPI
        ========================= */

        .kpi {
            display: grid;

            grid-template-columns: repeat(5, 1fr);

            gap: 16px;

            margin-bottom: 16px;
        }

        .kpi-card {
            background: white;

            border: 1px solid #dce8f3;

            border-radius: 8px;

            min-height: 105px;

            padding: 16px 18px;

            display: flex;

            align-items: center;

            gap: 14px;

            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }

        .kpi-icon {
            width: 52px;
            height: 52px;

            border-radius: 9px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 27px;

            flex-shrink: 0;
        }

        .icon-blue {
            background: #e7f1ff;
        }

        .icon-green {
            background: #e5f7eb;
        }

        .icon-yellow {
            background: #fff4d7;
        }

        .icon-red {
            background: #ffe8e8;
        }

        .kpi-info {
            flex: 1;
        }

        .kpi-title {
            font-size: 12px;

            color: #64748b;

            margin-bottom: 6px;

            font-weight: bold;
        }

        .kpi-number {
            font-size: 29px;

            font-weight: bold;

            color: #0759bd;
        }

        .kpi-red {
            color: #dc2626;
        }


        /* =========================
           GRID
        ========================= */

        .dashboard-grid {
            display: grid;

            grid-template-columns: 1.55fr 1fr;

            gap: 16px;

            margin-bottom: 16px;
        }

        .dashboard-grid.equal {
            grid-template-columns: 1fr 1fr;
        }


        /* =========================
           BOX
        ========================= */

        .box {
            background: white;

            border: 1px solid #dce8f3;

            border-radius: 8px;

            padding: 16px 18px;

            box-shadow: 0 2px 6px rgba(0,0,0,0.04);

            overflow: hidden;
        }

        .box-title {
            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 12px;
        }

        .box-title h2 {
            font-size: 15px;

            color: #0759bd;

            font-weight: bold;
        }

        .box-title span {
            font-size: 10px;

            color: #64748b;
        }


        /* =========================
           COMBINED BBM + PELUMAS
        ========================= */

        .combined-chart {
            width: 100%;
        }

        .combined-chart-area {
            position: relative;
            height: 250px;
            margin-top: 8px;
        }

        .chart-canvas-wrap {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 12px;
            font-size: 11px;
            color: #64748b;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .legend-box {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            background: #397ee8;
        }

        .legend-line {
            position: relative;
            width: 20px;
            height: 3px;
            border-radius: 3px;
            background: #dc2626;
        }

        .legend-line::after {
            content: "";
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #dc2626;
            left: 6px;
            top: -4px;
        }

        /* =========================
           TABLE
        ========================= */

        .table-wrapper {
            width: 100%;

            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;
        }

        th {
            background: #eef5fc;

            color: #4b6380;

            font-size: 10px;

            font-weight: bold;

            padding: 9px 8px;

            text-align: left;

            white-space: nowrap;
        }

        td {
            padding: 9px 8px;

            border-top: 1px solid #edf2f7;

            font-size: 10px;

            white-space: nowrap;
        }

        td strong {
            color: #1f2937;
        }


        /* =========================
           STATUS BADGE
        ========================= */

        .badge {
            display: inline-block;

            padding: 4px 9px;

            border-radius: 5px;

            font-size: 9px;

            font-weight: bold;

            text-align: center;

            min-width: 78px;
        }

        .badge-green {
            background: #dcfce7;

            color: #15803d;
        }

        .badge-yellow {
            background: #fef3c7;

            color: #a16207;
        }

        .badge-red {
            background: #fee2e2;

            color: #dc2626;
        }

        .badge-purple {
            background: #ede9fe;

            color: #7c3aed;
        }

        .badge-orange {
            background: #ffedd5;

            color: #ea580c;
        }

        .badge-blue {
            background: #dbeafe;

            color: #2563eb;
        }


        /* =========================
           UNIT PERHATIAN
        ========================= */

        .attention-table th,
        .attention-table td {
            font-size: 10px;
        }

        .attention-table .badge {
            min-width: 75px;
        }


        /* =========================
           DOWNTIME CHART
        ========================= */

        .downtime-chart {
            height: 210px;

            display: flex;

            align-items: flex-end;

            justify-content: space-around;

            border-bottom: 1px solid #dbe4ed;

            padding: 8px 20px 0;
        }

        .downtime-column {
            height: 100%;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: flex-end;

            gap: 5px;

            width: 14%;
        }

        .downtime-value {
            font-size: 11px;

            font-weight: bold;

            color: #475569;
        }

        .downtime-bar {
            width: 38px;

            border-radius: 4px 4px 0 0;

            background: #3981e8;
        }

        .downtime-bar.red {
            background: #f04444;
        }

        .downtime-label {
            font-size: 10px;

            color: #64748b;
        }

        .axis-label {
            font-size: 9px;

            color: #94a3b8;

            margin-top: 6px;
        }


        /* =========================
           LINK
        ========================= */

        .see-more {
            color: #0759bd;

            text-decoration: none;

            font-size: 10px;

            font-weight: bold;
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1200px) {

            .kpi {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media (max-width: 900px) {

            .dashboard-grid,
            .dashboard-grid.equal {
                grid-template-columns: 1fr;
            }

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

            .kpi {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;

                align-items: flex-start;

                gap: 10px;
            }

        }

        /* FILTER LOKASI DASHBOARD */
.location-filter {
    margin-top: 14px;
}

.location-filter label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
}

.location-filter form {
    margin: 0;
}

.location-filter select {
    width: 280px;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #d9e2ec;
    border-radius: 8px;
    background: #ffffff;
    color: #334155;
    font-size: 13px;
    font-family: inherit;
    cursor: pointer;
    outline: none;
}

.location-filter select:hover,
.location-filter select:focus {
    border-color: #0759bd;
}

.location-filter select:focus {
    box-shadow: 0 0 0 3px rgba(7, 89, 189, 0.08);
}
    </style>

</head>


<body>


    <!-- =========================
         SIDEBAR
    ========================= -->

    <aside class="sidebar">

        <div class="logo">

            <img
                src="assets/logo.png"
                alt="Logo PT Biringkassi Raya"
            >

            <div class="logo-text">
                PT BIRINGKASSI RAYA
            </div>

        </div>


        <ul class="menu">

            <li>
                <a
                    href="index.php"
                    class="active"
                >
                    Dashboard
                </a>
            </li>

            <li>
                <a href="equipment/index.php">
                    Equipment Master
                </a>
            </li>

            <li>
                <a href="#">
                    Status Unit
                </a>
            </li>

            <li>
                <a href="maintenance/index.php">
                    Jadwal Pemeliharaan
                </a>
            </li>

            <li>
                <a href="maintenance/history/index.php">
                    Histori Pemeliharaan
                </a>
            </li>

            <li>
                <a href="breakdown/index.php">
                    Breakdown & Failure
                </a>
            </li>

<li class="menu-dropdown">
    <a
        href="javascript:void(0);"
        onclick="
            var submenu = document.getElementById('spareSubmenu');
            var arrow = document.getElementById('spareArrow');

            if (submenu.style.display === 'none' || submenu.style.display === '') {
                submenu.style.display = 'block';
                arrow.textContent = '▼';
            } else {
                submenu.style.display = 'none';
                arrow.textContent = '▶';
            }
        "
    >
        Spare Part & Lubricant
        <span id="spareArrow" class="menu-arrow">▶</span>
    </a>

    <ul id="spareSubmenu" class="submenu" style="display: none;">
        <li>
            <a href="sparepart/index.php">
                Spare Part
            </a>
        </li>

        <li>
            <a href="lubricant/index.php">
                Lubricant
            </a>
        </li>
    </ul>
</li>

<li>
    <a href="fuel/index.php">
        Fuel Performance
    </a>
</li>

<li>
    <a href="#">
        Biaya Pemeliharaan
    </a>
    
</li>
            <li>
                <a href="#">
                    Kinerja & Evaluasi
                </a>
            </li>

            <li>
                <a href="#">
                    Laporan
                </a>
            </li>

        </ul>

    </aside>



    <!-- =========================
         MAIN
    ========================= -->

    <main class="main">


        <!-- HEADER -->

        <div class="header">

            <div class="header-left">

                <h1>
                    Dashboard
                </h1>

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

    <div class="kpi-card">

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
                            Pelumas (Kali)
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

                                backgroundColor: "#397ee8",

                                borderRadius: 5,

                                borderSkipped: false,

                                barPercentage: 0.62,

                                categoryPercentage: 0.72,

                                yAxisID: "y"
                            },

                            {
                                type: "line",

                                label: "Pelumas (Kali)",

                                data: <?= json_encode($dashboard_chart_lubricant_values); ?>,

                                borderColor: "#dc2626",

                                backgroundColor: "#dc2626",

                                borderWidth: 3,

                                tension: 0.25,

                                pointRadius: 5,

                                pointHoverRadius: 7,

                                pointBackgroundColor: "#ffffff",

                                pointBorderColor: "#dc2626",

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

                                backgroundColor: "#ffffff",

                                titleColor: "#1e293b",

                                bodyColor: "#475569",

                                borderColor: "#dbe4ee",

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

                                    color: "#64748b",

                                    font: {
                                        size: 10
                                    },

                                    callback: function(value) {
                                        return value.toLocaleString("id-ID");
                                    }

                                },

                                title: {

                                    display: true,

                                    text: "BBM (Liter)",

                                    color: "#1767b1",

                                    font: {
                                        size: 11,
                                        weight: "bold"
                                    }

                                },

                                grid: {
                                    color: "#e5eaf0",
                                    borderDash: [5, 5]
                                }

                            },

                            y1: {

                                position: "right",

                                beginAtZero: true,

                                max: 120,

                                ticks: {

                                    stepSize: 30,

                                    color: "#dc2626",

                                    font: {
                                        size: 10
                                    }

                                },

                                title: {

                                    display: true,

                                    text: "Pelumas (Kali)",

                                    color: "#dc2626",

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

                                    color: "#64748b",

                                    font: {
                                        size: 10
                                    }

                                },

                                grid: {

                                    color: "#edf2f7",

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

    borderWidth: 2
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
                                    ((value / total) * 100).toFixed(1);

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
                    "Overdue"
                ],

                datasets: [{
                    label: "Jumlah Jadwal",

                    data: [
                        <?= $maintenance_selesai; ?>,
                        <?= $maintenance_segera; ?>,
                        <?= $maintenance_overdue; ?>
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
                    <th>HM/KM Terakhir</th>
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
                        <td colspan="5" style="text-align:center; padding:20px; color:#64748b;">
                            Belum ada data BBM untuk filter ini.
                        </td>
                    </tr>

                <?php else: ?>

                    <tr>
                        <td colspan="4" style="text-align:center; padding:20px; color:#64748b;">
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
    <td colspan="6" style="text-align: center; padding: 20px; color: #64748b;">
        Belum ada data maintenance.
    </td>
</tr>

<?php

    endif;

    $maintenance_stmt->close();

} else {

?>

<tr>
    <td colspan="6" style="text-align: center; padding: 20px; color: #64748b;">
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



        <!-- =========================
             UNIT PERHATIAN + DOWNTIME
        ========================= -->

        <section class="dashboard-grid equal">


            <div class="box">

                <div class="box-title">

                    <h2>
                        ⚠ &nbsp; Unit Perlu Perhatian
                    </h2>

                    <a href="maintenance/index.php" class="see-more">
                        Lihat Semua
                    </a>

                </div>


                <div class="table-wrapper">

                    <table class="attention-table">

                        <thead>

                            <tr>

                                <th>ID Unit</th>
                                <th>Masalah</th>
                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>

                            <tr>

                                <td>
                                    <strong>EXC-001</strong>
                                </td>

                                <td>
                                    PM Overdue (150 HM)
                                </td>

                                <td>
                                    <span class="badge badge-red">
                                        Overdue
                                    </span>
                                </td>

                            </tr>


                            <tr>

                                <td>
                                    <strong>WL-003</strong>
                                </td>

                                <td>
                                    Frekuensi Failure (Hydraulic)
                                </td>

                                <td>
                                    <span class="badge badge-purple">
                                        Investigasi
                                    </span>
                                </td>

                            </tr>


                            <tr>

                                <td>
                                    <strong>DT-005</strong>
                                </td>

                                <td>
                                    Downtime tinggi (48 jam)
                                </td>

                                <td>
                                    <span class="badge badge-orange">
                                        Breakdown
                                    </span>
                                </td>

                            </tr>


                            <tr>

                                <td>
                                    <strong>EXC-007</strong>
                                </td>

                                <td>
                                    Konsumsi BBM tidak wajar
                                </td>

                                <td>
                                    <span class="badge badge-yellow">
                                        Monitoring
                                    </span>
                                </td>

                            </tr>


                            <tr>

                                <td>
                                    <strong>WL-009</strong>
                                </td>

                                <td>
                                    Menunggu spare part
                                </td>

                                <td>
                                    <span class="badge badge-blue">
                                        Waiting Part
                                    </span>
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>



            <div class="box">

                <div class="box-title">

                    <h2>
                        🕒 &nbsp; Top 5 Downtime Unit (Bulan Ini)
                    </h2>

                </div>


                <div class="downtime-chart">


                    <div class="downtime-column">

                        <div class="downtime-value">
                            72
                        </div>

                        <div
                            class="downtime-bar red"
                            style="height: 150px;"
                        ></div>

                        <div class="downtime-label">
                            DT-05
                        </div>

                    </div>


                    <div class="downtime-column">

                        <div class="downtime-value">
                            56
                        </div>

                        <div
                            class="downtime-bar"
                            style="height: 118px;"
                        ></div>

                        <div class="downtime-label">
                            EXC-01
                        </div>

                    </div>


                    <div class="downtime-column">

                        <div class="downtime-value">
                            38
                        </div>

                        <div
                            class="downtime-bar"
                            style="height: 82px;"
                        ></div>

                        <div class="downtime-label">
                            WL-03
                        </div>

                    </div>


                    <div class="downtime-column">

                        <div class="downtime-value">
                            26
                        </div>

                        <div
                            class="downtime-bar"
                            style="height: 58px;"
                        ></div>

                        <div class="downtime-label">
                            DT-02
                        </div>

                    </div>


                    <div class="downtime-column">

                        <div class="downtime-value">
                            18
                        </div>

                        <div
                            class="downtime-bar"
                            style="height: 42px;"
                        ></div>

                        <div class="downtime-label">
                            WL-07
                        </div>

                    </div>


                </div>


                <div class="axis-label">
                    Downtime (Jam)
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