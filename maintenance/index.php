<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   FILTER
========================================================= */

$search = $_GET['search'] ?? '';
$jenis = $_GET['jenis'] ?? '';
$status_filter = $_GET['status'] ?? '';


/* =========================================================
   DATA MAINTENANCE
========================================================= */

$sql = "
    SELECT
        ms.*,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type
    FROM maintenance_schedule ms
    INNER JOIN equipment e
        ON ms.equipment_id = e.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {

    $sql .= "
        AND (
            e.equipment_code LIKE ?
            OR e.equipment_name LIKE ?
        )
    ";

    $search_param = "%" . $search . "%";

    $params[] = $search_param;
    $params[] = $search_param;

    $types .= "ss";
}

if ($jenis !== '') {

    $sql .= " AND ms.jenis_maintenance = ?";

    $params[] = $jenis;
    $types .= "s";
}

if ($status_filter !== '') {

    $sql .= " AND ms.status = ?";

    $params[] = $status_filter;
    $types .= "s";
}

$sql .= "
    ORDER BY
        CASE
            WHEN ms.status = 'Overdue' THEN 1
            WHEN ms.status = 'Segera Diservis' THEN 2
            WHEN ms.status = 'Akan Jatuh Tempo' THEN 3
            WHEN ms.status = 'Normal' THEN 4
            WHEN ms.status = 'Selesai' THEN 5
            ELSE 6
        END,
        ms.remaining_hm_km ASC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================================================
   SUMMARY
========================================================= */

$total_jadwal = 0;
$segera_service = 0;
$overdue = 0;
$selesai = 0;

$summary_query = "
    SELECT
        COUNT(*) AS total_jadwal,
        SUM(status = 'Segera Diservis') AS segera_service,
        SUM(status = 'Overdue') AS overdue,
        SUM(status = 'Selesai') AS selesai
    FROM maintenance_schedule
";

$summary_result = $conn->query($summary_query);

if ($summary_result && $summary_result->num_rows > 0) {

    $summary = $summary_result->fetch_assoc();

    $total_jadwal = (int) ($summary['total_jadwal'] ?? 0);
    $segera_service = (int) ($summary['segera_service'] ?? 0);
    $overdue = (int) ($summary['overdue'] ?? 0);
    $selesai = (int) ($summary['selesai'] ?? 0);
}


/* =========================================================
   UNIT YANG SEGERA HARUS DISERVIS
========================================================= */

$urgent_sql = "
    SELECT
        ms.*,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type
    FROM maintenance_schedule ms
    INNER JOIN equipment e
        ON ms.equipment_id = e.id
    WHERE ms.status IN (
        'Segera Diservis',
        'Overdue',
        'Akan Jatuh Tempo'
    )
    ORDER BY
        CASE
            WHEN ms.status = 'Overdue' THEN 1
            WHEN ms.status = 'Segera Diservis' THEN 2
            WHEN ms.status = 'Akan Jatuh Tempo' THEN 3
        END,
        ms.remaining_hm_km ASC
    LIMIT 5
";

$urgent_result = $conn->query($urgent_sql);


/* =========================================================
   JENIS MAINTENANCE
========================================================= */

