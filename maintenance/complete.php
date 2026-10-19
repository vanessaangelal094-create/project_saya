<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   AMBIL ID MAINTENANCE
========================================================= */

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}


/* =========================================================
   AMBIL DATA MAINTENANCE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        ms.*,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type,
        e.brand_model,
        e.serial_number,
        e.year,
        e.capacity,
        e.location
    FROM maintenance_schedule ms
    INNER JOIN equipment e
        ON ms.equipment_id = e.id
    WHERE ms.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: index.php");
    exit;
}

$maintenance = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   JIKA SUDAH SELESAI
========================================================= */

if ($maintenance['status'] === 'Selesai') {
    header("Location: detail.php?id=" . $id);
    exit;
}


/* =========================================================
   PROSES SELESAIKAN MAINTENANCE
========================================================= */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
    $hm_saat_maintenance = $_POST['hm_saat_maintenance'] ?? '';
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $pic_teknisi = trim($_POST['pic_teknisi'] ?? '');
    $status_penyelesaian = $_POST['status_penyelesaian'] ?? 'Selesai';
    $status_unit_setelah = $_POST['status_unit_setelah'] ?? '';

    /* =====================================================
       VALIDASI
    ===================================================== */

    if (
        $tanggal_selesai === '' ||
        $hm_saat_maintenance === ''
    ) {

        $error = "Tanggal selesai dan HM saat maintenance wajib diisi.";

    } elseif (!is_numeric($hm_saat_maintenance)) {

        $error = "HM saat maintenance harus berupa angka.";

    } else {

        $hm_saat_maintenance = (float) $hm_saat_maintenance;

        $equipment_id = (int) $maintenance['equipment_id'];
        $maintenance_id = (int) $maintenance['id'];
        $jenis_maintenance = $maintenance['jenis_maintenance'];
        $interval_pm = (float) $maintenance['interval_pm'];
        if ($status_penyelesaian === 'Menunggu Spare Part') {

    $waiting_stmt = $conn->prepare("
        UPDATE maintenance_schedule
        SET status = 'Menunggu Spare Part'
        WHERE id = ?
    ");

    if (!$waiting_stmt) {
        $error = "Gagal memperbarui status maintenance.";
    } else {

        $waiting_stmt->bind_param(
            "i",
            $maintenance_id
        );

        if ($waiting_stmt->execute()) {

            $waiting_stmt->close();

            header(
                "Location: detail.php?id=" . $maintenance_id
            );

            exit;

        } else {

            $error = "Gagal menyimpan status Menunggu Spare Part.";

            $waiting_stmt->close();
        }
    }
}
$previous_last_service = $maintenance['last_service'];
$previous_current_hm_km = $maintenance['current_hm_km'];
$previous_next_service = $maintenance['next_service'];
$previous_remaining_hm_km = $maintenance['remaining_hm_km'];
$previous_tanggal_jadwal = $maintenance['tanggal_jadwal'];
$previous_status = $maintenance['status'];

$equipment_before_stmt = $conn->prepare("
    SELECT hm_current, status
    FROM equipment
    WHERE id = ?
    LIMIT 1
");

if (!$equipment_before_stmt) {
    throw new Exception(
        "Gagal mengambil kondisi equipment sebelumnya."
    );
}

$equipment_before_stmt->bind_param(
    "i",
    $equipment_id
);

$equipment_before_stmt->execute();

$equipment_before_result =
    $equipment_before_stmt->get_result();

if ($equipment_before_result->num_rows === 0) {
    $equipment_before_stmt->close();

    throw new Exception(
        "Data equipment tidak ditemukan."
    );
}

$equipment_before =
    $equipment_before_result->fetch_assoc();

$previous_equipment_hm =
    (float) $equipment_before['hm_current'];

$previous_equipment_status =
    $equipment_before['status'];

$equipment_before_stmt->close();

        /* =================================================
           HITUNG SIKLUS MAINTENANCE BERIKUTNYA
        ================================================= */

        $next_service = $hm_saat_maintenance + $interval_pm;
$next_hm = $hm_saat_maintenance + $interval_pm;

        $remaining_hm_km = $next_service - $hm_saat_maintenance;
$status = "Selesai";

        /*
         * Setelah maintenance selesai,
         * status jadwal menjadi Selesai.
         *
         * Siklus berikutnya dihitung dari HM
         * saat maintenance selesai.
         */

        $status = "Selesai";


        /* =================================================
           TRANSAKSI DATABASE
        ================================================= */

        $conn->begin_transaction();

        try {

            /* =============================================
               1. SIMPAN KE HISTORY
            ============================================= */

            $history_stmt = $conn->prepare("
    INSERT INTO maintenance_history (
        maintenance_id,
        equipment_id,
        tanggal_selesai,
        hm_saat_maintenance,
        next_hm,
        previous_last_service,
        previous_current_hm_km,
        previous_next_service,
        previous_remaining_hm_km,
        previous_tanggal_jadwal,
        previous_status,
        previous_equipment_hm,
        previous_equipment_status,
        jenis_maintenance,
        pekerjaan,
        pic_teknisi
    )
    VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?
    )
");

if (!$history_stmt) {
    throw new Exception(
        "Gagal menyiapkan data history."
    );
}

$history_stmt->bind_param(
    "iisddddddssdssss",
    $maintenance_id,
    $equipment_id,
    $tanggal_selesai,
    $hm_saat_maintenance,
    $next_hm,
    $previous_last_service,
    $previous_current_hm_km,
    $previous_next_service,
    $previous_remaining_hm_km,
    $previous_tanggal_jadwal,
    $previous_status,
    $previous_equipment_hm,
    $previous_equipment_status,
    $jenis_maintenance,
    $pekerjaan,
    $pic_teknisi
);

if (!$history_stmt->execute()) {
    throw new Exception(
        "Gagal menyimpan history maintenance."
    );
}

$history_stmt->close();

            /* =============================================
               2. UPDATE MAINTENANCE SCHEDULE
            ============================================= */

            $update_stmt = $conn->prepare("
                UPDATE maintenance_schedule
                SET
                    last_service = ?,
                    current_hm_km = ?,
                    next_service = ?,
                    remaining_hm_km = ?,
                    pekerjaan = ?,
                    pic_teknisi = ?,
                    tanggal_jadwal = ?,
                    status = ?
                WHERE id = ?
            ");

            if (!$update_stmt) {
                throw new Exception(
                    "Gagal menyiapkan update maintenance."
                );
            }

            $update_stmt->bind_param(
                "ddddssssi",
                $hm_saat_maintenance,
                $hm_saat_maintenance,
                $next_service,
                $remaining_hm_km,
                $pekerjaan,
                $pic_teknisi,
                $tanggal_selesai,
                $status,
                $maintenance_id
            );

            if (!$update_stmt->execute()) {
                throw new Exception(
                    "Gagal memperbarui maintenance schedule."
                );
            }

            $update_stmt->close();


            /* =============================================
               3. UPDATE HM EQUIPMENT
            ============================================= */

            $equipment_stmt = $conn->prepare("
                UPDATE equipment
                SET
                    hm_current = ?
                WHERE id = ?
            ");

            if (!$equipment_stmt) {
                throw new Exception(
                    "Gagal menyiapkan update equipment."
                );
            }

            $equipment_stmt->bind_param(
                "di",
                $hm_saat_maintenance,
                $equipment_id
            );

            if (!$equipment_stmt->execute()) {
                throw new Exception(
                    "Gagal memperbarui HM equipment."
                );
            }

            $equipment_stmt->close();


            /* =============================================
               4. STATUS EQUIPMENT
            ============================================= */

            $status_equipment_stmt = $conn->prepare("
                UPDATE equipment
                SET status = 'Standby'
                WHERE id = ?
            ");

            if (!$status_equipment_stmt) {
                throw new Exception(
                    "Gagal menyiapkan status equipment."
                );
            }

            $status_equipment_stmt->bind_param(
                "i",
                $equipment_id
            );

            if (!$status_equipment_stmt->execute()) {
                throw new Exception(
                    "Gagal memperbarui status equipment."
                );
            }

            $status_equipment_stmt->close();


            /* =============================================
               COMMIT
            ============================================= */

            $conn->commit();

            header("Location: detail.php?id=" . $maintenance_id);
            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $error = $e->getMessage();
        }
    }
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

    <title>Selesaikan Maintenance - PT Biringkassi Raya</title>


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
            margin: 7px 0 25px;
            color: #7a8799;
            font-size: 13px;
        }


        /* =====================================================
           CARD
        ===================================================== */

        .card {
            background: white;
            border: 1px solid #e4eaf2;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #edf1f6;
        }

        .card-header h3 {
            margin: 0;
            font-size: 15px;
            color: #172033;
        }

        .card-body {
            padding: 20px;
        }


        /* =====================================================
           INFO GRID
        ===================================================== */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .info-item {
            background: #f7f9fc;
            border: 1px solid #e7ecf3;
            border-radius: 8px;
            padding: 12px;
        }

        .info-item small {
            display: block;
            font-size: 10px;
            color: #7d899d;
            margin-bottom: 5px;
        }

        .info-item strong {
            display: block;
            font-size: 12px;
            color: #172033;
        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 11px;
            font-weight: bold;
            color: #344054;
            margin-bottom: 7px;
        }

        .required {
            color: #d64545;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            border: 1px solid #dce3ec;
            border-radius: 7px;
            padding: 10px;
            font-size: 12px;
            color: #344054;
            outline: none;
            font-family: Arial, Helvetica, sans-serif;
        }

        .form-group input {
            height: 40px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #0759bd;
        }


        /* =====================================================
           HASIL PERHITUNGAN
        ===================================================== */

        .calculation-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .calculation-box {
            background: #f7f9fc;
            border: 1px solid #e4eaf2;
            border-radius: 8px;
            padding: 14px;
        }

        .calculation-box small {
            display: block;
            color: #7d899d;
            font-size: 10px;
            margin-bottom: 5px;
        }

        .calculation-box strong {
            display: block;
            font-size: 18px;
            color: #0759bd;
        }


        /* =====================================================
           ERROR
        ===================================================== */

        .error-box {
            background: #ffe7e7;
            color: #c62828;
            border: 1px solid #f2bcbc;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 12px;
            margin-bottom: 18px;
        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .button-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 17px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .btn-back {
            background: white;
            color: #344054;
            border: 1px solid #d7dee8;
        }

        .btn-save {
            background: #0759bd;
            color: white;
        }

        .btn-save:hover {
            background: #064da4;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1000px) {

            .info-grid,
            .calculation-grid {
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

            .info-grid,
            .form-grid,
            .calculation-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


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
            <a href="#">
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


<main class="main">


    <div class="breadcrumb">
        Home &nbsp;›&nbsp;
        Jadwal Pemeliharaan &nbsp;›&nbsp;
        <span>Selesaikan Maintenance</span>
    </div>


    <h1 class="page-title">
        Selesaikan Maintenance
    </h1>

    <p class="page-subtitle">
        Masukkan data aktual setelah pekerjaan maintenance selesai dilakukan.
    </p>


    <?php if ($error !== ''): ?>

        <div class="error-box">
            <?= htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         INFORMASI MAINTENANCE
    ====================================================== -->

    <div class="card">

        <div class="card-header">
            <h3>Informasi Maintenance</h3>
        </div>

        <div class="card-body">

            <div class="info-grid">

                <div class="info-item">
                    <small>ID Unit</small>
                    <strong>
                        <?= htmlspecialchars($maintenance['equipment_code']); ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Equipment</small>
                    <strong>
                        <?= htmlspecialchars($maintenance['equipment_name']); ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Jenis Maintenance</small>
                    <strong>
                        <?= htmlspecialchars($maintenance['jenis_maintenance']); ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Last Service</small>
                    <strong>
                        <?= number_format(
                            $maintenance['last_service'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Current HM</small>
                    <strong>
                        <?= number_format(
                            $maintenance['current_hm_km'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Interval PM</small>
                    <strong>
                        <?= number_format(
                            $maintenance['interval_pm'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </strong>
                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         FORM PENYELESAIAN
    ====================================================== -->

    <div class="card">

        <div class="card-header">
            <h3>Data Penyelesaian Maintenance</h3>
        </div>

        <div class="card-body">

            <form method="POST">


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Tanggal Selesai
                            <span class="required">*</span>
                        </label>

                        <input
                            type="date"
                            name="tanggal_selesai"
                            value="<?= htmlspecialchars(
                                $_POST['tanggal_selesai']
                                ?? date('Y-m-d')
                            ); ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            HM Saat Maintenance
                            <span class="required">*</span>
                        </label>

                        <input
                            type="number"
                            name="hm_saat_maintenance"
                            id="hm_saat_maintenance"
                            step="0.01"
                            min="0"
                            value="<?= htmlspecialchars(
                                $_POST['hm_saat_maintenance']
                                ?? $maintenance['current_hm_km']
                            ); ?>"
                            oninput="calculateNextService()"
                            required
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Pekerjaan yang Dilakukan
                        </label>

                        <textarea
                            name="pekerjaan"
                            placeholder="Tuliskan pekerjaan maintenance yang telah dilakukan..."
                        ><?= htmlspecialchars(
                            $_POST['pekerjaan']
                            ?? $maintenance['pekerjaan']
                            ?? ''
                        ); ?></textarea>

                    </div>


                    <div class="form-group">

                        <label>
                            PIC / Teknisi
                        </label>

                        <input
                            type="text"
                            name="pic_teknisi"
                            value="<?= htmlspecialchars(
                                $_POST['pic_teknisi']
                                ?? $maintenance['pic_teknisi']
                                ?? ''
                            ); ?>"
                            placeholder="Nama teknisi"
                        >

                    </div>


                </div>


                <!-- =================================================
                     PERHITUNGAN
                ================================================== -->

                <div style="margin-top:25px;">

                    <h3
                        style="
                            margin:0 0 14px;
                            font-size:14px;
                            color:#172033;
                        "
                    >
                        Perhitungan Maintenance Berikutnya
                    </h3>


                    <div class="calculation-grid">

                        <div class="calculation-box">

                            <small>
                                Last Service Baru
                            </small>

                            <strong id="display_last_service">
                                <?= number_format(
                                    $maintenance['current_hm_km'],
                                    0,
                                    ',',
                                    '.'
                                ); ?>
                            </strong>

                        </div>


                        <div class="calculation-box">

                            <small>
                                Next Service
                            </small>

                            <strong id="display_next_service">
                                <?= number_format(
                                    $maintenance['current_hm_km']
                                    + $maintenance['interval_pm'],
                                    0,
                                    ',',
                                    '.'
                                ); ?>
                            </strong>

                        </div>


                        <div class="calculation-box">

                            <small>
                                Remaining HM
                            </small>

                            <strong id="display_remaining">
                                <?= number_format(
                                    $maintenance['interval_pm'],
                                    0,
                                    ',',
                                    '.'
                                ); ?>
                            </strong>

                        </div>

                    </div>

                </div>

<!-- STATUS PENYELESAIAN -->
<div class="form-group full">

    <label>
        Status Maintenance
    </label>

    <select name="status_penyelesaian" id="status_penyelesaian">
        <option value="Selesai">
            Selesaikan Maintenance
        </option>

        <option value="Menunggu Spare Part">
            Menunggu Spare Part
        </option>
    </select>

</div>

          
<div class="form-group full">
    <label>Status Unit Setelah Maintenance</label>

    <select name="status_unit_setelah" required>
        <option value="">Pilih kondisi aktual unit</option>
        <option value="Running">Running</option>
        <option value="Standby">Standby</option>
    </select>
</div>
<div class="button-row">
                    <a
                        href="detail.php?id=<?= $maintenance['id']; ?>"
                        class="btn btn-back"
                    >
                        Batal
                    </a>


                    <button
                        type="submit"
                        class="btn btn-save"
                        onclick="return confirm(
                            'Apakah maintenance ini benar-benar sudah selesai?'
                        );"
                    >
                        Simpan & Selesaikan
                    </button>

                </div>


            </form>

        </div>

    </div>


</main>


<script>

function formatNumber(number) {

    return new Intl.NumberFormat('id-ID').format(number);

}


function calculateNextService() {

    const hmInput =
        document.getElementById('hm_saat_maintenance');

    const lastServiceDisplay =
        document.getElementById('display_last_service');

    const nextServiceDisplay =
        document.getElementById('display_next_service');

    const remainingDisplay =
        document.getElementById('display_remaining');


    const hm =
        parseFloat(hmInput.value) || 0;


    const interval =
        <?= (float) $maintenance['interval_pm']; ?>;


    const nextService =
        hm + interval;


    const remaining =
        nextService - hm;


    lastServiceDisplay.textContent =
        formatNumber(hm);

    nextServiceDisplay.textContent =
        formatNumber(nextService);

    remainingDisplay.textContent =
        formatNumber(remaining);

}


document.addEventListener(
    'DOMContentLoaded',
    function () {

        calculateNextService();

    }
);

</script>


</body>

</html>