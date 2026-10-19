<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| Ambil ID Equipment
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die("ID equipment tidak valid.");
}


/*
|--------------------------------------------------------------------------
| Ambil Data Equipment
|--------------------------------------------------------------------------
*/

$sql = "SELECT * FROM equipment WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Data equipment tidak ditemukan.");
}

$equipment = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

$equipment_code = $equipment['equipment_code'];
$equipment_name = $equipment['equipment_name'];
$equipment_type = $equipment['equipment_type'];
$brand_model = $equipment['brand_model'];
$serial_number = $equipment['serial_number'];
$year = $equipment['year'];
$capacity = $equipment['capacity'];
$location = $equipment['location'];
$pic = $equipment['pic'];
$hm_current = $equipment['hm_current'];
$status = $equipment['status'];

$spesifikasi = $equipment['spesifikasi'];
$jumlah = $equipment['jumlah'];
$user_equipment = $equipment['user_equipment'];
$hasil_pengecekan_fisik = $equipment['hasil_pengecekan_fisik'];
$gambar = $equipment['gambar'];


/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/

$status_class = strtolower($status);

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Detail <?php echo htmlspecialchars($equipment_code); ?>
        | Monitoring Maintenance
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;

            color: #17365d;
        }


        /* =====================================================
           TOP BAR
        ===================================================== */

        .topbar {

            height: 82px;

            background: #eaf3ff;

            border-bottom: 1px solid #d6e4f5;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 28px;

            position: sticky;

            top: 0;

            z-index: 20;
        }


        .top-left {

            display: flex;

            align-items: center;

            gap: 20px;
        }


        .menu-btn {

            width: 44px;

            height: 44px;

            border: none;

            background: white;

            border-radius: 8px;

            color: #1261c9;

            font-size: 25px;

            cursor: pointer;

            box-shadow:
                0 1px 3px rgba(0,0,0,0.05);
        }


        .search-box {

            width: 440px;

            height: 44px;

            background: white;

            border: 1px solid #dbe7f5;

            border-radius: 7px;

            display: flex;

            align-items: center;

            padding: 0 16px;

            color: #7891ad;
        }


        .search-box span {

            font-size: 14px;
        }


        .top-right {

            display: flex;

            align-items: center;

            gap: 20px;
        }


        .notification {

            font-size: 24px;

            color: #0f60c8;

            position: relative;
        }


        .notification-dot {

            width: 8px;

            height: 8px;

            background: #ef4444;

            border-radius: 50%;

            position: absolute;

            top: 1px;

            right: -2px;
        }


        .profile {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #17365d;

            font-size: 14px;
        }


        .avatar {

            width: 42px;

            height: 42px;

            border-radius: 50%;

            background: #1768d1;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

            font-size: 16px;
        }


        .profile-name {

            font-weight: 500;
        }


        .profile-arrow {

            font-size: 11px;

            color: #58718f;
        }


        /* =====================================================
           CONTENT
        ===================================================== */

        .content {

            padding: 20px 28px 40px;
        }


        /* =====================================================
           BREADCRUMB
        ===================================================== */

        .breadcrumb {

            display: flex;

            align-items: center;

            gap: 10px;

            font-size: 14px;

            margin-bottom: 18px;

            color: #1261c9;
        }


        .breadcrumb .active {

            font-weight: 600;

            color: #17365d;
        }


        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .page-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 18px;
        }


        .title-area {

            display: flex;

            align-items: center;

            gap: 18px;
        }


        .back-btn {

            width: 42px;

            height: 42px;

            border-radius: 7px;

            border: 1px solid #cfe0f4;

            background: white;

            color: #1261c9;

            font-size: 24px;

            display: flex;

            align-items: center;

            justify-content: center;

            text-decoration: none;
        }


        .title-text h1 {

            margin: 0;

            font-size: 32px;

            color: #164f96;

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .title-text p {

            margin: 4px 0 0;

            color: #65809e;

            font-size: 15px;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {

            display: inline-flex;

            align-items: center;

            padding: 7px 14px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: 600;

            background: #e9eef4;

            color: #53677c;
        }


        .status.running {

            background: #dff7e8;

            color: #168244;
        }


        .status.standby {

            background: #e9eef4;

            color: #53677c;
        }


        .status.maintenance {

            background: #e4efff;

            color: #1768d1;
        }


        .status.breakdown {

            background: #ffe5e5;

            color: #d92d2d;
        }


        /* =====================================================
           ACTION BUTTON
        ===================================================== */

        .actions {

            display: flex;

            gap: 10px;
        }


        .btn {

            height: 44px;

            padding: 0 18px;

            border-radius: 7px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;
        }


        .btn-edit {

            border: 1px solid #2f80ed;

            background: white;

            color: #1261c9;
        }


        .btn-edit:hover {

            background: #eef6ff;
        }


        .btn-delete {

            border: 1px solid #ff7b7b;

            background: white;

            color: #e33d3d;
        }


        .btn-delete:hover {

            background: #fff2f2;
        }


        /* =====================================================
           TOP INFORMATION
        ===================================================== */

        .top-grid {

            display: grid;

            grid-template-columns: 315px 1fr 1fr;

            gap: 14px;

            margin-bottom: 14px;
        }


        .card {

            background: white;

            border: 1px solid #dbe7f5;

            border-radius: 7px;

            box-shadow:
                0 1px 2px rgba(0,0,0,0.02);
        }


        /* =====================================================
           PHOTO
        ===================================================== */

        .photo-card {

            padding: 12px;
        }


        .photo-box {

            height: 200px;

            background: #edf5fd;

            border-radius: 5px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            overflow: hidden;

            color: #7390ad;

            text-align: center;
        }


        .photo-box img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .photo-icon {

            font-size: 42px;

            margin-bottom: 8px;
        }


        .photo-empty {

            font-size: 14px;
        }


        .photo-note {

            font-size: 12px;

            color: #7390ad;

            text-align: center;

            margin-top: 10px;
        }


        /* =====================================================
           INFO CARD
        ===================================================== */

        .info-card {

            padding: 18px;
        }


        .card-title {

            display: flex;

            align-items: center;

            gap: 10px;

            font-size: 16px;

            font-weight: 700;

            color: #1261c9;

            padding-bottom: 14px;

            border-bottom: 1px solid #e1ebf6;

            margin-bottom: 12px;
        }


        .card-title-icon {

            font-size: 19px;
        }


        .info-row {

            display: grid;

            grid-template-columns: 160px 18px 1fr;

            align-items: center;

            min-height: 25px;

            font-size: 13px;

            margin-bottom: 2px;
        }


        .info-label {

            color: #6883a1;
        }


        .info-separator {

            color: #9bb0c8;

            text-align: center;
        }


        .info-value {

            color: #064bb0;

            font-weight: 600;

            word-break: break-word;
        }


        /* =====================================================
           OPERATIONAL + MAINTENANCE
        ===================================================== */

        .right-info {

            padding: 18px;
        }


        .mini-section {

            margin-bottom: 17px;
        }


        .mini-section:last-child {

            margin-bottom: 0;
        }


        .mini-title {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #1261c9;

            font-size: 16px;

            font-weight: 700;

            padding-bottom: 11px;

            border-bottom: 1px solid #e1ebf6;

            margin-bottom: 9px;
        }


        /* =====================================================
           TABS
        ===================================================== */

        .tabs {

            background: white;

            border: 1px solid #dbe7f5;

            border-radius: 7px;

            display: flex;

            align-items: stretch;

            overflow-x: auto;

            margin-bottom: 14px;
        }


        .tab {

            padding: 17px 20px;

            white-space: nowrap;

            color: #627c99;

            font-size: 13px;

            text-decoration: none;

            border-bottom: 3px solid transparent;
        }


        .tab:hover {

            color: #1261c9;

            background: #f7fbff;
        }


        .tab.active {

            color: #1261c9;

            font-weight: 700;

            border-bottom-color: #1261c9;
        }


        /* =====================================================
           KPI
        ===================================================== */

        .kpi-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 14px;

            margin-bottom: 14px;
        }


        .kpi-card {

            background: white;

            border: 1px solid #dbe7f5;

            border-radius: 7px;

            padding: 17px;

            display: flex;

            gap: 14px;

            align-items: center;
        }


        .kpi-icon {

            width: 55px;

            height: 55px;

            border-radius: 8px;

            background: #edf5ff;

            color: #1261c9;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 24px;
        }


        .kpi-label {

            font-size: 12px;

            color: #7089a4;

            margin-bottom: 4px;
        }


        .kpi-value {

            font-size: 20px;

            font-weight: 700;

            color: #1261c9;
        }


        .kpi-note {

            font-size: 11px;

            color: #7891ad;

            margin-top: 3px;
        }


        /* =====================================================
           LOWER CONTENT
        ===================================================== */

        .lower-grid {

            display: grid;

            grid-template-columns: 2fr 1fr;

            gap: 14px;
        }


        .detail-card {

            padding: 18px;
        }


        .detail-grid {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 10px 35px;
        }


        .detail-item {

            display: grid;

            grid-template-columns: 130px 15px 1fr;

            font-size: 13px;

            min-height: 28px;
        }


        .detail-item .label {

            color: #6883a1;
        }


        .detail-item .separator {

            color: #9bb0c8;
        }


        .detail-item .value {

            color: #064bb0;

            font-weight: 600;

            word-break: break-word;
        }


        .status-box {

            padding: 18px;
        }


        .status-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 10px 0;

            border-bottom: 1px solid #edf2f7;

            font-size: 13px;
        }


        .status-row:last-child {

            border-bottom: none;
        }


        .status-row .label {

            color: #6883a1;
        }


        .status-row .value {

            color: #064bb0;

            font-weight: 600;

            text-align: right;

            max-width: 60%;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {

            .top-grid {

                grid-template-columns: 280px 1fr;
            }

            .right-info {

                grid-column: span 2;
            }

            .kpi-grid {

                grid-template-columns: 1fr 1fr;
            }

        }


        @media (max-width: 850px) {

            .search-box {

                width: 280px;
            }

            .top-grid {

                grid-template-columns: 1fr;
            }

            .right-info {

                grid-column: auto;
            }

            .lower-grid {

                grid-template-columns: 1fr;
            }

            .detail-grid {

                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 650px) {

            .topbar {

                padding: 0 15px;
            }

            .content {

                padding: 15px;
            }

            .search-box {

                display: none;
            }

            .profile-name {

                display: none;
            }

            .page-header {

                align-items: flex-start;

                flex-direction: column;

                gap: 15px;
            }

            .actions {

                width: 100%;
            }

            .btn {

                flex: 1;
            }

            .kpi-grid {

                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     TOP BAR
========================================================= -->

<header class="topbar">

    <div class="top-left">

        <button
            type="button"
            class="menu-btn"
        >
            ☰
        </button>


        <div class="search-box">

            <span>
                ⌕&nbsp;&nbsp;
                Cari ID unit, merek, atau lokasi...
            </span>

        </div>

    </div>


    <div class="top-right">

        <div class="notification">

            ♧

            <span class="notification-dot"></span>

        </div>


        <div class="profile">

            <div class="avatar">
                A
            </div>

            <div class="profile-name">
                <?php echo htmlspecialchars($_SESSION['nama']); ?>
            </div>

            <div class="profile-arrow">
                ˅
            </div>

        </div>

    </div>

</header>



<!-- =========================================================
     CONTENT
========================================================= -->

<main class="content">


    <!-- BREADCRUMB -->

    <div class="breadcrumb">

        <a
            href="../index.php"
            style="text-decoration:none;color:#1261c9;"
        >
            Home
        </a>

        <span>›</span>

        <a
            href="index.php"
            style="text-decoration:none;color:#1261c9;"
        >
            Equipment Master
        </a>

        <span>›</span>

        <span class="active">
            Detail Equipment
        </span>

    </div>



    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="title-area">

            <a
                href="index.php"
                class="back-btn"
                title="Kembali"
            >
                ←
            </a>


            <div class="title-text">

                <h1>

                    <?php
                    echo htmlspecialchars($equipment_code);
                    ?>

                    <span
                        class="status <?php echo htmlspecialchars($status_class); ?>"
                    >
                        <?php
                        echo htmlspecialchars($status);
                        ?>
                    </span>

                </h1>


                <p>

                    <?php
                    echo htmlspecialchars($equipment_name);
                    ?>

                    &nbsp; | &nbsp;

                    <?php

                    echo !empty($brand_model)
                        ? htmlspecialchars($brand_model)
                        : htmlspecialchars($equipment_type);

                    ?>

                </p>

            </div>

        </div>



        <!-- ACTION -->

        <div class="actions">

            <a
                href="edit.php?id=<?php echo (int)$equipment['id']; ?>"
                class="btn btn-edit"
            >
                ✎&nbsp; Edit Data
            </a>


            <a
                href="#"
                class="btn btn-delete"
                onclick="return confirmDelete();"
            >
                ♜&nbsp; Hapus
            </a>

        </div>

    </div>



    <!-- =====================================================
         TOP INFORMATION
    ====================================================== -->

    <section class="top-grid">


        <!-- FOTO -->

        <div class="card photo-card">

            <div class="photo-box">

                <?php if (!empty($gambar)): ?>

                    <img
                        src="../assets/equipment/<?php echo htmlspecialchars($gambar); ?>"
                        alt="<?php echo htmlspecialchars($equipment_name); ?>"
                    >

                <?php else: ?>

                    <div class="photo-icon">
                        🚜
                    </div>

                    <div class="photo-empty">
                        Foto equipment belum tersedia
                    </div>

                <?php endif; ?>

            </div>


            <div class="photo-note">

                Foto alat dapat ditambahkan
                melalui menu Edit Data.

            </div>

        </div>



        <!-- INFORMASI UMUM -->

        <div class="card info-card">

            <div class="card-title">

                <span class="card-title-icon">
                    ▣
                </span>

                Informasi Umum

            </div>


            <div class="info-row">

                <div class="info-label">
                    ID Unit
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($equipment_code); ?>
                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Nama Equipment
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($equipment_name); ?>
                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Jenis Alat
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($equipment_type); ?>
                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Merek / Model
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">

                    <?php

                    echo !empty($brand_model)
                        ? htmlspecialchars($brand_model)
                        : "-";

                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Serial Number
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">

                    <?php

                    echo !empty($serial_number)
                        ? htmlspecialchars($serial_number)
                        : "-";

                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Tahun
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">

                    <?php

                    echo !empty($year)
                        ? htmlspecialchars($year)
                        : "-";

                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Kapasitas
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">

                    <?php

                    echo !empty($capacity)
                        ? htmlspecialchars($capacity)
                        : "-";

                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Lokasi
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">

                    <?php

                    echo !empty($location)
                        ? htmlspecialchars($location)
                        : "-";

                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Status
                </div>

                <div class="info-separator">
                    :
                </div>

                <div class="info-value">

                    <span
                        class="status <?php echo htmlspecialchars($status_class); ?>"
                    >
                        <?php echo htmlspecialchars($status); ?>
                    </span>

                </div>

            </div>

        </div>



        <!-- OPERASIONAL + MAINTENANCE -->

        <div class="card right-info">


            <div class="mini-section">

                <div class="mini-title">

                    <span>
                        ⚙
                    </span>

                    Informasi Operasional

                </div>


                <div class="info-row">

                    <div class="info-label">
                        HM Saat Ini
                    </div>

                    <div class="info-separator">
                        :
                    </div>

                    <div class="info-value">

                        <?php

                        echo number_format(
                            (float)$hm_current,
                            0,
                            ',',
                            '.'
                        );

                        ?>

                        H

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        PIC
                    </div>

                    <div class="info-separator">
                        :
                    </div>

                    <div class="info-value">

                        <?php

                        echo !empty($pic)
                            ? htmlspecialchars($pic)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        User
                    </div>

                    <div class="info-separator">
                        :
                    </div>

                    <div class="info-value">

                        <?php

                        echo !empty($user_equipment)
                            ? htmlspecialchars($user_equipment)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Lokasi
                    </div>

                    <div class="info-separator">
                        :
                    </div>

                    <div class="info-value">

                        <?php

                        echo !empty($location)
                            ? htmlspecialchars($location)
                            : "-";

                        ?>

                    </div>

                </div>

            </div>



            <div class="mini-section">

                <div class="mini-title">

                    <span>
                        🔧
                    </span>

                    Informasi Maintenance

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Last Service
                    </div>

                    <div class="info-separator">
                        :
                    </div>

                    <div class="info-value">
                        -
                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Next Service
                    </div>

                    <div class="info-separator">
                        :
                    </div>

                    <div class="info-value">
                        -
                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Interval PM
                    </div>

                    <div class="info-separator">
                        :
                    </div>

                    <div class="info-value">
                        -
                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Status Jadwal
                    </div>

                    <div class="info-separator">
                        :
                    </div>

                    <div class="info-value">
                        Belum ada data
                    </div>

                </div>

            </div>

        </div>

    </section>



    <!-- =====================================================
         TABS
    ====================================================== -->

    <div class="tabs">

        <a
            href="detail.php?id=<?php echo (int)$id; ?>"
            class="tab active"
        >
            Overview
        </a>


        <a
            href="#"
            class="tab"
            onclick="return moduleAlert('Operation');"
        >
            Operation
        </a>


        <a
            href="#"
            class="tab"
            onclick="return moduleAlert('Maintenance Schedule');"
        >
            Maintenance Schedule
        </a>


        <a
            href="#"
            class="tab"
            onclick="return moduleAlert('Maintenance History');"
        >
            Maintenance History
        </a>


        <a
            href="#"
            class="tab"
            onclick="return moduleAlert('Spare Part & Lubricant');"
        >
            Spare Part & Lubricant
        </a>


        <a
            href="#"
            class="tab"
            onclick="return moduleAlert('Breakdown & Failure');"
        >
            Breakdown & Failure
        </a>


        <a
            href="#"
            class="tab"
            onclick="return moduleAlert('Fuel Performance');"
        >
            Fuel Performance
        </a>


        <a
            href="#"
            class="tab"
            onclick="return moduleAlert('Maintenance Cost');"
        >
            Maintenance Cost
        </a>

    </div>



    <!-- =====================================================
         KPI
    ====================================================== -->

    <section class="kpi-grid">


        <div class="kpi-card">

            <div class="kpi-icon">
                ◷
            </div>

            <div>

                <div class="kpi-label">
                    Total HM
                </div>

                <div class="kpi-value">

                    <?php

                    echo number_format(
                        (float)$hm_current,
                        0,
                        ',',
                        '.'
                    );

                    ?>

                    H

                </div>

                <div class="kpi-note">
                    Data berdasarkan HM saat ini
                </div>

            </div>

        </div>



        <div class="kpi-card">

            <div class="kpi-icon">
                ▥
            </div>

            <div>

                <div class="kpi-label">
                    Jam Operasi Bulan Ini
                </div>

                <div class="kpi-value">
                    -
                </div>

                <div class="kpi-note">
                    Belum ada data operation
                </div>

            </div>

        </div>



        <div class="kpi-card">

            <div class="kpi-icon">
                ⛽
            </div>

            <div>

                <div class="kpi-label">
                    Total Konsumsi BBM Bulan Ini
                </div>

                <div class="kpi-value">
                    -
                </div>

                <div class="kpi-note">
                    Belum ada data fuel
                </div>

            </div>

        </div>



        <div class="kpi-card">

            <div class="kpi-icon">
                ◎
            </div>

            <div>

                <div class="kpi-label">
                    Total Biaya Maintenance Tahun Ini
                </div>

                <div class="kpi-value">
                    Rp -
                </div>

                <div class="kpi-note">
                    Belum ada data maintenance
                </div>

            </div>

        </div>

    </section>



    <!-- =====================================================
         LOWER DETAIL
    ====================================================== -->

    <section class="lower-grid">


        <!-- DETAIL EQUIPMENT -->

        <div class="card detail-card">

            <div class="card-title">

                <span class="card-title-icon">
                    ▣
                </span>

                Detail Equipment

            </div>


            <div class="detail-grid">


                <div class="detail-item">

                    <div class="label">
                        ID Unit
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">
                        <?php echo htmlspecialchars($equipment_code); ?>
                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Nama Equipment
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">
                        <?php echo htmlspecialchars($equipment_name); ?>
                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Jenis Alat
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">
                        <?php echo htmlspecialchars($equipment_type); ?>
                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Merek / Model
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo !empty($brand_model)
                            ? htmlspecialchars($brand_model)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Serial Number
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo !empty($serial_number)
                            ? htmlspecialchars($serial_number)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Tahun
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo !empty($year)
                            ? htmlspecialchars($year)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Kapasitas
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo !empty($capacity)
                            ? htmlspecialchars($capacity)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Lokasi
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo !empty($location)
                            ? htmlspecialchars($location)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        User
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo !empty($user_equipment)
                            ? htmlspecialchars($user_equipment)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Jumlah
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo $jumlah !== null && $jumlah !== ''
                            ? htmlspecialchars($jumlah)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Kondisi Fisik
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo !empty($hasil_pengecekan_fisik)
                            ? htmlspecialchars($hasil_pengecekan_fisik)
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="detail-item">

                    <div class="label">
                        Spesifikasi
                    </div>

                    <div class="separator">
                        :
                    </div>

                    <div class="value">

                        <?php

                        echo !empty($spesifikasi)
                            ? htmlspecialchars($spesifikasi)
                            : "-";

                        ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- STATUS TERKINI -->

        <div class="card status-box">

            <div class="card-title">

                <span class="card-title-icon">
                    ▣
                </span>

                Status Terkini

            </div>


            <div class="status-row">

                <div class="label">
                    Status Equipment
                </div>

                <div class="value">

                    <span
                        class="status <?php echo htmlspecialchars($status_class); ?>"
                    >
                        <?php echo htmlspecialchars($status); ?>
                    </span>

                </div>

            </div>


            <div class="status-row">

                <div class="label">
                    HM
                </div>

                <div class="value">

                    <?php

                    echo number_format(
                        (float)$hm_current,
                        0,
                        ',',
                        '.'
                    );

                    ?>

                    H

                </div>

            </div>


            <div class="status-row">

                <div class="label">
                    Lokasi
                </div>

                <div class="value">

                    <?php

                    echo !empty($location)
                        ? htmlspecialchars($location)
                        : "-";

                    ?>

                </div>

            </div>


            <div class="status-row">

                <div class="label">
                    PIC
                </div>

                <div class="value">

                    <?php

                    echo !empty($pic)
                        ? htmlspecialchars($pic)
                        : "-";

                    ?>

                </div>

            </div>


            <div class="status-row">

                <div class="label">
                    Kondisi Fisik
                </div>

                <div class="value">

                    <?php

                    echo !empty($hasil_pengecekan_fisik)
                        ? htmlspecialchars($hasil_pengecekan_fisik)
                        : "-";

                    ?>

                </div>

            </div>

        </div>

    </section>

</main>



<script>

/*
|--------------------------------------------------------------------------
| Konfirmasi Hapus
|--------------------------------------------------------------------------
*/

function confirmDelete() {

    alert(
        "Fitur Hapus akan kita aktifkan setelah Edit Equipment selesai."
    );

    return false;
}


/*
|--------------------------------------------------------------------------
| Tab yang belum dibuat
|--------------------------------------------------------------------------
*/

function moduleAlert(moduleName) {

    alert(
        "Halaman " +
        moduleName +
        " akan kita aktifkan pada tahap berikutnya."
    );

    return false;
}

</script>


</body>

</html>

<?php

$conn->close();

?>