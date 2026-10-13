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

$search   = $_GET['search'] ?? '';
$type     = $_GET['type'] ?? '';
$location = $_GET['location'] ?? '';
$status   = $_GET['status'] ?? '';

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {

    $where[] = "(equipment_code LIKE ?
                OR equipment_name LIKE ?
                OR brand_model LIKE ?
                OR serial_number LIKE ?)";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssss";
}

if ($type !== '') {

    $where[] = "equipment_type = ?";
    $params[] = $type;
    $types .= "s";
}

if ($location !== '') {

    $where[] = "location = ?";
    $params[] = $location;
    $types .= "s";
}

if ($status !== '') {

    $where[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

$whereSQL = '';

if (!empty($where)) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}


/* =========================================================
   DATA EQUIPMENT
========================================================= */

$sql = "SELECT * FROM equipment $whereSQL ORDER BY id ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query error: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================================================
   SUMMARY
========================================================= */

$totalEquipment = 0;
$running = 0;
$standby = 0;
$maintenance = 0;
$breakdown = 0;

$selectedLocation = $_GET['location'] ?? '';

if ($selectedLocation !== '') {

    $summaryStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Running') AS running,
            SUM(status = 'Standby') AS standby,
            SUM(status = 'Maintenance') AS maintenance,
            SUM(status = 'Breakdown') AS breakdown
        FROM equipment
        WHERE location = ?
    ");

    $summaryStmt->bind_param("s", $selectedLocation);
    $summaryStmt->execute();

    $summaryQuery = $summaryStmt->get_result();

} else {

    $summaryQuery = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Running') AS running,
            SUM(status = 'Standby') AS standby,
            SUM(status = 'Maintenance') AS maintenance,
            SUM(status = 'Breakdown') AS breakdown
        FROM equipment
    ");

}

if ($summaryQuery) {

    $summary = $summaryQuery->fetch_assoc();

    $totalEquipment = (int)($summary['total'] ?? 0);
    $running = (int)($summary['running'] ?? 0);
    $standby = (int)($summary['standby'] ?? 0);
    $maintenance = (int)($summary['maintenance'] ?? 0);
    $breakdown = (int)($summary['breakdown'] ?? 0);
}


/* =========================================================
   FILTER OPTIONS
========================================================= */

$typeQuery = $conn->query("
    SELECT DISTINCT equipment_type
    FROM equipment
    WHERE equipment_type IS NOT NULL
    AND equipment_type != ''
    ORDER BY equipment_type ASC
");

$locationQuery = $conn->query("
    SELECT DISTINCT location
    FROM equipment
    WHERE location IS NOT NULL
    AND location != ''
    ORDER BY location ASC
");


/* =========================================================
   STATUS CLASS
========================================================= */

function statusClass($status)
{
    switch ($status) {

        case 'Running':
            return 'running';

        case 'Standby':
            return 'standby';

        case 'Maintenance':
            return 'maintenance';

        case 'Breakdown':
            return 'breakdown';

        default:
            return 'standby';
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Equipment Master - Monitoring Maintenance</title>


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


        /* =====================================================
           HEADER
        ===================================================== */

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


        .header-right {
            display: flex;

            align-items: center;

            gap: 12px;
        }


        .add-btn {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 11px 17px;

            background: #0759bd;

            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-size: 13px;

            font-weight: bold;

            transition: 0.2s;
        }


        .add-btn:hover {
            background: #064c9f;
        }


        /* =====================================================
           PROFILE
        ===================================================== */

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


        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary-grid {
            display: grid;

            grid-template-columns: repeat(5, 1fr);

            gap: 14px;

            margin-bottom: 20px;
        }


        .summary-card {
            background: white;

            border: 1px solid #dce8f3;

            border-radius: 8px;

            min-height: 100px;

            padding: 16px;

            box-shadow: 0 2px 6px rgba(0,0,0,0.04);

            display: flex;

            align-items: center;

            gap: 13px;
        }


        .summary-icon {
            width: 48px;
            height: 48px;

            border-radius: 9px;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 23px;

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


        .icon-orange {
            background: #ffedd5;
        }


        .icon-red {
            background: #ffe8e8;
        }


        .summary-title {
            color: #64748b;

            font-size: 11px;

            font-weight: bold;

            margin-bottom: 5px;
        }


        .summary-number {
            font-size: 25px;

            font-weight: bold;

            color: #0759bd;
        }


        .number-green {
            color: #15803d;
        }


        .number-yellow {
            color: #a16207;
        }


        .number-orange {
            color: #ea580c;
        }


        .number-red {
            color: #dc2626;
        }


        /* =====================================================
           FILTER
        ===================================================== */

        .filter-box {
            background: white;

            border: 1px solid #dce8f3;

            border-radius: 8px;

            padding: 18px;

            margin-bottom: 20px;

            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }


        .filter-title {
            font-size: 15px;

            font-weight: bold;

            color: #0759bd;

            margin-bottom: 14px;
        }


        .filter-form {
            display: grid;

            grid-template-columns: 2fr 1fr 1fr 1fr auto auto;

            gap: 10px;

            align-items: end;
        }


        .form-group {
            display: flex;

            flex-direction: column;

            gap: 6px;
        }


        .form-group label {
            font-size: 11px;

            font-weight: bold;

            color: #64748b;
        }


        .form-group input,
        .form-group select {
            width: 100%;

            height: 39px;

            padding: 0 11px;

            border: 1px solid #d6e0eb;

            border-radius: 7px;

            background: white;

            color: #1f2937;

            font-size: 12px;

            outline: none;
        }


        .form-group input:focus,
        .form-group select:focus {
            border-color: #0759bd;
        }


        .filter-btn,
        .reset-btn {
            height: 39px;

            padding: 0 16px;

            border-radius: 7px;

            font-size: 12px;

            font-weight: bold;

            cursor: pointer;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;
        }


        .filter-btn {
            background: #0759bd;

            border: 1px solid #0759bd;

            color: white;
        }


        .filter-btn:hover {
            background: #064c9f;
        }


        .reset-btn {
            background: white;

            border: 1px solid #cbd5e1;

            color: #475569;
        }


        .reset-btn:hover {
            background: #f8fafc;
        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-box {
            background: white;

            border: 1px solid #dce8f3;

            border-radius: 8px;

            box-shadow: 0 2px 6px rgba(0,0,0,0.04);

            overflow: hidden;
        }


        .table-header {
            padding: 17px 18px;

            border-bottom: 1px solid #e5edf5;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .table-header h2 {
            font-size: 16px;

            color: #0759bd;
        }


        .table-wrapper {
            width: 100%;

            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 1200px;
        }


        th {
            background: #eef5fc;

            color: #4b6380;

            font-size: 10px;

            font-weight: bold;

            padding: 11px 10px;

            text-align: left;

            white-space: nowrap;
        }


        td {
            padding: 11px 10px;

            border-top: 1px solid #edf2f7;

            font-size: 11px;

            white-space: nowrap;

            color: #334155;
        }


        tbody tr:hover {
            background: #f8fbff;
        }


        td strong {
            color: #1f2937;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 5px;

            font-size: 9px;

            font-weight: bold;

            text-align: center;

            min-width: 78px;
        }


        .status.running {
            background: #dcfce7;

            color: #15803d;
        }


        .status.standby {
            background: #fef3c7;

            color: #a16207;
        }


        .status.maintenance {
            background: #ffedd5;

            color: #ea580c;
        }


        .status.breakdown {
            background: #fee2e2;

            color: #dc2626;
        }


        /* =====================================================
           ACTION
        ===================================================== */

        .action {
            display: flex;

            align-items: center;

            gap: 5px;
        }


        .action a {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 5px 8px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 9px;

            font-weight: bold;
        }


        .action .view {
            background: #dbeafe;

            color: #2563eb;
        }


        .action .edit {
            background: #fef3c7;

            color: #a16207;
        }


        .action .delete {
            background: #fee2e2;

            color: #dc2626;
        }


        .action a:hover {
            opacity: 0.8;
        }


        .empty {
            text-align: center;

            padding: 35px !important;

            color: #94a3b8;

            font-size: 12px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {

            .summary-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .filter-form {
                grid-template-columns: repeat(2, 1fr);
            }
        }


        @media (max-width: 900px) {

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .sidebar {
                width: 220px;
            }

            .main {
                margin-left: 220px;
            }

            .header {
                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }

            .header-right {
                width: 100%;

                justify-content: space-between;
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

                padding: 20px 15px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .header-left h1 {
                font-size: 26px;
            }

            .header-right {
                flex-direction: column;

                align-items: stretch;
            }

            .add-btn {
                width: 100%;
            }

            .profile {
                width: 100%;
            }

            .user-info {
                width: 100%;
            }
        }

    </style>

</head>


<body>


    <!-- =========================================================
         SIDEBAR
    ========================================================= -->

    <div class="sidebar">

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
                <a href="index.php" class="active">
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
                <a href="#">
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

    </div>


    <!-- =========================================================
         MAIN
    ========================================================= -->

    <div class="main">


        <!-- HEADER -->

        <div class="header">

            <div class="header-left">

                <h1>
                    Equipment Master
                </h1>

                <p>
                    Data master seluruh alat berat yang dimiliki perusahaan
                </p>

            </div>


            <div class="header-right">

                <a href="add.php" class="add-btn">
                    + Tambah Equipment
                </a>


                <div class="profile">

                    <div
                        class="user-info"
                        onclick="toggleProfile()"
                    >

                        <strong>
                            <?php echo htmlspecialchars($_SESSION['nama'] ?? 'User'); ?>
                        </strong>

                        ▾

                    </div>


                    <div
                        class="profile-menu"
                        id="profileMenu"
                    >

                        <div class="profile-name">

                            Login sebagai

                            <strong>
                                <?php echo htmlspecialchars($_SESSION['nama'] ?? 'User'); ?>
                            </strong>

                        </div>


                        <a
                            href="../auth/logout.php"
                            class="logout-link"
                        >
                            Logout
                        </a>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             SUMMARY
        ====================================================== -->

        <div class="summary-grid">


            <div
    class="summary-card"
    onclick="window.location.href='index.php?location=<?php echo urlencode($selectedLocation); ?>';"
    style="cursor: pointer;"
>

                <div class="summary-icon icon-blue">
                    🚜
                </div>

                <div>

                    <div class="summary-title">
                        TOTAL EQUIPMENT
                    </div>

                    <div class="summary-number">
                        <?php echo $totalEquipment; ?>
                    </div>

                </div>

            </div>


            <div
    class="summary-card"
    onclick="window.location.href='index.php?location=<?php echo urlencode($selectedLocation); ?>&status=Running';"
    style="cursor: pointer;"
>

                <div class="summary-icon icon-green">
                    ✓
                </div>

                <div>

                    <div class="summary-title">
                        RUNNING
                    </div>

                    <div class="summary-number number-green">
                        <?php echo $running; ?>
                    </div>

                </div>

            </div>


            <div
    class="summary-card"
    onclick="window.location.href='index.php?location=<?php echo urlencode($selectedLocation); ?>&status=Standby';"
    style="cursor: pointer;"
>

                <div class="summary-icon icon-yellow">
                    ◷
                </div>

                <div>

                    <div class="summary-title">
                        STANDBY
                    </div>

                    <div class="summary-number number-yellow">
                        <?php echo $standby; ?>
                    </div>

                </div>

            </div>


            <!-- =================================================
                 MAINTENANCE
                 SATU-SATUNYA BAGIAN YANG DIUBAH
            ================================================== -->

<div
    class="summary-card"
    onclick="<?php
        if ($selectedLocation !== '') {
            echo "window.location.href='index.php?location=" . urlencode($selectedLocation) . "&status=Maintenance';";
        } else {
            echo "window.location.href='../maintenance/index.php';";
        }
    ?>"
    style="cursor: pointer;"
>

                <div class="summary-icon icon-orange">
                    🔧
                </div>

                <div>

                    <div class="summary-title">
                        MAINTENANCE
                    </div>

                    <div class="summary-number number-orange">
                        <?php echo $maintenance; ?>
                    </div>

                </div>

            </div>


            <div
    class="summary-card"
    onclick="window.location.href='index.php?location=<?php echo urlencode($selectedLocation); ?>&status=Breakdown';"
    style="cursor: pointer;"
>

                <div class="summary-icon icon-red">
                    !
                </div>

                <div>

                    <div class="summary-title">
                        BREAKDOWN
                    </div>

                    <div class="summary-number number-red">
                        <?php echo $breakdown; ?>
                    </div>

                </div>

            </div>


        </div>


        <!-- =====================================================
             FILTER
        ====================================================== -->

        <div class="filter-box">

            <div class="filter-title">
                Filter Equipment
            </div>


            <form
                method="GET"
                class="filter-form"
            >


                <div class="form-group">

                    <label>
                        Search Equipment
                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari ID unit, nama, model, serial number..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Jenis Alat
                    </label>

                    <select name="type">

                        <option value="">
                            Semua Jenis
                        </option>


                        <?php

                        if ($typeQuery) {

                            while ($typeRow = $typeQuery->fetch_assoc()) {

                                $typeValue = $typeRow['equipment_type'];

                                $selected = ($type === $typeValue)
                                    ? 'selected'
                                    : '';

                                echo '<option value="' .
                                    htmlspecialchars($typeValue) .
                                    '" ' .
                                    $selected .
                                    '>' .
                                    htmlspecialchars($typeValue) .
                                    '</option>';
                            }
                        }

                        ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Lokasi
                    </label>

                    <select name="location">

                        <option value="">
                            Semua Lokasi
                        </option>


                        <?php

                        if ($locationQuery) {

                            while ($locationRow = $locationQuery->fetch_assoc()) {

                                $locationValue = $locationRow['location'];

                                $selected = ($location === $locationValue)
                                    ? 'selected'
                                    : '';

                                echo '<option value="' .
                                    htmlspecialchars($locationValue) .
                                    '" ' .
                                    $selected .
                                    '>' .
                                    htmlspecialchars($locationValue) .
                                    '</option>';
                            }
                        }

                        ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="">
                            Semua Status
                        </option>

                        <option
                            value="Running"
                            <?php echo ($status === 'Running') ? 'selected' : ''; ?>
                        >
                            Running
                        </option>

                        <option
                            value="Standby"
                            <?php echo ($status === 'Standby') ? 'selected' : ''; ?>
                        >
                            Standby
                        </option>

                        <option
                            value="Maintenance"
                            <?php echo ($status === 'Maintenance') ? 'selected' : ''; ?>
                        >
                            Maintenance
                        </option>

                        <option
                            value="Breakdown"
                            <?php echo ($status === 'Breakdown') ? 'selected' : ''; ?>
                        >
                            Breakdown
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="filter-btn"
                >
                    Filter
                </button>


                <a
                    href="index.php"
                    class="reset-btn"
                >
                    Reset
                </a>


            </form>

        </div>


        <!-- =====================================================
             TABLE
        ====================================================== -->

        <div class="table-box">


            <div class="table-header">

                <h2>
                    Daftar Equipment
                </h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                NO
                            </th>

                            <th>
                                ID UNIT
                            </th>

                            <th>
                                NAMA EQUIPMENT
                            </th>

                            <th>
                                JENIS ALAT
                            </th>

                            <th>
                                MEREK / MODEL
                            </th>

                            <th>
                                TAHUN
                            </th>

                            <th>
                                KAPASITAS
                            </th>

                            <th>
                                LOKASI
                            </th>

                            <th>
                                HM / KM SAAT INI
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                AKSI
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php

                    $no = 1;

                    if ($result->num_rows > 0) {

                        while ($row = $result->fetch_assoc()) {

                    ?>

                        <tr>


                            <td>
                                <?php echo $no++; ?>
                            </td>


                            <td>

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $row['equipment_code'] ?? '-'
                                    );

                                    ?>

                                </strong>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['equipment_name'] ?? '-'
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['equipment_type'] ?? '-'
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['brand_model'] ?? '-'
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['year'] ?? '-'
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['capacity'] ?? '-'
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['location'] ?? '-'
                                );

                                ?>

                            </td>


                            <td>
    <button
        type="button"
        class="hm-button"
        onclick="openHmModal(
            <?= (int)$row['id']; ?>,
            '<?= htmlspecialchars($row['equipment_code'], ENT_QUOTES); ?>',
            <?= (float)($row['hm_current'] ?? 0); ?>
        )"
    >
        <?= number_format(
            (float)($row['hm_current'] ?? 0),
            2,
            ',',
            '.'
        ); ?>
    </button>
</td>
                            <td>

                                <span
                                    class="status <?php echo statusClass($row['status'] ?? 'Standby'); ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $row['status'] ?? 'Standby'
                                    );

                                    ?>

                                </span>

                            </td>


                            <td>

                                <div class="action">


                                    <a
                                        href="detail.php?id=<?php echo $row['id']; ?>"
                                        class="view"
                                    >
                                        View
                                    </a>


                                    <a
                                        href="edit.php?id=<?php echo $row['id']; ?>"
                                        class="edit"
                                    >
                                        Edit
                                    </a>


                                    <a
                                        href="delete.php?id=<?php echo $row['id']; ?>"
                                        class="delete"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus equipment ini?');"
                                    >
                                        Delete
                                    </a>


                                </div>

                            </td>


                        </tr>

                    <?php

                        }

                    } else {

                    ?>

                        <tr>

                            <td
                                colspan="11"
                                class="empty"
                            >
                                Data equipment tidak ditemukan.
                            </td>

                        </tr>

                    <?php

                    }

                    ?>

                    </tbody>

                </table>

            </div>

        </div>


    </div>


<script>

function toggleProfile() {

    const menu = document.getElementById('profileMenu');

    menu.classList.toggle('show');

}


document.addEventListener('click', function(event) {

    const profile = document.querySelector('.profile');

    const menu = document.getElementById('profileMenu');

    if (!profile.contains(event.target)) {
        menu.classList.remove('show');
    }

});

</script>

<!-- MODAL UPDATE HM / KM -->
<div id="hmModal" style="
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35);
    z-index: 9999;
    align-items: center;
    justify-content: center;
">

    <div style="
        width: 420px;
        max-width: calc(100% - 40px);
        background: white;
        border-radius: 14px;
        padding: 25px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.2);
    ">

        <h2 style="
            margin: 0 0 20px;
            color: #0759bd;
            font-size: 20px;
        ">
            Update HM / KM
        </h2>

        <div style="margin-bottom: 15px;">
            <label style="
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
            ">
                ID Unit
            </label>

            <input
                type="text"
                id="hmEquipmentCode"
                readonly
                style="
                    width: 100%;
                    box-sizing: border-box;
                    padding: 11px 12px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    background: #f5f6f8;
                "
            >
        </div>

        <div style="margin-bottom: 15px;">
            <label style="
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
            ">
                HM / KM Saat Ini
            </label>

            <input
                type="text"
                id="hmCurrentValue"
                readonly
                style="
                    width: 100%;
                    box-sizing: border-box;
                    padding: 11px 12px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    background: #f5f6f8;
                "
            >
        </div>

        <div style="margin-bottom: 22px;">
            <label style="
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
            ">
                HM / KM Terbaru
            </label>

            <input
                type="number"
                id="hmNewValue"
                step="0.01"
                min="0"
                placeholder="Masukkan HM / KM terbaru"
                style="
                    width: 100%;
                    box-sizing: border-box;
                    padding: 11px 12px;
                    border: 1px solid #0759bd;
                    border-radius: 8px;
                    outline: none;
                "
            >
        </div>

        <div style="
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        ">

            <button
                type="button"
                onclick="closeHmModal()"
                style="
                    padding: 10px 18px;
                    border: none;
                    border-radius: 8px;
                    background: #eef1f5;
                    color: #333;
                    cursor: pointer;
                "
            >
                Batal
            </button>

            <button
                type="button"
                onclick="saveHmUpdate()"
                style="
                    padding: 10px 18px;
                    border: none;
                    border-radius: 8px;
                    background: #0759bd;
                    color: white;
                    cursor: pointer;
                "
            >
                Simpan
            </button>

        </div>

    </div>
</div>


<script>

let currentHmEquipmentId = null;


function openHmModal(id, equipmentCode, currentHm) {

    currentHmEquipmentId = id;

    document.getElementById('hmEquipmentCode').value = equipmentCode;

    document.getElementById('hmCurrentValue').value =
        new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(currentHm);

    document.getElementById('hmNewValue').value = '';

    document.getElementById('hmModal').style.display = 'flex';

    document.getElementById('hmNewValue').focus();
}


function closeHmModal() {

    document.getElementById('hmModal').style.display = 'none';

}


function saveHmUpdate() {

    const newHm = document.getElementById('hmNewValue').value;

    if (newHm === '') {
        alert('Silakan masukkan HM / KM terbaru.');
        return;
    }

    if (currentHmEquipmentId === null) {
        alert('ID equipment tidak ditemukan.');
        return;
    }

    fetch('update_hm.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body:
            'id=' + encodeURIComponent(currentHmEquipmentId) +
            '&hm_baru=' + encodeURIComponent(newHm)
    })
    .then(response => response.json())
    .then(data => {

        if (data.success) {

            alert(data.message);

            closeHmModal();

            location.reload();

        } else {

            alert(data.message);

        }

    })
    .catch(error => {

        console.error(error);

        alert('Terjadi kesalahan saat memperbarui HM/KM.');

    });

}

</script>
</body>

</html>


<?php

$stmt->close();

$conn->close();

?>