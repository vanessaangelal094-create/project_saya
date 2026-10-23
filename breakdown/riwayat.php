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

$errors = [];
$selected_id = 0;
$report = null;
$history = [];
$form_rows = [];

/* Menentukan laporan yang sedang dipilih */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_id = filter_input(
        INPUT_POST,
        'breakdown_id',
        FILTER_VALIDATE_INT
    ) ?: 0;
} else {
    $selected_id = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    ) ?: 0;
}

/* Mengambil daftar laporan breakdown */
$reports = [];

$report_result = $conn->query("
    SELECT
        b.id,
        b.tanggal_kejadian,
        b.jenis_kerusakan,
        b.status,
        e.equipment_code,
        e.equipment_name,
        e.location
    FROM breakdown_reports b
    LEFT JOIN equipment e ON b.equipment_id = e.id
    ORDER BY b.id DESC
");

if ($report_result) {
    while ($row = $report_result->fetch_assoc()) {
        $reports[] = $row;
    }
} else {
    $errors[] = 'Daftar laporan gagal dimuat. Periksa database.';
}

/* Mengambil laporan yang sedang dipilih */
if ($selected_id > 0) {
    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.tanggal_kejadian,
            b.jenis_kerusakan,
            b.status,
            e.equipment_code,
            e.equipment_name,
            e.location
        FROM breakdown_reports b
        LEFT JOIN equipment e ON b.equipment_id = e.id
        WHERE b.id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $selected_id);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$report) {
        $errors[] = 'Laporan tidak ditemukan. Silakan pilih laporan yang tersedia.';
        $selected_id = 0;
    }
}

