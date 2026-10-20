<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$message = "";
$message_type = "";


/* =========================================================
   PROSES SIMPAN DATA
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $equipment_id = $_POST['equipment_id'] ?? '';
    $jenis_maintenance = $_POST['jenis_maintenance'] ?? '';
    $last_service = $_POST['last_service'] ?? 0;
    $current_hm_km = $_POST['current_hm_km'] ?? 0;
    $interval_pm = $_POST['interval_pm'] ?? 0;
    $tanggal_jadwal = $_POST['tanggal_jadwal'] ?? null;
    $mulai_maintenance = $_POST['mulai_maintenance'] ?? '0';
    $pekerjaan = $_POST['pekerjaan'] ?? '';
    $pic_teknisi = $_POST['pic_teknisi'] ?? '';


    /* =====================================================
       DATA SPARE PART & LUBRICANT
    ===================================================== */

    $sparepart_nama = $_POST['sparepart_nama'] ?? [];
    $sparepart_kategori = $_POST['sparepart_kategori'] ?? [];
    $sparepart_jenis = $_POST['sparepart_jenis'] ?? [];
    $sparepart_spesifikasi = $_POST['sparepart_spesifikasi'] ?? [];
    $sparepart_jumlah = $_POST['sparepart_jumlah'] ?? [];
    $sparepart_satuan = $_POST['sparepart_satuan'] ?? [];
    $sparepart_tanggal_order = $_POST['sparepart_tanggal_order'] ?? [];
    $sparepart_perkiraan_tiba = $_POST['sparepart_perkiraan_tiba'] ?? [];
    $sparepart_tanggal_tiba = $_POST['sparepart_tanggal_tiba'] ?? [];
    $sparepart_status = $_POST['sparepart_status'] ?? [];
    $sparepart_keterangan = $_POST['sparepart_keterangan'] ?? [];


    $last_service = (float) $last_service;
    $current_hm_km = (float) $current_hm_km;
    $interval_pm = (float) $interval_pm;


    /*
        Perhitungan:

        Next Service = Last Service + Interval PM

        Remaining = Next Service - Current HM
    */

    $next_service = $last_service + $interval_pm;
    $remaining_hm_km = $next_service - $current_hm_km;


    /*
        Penentuan status sementara.
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


    if (
        empty($equipment_id) ||
        empty($jenis_maintenance) ||
        $interval_pm <= 0
    ) {

        $message = "Mohon lengkapi data yang wajib diisi.";
        $message_type = "error";

    } else {

        $sql = "
            INSERT INTO maintenance_schedule
            (
                equipment_id,
                jenis_maintenance,
                last_service,
                current_hm_km,
                interval_pm,
                next_service,
                remaining_hm_km,
                pekerjaan,
                pic_teknisi,
                tanggal_jadwal,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "isddddsssss",
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
            $status
        );


        if ($stmt->execute()) {

            /* =====================================================
               SIMPAN KEBUTUHAN SPARE PART & LUBRICANT
            ===================================================== */

            $maintenance_id = $stmt->insert_id;


            if (!empty($sparepart_nama) && is_array($sparepart_nama)) {

                $sparepart_sql = "
                    INSERT INTO maintenance_spare_parts
                    (
                        maintenance_id,
                        nama_item,
                        kategori,
                        jenis,
                        spesifikasi,
                        jumlah,
                        satuan,
                        tanggal_order,
                        perkiraan_tiba,
                        tanggal_tiba_aktual,
                        status,
                        keterangan
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";

                $sparepart_stmt = $conn->prepare($sparepart_sql);


                if ($sparepart_stmt) {

                    foreach ($sparepart_nama as $i => $nama_item) {

                        $nama_item = trim($nama_item);

                        if ($nama_item === '') {
                            continue;
                        }


                        $kategori = trim(
                            $sparepart_kategori[$i] ?? 'Spare Part'
                        );

                        $jenis = trim(
                            $sparepart_jenis[$i] ?? ''
                        );

                        $spesifikasi = trim(
                            $sparepart_spesifikasi[$i] ?? ''
                        );

                        $jumlah = (float)(
                            $sparepart_jumlah[$i] ?? 1
                        );

                        $satuan = trim(
                            $sparepart_satuan[$i] ?? 'pcs'
                        );


                        $tanggal_order =
                            !empty($sparepart_tanggal_order[$i])
                            ? $sparepart_tanggal_order[$i]
                            : null;


                        $perkiraan_tiba =
                            !empty($sparepart_perkiraan_tiba[$i])
                            ? $sparepart_perkiraan_tiba[$i]
                            : null;


                        $tanggal_tiba =
                            !empty($sparepart_tanggal_tiba[$i])
                            ? $sparepart_tanggal_tiba[$i]
                            : null;


                        $status_item = trim(
                            $sparepart_status[$i] ?? 'Menunggu Order'
                        );


                        $keterangan = trim(
                            $sparepart_keterangan[$i] ?? ''
                        );


                        $sparepart_stmt->bind_param(
                            "issssdssssss",
                            $maintenance_id,
                            $nama_item,
                            $kategori,
                            $jenis,
                            $spesifikasi,
                            $jumlah,
                            $satuan,
                            $tanggal_order,
                            $perkiraan_tiba,
                            $tanggal_tiba,
                            $status_item,
                            $keterangan
                        );

                        $sparepart_stmt->execute();
                    }

                    $sparepart_stmt->close();
                }
            }


            /*
                Setelah jadwal maintenance berhasil disimpan,
                status equipment otomatis menjadi Maintenance.
            */

            $updateEquipment = $conn->prepare("
                UPDATE equipment
                SET status = 'Maintenance'
                WHERE id = ?
            ");

            if ($updateEquipment) {
                $updateEquipment->bind_param("i", $equipment_id);
                $updateEquipment->execute();
                $updateEquipment->close();
            }

            header("Location: index.php");
            exit;

        } else {

            $message = "Data gagal disimpan. Silakan coba lagi.";
            $message_type = "error";
        }


        $stmt->close();
    }
}


