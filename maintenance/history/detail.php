<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

/* =========================================================
   AMBIL ID HISTORY
========================================================= */

$history_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($history_id <= 0) {
    header("Location: index.php");
    exit;
}

/* =========================================================
   AMBIL DATA HISTORY
========================================================= */

$stmt = $conn->prepare("
    SELECT
        h.id,
        h.maintenance_id,
        h.equipment_id,
        h.tanggal_selesai,
        h.hm_saat_maintenance,
        h.next_hm,
        h.jenis_maintenance,
        h.pekerjaan,
        h.pic_teknisi,

        e.equipment_code,
        e.equipment_name,
        e.equipment_type,
        e.brand_model,
        e.serial_number,
        e.year,
        e.capacity,
        e.location,
        e.hm_current,
        e.status

    FROM maintenance_history h
    INNER JOIN equipment e ON h.equipment_id = e.id
    WHERE h.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $history_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header("Location: index.php");
    exit;
}

$history = $result->fetch_assoc();
$stmt->close();

/* =========================================================
   FORMAT DATA
========================================================= */

$tanggalSelesai = '-';

if (!empty($history['tanggal_selesai']) && $history['tanggal_selesai'] !== '0000-00-00') {
    $tanggalSelesai = date('d/m/Y', strtotime($history['tanggal_selesai']));
}

$hmMaintenance = number_format((float) $history['hm_saat_maintenance'], 0, ',', '.');
$nextHM        = number_format((float) $history['next_hm'], 0, ',', '.');
$hmCurrent     = number_format((float) $history['hm_current'], 0, ',', '.');

$jenisClass = 'badge';

if ($history['jenis_maintenance'] === 'Preventive') {
    $jenisClass = 'badge badge-blue';
} elseif ($history['jenis_maintenance'] === 'Corrective') {
    $jenisClass = 'badge badge-orange';
}

$statusClass = 'badge';

switch ($history['status']) {
    case 'Running':     $statusClass = 'badge badge-green';  break;
    case 'Standby':     $statusClass = 'badge badge-yellow'; break;
    case 'Maintenance': $statusClass = 'badge badge-orange'; break;
    case 'Breakdown':   $statusClass = 'badge badge-red';    break;
}

