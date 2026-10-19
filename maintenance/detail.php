<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   AMBIL ID JADWAL
========================================================= */

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}


/* =========================================================
   AMBIL DATA MAINTENANCE
========================================================= */

$sql = "
    SELECT
        ms.*,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type,
        e.brand_model,
        e.serial_number,
        e.year,
        e.capacity,
        e.location,
        e.pic,
        e.hm_current,
        e.status AS equipment_status,
        e.gambar
    FROM maintenance_schedule ms
    INNER JOIN equipment e
        ON ms.equipment_id = e.id
    WHERE ms.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$data = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   STATUS CLASS
========================================================= */

$status = $data['status'];

if ($status === 'Overdue') {

    $status_class = 'danger';

} elseif (
    $status === 'Segera Diservis' ||
    $status === 'Akan Jatuh Tempo'
) {

    $status_class = 'warning';

} elseif ($status === 'Selesai') {

    $status_class = 'success';

} else {

    $status_class = 'normal';
}


/* =========================================================
   FORMAT ANGKA
========================================================= */

function formatNumber($number)
{
    if ($number === null || $number === '') {
        return '-';
    }

    return number_format(
        (float) $number,
        0,
        ',',
        '.'
    );
}


/* =========================================================
   FORMAT TANGGAL
========================================================= */

