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

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>

        /* khusus halaman tambah lubricant */

        .form-card {
            max-width: 1000px;
            padding: 24px;
        }

        .form-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 20px;
        }

        .form-card .form-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        @media (max-width: 800px) {

            .form-card .form-grid {
                grid-template-columns: 1fr;
            }

            .form-card .form-group.full {
                grid-column: auto;
            }

        }

    </style>

</head>


<body>

<?php
$base   = '../';
$active = 'lubricant';
include $base . 'includes/sidebar.php';
?>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <div class="breadcrumb">

        <a href="../index.php">Home</a>
        &nbsp;›&nbsp;

        <a href="index.php">Lubricant</a>

        &nbsp;›&nbsp;

        <span>
            Tambah Data
        </span>

    </div>


    <div class="header">

        <div class="header-left">

            <h1>
                Tambah Data Lubricant
            </h1>

            <p>

                Masukkan data penggunaan atau penggantian pelumas
                pada equipment.

            </p>

        </div>

        <a href="index.php" class="btn-reset">
            ← Kembali
        </a>

    </div>


    <section class="form-card">

        <div class="form-title">

            Data Penggunaan Pelumas

        </div>

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