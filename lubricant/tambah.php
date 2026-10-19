<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   SIMPAN DATA LUBRICANT
========================================================= */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $equipment_id  = (int) ($_POST['equipment_id'] ?? 0);
    $tanggal       = $_POST['tanggal'] ?? '';
    $jenis_pelumas = trim($_POST['jenis_pelumas'] ?? '');
    $merek         = trim($_POST['merek'] ?? '');
    $jumlah        = (float) ($_POST['jumlah'] ?? 0);
    $satuan        = trim($_POST['satuan'] ?? '');
    $hm_km         = ($_POST['hm_km'] ?? '') !== ''
        ? (float) $_POST['hm_km']
        : null;
    $keterangan    = trim($_POST['keterangan'] ?? '');


    if (
        $equipment_id <= 0 ||
        empty($tanggal) ||
        empty($jenis_pelumas) ||
        $jumlah <= 0 ||
        empty($satuan)
    ) {

        $error = "Mohon lengkapi data yang wajib diisi.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO lubricant (
                equipment_id,
                tanggal,
                jenis_pelumas,
                merek,
                jumlah,
                satuan,
                hm_km,
                keterangan
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "isssdsds",
                $equipment_id,
                $tanggal,
                $jenis_pelumas,
                $merek,
                $jumlah,
                $satuan,
                $hm_km,
                $keterangan
            );

            if ($stmt->execute()) {

                header(
                    "Location: index.php?equipment_id="
                    . $equipment_id
                );

                exit;

            } else {

                $error =
                    "Data gagal disimpan: "
                    . $stmt->error;
            }

            $stmt->close();

        } else {

            $error =
                "Gagal menyiapkan database: "
                . $conn->error;
        }
    }
}


/* =========================================================
   DATA EQUIPMENT
========================================================= */