/* helper: tampilkan nilai atau "-" */
function val($v)
{
    return !empty($v) ? htmlspecialchars($v) : '-';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Histori Pemeliharaan - PT Biringkassi Raya</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
</head>

<body>

    <?php
    $base   = '../../';
    $active = 'history'; // sesuaikan dengan key menu di includes/sidebar.php
    include $base . 'includes/sidebar.php';
    ?>

    <main class="main">

        <!-- HEADER -->
        <div class="header">

            <div class="header-left">
                <div class="breadcrumb">
                    <a href="index.php">Histori Pemeliharaan</a> &nbsp;›&nbsp; <span>Detail Histori</span>
                </div>
                <h1>Detail Histori Pemeliharaan</h1>
                <p>Informasi lengkap pemeliharaan equipment yang telah selesai dilakukan.</p>
            </div>

            <div class="header-right">
                <a href="index.php" class="back-btn" title="Kembali">←</a>
            </div>

        </div>

        <!-- EQUIPMENT HEADER -->
        <div class="card equipment-head">

            <div class="title-area">
                <div class="kpi-icon icon-blue" style="width:56px; height:56px; font-size:26px;">⚙️</div>

                <div>
                    <div style="font-size:20px; font-weight:700; letter-spacing:-0.4px;">
                        <?= htmlspecialchars($history['equipment_name']); ?>
                    </div>
                    <div style="font-size:13px; font-weight:600; color:var(--primary);">
                        <?= htmlspecialchars($history['equipment_code']); ?>
                    </div>
                    <div style="font-size:12px; color:var(--muted);">
                        <?= htmlspecialchars($history['equipment_type'] ?? '-'); ?>
                    </div>
                </div>
            </div>

            <span class="badge badge-green">✓ Maintenance Selesai</span>

        </div>

        <!-- INFORMASI EQUIPMENT + MAINTENANCE -->
        <section class="dashboard-grid equal">

            <div class="box">
                <div class="mini-title">🚜 Informasi Equipment</div>

                <div class="detail-grid" style="grid-template-columns:1fr;">
                    <div class="detail-item"><span class="label">ID Unit</span><span class="separator">:</span><span class="value"><?= val($history['equipment_code']); ?></span></div>
                    <div class="detail-item"><span class="label">Nama Equipment</span><span class="separator">:</span><span class="value"><?= val($history['equipment_name']); ?></span></div>
                    <div class="detail-item"><span class="label">Jenis Alat</span><span class="separator">:</span><span class="value"><?= val($history['equipment_type']); ?></span></div>
                    <div class="detail-item"><span class="label">Merek / Model</span><span class="separator">:</span><span class="value"><?= val($history['brand_model']); ?></span></div>
                    <div class="detail-item"><span class="label">Serial Number</span><span class="separator">:</span><span class="value"><?= val($history['serial_number']); ?></span></div>
                    <div class="detail-item"><span class="label">Tahun</span><span class="separator">:</span><span class="value"><?= val($history['year']); ?></span></div>
                    <div class="detail-item"><span class="label">Kapasitas</span><span class="separator">:</span><span class="value"><?= val($history['capacity']); ?></span></div>
                    <div class="detail-item"><span class="label">Lokasi</span><span class="separator">:</span><span class="value"><?= val($history['location']); ?></span></div>
                </div>
            </div>

            <div class="box">
                <div class="mini-title">🔧 Informasi Maintenance</div>

                <div class="detail-grid" style="grid-template-columns:1fr;">
                    <div class="detail-item">
                        <span class="label">Jenis Maintenance</span><span class="separator">:</span>
                        <span class="value">
                            <span class="<?= $jenisClass; ?>"><?= htmlspecialchars($history['jenis_maintenance']); ?></span>
                        </span>
                    </div>
                    <div class="detail-item"><span class="label">Tanggal Selesai</span><span class="separator">:</span><span class="value"><?= $tanggalSelesai; ?></span></div>
                    <div class="detail-item"><span class="label">PIC / Teknisi</span><span class="separator">:</span><span class="value"><?= val($history['pic_teknisi']); ?></span></div>
                    <div class="detail-item">
                        <span class="label">Status Saat Ini</span><span class="separator">:</span>
                        <span class="value">
                            <span class="<?= $statusClass; ?>"><?= htmlspecialchars($history['status']); ?></span>
                        </span>
                    </div>
                </div>
            </div>

        </section>

        <!-- PERHITUNGAN HM -->
        <div class="box">
            <div class="mini-title">⏱ Perhitungan HM Maintenance</div>

            <section class="summary-grid cols-3" style="margin-bottom:0; margin-top:12px;">

                <div class="summary-card">
                    <div class="summary-icon icon-blue">🕒</div>
                    <div class="summary-text">
                        <small>HM Saat Maintenance</small>
                        <strong><?= $hmMaintenance; ?></strong>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">🎯</div>
                    <div class="summary-text">
                        <small>Next HM</small>
                        <strong style="color:var(--primary);"><?= $nextHM; ?></strong>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon icon-green">⚙</div>
                    <div class="summary-text">
                        <small>HM Equipment Saat Ini</small>
                        <strong><?= $hmCurrent; ?></strong>
                    </div>
                </div>

            </section>
        </div>

        <!-- PEKERJAAN -->
        <div class="box">
            <div class="mini-title">📝 Pekerjaan Maintenance</div>

            <div class="work-box">
                <?= !empty($history['pekerjaan'])
                    ? nl2br(htmlspecialchars($history['pekerjaan']))
                    : 'Tidak ada data pekerjaan maintenance.'; ?>
            </div>
        </div>

        <!-- CATATAN -->
        <div class="info-note">
            Data pada halaman ini merupakan riwayat maintenance yang telah diselesaikan dan tersimpan dalam sistem.
            Nilai Next HM digunakan sebagai acuan untuk maintenance berikutnya.
        </div>

    </main>

</body>

</html>

<?php
$conn->close();
?>