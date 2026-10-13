<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   AMBIL ID HISTORY
========================================================= */

$history_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($history_id <= 0) {
    header("Location: history/index.php");
    exit;
}


/* =========================================================
   AMBIL DATA HISTORY
========================================================= */

$stmt = $conn->prepare("
    SELECT
        h.*,
        e.equipment_code,
        e.equipment_name
    FROM maintenance_history h
    INNER JOIN equipment e
        ON h.equipment_id = e.id
    WHERE h.id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Gagal menyiapkan data history.");
}

$stmt->bind_param(
    "i",
    $history_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    header("Location: history/index.php");
    exit;
}

$history = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   PROSES BATALKAN PENYELESAIAN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $maintenance_id =
        (int) $history['maintenance_id'];

    $equipment_id =
        (int) $history['equipment_id'];

    $conn->begin_transaction();

    try {


        /* =================================================
           1. KEMBALIKAN MAINTENANCE SCHEDULE
        ================================================= */

        $update_maintenance = $conn->prepare("
            UPDATE maintenance_schedule
            SET
                last_service = ?,
                current_hm_km = ?,
                next_service = ?,
                remaining_hm_km = ?,
                tanggal_jadwal = ?,
                status = ?
            WHERE id = ?
        ");

        if (!$update_maintenance) {
            throw new Exception(
                "Gagal menyiapkan pengembalian maintenance."
            );
        }

        $previous_tanggal_jadwal =
            $history['previous_tanggal_jadwal'];

        if (
            $previous_tanggal_jadwal === '0000-00-00' ||
            empty($previous_tanggal_jadwal)
        ) {
            $previous_tanggal_jadwal = null;
        }

        $update_maintenance->bind_param(
            "ddddssi",
            $history['previous_last_service'],
            $history['previous_current_hm_km'],
            $history['previous_next_service'],
            $history['previous_remaining_hm_km'],
            $previous_tanggal_jadwal,
            $history['previous_status'],
            $maintenance_id
        );

        if (!$update_maintenance->execute()) {
            throw new Exception(
                "Gagal mengembalikan data maintenance."
            );
        }

        $update_maintenance->close();


        /* =================================================
           2. KEMBALIKAN KONDISI EQUIPMENT
        ================================================= */

        $update_equipment = $conn->prepare("
            UPDATE equipment
            SET
                hm_current = ?,
                status = ?
            WHERE id = ?
        ");

        if (!$update_equipment) {
            throw new Exception(
                "Gagal menyiapkan pengembalian equipment."
            );
        }

        $update_equipment->bind_param(
            "dsi",
            $history['previous_equipment_hm'],
            $history['previous_equipment_status'],
            $equipment_id
        );

        if (!$update_equipment->execute()) {
            throw new Exception(
                "Gagal mengembalikan kondisi equipment."
            );
        }

        $update_equipment->close();


        /* =================================================
           3. HAPUS HISTORY YANG DIBATALKAN
        ================================================= */

        $delete_history = $conn->prepare("
            DELETE FROM maintenance_history
            WHERE id = ?
        ");

        if (!$delete_history) {
            throw new Exception(
                "Gagal menyiapkan penghapusan history."
            );
        }

        $delete_history->bind_param(
            "i",
            $history_id
        );

        if (!$delete_history->execute()) {
            throw new Exception(
                "Gagal menghapus history maintenance."
            );
        }

        $delete_history->close();


        /* =================================================
           COMMIT
        ================================================= */

        $conn->commit();

        header("Location: index.php");
        exit;


    } catch (Exception $e) {

        $conn->rollback();

        die(
            "Gagal membatalkan penyelesaian maintenance: "
            . htmlspecialchars($e->getMessage())
        );
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

    <title>
        Batalkan Penyelesaian - PT Biringkassi Raya
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item {
            background: #f7f9fc;
            border: 1px solid #e7ecf3;
            border-radius: 8px;
            padding: 14px;
        }

        .info-item small {
            display: block;
            font-size: 10px;
            color: #7d899d;
            margin-bottom: 5px;
        }

        .info-item strong {
            display: block;
            font-size: 13px;
            color: #172033;
        }

        .warning {
            background: #fff7e6;
            border: 1px solid #f2d28b;
            color: #8a5a00;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 18px;
        }

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
            padding: 0 18px;
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

        .btn-cancel {
            background: #d64545;
            color: white;
        }

        .btn-cancel:hover {
            background: #bd3535;
        }

        @media (max-width: 800px) {

            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
                padding: 20px;
            }

            .info-grid {
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
            <a href="index.php">
                Jadwal Pemeliharaan
            </a>
        </li>

        <li>
            <a href="history/index.php" class="active">
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

        Histori Pemeliharaan &nbsp;›&nbsp;

        <span>Batalkan Penyelesaian</span>

    </div>


    <h1 class="page-title">
        Batalkan Penyelesaian
    </h1>

    <p class="page-subtitle">
        Kembalikan maintenance ke kondisi sebelum penyelesaian.
    </p>


    <div class="card">

        <div class="card-header">

            <h3>
                Konfirmasi Pembatalan
            </h3>

        </div>


        <div class="card-body">

            <div class="warning">

                <strong>
                    Perhatian:
                </strong>

                <br>

                Tindakan ini akan mengembalikan data maintenance
                dan kondisi equipment ke keadaan sebelum maintenance
                dinyatakan selesai.

                <br><br>

                History maintenance ini juga akan dihapus dari
                Histori Pemeliharaan.

            </div>


            <div class="info-grid">

                <div class="info-item">

                    <small>
                        ID Unit
                    </small>

                    <strong>
                        <?= htmlspecialchars(
                            $history['equipment_code']
                        ); ?>
                    </strong>

                </div>


                <div class="info-item">

                    <small>
                        Equipment
                    </small>

                    <strong>
                        <?= htmlspecialchars(
                            $history['equipment_name']
                        ); ?>
                    </strong>

                </div>


                <div class="info-item">

                    <small>
                        HM Saat Maintenance
                    </small>

                    <strong>
                        <?= number_format(
                            (float) $history['hm_saat_maintenance'],
                            2,
                            ',',
                            '.'
                        ); ?>
                    </strong>

                </div>


                <div class="info-item">

                    <small>
                        Next HM
                    </small>

                    <strong>
                        <?= number_format(
                            (float) $history['next_hm'],
                            2,
                            ',',
                            '.'
                        ); ?>
                    </strong>

                </div>


                <div class="info-item">

                    <small>
                        Status Sebelumnya
                    </small>

                    <strong>
                        <?= htmlspecialchars(
                            $history['previous_status']
                        ); ?>
                    </strong>

                </div>


                <div class="info-item">

                    <small>
                        Status Equipment Sebelumnya
                    </small>

                    <strong>
                        <?= htmlspecialchars(
                            $history['previous_equipment_status']
                        ); ?>
                    </strong>

                </div>

            </div>


            <form
                method="POST"
                onsubmit="return confirm(
                    'Apakah Anda yakin ingin membatalkan penyelesaian maintenance ini?'
                );"
            >

                <div class="button-row">

                    <a
                        href="history/detail.php?id=<?= $history_id; ?>"
                        class="btn btn-back"
                    >
                        Kembali
                    </a>

                    <button
                        type="submit"
                        class="btn btn-cancel"
                    >
                        Batalkan Penyelesaian
                    </button>

                </div>

            </form>

        </div>

    </div>

</main>

</body>

</html>