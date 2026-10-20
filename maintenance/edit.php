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
   PROSES UPDATE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $equipment_id = $_POST['equipment_id'] ?? '';
    $jenis_maintenance = $_POST['jenis_maintenance'] ?? '';
    $last_service = (float) ($_POST['last_service'] ?? 0);
    $current_hm_km = (float) ($_POST['current_hm_km'] ?? 0);
    $interval_pm = (float) ($_POST['interval_pm'] ?? 0);
    $tanggal_jadwal = $_POST['tanggal_jadwal'] ?? null;
    $pekerjaan = $_POST['pekerjaan'] ?? '';
    $pic_teknisi = $_POST['pic_teknisi'] ?? '';

    /*
        Perhitungan otomatis
    */

    $next_service = $last_service + $interval_pm;
    $remaining_hm_km = $next_service - $current_hm_km;

    /*
        Penentuan status
    */

    if ($remaining_hm_km < 0) {
        $status = "Overdue";
    } elseif ($remaining_hm_km <= 100) {
        $status = "Segera Diservis";
    } elseif ($remaining_hm_km <= 250) {
        $status = "Akan Jatuh Tempo";
    } else {
        $status = "Normal";
    }

    /* =====================================================
       AMBIL EQUIPMENT LAMA
       Supaya jika ID Unit diganti, status equipment lama
       dapat disesuaikan kembali.
    ====================================================== */

    $old_equipment_id = 0;

    $old_stmt = $conn->prepare("
        SELECT equipment_id
        FROM maintenance_schedule
        WHERE id = ?
        LIMIT 1
    ");

    if ($old_stmt) {
        $old_stmt->bind_param("i", $id);
        $old_stmt->execute();

        $old_result = $old_stmt->get_result();

        if ($old_result->num_rows === 1) {
            $old_data = $old_result->fetch_assoc();
            $old_equipment_id = (int) $old_data['equipment_id'];
        }

        $old_stmt->close();
    }

    /* =====================================================
       UPDATE DATABASE
    ====================================================== */

    $sql = "
        UPDATE maintenance_schedule
        SET
            equipment_id = ?,
            jenis_maintenance = ?,
            last_service = ?,
            current_hm_km = ?,
            interval_pm = ?,
            next_service = ?,
            remaining_hm_km = ?,
            pekerjaan = ?,
            pic_teknisi = ?,
            tanggal_jadwal = ?,
            status = ?
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "isddddsssssi",
        $equipment_id,
        $jenis_maintenance,
        $last_service,
        $current_hm_km,
        $interval_pm,
        $next_service,
        $remaining_hm_km,
        $pekerjaan,
        $pic_teknisi,
        $tanggal_jadwal,
        $status,
        $id
    );

    if ($stmt->execute()) {

        $stmt->close();

        /* =================================================
           EQUIPMENT YANG DIPILIH PADA JADWAL INI
           OTOMATIS MENJADI MAINTENANCE
        ================================================== */

        $update_new_equipment = $conn->prepare("
            UPDATE equipment
            SET status = 'Maintenance'
            WHERE id = ?
        ");

        if ($update_new_equipment) {
            $update_new_equipment->bind_param("i", $equipment_id);
            $update_new_equipment->execute();
            $update_new_equipment->close();
        }

        /* =================================================
           JIKA ID UNIT DIGANTI, CEK EQUIPMENT LAMA
           Jika sudah tidak memiliki jadwal maintenance lain,
           statusnya dikembalikan menjadi Standby.
        ================================================== */

        if (
            $old_equipment_id > 0 &&
            $old_equipment_id != (int) $equipment_id
        ) {

            $check_old = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM maintenance_schedule
                WHERE equipment_id = ?
            ");

            if ($check_old) {
                $check_old->bind_param("i", $old_equipment_id);
                $check_old->execute();

                $old_result = $check_old->get_result();
                $old_count = 0;

                if ($old_result->num_rows === 1) {
                    $old_row = $old_result->fetch_assoc();
                    $old_count = (int) $old_row['total'];
                }

                $check_old->close();

                if ($old_count === 0) {
                    $restore_old_equipment = $conn->prepare("
                        UPDATE equipment
                        SET status = 'Standby'
                        WHERE id = ?
                    ");

                    if ($restore_old_equipment) {
                        $restore_old_equipment->bind_param("i", $old_equipment_id);
                        $restore_old_equipment->execute();
                        $restore_old_equipment->close();
                    }
                }
            }
        }

        $conn->close();

        header("Location: detail.php?id=" . $id);
        exit;
    }

    $stmt->close();
}


/* =========================================================
   AMBIL DATA JADWAL YANG DIEDIT
   (dibutuhkan untuk mengisi form di bawah)
========================================================= */

$data_stmt = $conn->prepare("
    SELECT *
    FROM maintenance_schedule
    WHERE id = ?
    LIMIT 1
");

$data_stmt->bind_param("i", $id);
$data_stmt->execute();

$data_result = $data_stmt->get_result();

if ($data_result->num_rows === 0) {

    $data_stmt->close();
    $conn->close();

    header("Location: index.php");
    exit;
}

$data = $data_result->fetch_assoc();

$data_stmt->close();


/* =========================================================
   AMBIL DATA EQUIPMENT
========================================================= */

$equipment_result = $conn->query("
    SELECT
        id,
        equipment_code,
        equipment_name,
        equipment_type,
        hm_current
    FROM equipment
    ORDER BY equipment_code ASC
");

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Jadwal - PT Biringkassi Raya</title>

    <!-- Terapkan tema tersimpan sebelum halaman tampil -->
    <script>
        try {
            document.documentElement.setAttribute(
                'data-theme',
                localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'
            );
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>

        /* khusus halaman form jadwal */

        .form-card {
            max-width: 1100px;
            padding: 24px 26px;
        }

        .form-section {
            margin-bottom: 26px;
        }

        .form-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 600;
            color: var(--ink);
            padding-bottom: 10px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--line);
        }

        .form-section-title::before {
            content: "";
            width: 4px;
            height: 16px;
            border-radius: 2px;
            background: var(--primary);
        }

        .form-grid.cols-2 {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px 20px;
        }

        .required {
            color: var(--red);
        }

        input[readonly] {
            background: var(--th-bg);
            color: var(--muted);
            cursor: not-allowed;
        }

        .helper {
            margin-top: 4px;
            font-size: 12px;
            color: var(--muted);
        }

        .form-footer {
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }

        /* kotak hitung otomatis */

        .calculation-box {
            background: var(--th-bg);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 16px;
        }

        .calculation-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .calculation-item {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .calculation-item span {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .calculation-item strong {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.4px;
            color: var(--ink);
        }

        .status-preview {
            display: inline-flex;
            align-items: center;
            margin-top: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-preview.normal  { background: var(--green-soft); color: var(--green); }
        .status-preview.warning { background: var(--amber-soft); color: var(--amber); }
        .status-preview.danger  { background: var(--red-soft);   color: var(--red); }

        @media (max-width: 900px) {

            .form-grid.cols-2,
            .calculation-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }
        }

    </style>

</head>


<body>

    <?php
    $base   = '../';
    $active = 'schedule';
    include $base . 'includes/sidebar.php';
    ?>


    <main class="main">


        <!-- HEADER -->

        <div class="header">

            <div class="header-left">

                <div class="breadcrumb">

                    <a href="index.php">
                        Jadwal Pemeliharaan
                    </a>

                    &nbsp;›&nbsp;

                    <span>
                        Edit Jadwal
                    </span>

                </div>

                <h1>
                    Edit Jadwal Pemeliharaan
                </h1>

                <p>
                    Perbarui informasi jadwal maintenance equipment.
                </p>

            </div>

            <div class="header-right">
                <a href="detail.php?id=<?= $id; ?>" class="back-btn" title="Kembali">←</a>
            </div>

        </div>


        <form
            method="POST"
            class="form-card"
        >


            <!-- =================================================
                 EQUIPMENT
            ================================================== -->

            <div class="form-section">

                <div class="form-section-title">
                    Data Equipment
                </div>


                <div class="form-grid cols-2">


                    <div class="form-group">

                        <label>
                            ID Unit
                            <span class="required">*</span>
                        </label>


                        <select
                            name="equipment_id"
                            id="equipment_id"
                            required
                            onchange="loadEquipment()"
                        >

                            <option value="">
                                Pilih ID Unit
                            </option>


                            <?php while ($equipment = $equipment_result->fetch_assoc()): ?>

                                <option
                                    value="<?= $equipment['id']; ?>"
                                    data-name="<?= htmlspecialchars($equipment['equipment_name']); ?>"
                                    data-type="<?= htmlspecialchars($equipment['equipment_type']); ?>"
                                    data-hm="<?= $equipment['hm_current']; ?>"
                                    <?= ($equipment['id'] == $data['equipment_id']) ? 'selected' : ''; ?>
                                >

                                    <?= htmlspecialchars($equipment['equipment_code']); ?>

                                    -

                                    <?= htmlspecialchars($equipment['equipment_name']); ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Nama Equipment
                        </label>


                        <input
                            type="text"
                            id="equipment_name"
                            readonly
                            value=""
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Jenis Alat
                        </label>


                        <input
                            type="text"
                            id="equipment_type"
                            readonly
                            value=""
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Current HM
                            <span class="required">*</span>
                        </label>


                        <input
                            type="number"
                            name="current_hm_km"
                            id="current_hm_km"
                            step="0.01"
                            min="0"
                            required
                            value="<?= htmlspecialchars($data['current_hm_km']); ?>"
                            oninput="calculateMaintenance()"
                        >

                    </div>

                </div>

            </div>


            <!-- =================================================
                 MAINTENANCE
            ================================================== -->

            <div class="form-section">

                <div class="form-section-title">
                    Informasi Maintenance
                </div>


                <div class="form-grid cols-2">


                    <div class="form-group">

                        <label>
                            Jenis Maintenance
                            <span class="required">*</span>
                        </label>


                        <select
                            name="jenis_maintenance"
                            required
                        >

                            <option value="">
                                Pilih Jenis Maintenance
                            </option>

                            <option
                                value="Preventive"
                                <?= ($data['jenis_maintenance'] === 'Preventive') ? 'selected' : ''; ?>
                            >
                                Preventive
                            </option>

                            <option
                                value="Corrective"
                                <?= ($data['jenis_maintenance'] === 'Corrective') ? 'selected' : ''; ?>
                            >
                                Corrective
                            </option>

                            <option
                                value="Inspection"
                                <?= ($data['jenis_maintenance'] === 'Inspection') ? 'selected' : ''; ?>
                            >
                                Inspection
                            </option>

                            <option
                                value="Lubrication"
                                <?= ($data['jenis_maintenance'] === 'Lubrication') ? 'selected' : ''; ?>
                            >
                                Lubrication
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Last Service
                            <span class="required">*</span>
                        </label>


                        <input
                            type="number"
                            name="last_service"
                            id="last_service"
                            step="0.01"
                            min="0"
                            required
                            value="<?= htmlspecialchars($data['last_service']); ?>"
                            oninput="calculateMaintenance()"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Interval PM
                            <span class="required">*</span>
                        </label>


                        <input
                            type="number"
                            name="interval_pm"
                            id="interval_pm"
                            step="0.01"
                            min="0"
                            required
                            value="<?= htmlspecialchars($data['interval_pm']); ?>"
                            oninput="calculateMaintenance()"
                        >


                        <div class="helper">
                            Contoh: 250 HM, 500 HM, 1.000 KM.
                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Tanggal Jadwal
                        </label>


                        <input
                            type="date"
                            name="tanggal_jadwal"
                            value="<?= htmlspecialchars($data['tanggal_jadwal'] ?? ''); ?>"
                        >

                    </div>


                    <div class="form-group full">

                        <div class="calculation-box">

                            <div class="calculation-grid">


                                <div class="calculation-item">

                                    <span>
                                        Next Service
                                    </span>

                                    <strong id="next_service_display">
                                        -
                                    </strong>

                                </div>


                                <div class="calculation-item">

                                    <span>
                                        Remaining HM
                                    </span>

                                    <strong id="remaining_display">
                                        -
                                    </strong>

                                </div>


                                <div class="calculation-item">

                                    <span>
                                        Status
                                    </span>

                                    <div
                                        id="status_display"
                                        class="status-preview normal"
                                    >
                                        Normal
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 DETAIL PEKERJAAN
            ================================================== -->

            <div class="form-section">

                <div class="form-section-title">
                    Detail Pekerjaan
                </div>


                <div class="form-grid cols-2">


                    <div class="form-group">

                        <label>
                            PIC / Teknisi
                        </label>


                        <input
                            type="text"
                            name="pic_teknisi"
                            value="<?= htmlspecialchars($data['pic_teknisi'] ?? ''); ?>"
                            placeholder="Nama PIC atau teknisi"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Pekerjaan
                        </label>


                        <input
                            type="text"
                            name="pekerjaan"
                            value="<?= htmlspecialchars($data['pekerjaan'] ?? ''); ?>"
                            placeholder="Contoh: Penggantian oli engine"
                        >

                    </div>

                </div>

            </div>


            <!-- =================================================
                 BUTTON
            ================================================== -->

            <div class="form-footer">

                <a
                    href="detail.php?id=<?= $id; ?>"
                    class="btn-cancel"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    class="btn-save"
                >
                    Simpan Perubahan
                </button>

            </div>


        </form>

    </main>


    <script>

        /* =========================================================
           DATA EQUIPMENT
        ========================================================= */

        function loadEquipment() {

            const select =
                document.getElementById('equipment_id');

            const option =
                select.options[select.selectedIndex];

            const name =
                option.getAttribute('data-name') || '';

            const type =
                option.getAttribute('data-type') || '';

            document.getElementById('equipment_name').value =
                name;

            document.getElementById('equipment_type').value =
                type;

            calculateMaintenance();

        }

        /* =========================================================
           CALCULATE
        ========================================================= */

        function calculateMaintenance() {

            const lastService =
                parseFloat(
                    document.getElementById('last_service').value
                ) || 0;

            const current =
                parseFloat(
                    document.getElementById('current_hm_km').value
                ) || 0;

            const interval =
                parseFloat(
                    document.getElementById('interval_pm').value
                ) || 0;

            if (interval <= 0) {

                document.getElementById('next_service_display').innerText =
                    '-';

                document.getElementById('remaining_display').innerText =
                    '-';

                document.getElementById('status_display').innerText =
                    'Normal';

                document.getElementById('status_display').className =
                    'status-preview normal';

                return;
            }

            const nextService =
                lastService + interval;

            const remaining =
                nextService - current;

            document.getElementById('next_service_display').innerText =
                formatNumber(nextService);

            document.getElementById('remaining_display').innerText =
                formatNumber(remaining);

            let status = 'Normal';

            let statusClass = 'normal';

            if (remaining < 0) {

                status = 'Overdue';

                statusClass = 'danger';

            } else if (remaining <= 100) {

                status = 'Segera Diservis';

                statusClass = 'danger';

            } else if (remaining <= 250) {

                status = 'Akan Jatuh Tempo';

                statusClass = 'warning';

            }

            const statusElement =
                document.getElementById('status_display');

            statusElement.innerText =
                status;

            statusElement.className =
                'status-preview ' + statusClass;

        }

        /* =========================================================
           FORMAT
        ========================================================= */

        function formatNumber(number) {

            return new Intl.NumberFormat('id-ID', {

                maximumFractionDigits: 0

            }).format(number);

        }

        /* =========================================================
           LOAD SAAT HALAMAN DIBUKA
        ========================================================= */

        document.addEventListener(

            'DOMContentLoaded',

            function () {

                loadEquipment();

                calculateMaintenance();

            }

        );

    </script>

</body>

</html>