$jenis_result = $conn->query("
    SELECT DISTINCT jenis_maintenance
    FROM maintenance_schedule
    WHERE jenis_maintenance IS NOT NULL
    AND jenis_maintenance != ''
    ORDER BY jenis_maintenance ASC
");

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Jadwal Pemeliharaan - PT Biringkassi Raya</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #172033;
        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

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
            margin: 0;
            padding: 0;
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
            background: rgba(255,255,255,0.18);
        }


        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            margin-left: 240px;
            min-height: 100vh;
            padding: 28px 32px 45px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .breadcrumb {
            font-size: 12px;
            color: #7d899d;
            margin-bottom: 7px;
        }

        .breadcrumb span {
            color: #0759bd;
            font-weight: 600;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            color: #13284a;
        }

        .page-subtitle {
            margin: 7px 0 0;
            color: #7a8799;
            font-size: 13px;
        }


        /* =====================================================
           BUTTON TAMBAH
        ===================================================== */

        .btn-primary {
            border: none;
            background: #0759bd;
            color: white;
            padding: 11px 17px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            background: #064da4;
        }


        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 18px;
        }

        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 18px;
            border: 1px solid #e4eaf2;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .summary-icon {
            width: 45px;
            height: 45px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
        }

        .icon-blue {
            background: #e7f1ff;
        }

        .icon-orange {
            background: #fff1dd;
        }

        .icon-red {
            background: #ffe7e7;
        }

        .icon-green {
            background: #e3f8ec;
        }

        .summary-text small {
            display: block;
            color: #7d899d;
            font-size: 11px;
            margin-bottom: 5px;
        }

        .summary-text strong {
            display: block;
            color: #172033;
            font-size: 23px;
        }


        /* =====================================================
           SECTION
        ===================================================== */

        .section {
            background: white;
            border: 1px solid #e4eaf2;
            border-radius: 10px;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .section-header {
            padding: 15px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #edf1f6;
        }

        .section-header h3 {
            margin: 0;
            font-size: 15px;
            color: #172033;
        }

        .section-header a {
            font-size: 11px;
            color: #0759bd;
            text-decoration: none;
            font-weight: bold;
        }


        /* =====================================================
           TABLE
        ===================================================== */

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
            color: #68758a;
            font-size: 10px;
            font-weight: bold;
            padding: 11px 10px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 11px 10px;
            border-top: 1px solid #edf1f6;
            font-size: 11px;
            color: #344054;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #f9fbfe;
        }

        .unit-code {
            font-weight: bold;
            color: #0759bd;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: bold;
        }

        .status-normal {
            background: #e3f7eb;
            color: #16804b;
        }

        .status-warning {
            background: #fff0d4;
            color: #c97900;
        }

        .status-danger {
            background: #ffe2e2;
            color: #c62828;
        }

        .status-success {
            background: #e0f5e9;
            color: #16804b;
        }

        .remaining-warning {
            color: #d47b00;
            font-weight: bold;
        }

        .remaining-danger {
            color: #d02b2b;
            font-weight: bold;
        }


        /* =====================================================
           FILTER
        ===================================================== */

        .filter-box {
            padding: 15px 18px;
            background: white;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
            gap: 10px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            font-size: 10px;
            font-weight: bold;
            color: #667085;
            margin-bottom: 6px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            height: 38px;
            border: 1px solid #dce3ec;
            border-radius: 7px;
            padding: 0 10px;
            outline: none;
            font-size: 11px;
            color: #344054;
            background: white;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #0759bd;
        }

        .btn-filter,
        .btn-reset {
            height: 38px;
            padding: 0 15px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
        }

        .btn-filter {
            background: #0759bd;
            color: white;
            border: 1px solid #0759bd;
        }

        .btn-reset {
            background: white;
            color: #344054;
            border: 1px solid #d7dee8;
        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {
            text-align: center;
            padding: 35px 20px;
            color: #8a96a8;
            font-size: 12px;
        }

        .empty-icon {
            font-size: 30px;
            margin-bottom: 8px;
        }


        /* =====================================================
           ACTION BUTTON
        ===================================================== */

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 9px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: bold;
            text-decoration: none;
            white-space: nowrap;
            transition: 0.2s;
        }

        .btn-detail {
            border: 1px solid #0759bd;
            background: white;
            color: #0759bd;
        }

        .btn-detail:hover {
            background: #0759bd;
            color: white;
        }

        .btn-edit {
            border: 1px solid #e09a16;
            background: white;
            color: #c27b00;
        }

        .btn-edit:hover {
            background: #e09a16;
            color: white;
        }

        .btn-delete {
            border: 1px solid #d64545;
            background: white;
            color: #d64545;
        }

        .btn-delete:hover {
            background: #d64545;
            color: white;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 800px) {

            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
                padding: 20px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                gap: 15px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <div class="logo">

        <img
            src="../assets/logo.png"
            alt="Logo PT Biringkassi Raya"
        >

        <div class="logo-text">
            PT BIRINGKASSI RAYA
        </div>

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
            <a href="../equipment/index.php">
                Status Unit
            </a>
        </li>

        <li>
            <a href="index.php" class="active">
                Jadwal Pemeliharaan
            </a>
        </li>

        <li>
            <a href="history/index.php">
                Histori Pemeliharaan
            </a>
        </li>

        <li>
            <a href="../breakdown/index.php">
                Breakdown & Failure
            </a>
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
        Spare Part & Lubricant
        <span id="spareArrow">▶</span>
    </a>

    <ul id="spareSubmenu" class="submenu" style="display: none;">
        <li>
            <a href="../sparepart/index.php">
                Spare Part
            </a>
        </li>

        <li>
            <a href="../lubricant/index.php">
                Lubricant
            </a>
        </li>
    </ul>
</li>

        <li>
            <a href="../fuel/index.php">
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


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <div class="topbar">

        <div>

            <div class="breadcrumb">
                Home &nbsp;›&nbsp;
                <span>Jadwal Pemeliharaan</span>
            </div>

            <h1 class="page-title">
                Maintenance Schedule
            </h1>

            <p class="page-subtitle">
                Jadwal pemeliharaan seluruh alat berat.
            </p>

        </div>

    </div>


    <!-- =====================================================
         TAMBAH JADWAL
    ====================================================== -->

    <div style="display:flex; justify-content:flex-end; margin-bottom:18px;">

        <a href="add.php" class="btn-primary">
            + Tambah Jadwal
        </a>

    </div>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <div class="summary-grid">


        <div class="summary-card">

            <div class="summary-icon icon-blue">
                📅
            </div>

            <div class="summary-text">

                <small>Total Jadwal</small>

                <strong>
                    <?= $total_jadwal; ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon icon-orange">
                🔧
            </div>

            <div class="summary-text">

                <small>Segera Diservis</small>

                <strong>
                    <?= $segera_service; ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon icon-red">
                ⚠
            </div>

            <div class="summary-text">

                <small>Overdue</small>

                <strong>
                    <?= $overdue; ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon icon-green">
                ✓
            </div>

            <div class="summary-text">

                <small>Selesai</small>

                <strong>
                    <?= $selesai; ?>
                </strong>

            </div>

        </div>


    </div>

    <!-- =====================================================
         FILTER
    ====================================================== -->

    <section class="section" id="daftar-maintenance">

        <div class="section-header">

            <h3>
                🔎 Filter Data
            </h3>

        </div>


        <div class="filter-box">

            <form method="GET">

                <div class="filter-grid">


                    <div class="filter-group">

                        <label>
                            Cari
                        </label>

                        <input
                            type="text"
                            name="search"
                            placeholder="Cari ID unit atau nama equipment..."
                            value="<?= htmlspecialchars($search); ?>"
                        >

                    </div>


                    <div class="filter-group">

                        <label>
                            Jenis Maintenance
                        </label>

                        <select name="jenis">

                            <option value="">
                                Semua
                            </option>

                            <?php if ($jenis_result): ?>

                                <?php while ($jenis_row = $jenis_result->fetch_assoc()): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $jenis_row['jenis_maintenance']
                                        ); ?>"
                                        <?= (
                                            $jenis === $jenis_row['jenis_maintenance']
                                        ) ? 'selected' : ''; ?>
                                    >

                                        <?= htmlspecialchars(
                                            $jenis_row['jenis_maintenance']
                                        ); ?>

                                    </option>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </select>

                    </div>


                    <div class="filter-group">

                        <label>
                            Status
                        </label>

                        <select name="status">

                            <option value="">
                                Semua
                            </option>

                            <option
                                value="Normal"
                                <?= ($status_filter === 'Normal')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Normal
                            </option>

                            <option
                                value="Akan Jatuh Tempo"
                                <?= ($status_filter === 'Akan Jatuh Tempo')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Akan Jatuh Tempo
                            </option>

                            <option
                                value="Segera Diservis"
                                <?= ($status_filter === 'Segera Diservis')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Segera Diservis
                            </option>

                            <option
                                value="Overdue"
                                <?= ($status_filter === 'Overdue')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Overdue
                            </option>

                            <option
                                value="Selesai"
                                <?= ($status_filter === 'Selesai')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Selesai
                            </option>

                        </select>

                    </div>


                    <div class="filter-group">

                        <label>
                            Periode Jadwal
                        </label>

                        <input
                            type="date"
                            name="tanggal"
                            value="<?= htmlspecialchars(
                                $_GET['tanggal'] ?? ''
                            ); ?>"
                        >

                    </div>


                    <div>

                        <button
                            type="submit"
                            class="btn-filter"
                        >
                            Tampilkan
                        </button>

                    </div>


                    <div>

                        <a
                            href="index.php"
                            class="btn-reset"
                            style="
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                text-decoration:none;
                            "
                        >
                            Reset
                        </a>

                    </div>


                </div>

            </form>

        </div>

    </section>


    <!-- =====================================================
         DAFTAR MAINTENANCE
    ====================================================== -->

    <section class="section">

        <div class="section-header">

            <h3>
                📋 Daftar Maintenance Schedule
            </h3>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>No</th>
                        <th>Tanggal</th>
                        <th>ID Unit</th>
                        <th>Equipment</th>
                        <th>Jenis Maintenance</th>
                        <th>HM / KM</th>
                        <th>Pekerjaan</th>
                        <th>PIC / Teknisi</th>
                        <th>Status</th>
                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <?php

                            $status = $row['status'];

                            if ($status === 'Overdue') {

                                $status_class = 'status-danger';

                            } elseif (
                                $status === 'Segera Diservis' ||
                                $status === 'Akan Jatuh Tempo'
                            ) {

                                $status_class = 'status-warning';

                           } elseif ($status === 'Menunggu Spare Part') {

    $status_class = 'status-warning';

} elseif ($status === 'Selesai') {

                                $status_class = 'status-success';

                            } else {

                                $status_class = 'status-normal';

                            }

                            ?>

                            <tr>

                                <td>
                                    <?= $no++; ?>
                                </td>


                                <td>

                                    <?php

                                    if (
                                        !empty($row['tanggal_jadwal']) &&
                                        $row['tanggal_jadwal'] !== '0000-00-00'
                                    ) {

                                        $timestamp = strtotime(
                                            $row['tanggal_jadwal']
                                        );

                                        if ($timestamp !== false) {

                                            echo date(
                                                'd/m/Y',
                                                $timestamp
                                            );

                                        } else {

                                            echo '-';

                                        }

                                    } else {

                                        echo '-';

                                    }

                                    ?>

                                </td>


                                <td class="unit-code">

                                    <?= htmlspecialchars(
                                        $row['equipment_code']
                                    ); ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $row['equipment_name']
                                    ); ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $row['jenis_maintenance']
                                    ); ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        $row['current_hm_km'],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $row['pekerjaan'] ?: '-'
                                    ); ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $row['pic_teknisi'] ?: '-'
                                    ); ?>

                                </td>


                                <td>

                                    <span class="status <?= $status_class; ?>">

                                        <?= htmlspecialchars($status); ?>

                                    </span>

                                </td>


                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="detail.php?id=<?= $row['id']; ?>"
                                            class="action-btn btn-detail"
                                        >
                                            Detail
                                        </a>


                                        <a
                                            href="edit.php?id=<?= $row['id']; ?>"
                                            class="action-btn btn-edit"
                                        >
                                            Edit
                                        </a>


                                        <a
                                            href="delete.php?id=<?= $row['id']; ?>"
                                            class="action-btn btn-delete"
                                            onclick="return confirm(
                                                'Apakah Anda yakin ingin menghapus jadwal maintenance ini?'
                                            );"
                                        >
                                            Hapus
                                          <?php if ($status !== 'Selesai'): ?>

            <a
                href="complete.php?id=<?= $row['id']; ?>"
                class="action-btn btn-detail"
            >
                Selesaikan
            </a>

        <?php endif; ?>

    </div>
</td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="10">

                                <div class="empty">

                                    <div class="empty-icon">
                                        📋
                                    </div>

                                    Belum ada data Maintenance Schedule.

                                    <br>

                                    Silakan tambahkan jadwal maintenance
                                    setelah data tersedia.

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


</main>


</body>

</html>