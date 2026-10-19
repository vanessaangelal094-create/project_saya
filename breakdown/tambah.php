<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
date_default_timezone_set('Asia/Makassar');

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$statuses = [
    'Dilaporkan',
    'Dalam Penanganan',
    'Menunggu Spare Part',
    'Selesai'
];

$equipment_list = [];
$errors = [];

$equipment_result = $conn->query("
    SELECT id, equipment_code, equipment_name, location
    FROM equipment
    ORDER BY equipment_code ASC
");

if ($equipment_result) {
    while ($row = $equipment_result->fetch_assoc()) {
        $equipment_list[] = $row;
    }
} else {
    $errors[] = 'Daftar equipment gagal dimuat. Periksa koneksi database.';
}

$equipment_id = '';
$tanggal_kejadian = date('Y-m-d\TH:i');
$hm = '';
$jenis_kerusakan = '';
$deskripsi_kerusakan = '';
$tindakan_perbaikan = '';
$pic_teknisi = '';
$status = 'Dilaporkan';
$tanggal_selesai = '';
$keterangan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_id = trim($_POST['equipment_id'] ?? '');
    $tanggal_kejadian = trim($_POST['tanggal_kejadian'] ?? '');
    $hm = trim($_POST['hm'] ?? '');
    $jenis_kerusakan = trim($_POST['jenis_kerusakan'] ?? '');
    $deskripsi_kerusakan = trim($_POST['deskripsi_kerusakan'] ?? '');
    $tindakan_perbaikan = trim($_POST['tindakan_perbaikan'] ?? '');
    $pic_teknisi = trim($_POST['pic_teknisi'] ?? '');
    $status = trim($_POST['status'] ?? 'Dilaporkan');
    $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($equipment_id === '' || !ctype_digit($equipment_id)) {
        $errors[] = 'Pilih unit alat berat terlebih dahulu.';
    } else {
        $check_equipment = $conn->prepare("
            SELECT id
            FROM equipment
            WHERE id = ?
            LIMIT 1
        ");

        if (!$check_equipment) {
            $errors[] = 'Pemeriksaan equipment gagal dilakukan.';
        } else {
            $equipment_id_int = (int)$equipment_id;
            $check_equipment->bind_param('i', $equipment_id_int);
            $check_equipment->execute();

            $equipment_exists =
                $check_equipment->get_result()->num_rows > 0;

            $check_equipment->close();

            if (!$equipment_exists) {
                $errors[] = 'Unit yang dipilih tidak ditemukan.';
            }
        }
    }

    $tanggal_kejadian_db = str_replace(
        'T',
        ' ',
        $tanggal_kejadian
    );

    if (
        $tanggal_kejadian === '' ||
        strtotime($tanggal_kejadian_db) === false
    ) {
        $errors[] = 'Tanggal dan waktu kejadian wajib diisi.';
    } elseif (strlen($tanggal_kejadian_db) === 16) {
        $tanggal_kejadian_db .= ':00';
    }

    $hm_value = null;

    if ($hm !== '') {
        if (!is_numeric($hm) || (float)$hm < 0) {
            $errors[] = 'HM harus berupa angka nol atau lebih.';
        } else {
            $hm_value = (float)$hm;
        }
    }

    if ($jenis_kerusakan === '') {
        $errors[] = 'Jenis kerusakan wajib diisi.';
    }

    if ($deskripsi_kerusakan === '') {
        $errors[] = 'Deskripsi kerusakan wajib diisi.';
    }

    if (!in_array($status, $statuses, true)) {
        $errors[] = 'Status laporan tidak valid.';
    }

    $tanggal_selesai_db = null;

    if ($tanggal_selesai !== '') {
        $tanggal_selesai_db = str_replace(
            'T',
            ' ',
            $tanggal_selesai
        );

        if (strtotime($tanggal_selesai_db) === false) {
            $errors[] = 'Tanggal selesai tidak valid.';
        } elseif (strlen($tanggal_selesai_db) === 16) {
            $tanggal_selesai_db .= ':00';
        }
    }

    if (
        $status === 'Selesai' &&
        $tanggal_selesai_db === null
    ) {
        $errors[] =
            'Isi tanggal selesai jika status laporan dipilih Selesai.';
    }

    if (empty($errors)) {
        $equipment_id_int = (int)$equipment_id;

        $insert = $conn->prepare("
            INSERT INTO breakdown_reports (
                equipment_id,
                tanggal_kejadian,
                hm_km,
                jenis_kerusakan,
                deskripsi_kerusakan,
                tindakan_perbaikan,
                pic_teknisi,
                tanggal_selesai,
                status,
                keterangan
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$insert) {
            $errors[] =
                'Form tidak dapat menyimpan data. Periksa struktur tabel breakdown_reports.';
        } else {
            $insert->bind_param(
                'isdsssssss',
                $equipment_id_int,
                $tanggal_kejadian_db,
                $hm_value,
                $jenis_kerusakan,
                $deskripsi_kerusakan,
                $tindakan_perbaikan,
                $pic_teknisi,
                $tanggal_selesai_db,
                $status,
                $keterangan
            );

            if ($insert->execute()) {
                $insert->close();
                header('Location: index.php?success=1');
                exit;
            }

            $errors[] =
                'Laporan gagal disimpan: ' . $insert->error;

            $insert->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Laporan Breakdown | Monitoring Alat Berat</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #253247;
            font-size: 14px;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background: #0759bd;
            color: #fff;
            padding: 25px 15px;
            overflow-y: auto;
            z-index: 10;
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
        }

        .menu li {
            margin-bottom: 7px;
        }

        .menu a {
            display: block;
            padding: 12px 14px;
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
        }

        .menu a:hover,
        .menu a.active {
            background: rgba(255, 255, 255, .18);
        }

        .main-content {
            margin-left: 240px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 25px;
            font-weight: 700;
            color: #172b4d;
            margin-bottom: 8px;
        }

        .page-description {
            color: #78869b;
            font-size: 13px;
            line-height: 1.6;
        }

        .panel {
            background: #fff;
            border: 1px solid #e6ebf2;
            border-radius: 12px;
            overflow: hidden;
        }

        .panel-header {
            padding: 20px 22px;
            border-bottom: 1px solid #edf0f5;
        }

        .panel-title {
            font-size: 16px;
            font-weight: 700;
            color: #172b4d;
        }

        .form-body {
            padding: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            color: #526176;
            font-size: 12px;
            font-weight: 600;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid #d9e0e9;
            border-radius: 7px;
            padding: 11px 12px;
            background: #fff;
            color: #253247;
            font: inherit;
            outline: none;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #0759bd;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .help {
            color: #8995a8;
            font-size: 11px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            padding: 11px 16px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #0759bd;
            border: 1px solid #0759bd;
            color: #fff;
        }

        .btn-primary:hover {
            background: #064a9c;
        }

        .btn-secondary {
            background: #fff;
            border: 1px solid #d9e0e9;
            color: #526176;
        }

        .alert {
            border-radius: 8px;
            padding: 13px 16px;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .alert-error {
            background: #fff0ef;
            border: 1px solid #f4c7c3;
            color: #a12820;
        }

        .required {
            color: #c62828;
        }

        @media (max-width: 850px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .page-header {
                flex-direction: column;
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
            <div class="logo-text">PT BIRINGKASSI RAYA</div>
        </div>

        <ul class="menu">
            <li>
                <a href="../index.php">Dashboard</a>
            </li>
            <li>
                <a href="../equipment/index.php">Equipment Master</a>
            </li>
            <li>
                <a href="../equipment/index.php">Status Unit</a>
            </li>
            <li>
                <a href="../maintenance/index.php">Jadwal Pemeliharaan</a>
            </li>
            <li>
                <a href="../maintenance/history/index.php">
                    Histori Pemeliharaan
                </a>
            </li>
            <li>
                <a href="index.php" class="active">
                    Breakdown &amp; Failure
                </a>
            </li>
            <li>
                <a href="../sparepart/index.php">Spare Part</a>
            </li>
            <li>
                <a href="../lubricant/index.php">Lubricant</a>
            </li>
            <li>
                <a href="../fuel/index.php">Fuel Performance</a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    Tambah Laporan Breakdown
                </h1>

                <p class="page-description">
                    Catat kejadian kerusakan atau gangguan pada alat berat.
                </p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <div>• <?= h($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Informasi Laporan</h2>
            </div>

            <div class="form-body">
                <?php if (empty($equipment_list)): ?>
                    <div class="alert alert-error">
                        Belum ada data equipment yang dapat dipilih.
                        Tambahkan data unit terlebih dahulu pada Equipment Master.
                    </div>
                <?php endif; ?>

                <form method="POST" action="tambah.php">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="equipment_id">
                                Unit Alat Berat
                                <span class="required">*</span>
                            </label>

                            <select
                                name="equipment_id"
                                id="equipment_id"
                                required
                            >
                                <option value="">
                                    Pilih unit alat berat
                                </option>

                                <?php foreach ($equipment_list as $equipment): ?>
                                    <option
                                        value="<?= (int)$equipment['id'] ?>"
                                        <?= (string)$equipment_id === (string)$equipment['id'] ? 'selected' : '' ?>
                                    >
                                        <?= h($equipment['equipment_code']) ?>
                                        — <?= h($equipment['equipment_name']) ?>
                                        <?= !empty($equipment['location'])
                                            ? ' (' . h($equipment['location']) . ')'
                                            : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tanggal_kejadian">
                                Tanggal dan Waktu Kejadian
                                <span class="required">*</span>
                            </label>

                            <input
                                type="datetime-local"
                                name="tanggal_kejadian"
                                id="tanggal_kejadian"
                                value="<?= h($tanggal_kejadian) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="hm">HM saat Kejadian</label>

                            <input
                                type="number"
                                name="hm"
                                id="hm"
                                min="0"
                                step="0.1"
                                value="<?= h($hm) ?>"
                                placeholder="Contoh: 51642"
                            >

                            <span class="help">
                                Isi pembacaan HM saat kejadian jika tersedia.
                            </span>
                        </div>

                        <div class="form-group full">
                            <label for="jenis_kerusakan">
                                Jenis Kerusakan / Gangguan
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                name="jenis_kerusakan"
                                id="jenis_kerusakan"
                                value="<?= h($jenis_kerusakan) ?>"
                                required
                                maxlength="255"
                                placeholder="Contoh: Kebocoran oli hidrolik"
                            >
                        </div>

                        <div class="form-group full">
                            <label for="deskripsi_kerusakan">
                                Deskripsi Kerusakan
                                <span class="required">*</span>
                            </label>

                            <textarea
                                name="deskripsi_kerusakan"
                                id="deskripsi_kerusakan"
                                required
                                placeholder="Jelaskan gejala atau kondisi yang dilaporkan."
                            ><?= h($deskripsi_kerusakan) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="pic_teknisi">PIC / Teknisi</label>

                            <input
                                type="text"
                                name="pic_teknisi"
                                id="pic_teknisi"
                                value="<?= h($pic_teknisi) ?>"
                                maxlength="150"
                                placeholder="Nama PIC atau teknisi"
                            >
                        </div>

                        <div class="form-group">
                            <label for="status">Status Penanganan</label>

                            <select name="status" id="status">
                                <?php foreach ($statuses as $status_option): ?>
                                    <option
                                        value="<?= h($status_option) ?>"
                                        <?= $status === $status_option ? 'selected' : '' ?>
                                    >
                                        <?= h($status_option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tindakan_perbaikan">
                                Tindakan Perbaikan
                            </label>

                            <textarea
                                name="tindakan_perbaikan"
                                id="tindakan_perbaikan"
                                placeholder="Isi jika tindakan perbaikan sudah dilakukan."
                            ><?= h($tindakan_perbaikan) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="tanggal_selesai">
                                Tanggal Selesai
                            </label>

                            <input
                                type="datetime-local"
                                name="tanggal_selesai"
                                id="tanggal_selesai"
                                value="<?= h($tanggal_selesai) ?>"
                            >

                            <span class="help">
                                Wajib diisi jika status dipilih Selesai.
                            </span>
                        </div>

                        <div class="form-group full">
                            <label for="keterangan">
                                Keterangan Tambahan
                            </label>

                            <textarea
                                name="keterangan"
                                id="keterangan"
                                placeholder="Catatan tambahan jika ada."
                            ><?= h($keterangan) ?></textarea>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="index.php" class="btn btn-secondary">
                            Batal
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= empty($equipment_list) ? 'disabled' : '' ?>
                        >
                            Simpan Laporan
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>