/* Proses penyimpanan catatan penanganan */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dates = $_POST['tanggal_riwayat'] ?? [];
    $descriptions = $_POST['uraian'] ?? [];
    $attachments = $_POST['lampiran'] ?? [];

    if (!is_array($dates)) {
        $dates = [];
    }

    if (!is_array($descriptions)) {
        $descriptions = [];
    }

    if (!is_array($attachments)) {
        $attachments = [];
    }

    $row_count = max(
        count($dates),
        count($descriptions),
        count($attachments)
    );

    $valid_rows = [];

    for ($i = 0; $i < $row_count; $i++) {

        $date = trim((string)($dates[$i] ?? ''));
        $description = trim((string)($descriptions[$i] ?? ''));
        $attachment = trim((string)($attachments[$i] ?? ''));

        /* Lewati baris yang benar-benar kosong */
        if (
            $date === '' &&
            $description === '' &&
            $attachment === ''
        ) {
            continue;
        }

        if ($date === '' || $description === '') {
            $errors[] = 'Setiap catatan yang diisi harus memiliki tanggal/periode dan uraian kegiatan.';
            continue;
        }

        $valid_rows[] = [
            'tanggal' => $date,
            'uraian' => $description,
            'lampiran' => $attachment
        ];
    }

    if ($selected_id < 1) {
        $errors[] = 'Pilih laporan breakdown terlebih dahulu.';
    }

    if (empty($valid_rows)) {
        $errors[] = 'Masukkan minimal satu catatan penanganan.';
    }

    /* Memastikan laporan tersedia */
    if ($selected_id > 0) {

        $check = $conn->prepare("
            SELECT id
            FROM breakdown_reports
            WHERE id = ?
            LIMIT 1
        ");

        if (!$check) {
            $errors[] = 'Laporan tidak dapat diperiksa.';
        } else {

            $check->bind_param("i", $selected_id);
            $check->execute();

            $exists = $check->get_result()->num_rows > 0;

            $check->close();

            if (!$exists) {
                $errors[] = 'Laporan yang dipilih tidak ditemukan.';
            }
        }
    }

    /* Simpan catatan dengan nomor urut berikutnya */
    if (empty($errors)) {

        $max_stmt = $conn->prepare("
            SELECT COALESCE(MAX(urutan), 0) AS urutan_terakhir
            FROM breakdown_history
            WHERE breakdown_id = ?
        ");

        if (!$max_stmt) {

            $errors[] = 'Nomor urut catatan gagal diperiksa.';

        } else {

            $max_stmt->bind_param("i", $selected_id);
            $max_stmt->execute();

            $max_result = $max_stmt->get_result()->fetch_assoc();

            $urutan = (int)($max_result['urutan_terakhir'] ?? 0);

            $max_stmt->close();

            $conn->begin_transaction();

            $insert = $conn->prepare("
                INSERT INTO breakdown_history (
                    breakdown_id,
                    urutan,
                    tanggal_riwayat,
                    uraian,
                    lampiran
                ) VALUES (?, ?, ?, ?, ?)
            ");

            if (!$insert) {

                $conn->rollback();

                $errors[] = 'Form penyimpanan gagal disiapkan. Periksa tabel breakdown_history.';

            } else {

                $save_ok = true;

                foreach ($valid_rows as $item) {

                    $urutan++;

                    $tanggal = $item['tanggal'];
                    $uraian = $item['uraian'];
                    $lampiran = $item['lampiran'];

                    $insert->bind_param(
                        "iisss",
                        $selected_id,
                        $urutan,
                        $tanggal,
                        $uraian,
                        $lampiran
                    );

                    if (!$insert->execute()) {
                        $save_ok = false;
                        break;
                    }
                }

                $insert->close();

                if ($save_ok) {

                    $conn->commit();

                    header(
                        "Location: index.php?view=detail&id=" .
                        $selected_id . "&saved=1"
                    );

                    exit;

                } else {

                    $conn->rollback();

                    $errors[] = 'Catatan gagal disimpan: ' . $conn->error;
                }
            }
        }
    }

    /* Mempertahankan isi formulir ketika terjadi kesalahan */
    for ($i = 0; $i < $row_count; $i++) {

        $form_rows[] = [
            'tanggal' => (string)($dates[$i] ?? ''),
            'uraian' => (string)($descriptions[$i] ?? ''),
            'lampiran' => (string)($attachments[$i] ?? '')
        ];
    }
}

/* Sediakan satu formulir kosong saat pertama dibuka */
if (empty($form_rows)) {
    $form_rows[] = [
        'tanggal' => '',
        'uraian' => '',
        'lampiran' => ''
    ];
}

/* Mengambil riwayat yang sudah tersimpan */
if ($selected_id > 0) {

    $history_stmt = $conn->prepare("
        SELECT id, urutan, tanggal_riwayat, uraian, lampiran
        FROM breakdown_history
        WHERE breakdown_id = ?
        ORDER BY urutan ASC, id ASC
    ");

    if ($history_stmt) {

        $history_stmt->bind_param("i", $selected_id);
        $history_stmt->execute();

        $history_result = $history_stmt->get_result();

        while ($row = $history_result->fetch_assoc()) {
            $history[] = $row;
        }

        $history_stmt->close();
    }
}

/* Warna status laporan */
$status = $report['status'] ?? '-';
$status_class = 'status-default';

if ($status === 'Menunggu Spare Part') {
    $status_class = 'status-waiting';
} elseif ($status === 'Dalam Penanganan') {
    $status_class = 'status-progress';
} elseif ($status === 'Selesai') {
    $status_class = 'status-done';
} elseif ($status === 'Dilaporkan') {
    $status_class = 'status-reported';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Catatan Penanganan | Monitoring Alat Berat</title>

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>
        .add-history-page {
            max-width: 1450px;
            margin: 0 auto;
            padding-bottom: 36px;
            color: #1e293b;
        }

        .ah-breadcrumb {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            align-items: center;
            margin: 0 0 20px;
            color: #94a3b8;
            font-size: 12px;
        }

        .ah-breadcrumb a {
            color: #64748b;
            text-decoration: none;
        }

        .ah-breadcrumb a:hover {
            color: #6045db;
        }

        .ah-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .ah-title {
            margin: 0 0 7px;
            color: #172554;
            font-size: 26px;
            font-weight: 750;
            line-height: 1.3;
        }

        .ah-subtitle {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.75;
        }

        .ah-header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ah-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 14px;
            border: 1px solid #dbe3ed;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .ah-btn:hover {
            background: #f8fafc;
        }

        .ah-btn-primary {
            border-color: #6045db;
            background: #6045db;
            color: #fff;
        }

        .ah-btn-primary:hover {
            background: #5035c4;
            color: #fff;
        }

        .ah-btn-soft {
            background: #f8fafc;
        }

        .ah-card {
            margin-bottom: 19px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .025);
        }

        .ah-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            padding: 18px 21px;
            border-bottom: 1px solid #e8edf4;
        }

        .ah-card-title {
            margin: 0;
            color: #1e293b;
            font-size: 16px;
            font-weight: 750;
        }

        .ah-card-desc {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        }

        .ah-card-body {
            padding: 21px;
        }

        .ah-report-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .ah-report-field {
            min-width: 0;
        }

        .ah-label {
            display: block;
            margin-bottom: 7px;
            color: #64748b;
            font-size: 11px;
            font-weight: 750;
        }

        .ah-value {
            color: #1e293b;
            font-size: 13px;
            font-weight: 650;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .ah-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            width: max-content;
            max-width: 100%;
            padding: 5px 9px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 750;
        }

        .ah-status::before {
            content: "";
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-waiting {
            background: #fff3df;
            color: #a64b08;
        }

        .status-progress {
            background: #e8f1ff;
            color: #1d4ed8;
        }

        .status-done {
            background: #e3f8eb;
            color: #167344;
        }

        .status-reported {
            background: #fef3c7;
            color: #92400e;
        }

        .status-default {
            background: #edf2f7;
            color: #475569;
        }

        .ah-alert {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 17px;
            font-size: 13px;
            line-height: 1.7;
        }

        .ah-alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .ah-alert-note {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .ah-select-wrap {
            max-width: 700px;
        }

        .ah-select-wrap select,
        .ah-entry input,
        .ah-entry textarea {
            width: 100%;
            border: 1px solid #d6deea;
            border-radius: 8px;
            padding: 11px 12px;
            color: #1e293b;
            background: #fff;
            font: inherit;
            font-size: 13px;
            outline: none;
        }

        .ah-select-wrap select:focus,
        .ah-entry input:focus,
        .ah-entry textarea:focus {
            border-color: #8b7af0;
            box-shadow: 0 0 0 3px rgba(96, 69, 219, .10);
        }

        .ah-entry {
            padding: 18px;
            margin-bottom: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fcfdff;
        }

        .ah-entry-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .ah-entry-name {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 750;
        }

        .ah-entry-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #efedff;
            color: #6045db;
            font-size: 12px;
            font-weight: 800;
        }

        .ah-entry-grid {
            display: grid;
            grid-template-columns: minmax(220px, .8fr) minmax(260px, 1.8fr);
            gap: 15px;
        }

        .ah-form-group {
            min-width: 0;
            margin-bottom: 14px;
        }

        .ah-form-group label {
            display: block;
            margin-bottom: 7px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .ah-entry textarea {
            min-height: 115px;
            resize: vertical;
            line-height: 1.7;
        }

        .ah-help {
            display: block;
            margin-top: 6px;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.6;
        }

        .ah-remove {
            padding: 7px 10px;
            border: 1px solid #fecaca;
            border-radius: 7px;
            background: #fff7f7;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .ah-form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 19px;
            padding-top: 17px;
            border-top: 1px solid #e8edf4;
        }

        .ah-footer-left {
            display: flex;
            gap: 9px;
            flex-wrap: wrap;
        }

        .ah-history-wrap {
            overflow-x: auto;
        }

        .ah-history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ah-history-table th,
        .ah-history-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e8edf4;
            text-align: left;
            vertical-align: top;
            font-size: 12px;
            line-height: 1.7;
        }

        .ah-history-table th {
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            white-space: nowrap;
        }

        .ah-history-table td {
            color: #334155;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .ah-history-table tr:last-child td {
            border-bottom: 0;
        }

        .ah-empty {
            padding: 19px;
            border: 1px dashed #cbd5e1;
            border-radius: 9px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.8;
            background: #fafcff;
        }

        .ah-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 25px;
            height: 24px;
            padding: 0 7px;
            margin-left: 5px;
            border-radius: 6px;
            background: #efedff;
            color: #6045db;
            font-size: 11px;
            font-weight: 800;
        }

        @media (max-width: 1000px) {
            .ah-report-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .ah-title {
                font-size: 22px;
            }

            .ah-card-body {
                padding: 16px;
            }

            .ah-report-grid,
            .ah-entry-grid {
                grid-template-columns: 1fr;
            }

            .ah-header-actions {
                width: 100%;
            }

            .ah-header-actions .ah-btn {
                flex: 1;
            }

            .ah-entry {
                padding: 14px;
            }

            .ah-form-footer {
                align-items: stretch;
            }

            .ah-footer-left {
                width: 100%;
            }

            .ah-form-footer > button,
            .ah-footer-left .ah-btn {
                flex: 1;
            }
        }
    </style>
</head>

<body>

<?php
$base = '../';
$active = 'breakdown';
include $base . 'includes/sidebar.php';
?>

<main class="main">
<div class="add-history-page">

    <div class="ah-breadcrumb">
        <a href="../index.php">Home</a>
        <span>›</span>
        <a href="index.php">Breakdown &amp; Failure</a>
        <span>›</span>
        <span>Tambah Catatan Penanganan</span>
    </div>

    <div class="ah-header">
        <div>
            <h1 class="ah-title">Tambah Catatan Penanganan</h1>
            <p class="ah-subtitle">
                Tambahkan aktivitas pemeriksaan, perbaikan, atau tindak lanjut
                pada laporan breakdown yang sama.
            </p>
        </div>

        <div class="ah-header-actions">
            <?php if ($selected_id > 0): ?>
                <a
                    class="ah-btn"
                    href="index.php?view=detail&amp;id=<?= (int)$selected_id ?>"
                >
                    ← Kembali ke Detail
                </a>
            <?php else: ?>
                <a class="ah-btn" href="index.php">
                    ← Kembali ke Daftar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="ah-alert ah-alert-error">
            <?php foreach (array_unique($errors) as $error): ?>
                <div>• <?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$report): ?>

        <section class="ah-card">
            <div class="ah-card-header">
                <div>
                    <h2 class="ah-card-title">Pilih Laporan Breakdown</h2>
                    <p class="ah-card-desc">
                        Pilih laporan yang akan ditambahkan catatan penanganannya.
                    </p>
                </div>
            </div>

            <div class="ah-card-body">
                <?php if (empty($reports)): ?>

                    <div class="ah-empty">
                        Belum ada laporan breakdown.
                        Buat laporan utama terlebih dahulu.
                    </div>

                <?php else: ?>

                    <form method="GET" action="index.php" class="ah-select-wrap">

                        <input type="hidden" name="view" value="riwayat">

                        <div class="ah-form-group">
                            <label for="report_id">
                                Laporan / Unit Alat Berat
                            </label>

                            <select name="id" id="report_id" required>
                                <option value="">Pilih laporan</option>

                                <?php foreach ($reports as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>">
                                        BR-<?= str_pad(
                                            (string)$item['id'],
                                            4,
                                            '0',
                                            STR_PAD_LEFT
                                        ) ?>
                                        —
                                        <?= h($item['equipment_code'] ?? '-') ?>
                                        <?= h($item['equipment_name'] ?? '') ?>
                                        —
                                        <?= h($item['jenis_kerusakan'] ?? '-') ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <button type="submit" class="ah-btn ah-btn-primary">
                            Buka Laporan
                        </button>

                    </form>

                <?php endif; ?>
            </div>
        </section>

    <?php else: ?>

        <!-- Informasi laporan yang akan ditambahkan catatan -->
        <section class="ah-card">

            <div class="ah-card-header">
                <div>
                    <h2 class="ah-card-title">Informasi Laporan</h2>
                    <p class="ah-card-desc">
                        Catatan baru akan disimpan pada laporan breakdown berikut.
                    </p>
                </div>

                <span class="ah-status <?= h($status_class) ?>">
                    <?= h($status) ?>
                </span>
            </div>

            <div class="ah-card-body">

                <div class="ah-report-grid">

                    <div class="ah-report-field">
                        <span class="ah-label">Nomor Laporan</span>
                        <div class="ah-value">
                            BR-<?= str_pad(
                                (string)$report['id'],
                                4,
                                '0',
                                STR_PAD_LEFT
                            ) ?>
                        </div>
                    </div>

                    <div class="ah-report-field">
                        <span class="ah-label">ID Unit</span>
                        <div class="ah-value">
                            <?= h($report['equipment_code'] ?? '-') ?>
                        </div>
                    </div>

                    <div class="ah-report-field">
                        <span class="ah-label">Nama Alat Berat</span>
                        <div class="ah-value">
                            <?= h($report['equipment_name'] ?? '-') ?>
                        </div>
                    </div>

                    <div class="ah-report-field">
                        <span class="ah-label">Lokasi</span>
                        <div class="ah-value">
                            <?= h($report['location'] ?? '-') ?>
                        </div>
                    </div>

                    <div class="ah-report-field" style="grid-column:1/-1;">
                        <span class="ah-label">Jenis Kerusakan</span>
                        <div class="ah-value">
                            <?= h($report['jenis_kerusakan'] ?? '-') ?>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <!-- Form untuk mencatat kegiatan berikutnya -->
        <section class="ah-card">

            <div class="ah-card-header">
                <div>
                    <h2 class="ah-card-title">Form Catatan Penanganan</h2>
                    <p class="ah-card-desc">
                        Isi tanggal kegiatan dan uraian pekerjaan.
                        Lampiran bersifat opsional.
                    </p>
                </div>
            </div>

            <div class="ah-card-body">

                <div class="ah-alert ah-alert-note">
                    Tambahkan catatan baru untuk aktivitas berikutnya.
                    Catatan yang sudah tersimpan tidak akan ditimpa.
                </div>

                <form
                    method="POST"
                    action="index.php?view=riwayat&amp;id=<?= (int)$selected_id ?>"
                    id="history-form"
                >

                    <input
                        type="hidden"
                        name="breakdown_id"
                        value="<?= (int)$selected_id ?>"
                    >

                    <div id="history-entries">

                        <?php foreach ($form_rows as $index => $item): ?>

                            <div class="ah-entry">

                                <div class="ah-entry-head">
                                    <div class="ah-entry-name">
                                        <span class="ah-entry-number">
                                            <?= $index + 1 ?>
                                        </span>
                                        <span>Catatan Penanganan</span>
                                    </div>

                                    <button
                                        type="button"
                                        class="ah-remove"
                                        onclick="removeEntry(this)"
                                    >
                                        Hapus Baris
                                    </button>
                                </div>

                                <div class="ah-entry-grid">

                                    <div class="ah-form-group">
                                        <label>
                                            Tanggal / Periode Kegiatan
                                            <span style="color:#dc2626;">*</span>
                                        </label>

                                        <input
                                            type="text"
                                            name="tanggal_riwayat[]