function formatDateIndonesia($date)
{
    if (empty($date)) {
        return '-';
    }

    return date('d/m/Y', strtotime($date));
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Detail Maintenance - PT Biringkassi Raya
    </title>


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
            padding: 30px 35px 50px;
        }


        .breadcrumb {
            font-size: 12px;
            color: #7d899d;
            margin-bottom: 8px;
        }

        .breadcrumb a {
            color: #0759bd;
            text-decoration: none;
            font-weight: 600;
        }


        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
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
           BUTTON
        ===================================================== */

        .button-group {
            display: flex;
            gap: 9px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-back {
            background: white;
            color: #475467;
            border: 1px solid #d7dee8;
        }

        .btn-edit {
            background: #0759bd;
            color: white;
            border: 1px solid #0759bd;
        }

        .btn-edit:hover {
            background: #064da4;
        }


        /* =====================================================
           EQUIPMENT HEADER
        ===================================================== */

        .equipment-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            padding: 20px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .equipment-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .equipment-image {
            width: 75px;
            height: 75px;
            border-radius: 9px;
            background: #edf4ff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #0759bd;
            font-size: 28px;
            font-weight: bold;
        }

        .equipment-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .equipment-name {
            margin: 0 0 5px;
            font-size: 19px;
            color: #13284a;
        }

        .equipment-code {
            font-size: 12px;
            color: #0759bd;
            font-weight: bold;
        }

        .equipment-type {
            margin-top: 5px;
            color: #7d899d;
            font-size: 11px;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
        }

        .normal {
            background: #e3f7eb;
            color: #16804b;
        }

        .warning {
            background: #fff0d4;
            color: #c97900;
        }

        .danger {
            background: #ffe2e2;
            color: #c62828;
        }

        .success {
            background: #e0f5e9;
            color: #16804b;
        }


        /* =====================================================
           CONTENT GRID
        ===================================================== */

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            overflow: hidden;
        }

        .card.full {
            grid-column: 1 / -1;
        }

        .card-header {
            padding: 15px 18px;
            border-bottom: 1px solid #edf1f6;
        }

        .card-header h3 {
            margin: 0;
            font-size: 14px;
            color: #172033;
        }

        .card-body {
            padding: 18px;
        }


        /* =====================================================
           INFO ROW
        ===================================================== */

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 25px;
        }

        .info-item span {
            display: block;
            font-size: 10px;
            color: #7d899d;
            margin-bottom: 5px;
        }

        .info-item strong {
            display: block;
            font-size: 12px;
            color: #344054;
        }


        /* =====================================================
           SERVICE SUMMARY
        ===================================================== */

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .service-box {
            background: #f7f9fc;
            border: 1px solid #e5eaf1;
            border-radius: 8px;
            padding: 13px;
        }

        .service-box span {
            display: block;
            font-size: 10px;
            color: #7d899d;
            margin-bottom: 6px;
        }

        .service-box strong {
            font-size: 16px;
            color: #13284a;
        }


        /* =====================================================
           REMAINING
        ===================================================== */

        .remaining-box {
            background: #f7faff;
            border: 1px solid #dce9fa;
            border-radius: 9px;
            padding: 18px;
            margin-top: 15px;
            text-align: center;
        }

        .remaining-box span {
            display: block;
            font-size: 10px;
            color: #7d899d;
            margin-bottom: 7px;
        }

        .remaining-value {
            font-size: 25px;
            font-weight: bold;
            color: #0759bd;
        }


        /* =====================================================
           ACTION AREA
        ===================================================== */

        .work-card {
            background: #f7faff;
            border: 1px solid #dce9fa;
            border-radius: 9px;
            padding: 18px;
        }

        .work-card h4 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #13284a;
        }

        .work-card p {
            margin: 0;
            color: #667085;
            font-size: 11px;
            line-height: 1.6;
        }

        .work-placeholder {
            margin-top: 14px;
            padding: 12px;
            background: white;
            border: 1px dashed #c8d4e4;
            border-radius: 7px;
            color: #98a2b3;
            font-size: 10px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
                padding: 20px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .card.full {
                grid-column: auto;
            }

            .page-header {
                flex-direction: column;
            }

            .equipment-card {
                align-items: flex-start;
                flex-direction: column;
            }

        }

        @media (max-width: 650px) {

            .info-grid {
                grid-template-columns: 1fr;
            }

            .service-grid {
                grid-template-columns: 1fr;
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
            <a href="#">
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


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <div class="breadcrumb">

        <a href="index.php">
            Jadwal Pemeliharaan
        </a>

        &nbsp;›&nbsp;

        Detail Jadwal

    </div>


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">

        <div>

            <h1 class="page-title">
                Detail Jadwal Pemeliharaan
            </h1>

            <p class="page-subtitle">
                Informasi lengkap jadwal maintenance equipment.
            </p>

        </div>


        <div class="button-group">

            <a
                href="index.php"
                class="btn btn-back"
            >
                ← Kembali
            </a>

            <a
                href="edit.php?id=<?= $data['id']; ?>"
                class="btn btn-edit"
            >
                ✎ Edit Jadwal
            </a>

        </div>

    </div>


    <!-- =====================================================
         EQUIPMENT HEADER
    ====================================================== -->

    <div class="equipment-card">

        <div class="equipment-info">

            <div class="equipment-image">

                <?php if (!empty($data['gambar'])): ?>

                    <img
                        src="../uploads/equipment/<?= htmlspecialchars($data['gambar']); ?>"
                        alt="Equipment"
                    >

                <?php else: ?>

                    ⚙

                <?php endif; ?>

            </div>


            <div>

                <h2 class="equipment-name">
                    <?= htmlspecialchars($data['equipment_name']); ?>
                </h2>

                <div class="equipment-code">
                    <?= htmlspecialchars($data['equipment_code']); ?>
                </div>

                <div class="equipment-type">
                    <?= htmlspecialchars($data['equipment_type']); ?>

                    <?php if (!empty($data['brand_model'])): ?>

                        &nbsp;•&nbsp;

                        <?= htmlspecialchars($data['brand_model']); ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <div>

            <span class="status <?= $status_class; ?>">
                <?= htmlspecialchars($status); ?>
            </span>

        </div>

    </div>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <div class="content-grid">


        <!-- =================================================
             DATA EQUIPMENT
        ================================================== -->

        <div class="card">

            <div class="card-header">

                <h3>
                    Informasi Equipment
                </h3>

            </div>


            <div class="card-body">

                <div class="info-grid">


                    <div class="info-item">

                        <span>
                            ID Unit
                        </span>

                        <strong>
                            <?= htmlspecialchars($data['equipment_code']); ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Nama Equipment
                        </span>

                        <strong>
                            <?= htmlspecialchars($data['equipment_name']); ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Jenis Alat
                        </span>

                        <strong>
                            <?= htmlspecialchars($data['equipment_type']); ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Merek / Model
                        </span>

                        <strong>
                            <?= !empty($data['brand_model'])
                                ? htmlspecialchars($data['brand_model'])
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Serial Number
                        </span>

                        <strong>
                            <?= !empty($data['serial_number'])
                                ? htmlspecialchars($data['serial_number'])
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Tahun
                        </span>

                        <strong>
                            <?= !empty($data['year'])
                                ? htmlspecialchars($data['year'])
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Kapasitas
                        </span>

                        <strong>
                            <?= !empty($data['capacity'])
                                ? htmlspecialchars($data['capacity'])
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Lokasi
                        </span>

                        <strong>
                            <?= !empty($data['location'])
                                ? htmlspecialchars($data['location'])
                                : '-'; ?>
                        </strong>

                    </div>


                </div>

            </div>

        </div>


        <!-- =================================================
             INFORMASI MAINTENANCE
        ================================================== -->

        <div class="card">

            <div class="card-header">

                <h3>
                    Informasi Maintenance
                </h3>

            </div>


            <div class="card-body">

                <div class="info-grid">


                    <div class="info-item">

                        <span>
                            Jenis Maintenance
                        </span>

                        <strong>
                            <?= htmlspecialchars($data['jenis_maintenance']); ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Tanggal Jadwal
                        </span>

                        <strong>
                            <?= formatDateIndonesia($data['tanggal_jadwal']); ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            PIC / Teknisi
                        </span>

                        <strong>
                            <?= !empty($data['pic_teknisi'])
                                ? htmlspecialchars($data['pic_teknisi'])
                                : '-'; ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Status Equipment
                        </span>

                        <strong>
                            <?= htmlspecialchars($data['equipment_status']); ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Pekerjaan
                        </span>

                        <strong>
                            <?= !empty($data['pekerjaan'])
                                ? htmlspecialchars($data['pekerjaan'])
                                : '-'; ?>
                        </strong>

                    </div>


                </div>

            </div>

        </div>


        <!-- =================================================
             SERVICE CALCULATION
        ================================================== -->

        <div class="card full">

            <div class="card-header">

                <h3>
                    Perhitungan Jadwal Service
                </h3>

            </div>


            <div class="card-body">

                <div class="service-grid">


                    <div class="service-box">

                        <span>
                            Last Service
                        </span>

                        <strong>
                            <?= formatNumber($data['last_service']); ?>
                        </strong>

                    </div>


                    <div class="service-box">

                        <span>
                            Current HM
                        </span>

                        <strong>
                            <?= formatNumber($data['current_hm_km']); ?>
                        </strong>

                    </div>


                    <div class="service-box">

                        <span>
                            Interval PM
                        </span>

                        <strong>
                            <?= formatNumber($data['interval_pm']); ?>
                        </strong>

                    </div>


                    <div class="service-box">

                        <span>
                            Next Service
                        </span>

                        <strong>
                            <?= formatNumber($data['next_service']); ?>
                        </strong>

                    </div>


                    <div class="service-box">

                        <span>
                            Remaining HM
                        </span>

                        <strong>
                            <?= formatNumber($data['remaining_hm_km']); ?>
                        </strong>

                    </div>


                    <div class="service-box">

                        <span>
                            Status
                        </span>

                        <span class="status <?= $status_class; ?>">
                            <?= htmlspecialchars($status); ?>
                        </span>

                    </div>


                </div>


                <div class="remaining-box">

                    <span>
                        Sisa HM menuju service berikutnya
                    </span>

                    <div class="remaining-value">

                        <?= formatNumber($data['remaining_hm_km']); ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             LEMBAR KERJA
        ================================================== -->

        <div class="card full">

            <div class="card-header">

                <h3>
                    Lembar Kerja Maintenance
                </h3>

            </div>


            <div class="card-body">

                <div class="work-card">

                    <h4>
                        Lembar Kerja Pemeliharaan
                    </h4>

                    <p>
                        Lembar kerja maintenance akan dihubungkan
                        dengan format PDF perusahaan setelah format
                        lembar kerja resmi tersedia.
                    </p>


                    <div class="work-placeholder">

                        Format lembar kerja perusahaan belum
                        diintegrasikan pada tahap ini.

                    </div>

                </div>

            </div>

        </div>


    </div>

</main>


</body>

</html>