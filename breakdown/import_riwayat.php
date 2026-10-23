
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
$success = false;
$report_id = 0;
$already_exists = false;

/*
 * Unit yang akan digunakan:
 * Equipment ID 10, kode SM-02.
 */
$equipment_id = 10;

$jenis_kerusakan =
    'Korsleting listrik (short circuit) pada kabel panel control';

/*
 * Empat belas catatan berdasarkan Excel.
 * Tanggal/periode disimpan sebagai teks agar rentang tanggal tetap utuh.
 */
$history_data = [
    [
        '03 April 2023',
        'Mulai rusak dengan diagnosis kerusakan korsleting listrik (short circuit) pada kabel panel control.',
        'Lamp. 1'
    ],
    [
        '30 Juli 2024',
        'Mulai dilakukan pemeriksaan jaringan kelistrikan dan perbaikan dengan mengukur jaringan kelistrikan pada alat oleh Kanit Workshop dan Tim Mekanik.',
        'Lamp. 2'
    ],
    [
        'Agustus s/d November 2024',
        'Proses perbaikan (service) dan inventarisasi kebutuhan sparepart alat.',
        ''
    ],
    [
        '05 Desember 2024',
        'Kanit Workshop membuat DPPB sparepart yang dibutuhkan dengan Nomor PR-HO-24000347.',
        'Lamp. 3'
    ],
    [
        '12 Februari 2025',
        'Penerbitan dan penunjukan Purchase Order kepada PT ALTRAK 1978 dengan PO-HO-25000025 untuk DPPB PR-HO-24000347.',
        'Lamp. 4'
    ],
    [
        '20 Maret 2025',
        'Laporan Penerimaan Barang sesuai Permintaan DPPB.',
        'Lamp. 5'
    ],
    [
        'April s/d September 2025',
        'Proses perbaikan dan pemasangan sparepart yang telah dipesan. Setelah dilakukan pemasangan sparepart oleh Tim Mekanik Workshop, kondisi Backhoe Loader Case 580 SM 02 dalam kondisi dengan status baik 60%, dan selanjutnya perlu perbaikan lebih mendalam karena terindikasi tidak ada respon dari ECU untuk power.',
        'Lamp. 6'
    ],
    [
        '20 September 2025',
        'Kanit Workshop membuat SP3J PR. HO. 25000443 untuk repair electrical pada Backhoe Loader Case 580 SM 02.',
        'Lamp. 7'
    ],
    [
        'Oktober 2025',
        'Proses penawaran, negosiasi dan penentuan vendor SP3J Nomor WO-HO 25000443.',
        ''
    ],
    [
        '04 November 2025',
        'Penerbitan dan penunjukan Work Order kepada Koperasi Konsumen Karyawan Bahari untuk SP3J Nomor WO-HO 25000443.',
        'Lamp. 8'
    ],
    [
        'November 2025 s/d April 2026',
        'Mulai perbaikan Backhoe Loader Case 580 SM 02 sesuai SP3J oleh Vendor Koperasi Konsumen Bahari.',
        ''
    ],
    [
        'Mei 2026',
        'Selesai perbaikan electrical Backhoe Loader Case SM 02 oleh vendor Koperasi Konsumen Karyawan Bahari.',
        ''
    ],
    [
        'Juni s/d September 2026',
        'Identifikasi sparepart tambahan (non electrical) yang dibutuhkan.',
        ''
    ],
    [
        '15 Oktober 2026',
        'Pembuatan DPPB kekurangan sparepart (Non electrical) Backhoe Loader Case 580 SM 02 dengan Nomor DPPB PR-HO-26000394.',
        ''
    ]
];

