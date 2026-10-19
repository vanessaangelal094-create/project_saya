<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

/* =========================================================
   FILTER EQUIPMENT
========================================================= */

$selected_equipment = isset($_GET['equipment_id'])
    ? (int) $_GET['equipment_id']
    : 0;


/* =========================================================
   DATA EQUIPMENT
========================================================= */

$equipment_result = $conn->query("
    SELECT
        id,
        equipment_code,
        equipment_name,
        equipment_type
    FROM equipment
    ORDER BY equipment_code ASC
");


/* =========================================================
   DATA EQUIPMENT TERPILIH
========================================================= */

$selected_equipment_data = null;

if ($selected_equipment > 0) {

    $stmt_equipment = $conn->prepare("
        SELECT
            id,
            equipment_code,
            equipment_name,
            equipment_type,
            hm_current
        FROM equipment
        WHERE id = ?
    ");

    if ($stmt_equipment) {

        $stmt_equipment->bind_param(
            "i",
            $selected_equipment
        );

        $stmt_equipment->execute();

        $equipment_selected_result =
            $stmt_equipment->get_result();

        $selected_equipment_data =
            $equipment_selected_result->fetch_assoc();

        $stmt_equipment->close();
    }
}


/* =========================================================
   DATA LUBRICANT
========================================================= */

$lubricant_data = [];

if ($selected_equipment > 0) {

    $stmt = $conn->prepare("
        SELECT
            l.id,
            l.tanggal,
            l.jenis_pelumas,
            l.merek,
            l.jumlah,
            l.satuan,
            l.hm_km,
            l.keterangan,
            e.equipment_code,
            e.equipment_name,
            e.equipment_type
        FROM lubricant l
        INNER JOIN equipment e
            ON l.equipment_id = e.id
        WHERE l.equipment_id = ?
        ORDER BY l.tanggal DESC, l.id DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $selected_equipment
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $lubricant_data[] = $row;
        }

        $stmt->close();
    }
}


/* =========================================================
   SUMMARY
========================================================= */

$total_replacement = count($lubricant_data);

$total_liter = 0;
$this_month_replacement = 0;
$last_replacement = '-';

$current_month = date('Y-m');

foreach ($lubricant_data as $item) {

    $satuan = strtolower(trim($item['satuan'] ?? ''));

    if ($satuan === 'liter' || $satuan === 'l') {
        $total_liter += (float) $item['jumlah'];
    }

    if (
        !empty($item['tanggal']) &&
        date('Y-m', strtotime($item['tanggal'])) === $current_month
    ) {
        $this_month_replacement++;
    }

    if ($last_replacement === '-' && !empty($item['tanggal'])) {
        $last_replacement = date(
            'd/m/Y',
            strtotime($item['tanggal'])
        );
    }
}


/* =========================================================
   DATA GRAFIK BULANAN
========================================================= */

$monthly_usage = [];

for ($i = 8; $i >= 0; $i--) {

    $month_key = date(
        'Y-m',
        strtotime("-$i months")
    );

    $month_label = date(
        'M Y',
        strtotime("-$i months")
    );

    $monthly_usage[$month_key] = [
        'label' => $month_label,
        'value' => 0
    ];
}

foreach ($lubricant_data as $item) {

    if (empty($item['tanggal'])) {
        continue;
    }

    $month_key = date(
        'Y-m',
        strtotime($item['tanggal'])
    );

    if (isset($monthly_usage[$month_key])) {

        $satuan = strtolower(
            trim($item['satuan'] ?? '')
        );

        if ($satuan === 'liter' || $satuan === 'l') {

            $monthly_usage[$month_key]['value']
                += (float) $item['jumlah'];
        }
    }
}


/* =========================================================
   DATA GRAFIK BERDASARKAN JENIS
========================================================= */

$type_usage = [];

foreach ($lubricant_data as $item) {

    $jenis = trim(
        $item['jenis_pelumas'] ?? ''
    );

    if ($jenis === '') {
        $jenis = 'Tidak diketahui';
    }

    if (!isset($type_usage[$jenis])) {
        $type_usage[$jenis] = 0;
    }

    $type_usage[$jenis]++;
}


/* =========================================================
   DATA UNTUK JAVASCRIPT
========================================================= */

$chart_month_labels = [];

$chart_month_values = [];

foreach ($monthly_usage as $month) {

    $chart_month_labels[] = $month['label'];

    $chart_month_values[] = $month['value'];
}


$chart_type_labels = array_keys($type_usage);

$chart_type_values = array_values($type_usage);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Lubricant - PT Biringkassi Raya</title>


    <!-- Chart.js -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;

            color: #172033;
        }


        /* =================================================
           SIDEBAR
        ================================================= */

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


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 35px;

            padding-left: 10px;
        }


        .brand img {

            width: 42px;

            height: 42px;

            object-fit: contain;
        }


        .brand span {

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


        .menu a:hover {

            background: rgba(255, 255, 255, 0.12);
        }


        .menu a.active {

            background: rgba(255, 255, 255, 0.18);
        }


        .menu-dropdown > a {

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .submenu {

            list-style: none;

            display: block;

            margin-top: 3px;
        }


        .submenu a {

            padding: 10px 14px 10px 30px;

            font-size: 13px;

            background: rgba(255,255,255,0.08);

            margin-bottom: 3px;
        }


        .submenu a.active {

            background: rgba(255,255,255,0.18);
        }


        /* =================================================
           CONTENT
        ================================================= */

        .main {

            margin-left: 240px;

            min-height: 100vh;

            padding: 35px 40px;
        }


        .breadcrumb {

            color: #64748b;

            font-size: 14px;

            margin-bottom: 10px;
        }


        .breadcrumb span {

            color: #0759bd;

            font-weight: 600;
        }


        h1 {

            color: #123b6d;

            font-size: 30px;

            margin-bottom: 8px;
        }


        .description {

            color: #64748b;

            margin-bottom: 28px;
        }
.btn-add {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    margin-bottom: 25px;
    border-radius: 8px;
    background: #0759bd;
    color: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.btn-add:hover {
    background: #064b9f;
}

        /* =================================================
           FILTER
        ================================================= */

        .filter-box {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 25px;
        }


        .filter-title {

            font-size: 18px;

            font-weight: 700;

            margin-bottom: 18px;

            color: #172033;
        }


        .filter-form {

            display: flex;

            gap: 15px;

            align-items: end;
        }


        .form-group {

            flex: 1;
        }


        .form-group label {

            display: block;

            font-size: 13px;

            font-weight: 600;

            color: #475569;

            margin-bottom: 7px;
        }


        .form-group select {

            width: 100%;

            height: 44px;

            padding: 0 13px;

            border: 1px solid #d5dee9;

            border-radius: 8px;

            background: white;

            color: #334155;

            font-size: 14px;

            outline: none;
        }


        .form-group select:focus {

            border-color: #0759bd;

            box-shadow:
                0 0 0 3px
                rgba(7,89,189,0.08);
        }


        .btn-filter {

            height: 44px;

            padding: 0 24px;

            border: none;

            border-radius: 8px;

            background: #0759bd;

            color: white;

            font-weight: 600;

            cursor: pointer;
        }


        .btn-filter:hover {

            background: #064b9f;
        }


        .btn-reset {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 44px;

            padding: 0 20px;

            border: 1px solid #d5dee9;

            border-radius: 8px;

            color: #334155;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            background: white;
        }


        /* =================================================
           EQUIPMENT INFO
        ================================================= */

        .equipment-info {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 25px;
        }


        .equipment-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;
        }


        .equipment-header h2 {

            font-size: 20px;

            color: #123b6d;
        }


        .equipment-code {

            background: #eaf3ff;

            color: #0759bd;

            padding: 7px 13px;

            border-radius: 7px;

            font-weight: 700;

            font-size: 13px;
        }


        .equipment-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;
        }


        .info-item {

            background: #f8fafc;

            border-radius: 9px;

            padding: 15px;
        }


        .info-label {

            color: #64748b;

            font-size: 12px;

            margin-bottom: 5px;
        }


        .info-value {

            font-size: 15px;

            font-weight: 600;

            color: #172033;
        }


        /* =================================================
           SUMMARY
        ================================================= */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 25px;
        }


        .summary-card {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            padding: 20px;
        }


        .summary-label {

            color: #64748b;

            font-size: 13px;

            margin-bottom: 9px;
        }


        .summary-value {

            color: #123b6d;

            font-size: 26px;

            font-weight: 700;
        }


        .summary-unit {

            color: #64748b;

            font-size: 12px;

            margin-left: 4px;
        }


        /* =================================================
           CHART
        ================================================= */

        .chart-grid {

            display: grid;

            grid-template-columns:
                1.5fr 1fr;

            gap: 20px;

            margin-bottom: 25px;
        }


        .chart-box {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            padding: 22px;

            min-height: 350px;
        }


        .chart-header {

            margin-bottom: 18px;
        }


        .chart-header h2 {

            font-size: 19px;

            color: #172033;

            margin-bottom: 5px;
        }


        .chart-header p {

            color: #64748b;

            font-size: 12px;
        }


        .chart-container {

            position: relative;

            height: 270px;
        }


        /* =================================================
           TABLE
        ================================================= */

        .table-box {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            overflow: hidden;
        }


        .table-header {

            padding: 22px 25px;

            border-bottom: 1px solid #e8edf3;
        }


        .table-header h2 {

            font-size: 20px;

            color: #172033;
        }


        .table-header p {

            color: #64748b;

            font-size: 13px;

            margin-top: 5px;
        }


        .table-wrapper {

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 950px;
        }


        th {

            background: #f5f8fc;

            color: #64748b;

            font-size: 12px;

            text-align: left;

            padding: 14px 16px;

            white-space: nowrap;
        }


        td {

            padding: 15px 16px;

            border-top: 1px solid #edf1f5;

            font-size: 13px;

            color: #334155;

            vertical-align: top;
        }


        tbody tr:hover {

            background: #fafcff;
        }


        .item-name {

            font-weight: 700;

            color: #172033;
        }


        .spec {

            color: #64748b;

            margin-top: 4px;

            font-size: 12px;
        }


        .badge {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

            background: #eaf3ff;

            color: #0759bd;

            white-space: nowrap;
        }


        .empty {

            padding: 55px 20px;

            text-align: center;

            color: #64748b;
        }


        .empty-icon {

            font-size: 40px;

            margin-bottom: 12px;
        }


        .empty h3 {

            color: #334155;

            margin-bottom: 7px;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1100px) {

            .equipment-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .summary-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .chart-grid {

                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 800px) {

            .sidebar {

                width: 210px;
            }

            .main {

                margin-left: 210px;

                padding: 25px;
            }

            .filter-form {

                flex-direction: column;

                align-items: stretch;
            }

            .summary-grid {

                grid-template-columns: 1fr;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="brand">

        <img
            src="../assets/logo.png"
            alt="Logo PT Biringkassi Raya"
        >

        <span>
            PT BIRINGKASSI<br>
            RAYA
        </span>

    </div>


    <ul class="menu">

        <li>
            <a href="../index.php">
                Dashboard
            </a>
        </li>


        <li>
            <a href="../equipment/index.php">
                Equipment Master
            </a>
        </li>


        <li>
            <a href="#">
                Status Unit
            </a>
        </li>


        <li>
            <a href="../maintenance/index.php">
                Jadwal Pemeliharaan
            </a>
        </li>


        <li>
            <a href="../maintenance/history/index.php">
                Histori Pemeliharaan
            </a>
        </li>


        <li>
            <a href="#">
                Breakdown & Failure
            </a>
        </li>


        <li class="menu-dropdown">

            <a href="javascript:void(0);">

                Spare Part & Lubricant

                <span>▴</span>

            </a>


            <ul class="submenu">

                <li>

                    <a href="../sparepart/index.php">

                        Spare Part

                    </a>

                </li>


                <li>

                    <a
                        href="index.php"
                        class="active"
                    >

                        Lubricant

                    </a>

                </li>

            </ul>

        </li>


        <li>
            <a href="#">
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


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main">


    <div class="breadcrumb">

        Home
        &nbsp;›&nbsp;

        <span>
            Lubricant
        </span>

    </div>


    <h1>
        Lubricant
    </h1>


    <p class="description">
        
        Rekam jejak penggunaan pelumas pada setiap alat berat.

    </p>
    <a href="tambah.php" class="btn-add">
    + Tambah Data Pelumas
</a>


    <!-- =================================================
         FILTER EQUIPMENT
    ================================================= -->

    <section class="filter-box">

        <div class="filter-title">

    🔎 Pilih Equipment

    <a href="javascript:history.back()"
       style="
           float:right;
           padding:9px 16px;
           border:1px solid #0759bd;
           border-radius:8px;
           color:#0759bd;
           background:white;
           text-decoration:none;
           font-size:13px;
           font-weight:600;
       ">
        ← Kembali
    </a>

</div>


        <form
            method="GET"
            action="index.php"
            class="filter-form"
        >


            <div class="form-group">

                <label for="equipment_id">

                    Equipment

                </label>


                <select
                    name="equipment_id"
                    id="equipment_id"
                    required
                >

                    <option value="">

                        Pilih Equipment

                    </option>


                    <?php if ($equipment_result): ?>

                        <?php while (
                            $equipment =
                            $equipment_result->fetch_assoc()
                        ): ?>

                            <option
                                value="<?= (int) $equipment['id']; ?>"
                                <?= (
                                    $selected_equipment ==
                                    $equipment['id']
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $equipment['equipment_code']
                                ); ?>

                                -

                                <?= htmlspecialchars(
                                    $equipment['equipment_name']
                                ); ?>

                            </option>

                        <?php endwhile; ?>

                    <?php endif; ?>

                </select>

            </div>


            <button
                type="submit"
                class="btn-filter"
            >

                Tampilkan

            </button>


            <a
                href="index.php"
                class="btn-reset"
            >

                Reset

            </a>


        </form>

    </section>


    <?php if ($selected_equipment_data): ?>


        <!-- =================================================
             EQUIPMENT INFO
        ================================================= -->

        <section class="equipment-info">

            <div class="equipment-header">

                <h2>
                    Informasi Equipment
                </h2>


                <span class="equipment-code">

                    <?= htmlspecialchars(
                        $selected_equipment_data['equipment_code']
                    ); ?>

                </span>

            </div>


            <div class="equipment-grid">


                <div class="info-item">

                    <div class="info-label">
                        Nama Equipment
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $selected_equipment_data['equipment_name']
                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Jenis
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $selected_equipment_data['equipment_type']
                            ?? '-'
                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        HM Saat Ini
                    </div>

                    <div class="info-value">

                        <?= number_format(
                            (float) (
                                $selected_equipment_data['hm_current']
                                ?? 0
                            ),
                            0,
                            ',',
                            '.'
                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Total Penggantian
                    </div>

                    <div class="info-value">

                        <?= $total_replacement; ?>

                        Kali

                    </div>

                </div>


            </div>

        </section>


        <!-- =================================================
             SUMMARY
        ================================================= -->

        <section class="summary-grid">


            <div class="summary-card">

                <div class="summary-label">

                    Total Penggunaan

                </div>

                <div class="summary-value">

                    <?= number_format(
                        $total_liter,
                        2,
                        ',',
                        '.'
                    ); ?>

                    <span class="summary-unit">
                        Liter
                    </span>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">

                    Total Penggantian

                </div>

                <div class="summary-value">

                    <?= $total_replacement; ?>

                    <span class="summary-unit">
                        Kali
                    </span>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">

                    Penggantian Bulan Ini

                </div>

                <div class="summary-value">

                    <?= $this_month_replacement; ?>

                    <span class="summary-unit">
                        Kali
                    </span>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">

                    Penggantian Terakhir

                </div>

                <div class="summary-value"
                     style="font-size:22px;">

                    <?= $last_replacement; ?>

                </div>

            </div>


        </section>


        <!-- =================================================
             CHARTS
        ================================================= -->

        <section class="chart-grid">


            <div class="chart-box">

                <div class="chart-header">

                    <h2>
                        Tren Penggunaan Pelumas
                    </h2>

                    <p>
                        Penggunaan pelumas berdasarkan bulan.
                    </p>

                </div>


                <div class="chart-container">

                    <canvas id="monthlyChart"></canvas>

                </div>

            </div>


            <div class="chart-box">

                <div class="chart-header">

                    <h2>
                        Penggantian Berdasarkan Jenis
                    </h2>

                    <p>
                        Jumlah penggantian berdasarkan jenis pelumas.
                    </p>

                </div>


                <div class="chart-container">

                    <canvas id="typeChart"></canvas>

                </div>

            </div>


        </section>


        <!-- =================================================
             TABLE
        ================================================= -->

        <section class="table-box">

            <div class="table-header">

                <h2>
                    Riwayat Penggunaan Pelumas
                </h2>

                <p>
                    Daftar penggunaan dan penggantian pelumas
                    berdasarkan equipment.
                </p>

            </div>


            <?php if (!empty($lubricant_data)): ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    No
                                </th>

                                <th>
                                    Tanggal
                                </th>

                                <th>
                                    ID Unit
                                </th>

                                <th>
                                    Jenis Pelumas
                                </th>

                                <th>
                                    Merek
                                </th>

                                <th>
                                    Jumlah
                                </th>

                                <th>
                                    Satuan
                                </th>

                                <th>
                                    HM
                                </th>

                                <th>
                                    Keterangan
                                </th>
                        <th>
    Aksi
</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php
                            $no = 1;

                            foreach (
                                $lubricant_data
                                as $item
                            ):
                            ?>

                                <tr>

                                    <td>
                                        <?= $no++; ?>
                                    </td>


                                    <td>

                                        <?= !empty(
                                            $item['tanggal']
                                        )
                                            ? date(
                                                'd/m/Y',
                                                strtotime(
                                                    $item['tanggal']
                                                )
                                            )
                                            : '-';
                                        ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $item['equipment_code']
                                            ); ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <div class="item-name">

                                            <?= htmlspecialchars(
                                                $item['jenis_pelumas']
                                            ); ?>

                                        </div>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $item['merek']
                                            ?? '-'
                                        ); ?>

                                    </td>


                                    <td>

                                        <?= number_format(
                                            (float) (
                                                $item['jumlah']
                                                ?? 0
                                            ),
                                            2,
                                            ',',
                                            '.'
                                        ); ?>

                                    </td>


                                    <td>

                                        <span class="badge">

                                            <?= htmlspecialchars(
                                                $item['satuan']
                                                ?? '-'
                                            ); ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?= $item['hm_km'] !== null
                                            && $item['hm_km'] !== ''
                                            ? number_format(
                                                (float)
                                                $item['hm_km'],
                                                2,
                                                ',',
                                                '.'
                                            )
                                            : '-';
                                        ?>

                                    </td>


                                  <td>
    <?= htmlspecialchars(
        $item['keterangan']
        ?? '-'
    ); ?>
</td>

<td>
    <a
        href="delete.php?id=<?= (int) $item['id']; ?>"
        onclick="return confirm('Yakin ingin menghapus data pelumas ini?');"
        style="
            display:inline-block;
            padding:7px 12px;
            background:#dc3545;
            color:white;
            text-decoration:none;
            border-radius:6px;
            font-size:12px;
            font-weight:600;
        "
    >
        Hapus
    </a>
</td>

</tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-icon">
                        🛢️
                    </div>


                    <h3>
                        Belum Ada Data Pelumas
                    </h3>


                    <p>
                        Belum terdapat riwayat penggunaan
                        pelumas untuk equipment ini.
                    </p>

                </div>


            <?php endif; ?>

        </section>


  <?php else: ?>

<!-- =================================================
     REKAP SEMUA EQUIPMENT
================================================= -->

<?php
$stmt_all = $conn->prepare("
    SELECT
        COUNT(*) AS total_replacement,
        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(TRIM(satuan)) IN ('liter', 'l')
                    THEN jumlah
                    ELSE 0
                END
            ), 0
        ) AS total_liter,
        COALESCE(
            SUM(
                CASE
                    WHEN DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                    THEN 1
                    ELSE 0
                END
            ), 0
        ) AS this_month_replacement,
        MAX(tanggal) AS last_replacement
    FROM lubricant
");

$stmt_all->execute();
$all_summary = $stmt_all->get_result()->fetch_assoc();
$stmt_all->close();

$all_total_liter = (float) ($all_summary['total_liter'] ?? 0);
$all_total_replacement = (int) ($all_summary['total_replacement'] ?? 0);
$all_this_month = (int) ($all_summary['this_month_replacement'] ?? 0);

$all_last_replacement = '-';

if (!empty($all_summary['last_replacement'])) {
    $all_last_replacement = date(
        'd/m/Y',
        strtotime($all_summary['last_replacement'])
    );
}
?>

<section class="summary-grid">

    <div class="summary-card">
        <div class="summary-label">
            Total Penggunaan
        </div>

        <div class="summary-value">
            <?= number_format($all_total_liter, 2); ?>
            <span class="summary-unit">Liter</span>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-label">
            Total Penggantian
        </div>

        <div class="summary-value">
            <?= $all_total_replacement; ?>
            <span class="summary-unit">Kali</span>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-label">
            Penggantian Bulan Ini
        </div>

        <div class="summary-value">
            <?= $all_this_month; ?>
            <span class="summary-unit">Kali</span>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-label">
            Penggantian Terakhir
        </div>

        <div class="summary-value">
            <?= $all_last_replacement; ?>
        </div>
    </div>

</section>

<?php
/* =================================================
   DATA GRAFIK SEMUA EQUIPMENT
================================================= */

$all_monthly_usage = [];

for ($i = 8; $i >= 0; $i--) {

    $month_key = date(
        'Y-m',
        strtotime("-$i months")
    );

    $month_label = date(
        'M Y',
        strtotime("-$i months")
    );

    $all_monthly_usage[$month_key] = [
        'label' => $month_label,
        'value' => 0
    ];
}

$all_type_usage = [];

$stmt_chart = $conn->query("
    SELECT
        tanggal,
        jumlah,
        satuan,
        jenis_pelumas
    FROM lubricant
    ORDER BY tanggal ASC
");

if ($stmt_chart) {

    while ($row = $stmt_chart->fetch_assoc()) {

        /* Grafik bulanan */
        if (!empty($row['tanggal'])) {

            $month_key = date(
                'Y-m',
                strtotime($row['tanggal'])
            );

            if (isset($all_monthly_usage[$month_key])) {

                $satuan = strtolower(
                    trim($row['satuan'] ?? '')
                );

                if ($satuan === 'liter' || $satuan === 'l') {

                    $all_monthly_usage[$month_key]['value']
                        += (float) $row['jumlah'];
                }
            }
        }

        /* Grafik berdasarkan jenis */
        $jenis = trim(
            $row['jenis_pelumas'] ?? ''
        );

        if ($jenis === '') {
            $jenis = 'Tidak diketahui';
        }

        if (!isset($all_type_usage[$jenis])) {
            $all_type_usage[$jenis] = 0;
        }

        $all_type_usage[$jenis]++;
    }
}

$all_chart_month_labels = [];
$all_chart_month_values = [];

foreach ($all_monthly_usage as $month) {

    $all_chart_month_labels[] = $month['label'];
    $all_chart_month_values[] = $month['value'];
}

$all_chart_type_labels = array_keys(
    $all_type_usage
);

$all_chart_type_values = array_values(
    $all_type_usage
);
?>

<section class="chart-grid">

    <div class="chart-box">

        <div class="chart-header">

            <h2>
                Tren Penggunaan Pelumas
            </h2>

            <p>
                Total penggunaan pelumas seluruh equipment
                dalam 9 bulan terakhir.
            </p>

        </div>

        <div class="chart-container">
            <canvas id="allMonthlyChart"></canvas>
        </div>

    </div>


    <div class="chart-box">

        <div class="chart-header">

            <h2>
                Penggantian Berdasarkan Jenis
            </h2>

            <p>
                Jumlah penggantian berdasarkan jenis pelumas
                pada seluruh equipment.
            </p>

        </div>

        <div class="chart-container">
            <canvas id="allTypeChart"></canvas>
        </div>

    </div>

</section>

<?php endif; ?>

</main>


<script>
/* =====================================================
   GRAFIK EQUIPMENT TERPILIH
===================================================== */

<?php if ($selected_equipment_data): ?>

const monthlyLabels = <?= json_encode($chart_month_labels); ?>;
const monthlyValues = <?= json_encode($chart_month_values); ?>;

const monthlyCanvas = document.getElementById('monthlyChart');

if (monthlyCanvas) {

    new Chart(monthlyCanvas, {

        type: 'line',

        data: {
            labels: monthlyLabels,

            datasets: [{
                label: 'Penggunaan Pelumas (Liter)',
                data: monthlyValues,
                borderWidth: 3,
                tension: 0.35,
                fill: false
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true
                }
            },

            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }

    });
}


/* =====================================================
   GRAFIK JENIS EQUIPMENT TERPILIH
===================================================== */

const typeLabels = <?= json_encode($chart_type_labels); ?>;
const typeValues = <?= json_encode($chart_type_values); ?>;

const typeCanvas = document.getElementById('typeChart');

if (typeCanvas) {

    new Chart(typeCanvas, {

        type: 'doughnut',

        data: {
            labels: typeLabels,

            datasets: [{
                label: 'Jumlah Penggantian',
                data: typeValues,
                borderWidth: 1
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }

    });
}

<?php endif; ?>

/* =====================================================
   GRAFIK SEMUA EQUIPMENT
===================================================== */

const allMonthlyLabels =
    <?= json_encode($all_chart_month_labels); ?>;

const allMonthlyValues =
    <?= json_encode($all_chart_month_values); ?>;

const allMonthlyCanvas =
    document.getElementById('allMonthlyChart');

if (allMonthlyCanvas) {

    new Chart(
        allMonthlyCanvas,
        {
            type: 'line',

            data: {
                labels: allMonthlyLabels,

                datasets: [
                    {
                        label: 'Penggunaan Pelumas (Liter)',

                        data: allMonthlyValues,

                        borderWidth: 3,

                        tension: 0.35,

                        fill: false
                    }
                ]
            },

            options: {
                responsive: true,

                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: true
                    }
                },

                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        }
    );
}


/* =====================================================
   GRAFIK JENIS SEMUA EQUIPMENT
===================================================== */

const allTypeLabels =
    <?= json_encode($all_chart_type_labels); ?>;

const allTypeValues =
    <?= json_encode($all_chart_type_values); ?>;

const allTypeCanvas =
    document.getElementById('allTypeChart');

if (allTypeCanvas) {

    new Chart(
        allTypeCanvas,
        {
            type: 'doughnut',

            data: {
                labels: allTypeLabels,

                datasets: [
                    {
                        label: 'Jumlah Penggantian',

                        data: allTypeValues,

                        borderWidth: 1
                    }
                ]
            },

            options: {
                responsive: true,

                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        }
    );
}
</script>


</body>

</html>