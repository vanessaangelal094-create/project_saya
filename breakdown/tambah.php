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

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>

        /* khusus halaman tambah laporan */

        .form-body {
            padding: 20px;
        }

        .form-body .form-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .help {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        .btn-primary[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 850px) {

            .form-body .form-grid {
                grid-template-columns: 1fr;
            }

            .form-body .form-group.full {
                grid-column: auto;
            }

        }

    </style>
</head>

<body>

    <?php
    $base   = '../';
    $active = 'breakdown';
    include $base . 'includes/sidebar.php';
    ?>

    <main class="main">

        <div class="breadcrumb">
            <a href="../index.php">Home</a> &nbsp;›&nbsp;
            <a href="index.php">Breakdown &amp; Failure</a> &nbsp;›&nbsp;
            <span>Tambah Laporan</span>
        </div>

        <div class="header">
            <div class="header-left">
                <h1>
                    Tambah Laporan Breakdown
                </h1>

                <p>
                    Catat kejadian kerusakan atau gangguan pada alat berat.
                </p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $error): ?>
                    <div>• <?= h($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h3>Informasi Laporan</h3>
            </div>

            <div class="form-body">
                <?php if (empty($equipment_list)): ?>
                    <div class="error-box">
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

                    <div class="form-actions">
                        <a href="index.php" class="btn-cancel">
                            Batal
                        </a>

                        <button
                            type="submit"
                            class="btn-primary"
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