/* Proses hanya berjalan setelah tombol ditekan */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $conn->begin_transaction();

    try {
        /*
         * Pastikan unit ID 10 benar-benar SM-02.
         */
        $unit_stmt = $conn->prepare("
            SELECT id, equipment_code, equipment_name, spesifikasi
            FROM equipment
            WHERE id = ?
            LIMIT 1
        ");

        if (!$unit_stmt) {
            throw new Exception('Gagal memeriksa data unit.');
        }

        $unit_stmt->bind_param('i', $equipment_id);
        $unit_stmt->execute();
        $unit = $unit_stmt->get_result()->fetch_assoc();
        $unit_stmt->close();

        if (
            !$unit ||
            $unit['equipment_code'] !== 'SM-02' ||
            $unit['equipment_name'] !== 'Backhoe Loader'
        ) {
            throw new Exception(
                'Unit ID 10 tidak sesuai dengan Backhoe Loader SM-02. Data tidak disimpan.'
            );
        }

        /*
         * Periksa apakah laporan utama yang sama sudah ada.
         */
        $check_stmt = $conn->prepare("
            SELECT id
            FROM breakdown_reports
            WHERE equipment_id = ?
              AND tanggal_kejadian >= '2023-04-03 00:00:00'
              AND tanggal_kejadian < '2023-04-04 00:00:00'
              AND jenis_kerusakan = ?
            LIMIT 1
        ");

        if (!$check_stmt) {
            throw new Exception('Gagal memeriksa laporan yang sudah ada.');
        }

        $check_stmt->bind_param(
            'is',
            $equipment_id,
            $jenis_kerusakan
        );

        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
            $report_id = (int)$existing['id'];

            $history_check = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM breakdown_history
                WHERE breakdown_id = ?
            ");

            if (!$history_check) {
                throw new Exception('Gagal memeriksa riwayat yang sudah ada.');
            }

            $history_check->bind_param('i', $report_id);
            $history_check->execute();

            $history_count = (int)(
                $history_check->get_result()->fetch_assoc()['total'] ?? 0
            );

            $history_check->close();

            if ($history_count > 0) {
                throw new Exception(
                    'Laporan ini sudah memiliki riwayat. Import dibatalkan agar tidak terjadi data ganda.'
                );
            }

            /*
             * Laporan ada tetapi riwayat masih kosong.
             * Gunakan laporan tersebut, jangan buat laporan baru.
             */
            $already_exists = true;

        } else {

            /*
             * Buat satu laporan utama.
             * HM dan tanggal selesai dikosongkan karena tidak tersedia
             * dan penanganan keseluruhan belum dinyatakan selesai.
             */
            $tanggal_kejadian = '2023-04-03 00:00:00';
            $hm = null;
            $tindakan = 'Perbaikan elektrikal dinyatakan selesai pada Mei 2026, tetapi penanganan keseluruhan masih memiliki kekurangan spare part non-electrical.';
            $pic = null;
            $tanggal_selesai = null;
            $status = 'Menunggu Spare Part';
            $keterangan = 'Catatan terakhir 15 Oktober 2026: DPPB kekurangan spare part non-electrical Nomor PR-HO-26000394. Penanganan keseluruhan belum dinyatakan selesai.';

            $insert_report = $conn->prepare("
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$insert_report) {
                throw new Exception('Gagal menyiapkan laporan utama.');
            }

            $deskripsi =
                'Pada 3 April 2023, Backhoe Loader Case 580 SM 02 mengalami kerusakan dengan diagnosis korsleting listrik (short circuit) pada kabel panel control.';

            $insert_report->bind_param(
                'isdsssssss',
                $equipment_id,
                $tanggal_kejadian,
                $hm,
                $jenis_kerusakan,
                $deskripsi,
                $tindakan,
                $pic,
                $tanggal_selesai,
                $status,
                $keterangan
            );

            if (!$insert_report->execute()) {
                throw new Exception('Gagal menyimpan laporan utama.');
            }

            $report_id = (int)$conn->insert_id;
            $insert_report->close();
        }

        /*
         * Masukkan empat belas catatan kronologi.
         */
        $insert_history = $conn->prepare("
            INSERT INTO breakdown_history (
                breakdown_id,
                urutan,
                tanggal_riwayat,
                uraian,
                lampiran
            ) VALUES (?, ?, ?, ?, ?)
        ");

        if (!$insert_history) {
            throw new Exception('Gagal menyiapkan penyimpanan riwayat.');
        }

        foreach ($history_data as $index => $item) {
            $urutan = $index + 1;
            $tanggal = $item[0];
            $uraian = $item[1];
            $lampiran = $item[2];

            $insert_history->bind_param(
                'iisss',
                $report_id,
                $urutan,
                $tanggal,
                $uraian,
                $lampiran
            );

            if (!$insert_history->execute()) {
                throw new Exception(
                    'Gagal menyimpan riwayat nomor ' . $urutan . '.'
                );
            }
        }

        $insert_history->close();
        $conn->commit();
        $success = true;

    } catch (Throwable $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Riwayat Breakdown</title>
    <link rel="stylesheet" href="../assets/css/app.css">

    <style>
        .import-page {
            padding-bottom: 30px;
        }

        .import-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .import-card h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .import-note {
            color: #64748b;
            line-height: 1.7;
        }

        .import-success {
            padding: 16px;
            margin-bottom: 20px;
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #86efac;
            border-radius: 8px;
        }

        .import-error {
            padding: 16px;
            margin-bottom: 20px;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-radius: 8px;
        }

        .import-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .import-table-wrap {
            overflow-x: auto;
        }

        .import-table {
            width: 100%;
            border-collapse: collapse;
        }

        .import-table th,
        .import-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        .import-table th {
            background: #f8fafc;
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
    <div class="breadcrumb">
        <a href="../index.php">Home</a> &nbsp;›&nbsp;
        <a href="index.php">Breakdown &amp; Failure</a> &nbsp;›&nbsp;
        <span>Import Riwayat</span>
    </div>

    <div class="header">
        <div class="header-left">
            <h1>Import Riwayat Breakdown</h1>
            <p>Memasukkan satu laporan dan 14 catatan kronologi Backhoe Loader SM-02.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="import-success">
            <strong>Import berhasil!</strong><br>
            Satu laporan utama dan 14 catatan kronologi sudah disimpan.<br>
            Status: Menunggu Spare Part.<br>
            Tanggal selesai: belum diisi.
            <div class="import-actions">
                <a class="btn-primary" href="detail.php?id=<?= (int)$report_id ?>">
                    Lihat Detail Breakdown
                </a>
                <a class="btn-cancel" href="index.php">
                    Kembali ke Breakdown
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="import-error">
            <strong>Import belum berhasil.</strong>
            <?php foreach ($errors as $error): ?>
                <div>• <?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
        <section class="import-card">
            <h2>Data yang akan dimasukkan</h2>

            <p class="import-note">
                <strong>Unit:</strong> Backhoe Loader SM-02<br>
                <strong>Tanggal kejadian:</strong> 03 April 2023<br>
                <strong>Jumlah kronologi:</strong> 14 catatan<br>
                <strong>Status:</strong> Menunggu Spare Part<br>
                <strong>Tanggal selesai:</strong> Kosong
            </p>

            <p class="import-note">
                Sistem akan memeriksa laporan yang sama sebelum menyimpan.
                Jika laporan sudah memiliki riwayat, import akan dibatalkan
                untuk mencegah duplikasi.
            </p>

            <div class="import-actions">
                <form method="POST" action="import_riwayat.php"
                      onsubmit="return confirm('Yakin ingin memasukkan satu laporan dan 14 catatan kronologi ke database?');">
                    <button type="submit" class="btn-primary">
                        Import 1 Laporan + 14 Riwayat
                    </button>
                </form>

                <a href="index.php" class="btn-cancel">
                    Batal
                </a>
            </div>
        </section>

        <section class="import-card">
            <h2>Pratinjau kronologi</h2>

            <div class="import-table-wrap">
                <table class="import-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tanggal / Periode</th>
                            <th>Uraian</th>
                            <th>Lampiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_data as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= h($item[0]) ?></td>
                                <td><?= h($item[1]) ?></td>
                                <td><?= h($item[2] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

</body>
</html>
