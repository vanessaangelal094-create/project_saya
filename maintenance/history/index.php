<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

/* =========================================================
   FILTER
========================================================= */

$search = trim($_GET['search'] ?? '');
$jenis  = $_GET['jenis'] ?? '';

/* =========================================================
   QUERY DATA HISTORY
========================================================= */

$sql = "
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
        e.brand_model
    FROM maintenance_history h
    INNER JOIN equipment e ON h.equipment_id = e.id
    WHERE 1=1
";

$params = [];
$types  = "";

if ($search !== '') {
    $sql .= "
        AND (
            e.equipment_code LIKE ?
            OR e.equipment_name LIKE ?
            OR h.pekerjaan LIKE ?
            OR h.pic_teknisi LIKE ?
        )
    ";
    $searchValue = "%" . $search . "%";
    array_push($params, $searchValue, $searchValue, $searchValue, $searchValue);
    $types .= "ssss";
}

if ($jenis !== '') {
    $sql .= " AND h.jenis_maintenance = ? ";
    $params[] = $jenis;
    $types .= "s";
}

$sql .= " ORDER BY h.tanggal_selesai DESC, h.id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================================================
   SUMMARY
========================================================= */

$total_history    = 0;
$total_preventive = 0;
$total_corrective = 0;

$summaryQuery = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(jenis_maintenance = 'Preventive') AS preventive,
        SUM(jenis_maintenance = 'Corrective') AS corrective
    FROM maintenance_history
");

if ($summaryQuery) {
    $summary          = $summaryQuery->fetch_assoc();
    $total_history    = (int) ($summary['total'] ?? 0);
    $total_preventive = (int) ($summary['preventive'] ?? 0);
    $total_corrective = (int) ($summary['corrective'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histori Pemeliharaan - PT Biringkassi Raya</title>
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
                    <a href="../../index.php">Home</a> &nbsp;›&nbsp; <span>Histori Pemeliharaan</span>
                </div>
                <h1>Histori Pemeliharaan</h1>
                <p>Riwayat pemeliharaan alat berat yang telah selesai dilakukan.</p>
            </div>
        </div>

        <!-- SUMMARY -->
        <section class="summary-grid cols-3">

            <div class="summary-card">
                <div class="summary-icon icon-blue">📋</div>
                <div class="summary-text">
                    <small>Total Histori</small>
                    <strong><?= $total_history; ?></strong>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon icon-green">🔧</div>
                <div class="summary-text">
                    <small>Preventive Maintenance</small>
                    <strong><?= $total_preventive; ?></strong>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon icon-orange">🛠️</div>
                <div class="summary-text">
                    <small>Corrective Maintenance</small>
                    <strong><?= $total_corrective; ?></strong>
                </div>
            </div>

        </section>

        <!-- FILTER -->
        <div class="filter-box">

            <div class="filter-title">Filter Histori</div>

            <form method="GET" class="filter-form cols-4">

                <div class="form-group">
                    <label for="search">Cari Equipment</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= htmlspecialchars($search); ?>"
                        placeholder="ID Unit, nama equipment, pekerjaan, teknisi..."
                    >
                </div>

                <div class="form-group">
                    <label for="jenis">Jenis Maintenance</label>
                    <select name="jenis" id="jenis">
                        <option value="">Semua Jenis</option>
                        <option value="Preventive" <?= $jenis === 'Preventive' ? 'selected' : ''; ?>>Preventive</option>
                        <option value="Corrective" <?= $jenis === 'Corrective' ? 'selected' : ''; ?>>Corrective</option>
                    </select>
                </div>

                <button type="submit" class="btn-filter">Filter</button>
                <a href="index.php" class="btn-reset">Reset</a>

            </form>

        </div>

        <!-- TABEL -->
        <div class="section">

            <div class="section-header">
                <h3>Riwayat Maintenance</h3>
                <span style="font-size:13px; color:var(--muted);">Data maintenance yang telah selesai</span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Unit</th>
                            <th>Equipment</th>
                            <th>Jenis</th>
                            <th>Tanggal Selesai</th>
                            <th>HM Saat Maintenance</th>
                            <th>Next HM</th>
                            <th>Pekerjaan</th>
                            <th>PIC / Teknisi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($result->num_rows > 0): ?>

                            <?php $no = 1; ?>

                            <?php while ($row = $result->fetch_assoc()): ?>

                                <?php
                                $jenisClass = 'badge';

                                if ($row['jenis_maintenance'] === 'Preventive') {
                                    $jenisClass = 'badge badge-blue';
                                } elseif ($row['jenis_maintenance'] === 'Corrective') {
                                    $jenisClass = 'badge badge-orange';
                                }

                                $tanggalSelesai = '-';

                                if (!empty($row['tanggal_selesai']) && $row['tanggal_selesai'] !== '0000-00-00') {
                                    $tanggalSelesai = date('d/m/Y', strtotime($row['tanggal_selesai']));
                                }
                                ?>

                                <tr>
                                    <td><?= $no++; ?></td>

                                    <td>
                                        <a href="detail.php?id=<?= (int) $row['id']; ?>"
                                           class="unit-code"
                                           style="text-decoration:none; color:var(--primary);">
                                            <?= htmlspecialchars($row['equipment_code']); ?>
                                        </a>
                                    </td>

                                    <td>
                                        <strong><?= htmlspecialchars($row['equipment_name']); ?></strong>
                                        <div style="font-size:11px; color:var(--muted); margin-top:2px;">
                                            <?= htmlspecialchars($row['equipment_type'] ?? '-'); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="<?= $jenisClass; ?>">
                                            <?= htmlspecialchars($row['jenis_maintenance']); ?>
                                        </span>
                                    </td>

                                    <td><?= $tanggalSelesai; ?></td>

                                    <td>
                                        <strong><?= number_format((float) $row['hm_saat_maintenance'], 0, ',', '.'); ?></strong>
                                    </td>

                                    <td>
                                        <strong style="color:var(--primary);">
                                            <?= number_format((float) $row['next_hm'], 0, ',', '.'); ?>
                                        </strong>
                                    </td>

                                    <td><?= !empty($row['pekerjaan']) ? htmlspecialchars($row['pekerjaan']) : '-'; ?></td>

                                    <td><?= !empty($row['pic_teknisi']) ? htmlspecialchars($row['pic_teknisi']) : '-'; ?></td>

                                    <td>
                                        <div class="action">
                                            <a href="detail.php?id=<?= (int) $row['id']; ?>" class="view">Detail</a>

                                            <a href="../cancel_complete.php?id=<?= (int) $row['id']; ?>"
                                               class="delete"
                                               onclick="return confirm('Apakah Anda yakin ingin membatalkan penyelesaian maintenance ini?');">
                                                Batalkan
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="10" class="empty">
                                    <div class="empty-icon">📭</div>
                                    Belum ada histori pemeliharaan.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

    </main>

</body>

</html>

<?php
$stmt->close();
$conn->close();
?>