$equipment_result = $conn->query("
    SELECT
        id,
        equipment_code,
        equipment_name
    FROM equipment
    ORDER BY equipment_code ASC
");

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Tambah Lubricant - PT Biringkassi Raya</title>


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

            background: rgba(255,255,255,0.12);
        }


        .menu a.active {

            background: rgba(255,255,255,0.18);
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
           MAIN
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


        /* =================================================
           FORM BOX
        ================================================= */

        .form-box {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            padding: 28px;

            max-width: 1000px;
        }


        .form-title {

            font-size: 20px;

            font-weight: 700;

            color: #172033;

            margin-bottom: 22px;
        }
.btn-back {
    float: right;
    margin-top: -42px;
    padding: 9px 16px;
    border: 1px solid #0759bd;
    border-radius: 8px;
    color: #0759bd;
    background: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.btn-back:hover {
    background: #f0f6ff;
}

        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;
        }


        .form-group {

            display: flex;

            flex-direction: column;
        }


        .form-group.full {

            grid-column: 1 / -1;
        }


        .form-group label {

            font-size: 13px;

            font-weight: 600;

            color: #475569;

            margin-bottom: 7px;
        }


        .form-group input,
        .form-group select,
        .form-group textarea {

            width: 100%;

            border: 1px solid #d5dee9;

            border-radius: 8px;

            padding: 11px 13px;

            font-family: inherit;

            font-size: 14px;

            color: #334155;

            background: white;

            outline: none;
        }


        .form-group input,
        .form-group select {

            height: 44px;
        }


        .form-group textarea {

            min-height: 100px;

            resize: vertical;
        }


        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {

            border-color: #0759bd;

            box-shadow:
                0 0 0 3px
                rgba(7,89,189,0.08);
        }


        .required {

            color: #dc2626;
        }


        .form-note {

            font-size: 12px;

            color: #64748b;

            margin-top: 6px;
        }


        /* =================================================
           ERROR
        ================================================= */

        .error-box {

            background: #fff1f2;

            border: 1px solid #fecdd3;

            color: #be123c;

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 13px;
        }


        /* =================================================
           BUTTON
        ================================================= */

        .form-actions {

            display: flex;

            gap: 12px;

            margin-top: 25px;
        }


        .btn-save {

            height: 44px;

            padding: 0 25px;

            border: none;

            border-radius: 8px;

            background: #0759bd;

            color: white;

            font-weight: 600;

            cursor: pointer;
        }


        .btn-save:hover {

            background: #064b9f;
        }


        .btn-cancel {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 44px;

            padding: 0 22px;

            border: 1px solid #d5dee9;

            border-radius: 8px;

            background: white;

            color: #334155;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;
        }


        @media (max-width: 800px) {

            .sidebar {

                width: 210px;
            }

            .main {

                margin-left: 210px;

                padding: 25px;
            }

            .form-grid {

                grid-template-columns: 1fr;
            }

            .form-group.full {

                grid-column: auto;
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
     MAIN
===================================================== -->

<main class="main">


    <div class="breadcrumb">

        Home
        &nbsp;›&nbsp;

        <span>
            Lubricant
        </span>

        &nbsp;›&nbsp;

        <span>
            Tambah Data
        </span>

    </div>


    <h1>
        Tambah Data Lubricant
    </h1>


    <p class="description">

        Masukkan data penggunaan atau penggantian pelumas
        pada equipment.

    </p>


    <section class="form-box">

        <div class="form-title">

            Data Penggunaan Pelumas

        </div>

<a href="index.php" class="btn-back">
    ← Kembali
</a>

        <?php if ($error): ?>

            <div class="error-box">

                <?= htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
        >


            <div class="form-grid">


                <!-- EQUIPMENT -->

                <div class="form-group">

                    <label for="equipment_id">

                        Equipment
                        <span class="required">*</span>

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


                <!-- TANGGAL -->

                <div class="form-group">

                    <label for="tanggal">

                        Tanggal
                        <span class="required">*</span>

                    </label>


                    <input
                        type="date"
                        name="tanggal"
                        id="tanggal"
                        value="<?= date('Y-m-d'); ?>"
                        required
                    >

                </div>


                <!-- JENIS PELUMAS -->

                <div class="form-group">

                    <label for="jenis_pelumas">

                        Jenis Pelumas
                        <span class="required">*</span>

                    </label>


                    <select
                        name="jenis_pelumas"
                        id="jenis_pelumas"
                        required
                    >

                        <option value="">

                            Pilih Jenis Pelumas

                        </option>

                        <option value="Engine Oil">
                            Engine Oil
                        </option>

                        <option value="Hydraulic Oil">
                            Hydraulic Oil
                        </option>

                        <option value="Gear Oil">
                            Gear Oil
                        </option>

                        <option value="Transmission Oil">
                            Transmission Oil
                        </option>

                        <option value="Grease">
                            Grease
                        </option>

                        <option value="Coolant">
                            Coolant
                        </option>

                        <option value="Other">
                            Lainnya
                        </option>

                    </select>

                </div>


                <!-- MEREK -->

                <div class="form-group">

                    <label for="merek">

                        Merek

                    </label>


                    <input
                        type="text"
                        name="merek"
                        id="merek"
                        placeholder="Contoh: Pertamina"
                    >

                </div>


                <!-- JUMLAH -->

                <div class="form-group">

                    <label for="jumlah">

                        Jumlah
                        <span class="required">*</span>

                    </label>


                    <input
                        type="number"
                        name="jumlah"
                        id="jumlah"
                        min="0.01"
                        step="0.01"
                        placeholder="Contoh: 20"
                        required
                    >

                </div>


                <!-- SATUAN -->

                <div class="form-group">

                    <label for="satuan">

                        Satuan
                        <span class="required">*</span>

                    </label>


                    <select
                        name="satuan"
                        id="satuan"
                        required
                    >

                        <option value="">

                            Pilih Satuan

                        </option>

                        <option value="Liter">
                            Liter
                        </option>

                        <option value="Kg">
                            Kg
                        </option>

                    </select>

                </div>


                <!-- HM -->

                <div class="form-group">

                    <label for="hm_km">

                        HM

                    </label>


                    <input
                        type="number"
                        name="hm_km"
                        id="hm_km"
                        min="0"
                        step="0.01"
                        placeholder="Contoh: 12500"
                    >


                    <div class="form-note">

                        Isi Hour Meter atau Kilometer
                        saat pelumas digunakan.

                    </div>

                </div>


                <!-- KETERANGAN -->

                <div class="form-group full">

                    <label for="keterangan">

                        Keterangan

                    </label>


                    <textarea
                        name="keterangan"
                        id="keterangan"
                        placeholder="Tambahkan keterangan jika diperlukan..."
                    ></textarea>

                </div>


            </div>


            <div class="form-actions">


                <button
                    type="submit"
                    class="btn-save"
                >

                    Simpan Data

                </button>


                <a
                    href="index.php"
                    class="btn-cancel"
                >

                    Batal

                </a>


            </div>


        </form>

    </section>


</main>


</body>

</html>