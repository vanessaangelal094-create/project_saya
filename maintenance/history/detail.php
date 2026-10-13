<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";


/* =========================================================
   AMBIL ID HISTORY
========================================================= */

$history_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($history_id <= 0) {
    header("Location: index.php");
    exit;
}


/* =========================================================
   AMBIL DATA HISTORY
========================================================= */

$stmt = $conn->prepare("
    SELECT
        h.id,
        h.maintenance_id,
        h.equipment_id,
        h.tanggal_selesai,
        h.hm_saat_maintenance,
        h.next_hm,
        h.jenis_maintenance,
        h.pekerjaan,
        h.pic_teknisi,

        e.equipment_code,
        e.equipment_name,
        e.equipment_type,
        e.brand_model,
        e.serial_number,
        e.year,
        e.capacity,
        e.location,
        e.hm_current,
        e.status

    FROM maintenance_history h

    INNER JOIN equipment e
        ON h.equipment_id = e.id

    WHERE h.id = ?

    LIMIT 1
");


$stmt->bind_param(
    "i",
    $history_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();
    $conn->close();

    header("Location: index.php");
    exit;
}


$history = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   FORMAT DATA
========================================================= */

$tanggalSelesai = '-';

if (
    !empty($history['tanggal_selesai']) &&
    $history['tanggal_selesai'] !== '0000-00-00'
) {

    $tanggalSelesai = date(
        'd/m/Y',
        strtotime($history['tanggal_selesai'])
    );
}


$hmMaintenance = number_format(
    (float) $history['hm_saat_maintenance'],
    0,
    ',',
    '.'
);


$nextHM = number_format(
    (float) $history['next_hm'],
    0,
    ',',
    '.'
);


$hmCurrent = number_format(
    (float) $history['hm_current'],
    0,
    ',',
    '.'
);


$jenisClass = 'other';

if ($history['jenis_maintenance'] === 'Preventive') {

    $jenisClass = 'preventive';

} elseif ($history['jenis_maintenance'] === 'Corrective') {

    $jenisClass = 'corrective';
}

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
        Detail Histori Pemeliharaan - PT Biringkassi Raya
    </title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;

            color: #172b4d;

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

            background:
                rgba(255,255,255,0.18);

        }


        /* =================================================
           MAIN
        ================================================= */

        .main {

            margin-left: 240px;

            min-height: 100vh;

            padding:
                28px
                32px
                45px;

        }


        .breadcrumb {

            font-size: 13px;

            color: #7c8da6;

            margin-bottom: 10px;

        }


        .breadcrumb a {

            color: #0759bd;

            text-decoration: none;

            font-weight: 600;

        }


        .breadcrumb span {

            color: #7c8da6;

        }


        .page-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 25px;

        }


        .page-title {

            font-size: 28px;

            margin-bottom: 7px;

            color: #122b52;

        }


        .page-subtitle {

            color: #7c8da6;

            font-size: 14px;

        }


        .back-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding:
                11px 18px;

            border:
                1px solid
                #d8e0eb;

            border-radius: 8px;

            background: white;

            color: #334155;

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;

        }


        .back-button:hover {

            background: #f8fafc;

        }


        /* =================================================
           EQUIPMENT HEADER
        ================================================= */

        .equipment-header {

            background: white;

            border:
                1px solid
                #e2e8f0;

            border-radius: 12px;

            padding: 24px;

            margin-bottom: 20px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            box-shadow:
                0 2px 8px
                rgba(20, 40, 70, 0.04);

        }


        .equipment-info {

            display: flex;

            align-items: center;

            gap: 18px;

        }


        .equipment-icon {

            width: 64px;

            height: 64px;

            border-radius: 12px;

            background: #eaf2ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 28px;

        }


        .equipment-name {

            font-size: 22px;

            font-weight: 700;

            color: #172b4d;

            margin-bottom: 5px;

        }


        .equipment-code {

            font-size: 14px;

            color: #0759bd;

            font-weight: 700;

        }


        .equipment-type {

            font-size: 12px;

            color: #7c8da6;

            margin-top: 4px;

        }


        .status-badge {

            display: inline-flex;

            align-items: center;

            padding:
                7px 13px;

            border-radius: 20px;

            background: #dff5e9;

            color: #16834b;

            font-size: 12px;

            font-weight: 700;

        }


        /* =================================================
           GRID
        ================================================= */

        .content-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;

            margin-bottom: 20px;

        }


        .card {

            background: white;

            border:
                1px solid
                #e2e8f0;

            border-radius: 12px;

            overflow: hidden;

            box-shadow:
                0 2px 8px
                rgba(20, 40, 70, 0.04);

        }


        .card-header {

            padding:
                18px 22px;

            border-bottom:
                1px solid
                #e7edf5;

        }


        .card-header h2 {

            font-size: 16px;

            color: #172b4d;

        }


        .card-body {

            padding: 22px;

        }


        /* =================================================
           DETAIL ITEM
        ================================================= */

        .detail-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                20px 25px;

        }


        .detail-item label {

            display: block;

            font-size: 11px;

            color: #8a9ab0;

            margin-bottom: 6px;

        }


        .detail-item strong {

            display: block;

            font-size: 14px;

            color: #334155;

            line-height: 1.4;

        }


        /* =================================================
           MAINTENANCE INFORMATION
        ================================================= */

        .maintenance-type {

            display: inline-block;

            padding:
                6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;

        }


        .maintenance-type.preventive {

            background: #e7f1ff;

            color: #0759bd;

        }


        .maintenance-type.corrective {

            background: #fff0e6;

            color: #d97706;

        }


        .maintenance-type.other {

            background: #f0f2f5;

            color: #64748b;

        }


        .work-box {

            background: #f8fafc;

            border:
                1px solid
                #e4eaf1;

            border-radius: 8px;

            padding: 15px;

            line-height: 1.6;

            font-size: 13px;

            color: #334155;

            min-height: 80px;

        }


        /* =================================================
           HM CARD
        ================================================= */

        .hm-section {

            margin-bottom: 20px;

        }


        .hm-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

        }


        .hm-card {

            background: #f7f9fc;

            border:
                1px solid
                #e2e8f0;

            border-radius: 10px;

            padding: 18px;

        }


        .hm-card label {

            display: block;

            font-size: 11px;

            color: #7c8da6;

            margin-bottom: 8px;

        }


        .hm-card strong {

            display: block;

            font-size: 24px;

            color: #0759bd;

        }


        .hm-card.next {

            background: #eef6ff;

            border-color: #cfe2ff;

        }


        .hm-card.next strong {

            color: #0759bd;

        }


        /* =================================================
           ADDITIONAL EQUIPMENT
        ================================================= */

        .full-card {

            margin-bottom: 20px;

        }


        /* =================================================
           FOOTER NOTE
        ================================================= */

        .info-note {

            padding: 15px 18px;

            background: #f7f9fc;

            border:
                1px solid
                #e2e8f0;

            border-radius: 9px;

            font-size: 12px;

            color: #718096;

            line-height: 1.6;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1000px) {

            .content-grid {

                grid-template-columns:
                    1fr;

            }


            .hm-grid {

                grid-template-columns:
                    1fr 1fr;

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


            .page-header {

                flex-direction: column;

                align-items: flex-start;

            }


            .equipment-header {

                flex-direction: column;

                align-items: flex-start;

            }


            .detail-grid {

                grid-template-columns:
                    1fr;

            }


            .hm-grid {

                grid-template-columns:
                    1fr;

            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">


    <div class="logo">

        <img
            src="../../assets/logo.png"
            alt="Logo PT Biringkassi Raya"
        >

        <div class="logo-text">

            PT BIRINGKASSI RAYA

        </div>

    </div>


    <ul class="menu">


        <li>

            <a href="../../index.php">

                Dashboard

            </a>

        </li>


        <li>

            <a href="../../equipment/index.php">

                Equipment Master

            </a>

        </li>


        <li>

            <a href="#">

                Status Unit

            </a>

        </li>


        <li>

            <a href="../index.php">

                Jadwal Pemeliharaan

            </a>

        </li>


        <li>

            <a
                href="index.php"
                class="active"
            >

                Histori Pemeliharaan

            </a>

        </li>


        <li>

            <a href="#">

                Breakdown & Failure

            </a>

        </li>


        <li>

            <a href="#">

                Spare Part & Lubricant

            </a>

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
     MAIN
====================================================== -->

<main class="main">


    <div class="breadcrumb">

        <a href="index.php">
            Histori Pemeliharaan
        </a>

        &nbsp;›&nbsp;

        <span>
            Detail Histori
        </span>

    </div>



    <div class="page-header">

        <div>

            <h1 class="page-title">

                Detail Histori Pemeliharaan

            </h1>


            <p class="page-subtitle">

                Informasi lengkap pemeliharaan equipment yang telah selesai dilakukan.

            </p>

        </div>


        <a
            href="index.php"
            class="back-button"
        >

            ← Kembali

        </a>

    </div>



    <!-- =================================================
         EQUIPMENT HEADER
    ================================================== -->

    <div class="equipment-header">


        <div class="equipment-info">


            <div class="equipment-icon">

                ⚙️

            </div>


            <div>

                <div class="equipment-name">

                    <?= htmlspecialchars(
                        $history['equipment_name']
                    ); ?>

                </div>


                <div class="equipment-code">

                    <?= htmlspecialchars(
                        $history['equipment_code']
                    ); ?>

                </div>


                <div class="equipment-type">

                    <?= htmlspecialchars(
                        $history['equipment_type']
                        ?? '-'
                    ); ?>

                </div>

            </div>


        </div>


        <div class="status-badge">

            ✓ Maintenance Selesai

        </div>


    </div>



    <!-- =================================================
         DETAIL GRID
    ================================================== -->

    <div class="content-grid">


        <!-- EQUIPMENT -->

        <div class="card">


            <div class="card-header">

                <h2>
                    Informasi Equipment
                </h2>

            </div>


            <div class="card-body">


                <div class="detail-grid">


                    <div class="detail-item">

                        <label>
                            ID Unit
                        </label>

                        <strong>
                            <?= htmlspecialchars(
                                $history['equipment_code']
                            ); ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Nama Equipment
                        </label>

                        <strong>
                            <?= htmlspecialchars(
                                $history['equipment_name']
                            ); ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Jenis Alat
                        </label>

                        <strong>
                            <?= htmlspecialchars(
                                $history['equipment_type']
                                ?? '-'
                            ); ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Merek / Model
                        </label>

                        <strong>
                            <?= !empty(
                                $history['brand_model']
                            )
                                ? htmlspecialchars(
                                    $history['brand_model']
                                )
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Serial Number
                        </label>

                        <strong>
                            <?= !empty(
                                $history['serial_number']
                            )
                                ? htmlspecialchars(
                                    $history['serial_number']
                                )
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Tahun
                        </label>

                        <strong>
                            <?= !empty(
                                $history['year']
                            )
                                ? htmlspecialchars(
                                    $history['year']
                                )
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Kapasitas
                        </label>

                        <strong>
                            <?= !empty(
                                $history['capacity']
                            )
                                ? htmlspecialchars(
                                    $history['capacity']
                                )
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Lokasi
                        </label>

                        <strong>
                            <?= !empty(
                                $history['location']
                            )
                                ? htmlspecialchars(
                                    $history['location']
                                )
                                : '-'; ?>
                        </strong>

                    </div>


                </div>

            </div>

        </div>



        <!-- MAINTENANCE -->

        <div class="card">


            <div class="card-header">

                <h2>
                    Informasi Maintenance
                </h2>

            </div>


            <div class="card-body">


                <div class="detail-grid">


                    <div class="detail-item">

                        <label>
                            Jenis Maintenance
                        </label>

                        <strong>

                            <span
                                class="
                                    maintenance-type
                                    <?= $jenisClass; ?>
                                "
                            >

                                <?= htmlspecialchars(
                                    $history['jenis_maintenance']
                                ); ?>

                            </span>

                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Tanggal Selesai
                        </label>

                        <strong>
                            <?= $tanggalSelesai; ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            PIC / Teknisi
                        </label>

                        <strong>
                            <?= !empty(
                                $history['pic_teknisi']
                            )
                                ? htmlspecialchars(
                                    $history['pic_teknisi']
                                )
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <label>
                            Status Equipment Saat Ini
                        </label>

                        <strong>
                            <?= htmlspecialchars(
                                $history['status']
                            ); ?>
                        </strong>

                    </div>


                </div>

            </div>

        </div>


    </div>



    <!-- =================================================
         HM INFORMATION
    ================================================== -->

    <div class="card hm-section">


        <div class="card-header">

            <h2>
                Perhitungan HM Maintenance
            </h2>

        </div>


        <div class="card-body">


            <div class="hm-grid">


                <div class="hm-card">

                    <label>
                        HM Saat Maintenance
                    </label>

                    <strong>

                        <?= $hmMaintenance; ?>

                    </strong>

                </div>


                <div class="hm-card next">

                    <label>
                        Next HM
                    </label>

                    <strong>

                        <?= $nextHM; ?>

                    </strong>

                </div>


                <div class="hm-card">

                    <label>
                        HM Equipment Saat Ini
                    </label>

                    <strong>

                        <?= $hmCurrent; ?>

                    </strong>

                </div>


            </div>

        </div>

    </div>



    <!-- =================================================
         PEKERJAAN
    ================================================== -->

    <div class="card full-card">


        <div class="card-header">

            <h2>
                Pekerjaan Maintenance
            </h2>

        </div>


        <div class="card-body">


            <div class="work-box">

                <?= !empty(
                    $history['pekerjaan']
                )
                    ? nl2br(
                        htmlspecialchars(
                            $history['pekerjaan']
                        )
                    )
                    : 'Tidak ada data pekerjaan maintenance.'; ?>

            </div>

        </div>

    </div>



    <!-- =================================================
         CATATAN
    ================================================== -->

    <div class="info-note">

        Data pada halaman ini merupakan riwayat maintenance
        yang telah diselesaikan dan tersimpan dalam sistem.
        Nilai Next HM digunakan sebagai acuan untuk maintenance
        berikutnya.

    </div>


</main>


</body>

</html>


<?php

$conn->close();

?>