/* =========================================================
   AMBIL DATA EQUIPMENT
========================================================= */

$equipment_result = $conn->query("
    SELECT
        e.id,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type,
        e.hm_current,
        (
            SELECT mh.hm_saat_maintenance
            FROM maintenance_history mh
            WHERE mh.equipment_id = e.id
            ORDER BY mh.tanggal_selesai DESC, mh.id DESC
            LIMIT 1
        ) AS last_service
    FROM equipment e
    ORDER BY e.equipment_code ASC
");

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Jadwal - PT Biringkassi Raya</title>

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

        /* pesan */

        .message {
            max-width: 1100px;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            font-size: 13px;
        }

        .message.error {
            background: var(--red-soft);
            border: 1px solid var(--delete-border);
            color: var(--red);
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

        /* spare part & lubricant */

        .sparepart-wrapper {
            background: var(--th-bg);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 16px;
        }

        .sparepart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6px;
        }

        .sparepart-header strong {
            font-size: 14px;
            color: var(--ink);
        }

        .sparepart-description {
            font-size: 12px;
            line-height: 1.6;
            color: var(--muted);
            margin-bottom: 14px;
        }

        .btn-add-item {
            display: inline-flex;
            align-items: center;
            height: 34px;
            padding: 0 14px;
            border: 1px solid var(--primary);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--primary);
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
        }

        .btn-add-item:hover {
            background: var(--primary-soft);
        }

        .sparepart-empty {
            border: 1.5px dashed var(--line-strong);
            border-radius: 10px;
            padding: 22px;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            background: var(--surface);
        }

        .sparepart-item {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .sparepart-item:last-child {
            margin-bottom: 0;
        }

        .sparepart-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--line);
        }

        .sparepart-item-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .btn-remove-item {
            border: 1px solid var(--delete-border);
            background: transparent;
            color: var(--red);
            border-radius: 7px;
            padding: 5px 11px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-remove-item:hover {
            background: var(--red-soft);
        }

        .sparepart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px 16px;
        }

        .sparepart-grid .form-group.full {
            grid-column: 1 / -1;
        }

        .sparepart-grid textarea {
            min-height: 76px;
        }

        @media (max-width: 900px) {

            .form-grid.cols-2,
            .calculation-grid,
            .sparepart-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full,
            .sparepart-grid .form-group.full {
                grid-column: auto;
            }

            .sparepart-header {
                flex-direction: column;
                align-items: flex-start;
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
                        Tambah Jadwal
                    </span>

                </div>

                <h1>
                    Tambah Jadwal Pemeliharaan
                </h1>

                <p>
                    Tambahkan jadwal preventive maintenance untuk alat berat.
                </p>

            </div>

            <div class="header-right">
                <a href="index.php" class="back-btn" title="Kembali">←</a>
            </div>

        </div>


        <?php if ($message !== ''): ?>

            <div class="message <?= $message_type; ?>">

                <?= htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            class="form-card"
        >


            <!-- =================================================
                 DATA EQUIPMENT
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


                            <?php if ($equipment_result): ?>

                                <?php while ($equipment = $equipment_result->fetch_assoc()): ?>

                                    <option
                                        value="<?= $equipment['id']; ?>"
                                        data-name="<?= htmlspecialchars($equipment['equipment_name']); ?>"
                                        data-type="<?= htmlspecialchars($equipment['equipment_type']); ?>"
                                        data-hm="<?= $equipment['hm_current']; ?>"
                                        data-last-service="<?= htmlspecialchars((string)($equipment['last_service'] ?? '')); ?>"
                                    >

                                        <?= htmlspecialchars($equipment['equipment_code']); ?>

                                        -

                                        <?= htmlspecialchars($equipment['equipment_name']); ?>

                                    </option>

                                <?php endwhile; ?>

                            <?php endif; ?>

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
                            placeholder="Nama equipment otomatis"
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
                            placeholder="Jenis alat otomatis"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Current HM
                        </label>


                        <input
                            type="number"
                            name="current_hm_km"
                            id="current_hm_km"
                            step="0.01"
                            min="0"
                            placeholder="Masukkan Current HM"
                            oninput="calculateMaintenance()"
                        >


                        <div class="helper">
                            Nilai dapat disesuaikan dengan data aktual.
                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 DATA MAINTENANCE
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

                            <option value="Preventive">
                                Preventive
                            </option>

                            <option value="Corrective">
                                Corrective
                            </option>

                            <option value="Inspection">
                                Inspection
                            </option>

                            <option value="Lubrication">
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
                            placeholder="Masukkan HM Last Service"
                            required
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
                            placeholder="Contoh: 250"
                            required
                            oninput="calculateMaintenance()"
                        >


                        <div class="helper">
                            Contoh interval: 250 HM, 500 HM, 1.000 KM.
                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Tanggal Jadwal
                        </label>


                        <input
                            type="date"
                            name="tanggal_jadwal"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Pelaksanaan Maintenance
                        </label>

                        <select name="mulai_maintenance" required>

                            <option value="0">
                                Jadwal saja (belum dimulai)
                            </option>

                            <option value="1">
                                Mulai maintenance sekarang
                            </option>

                        </select>

                        <div class="helper">
                            Pilih mulai sekarang hanya jika pekerjaan benar-benar dimulai.
                        </div>

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
                 SPARE PART & LUBRICANT
            ================================================== -->

            <div class="form-section">

                <div class="form-section-title">
                    Kebutuhan Spare Part & Lubricant
                </div>


                <div class="sparepart-wrapper">


                    <div class="sparepart-header">

                        <strong>
                            Spare Part / Lubricant yang Dibutuhkan
                        </strong>


                        <button
                            type="button"
                            class="btn-add-item"
                            onclick="addSparepartItem()"
                        >
                            + Tambah Item
                        </button>

                    </div>


                    <div class="sparepart-description">

                        Tambahkan spare part atau lubricant yang diperlukan
                        untuk maintenance ini. Satu jadwal maintenance
                        dapat memiliki lebih dari satu item.

                    </div>


                    <div id="sparepartContainer">

                        <div
                            class="sparepart-empty"
                            id="sparepartEmpty"
                        >

                            Belum ada kebutuhan spare part atau lubricant.

                            Klik
                            <strong>
                                + Tambah Item
                            </strong>
                            untuk menambahkan.

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 PEKERJAAN
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
                            placeholder="Contoh: Penggantian oli engine"
                        >

                    </div>

                </div>

            </div>


            <!-- =================================================
                 ACTION
            ================================================== -->

            <div class="form-footer">

                <a
                    href="index.php"
                    class="btn-cancel"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    class="btn-save"
                >
                    Simpan Jadwal
                </button>

            </div>


        </form>

    </main>


    <script>


        /* =========================================================
           LOAD DATA EQUIPMENT
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

            const hm =
                option.getAttribute('data-hm') || '';

            const lastService =
                option.getAttribute('data-last-service') || '';


            document.getElementById(
                'equipment_name'
            ).value = name;


            document.getElementById(
                'equipment_type'
            ).value = type;

            document.getElementById(
                'last_service'
            ).value = lastService;


            /*
                Current HM dari Equipment Master
                hanya diisi otomatis jika nilainya tersedia.
            */

            const currentInput =
                document.getElementById(
                    'current_hm_km'
                );


            if (
                hm !== '' &&
                parseFloat(hm) > 0
            ) {

                currentInput.value = hm;

            }


            calculateMaintenance();

        }


        /* =========================================================
           CALCULATE MAINTENANCE
        ========================================================= */

        function calculateMaintenance() {

            const lastService =
                parseFloat(
                    document.getElementById(
                        'last_service'
                    ).value
                ) || 0;


            const current =
                parseFloat(
                    document.getElementById(
                        'current_hm_km'
                    ).value
                ) || 0;


            const interval =
                parseFloat(
                    document.getElementById(
                        'interval_pm'
                    ).value
                ) || 0;


            if (interval <= 0) {

                document.getElementById(
                    'next_service_display'
                ).innerText = '-';


                document.getElementById(
                    'remaining_display'
                ).innerText = '-';


                document.getElementById(
                    'status_display'
                ).innerText = 'Normal';


                document.getElementById(
                    'status_display'
                ).className =
                    'status-preview normal';


                return;
            }


            const nextService =
                lastService + interval;


            const remaining =
                nextService - current;


            document.getElementById(
                'next_service_display'
            ).innerText =
                formatNumber(nextService);


            document.getElementById(
                'remaining_display'
            ).innerText =
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
                document.getElementById(
                    'status_display'
                );


            statusElement.innerText =
                status;


            statusElement.className =
                'status-preview ' + statusClass;

        }


        /* =========================================================
           SPARE PART & LUBRICANT
        ========================================================= */

        let sparepartIndex = 0;


        function addSparepartItem() {

            const container =
                document.getElementById(
                    'sparepartContainer'
                );


            const empty =
                document.getElementById(
                    'sparepartEmpty'
                );


            if (empty) {
                empty.remove();
            }


            sparepartIndex++;


            const item =
                document.createElement('div');


            item.className =
                'sparepart-item';


            item.innerHTML = `

                <div class="sparepart-item-top">

                    <div class="sparepart-item-title">
                        Item ${sparepartIndex}
                    </div>


                    <button
                        type="button"
                        class="btn-remove-item"
                        onclick="removeSparepartItem(this)"
                    >
                        Hapus Item
                    </button>

                </div>


                <div class="sparepart-grid">


                    <div class="form-group">

                        <label>
                            Nama Item
                            <span class="required">*</span>
                        </label>


                        <input
                            type="text"
                            name="sparepart_nama[]"
                            placeholder="Contoh: Filter Oli Engine"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Kategori
                            <span class="required">*</span>
                        </label>


                        <select
                            name="sparepart_kategori[]"
                            required
                        >

                            <option value="Spare Part">
                                Spare Part
                            </option>

                            <option value="Lubricant">
                                Lubricant
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Jenis
                        </label>


                        <input
                            type="text"
                            name="sparepart_jenis[]"
                            placeholder="Contoh: Oli Mesin / Oli Hydraulic"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Spesifikasi
                        </label>


                        <input
                            type="text"
                            name="sparepart_spesifikasi[]"
                            placeholder="Contoh: SAE 15W-40"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Jumlah
                            <span class="required">*</span>
                        </label>


                        <input
                            type="number"
                            name="sparepart_jumlah[]"
                            value="1"
                            min="0.01"
                            step="0.01"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Satuan
                            <span class="required">*</span>
                        </label>


                        <input
                            type="text"
                            name="sparepart_satuan[]"
                            value="pcs"
                            placeholder="pcs / liter / set"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tanggal Order
                        </label>


                        <input
                            type="date"
                            name="sparepart_tanggal_order[]"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Perkiraan Tiba
                        </label>


                        <input
                            type="date"
                            name="sparepart_perkiraan_tiba[]"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tanggal Tiba Aktual
                        </label>


                        <input
                            type="date"
                            name="sparepart_tanggal_tiba[]"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Status
                            <span class="required">*</span>
                        </label>


                        <select
                            name="sparepart_status[]"
                            required
                        >

                            <option value="Menunggu Order">
                                Menunggu Order
                            </option>

                            <option value="Sudah Diorder">
                                Sudah Diorder
                            </option>

                            <option value="Dalam Pengiriman">
                                Dalam Pengiriman
                            </option>

                            <option value="Sudah Tiba">
                                Sudah Tiba
                            </option>

                        </select>

                    </div>


                    <div class="form-group full">

                        <label>
                            Keterangan
                        </label>


                        <textarea
                            name="sparepart_keterangan[]"
                            placeholder="Keterangan tambahan jika diperlukan"
                        ></textarea>

                    </div>


                </div>

            `;


            container.appendChild(item);

        }


        function removeSparepartItem(button) {

            const item =
                button.closest(
                    '.sparepart-item'
                );


            if (item) {
                item.remove();
            }


            const container =
                document.getElementById(
                    'sparepartContainer'
                );


            if (
                container &&
                container.querySelectorAll(
                    '.sparepart-item'
                ).length === 0
            ) {

                container.innerHTML = `

                    <div
                        class="sparepart-empty"
                        id="sparepartEmpty"
                    >

                        Belum ada kebutuhan spare part atau lubricant.

                        Klik
                        <strong>
                            + Tambah Item
                        </strong>
                        untuk menambahkan.

                    </div>

                `;

            }

        }


        /* =========================================================
           FORMAT ANGKA
        ========================================================= */

        function formatNumber(number) {

            return new Intl.NumberFormat(
                'id-ID',
                {
                    maximumFractionDigits: 0
                }
            ).format(number);

        }

    </script>

</body>

</html>