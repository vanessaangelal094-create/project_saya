<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   FILTER
========================================================= */

$search = $_GET['search'] ?? '';
$jenis = $_GET['jenis'] ?? '';
$status_filter = $_GET['status'] ?? '';


/* =========================================================
   DATA MAINTENANCE
========================================================= */

$sql = "
    SELECT
        ms.*,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type
    FROM maintenance_schedule ms
    INNER JOIN equipment e
        ON ms.equipment_id = e.id
    WHERE ms.status <> 'Selesai'
";

$params = [];
$types = "";

if ($search !== '') {

    $sql .= "
        AND (
            e.equipment_code LIKE ?
            OR e.equipment_name LIKE ?
        )
    ";

    $search_param = "%" . $search . "%";

    $params[] = $search_param;
    $params[] = $search_param;

    $types .= "ss";
}

if ($jenis !== '') {

    $sql .= " AND ms.jenis_maintenance = ?";

    $params[] = $jenis;
    $types .= "s";
}

if ($status_filter !== '') {

    $sql .= " AND ms.status = ?";

    $params[] = $status_filter;
    $types .= "s";
}

$sql .= "
    ORDER BY
        CASE
            WHEN ms.status = 'Overdue' THEN 1
            WHEN ms.status = 'Segera Diservis' THEN 2
            WHEN ms.status = 'Akan Jatuh Tempo' THEN 3
            WHEN ms.status = 'Normal' THEN 4
            WHEN ms.status = 'Selesai' THEN 5
            ELSE 6
        END,
        ms.remaining_hm_km ASC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================================================
   SUMMARY
========================================================= */

$total_jadwal = 0;
$segera_service = 0;
$overdue = 0;
$selesai = 0;

$summary_query = "
    SELECT
        SUM(status <> 'Selesai') AS total_jadwal,
        SUM(status = 'Segera Diservis') AS segera_service,
        SUM(status = 'Overdue') AS overdue,
        SUM(status = 'Selesai') AS selesai
    FROM maintenance_schedule
";

$summary_result = $conn->query($summary_query);

if ($summary_result && $summary_result->num_rows > 0) {

    $summary = $summary_result->fetch_assoc();

    $total_jadwal = (int) ($summary['total_jadwal'] ?? 0);
    $segera_service = (int) ($summary['segera_service'] ?? 0);
    $overdue = (int) ($summary['overdue'] ?? 0);
    $selesai = (int) ($summary['selesai'] ?? 0);
}


/* =========================================================
   UNIT YANG SEGERA HARUS DISERVIS
========================================================= */

$urgent_sql = "
    SELECT
        ms.*,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type
    FROM maintenance_schedule ms
    INNER JOIN equipment e
        ON ms.equipment_id = e.id
    WHERE ms.status IN (
        'Segera Diservis',
        'Overdue',
        'Akan Jatuh Tempo'
    )
    ORDER BY
        CASE
            WHEN ms.status = 'Overdue' THEN 1
            WHEN ms.status = 'Segera Diservis' THEN 2
            WHEN ms.status = 'Akan Jatuh Tempo' THEN 3
        END,
        ms.remaining_hm_km ASC
    LIMIT 5
";

$urgent_result = $conn->query($urgent_sql);


/* =========================================================
   JENIS MAINTENANCE
========================================================= */

$jenis_result = $conn->query("
    SELECT DISTINCT jenis_maintenance
    FROM maintenance_schedule
    WHERE jenis_maintenance IS NOT NULL
    AND jenis_maintenance != ''
    ORDER BY jenis_maintenance ASC
");

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Jadwal Pemeliharaan - PT Biringkassi Raya</title>

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>

        .schedule-table {
            min-width: 950px;
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


    <div class="topbar">

        <div>

            <div class="breadcrumb">
                Home &nbsp;›&nbsp;
                <span>Jadwal Pemeliharaan</span>
            </div>

            <h1 class="page-title">
                Maintenance Schedule
            </h1>

            <p class="page-subtitle">
                Jadwal pemeliharaan seluruh alat berat.
            </p>

        </div>


        <a href="add.php" class="btn-primary">
            + Tambah Jadwal
        </a>

    </div>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <div class="summary-grid cols-4">


        <div class="summary-card">

            <div class="summary-icon icon-blue">
                📅
            </div>

            <div class="summary-text">

                <small>Total Jadwal</small>

                <strong>
                    <?= $total_jadwal; ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon icon-orange">
                🔧
            </div>

            <div class="summary-text">

                <small>Segera Diservis</small>

                <strong>
                    <?= $segera_service; ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon icon-red">
                ⚠
            </div>

            <div class="summary-text">

                <small>Overdue</small>

                <strong>
                    <?= $overdue; ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon icon-green">
                ✓
            </div>

            <div class="summary-text">

                <small>Selesai</small>

                <strong>
                    <?= $selesai; ?>
                </strong>

            </div>

        </div>


    </div>

    <!-- =====================================================
         FILTER
    ====================================================== -->

    <section class="section" id="daftar-maintenance">

        <div class="section-header">

            <h3>
                🔎 Filter Data
            </h3>

        </div>


        <div class="filter-box">

            <form method="GET">

                <div class="filter-grid">


                    <div class="filter-group">

                        <label>
                            Cari
                        </label>

                        <input
                            type="text"
                            name="search"
                            placeholder="Cari ID unit atau nama equipment..."
                            value="<?= htmlspecialchars($search); ?>"
                        >

                    </div>


                    <div class="filter-group">

                        <label>
                            Jenis Maintenance
                        </label>

                        <select name="jenis">

                            <option value="">
                                Semua
                            </option>

                            <?php if ($jenis_result): ?>

                                <?php while ($jenis_row = $jenis_result->fetch_assoc()): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $jenis_row['jenis_maintenance']
                                        ); ?>"
                                        <?= (
                                            $jenis === $jenis_row['jenis_maintenance']
                                        ) ? 'selected' : ''; ?>
                                    >

                                        <?= htmlspecialchars(
                                            $jenis_row['jenis_maintenance']
                                        ); ?>

                                    </option>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </select>

                    </div>


                    <div class="filter-group">

                        <label>
                            Status
                        </label>

                        <select name="status">

                            <option value="">
                                Semua
                            </option>

                            <option
                                value="Normal"
                                <?= ($status_filter === 'Normal')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Normal
                            </option>

                            <option
                                value="Akan Jatuh Tempo"
                                <?= ($status_filter === 'Akan Jatuh Tempo')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Akan Jatuh Tempo
                            </option>

                            <option
                                value="Segera Diservis"
                                <?= ($status_filter === 'Segera Diservis')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Segera Diservis
                            </option>

                            <option
                                value="Overdue"
                                <?= ($status_filter === 'Overdue')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Overdue
                            </option>

                            <option
                                value="Selesai"
                                <?= ($status_filter === 'Selesai')
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Selesai
                            </option>

                        </select>

                    </div>


                    <div class="filter-group">

                        <label>
                            Periode Jadwal
                        </label>

                        <input
                            type="date"
                            name="tanggal"
                            value="<?= htmlspecialchars(
                                $_GET['tanggal'] ?? ''
                            ); ?>"
                        >

                    </div>


                    <div>

                        <button
                            type="submit"
                            class="btn-filter"
                        >
                            Tampilkan
                        </button>

                    </div>


                    <div>

                        <a
                            href="index.php"
                            class="btn-reset"
                        >
                            Reset
                        </a>

                    </div>


                </div>

            </form>

        </div>

    </section>


    <!-- =====================================================
         DAFTAR MAINTENANCE
    ====================================================== -->

    <section class="section">

        <div class="section-header">

            <h3>
                📋 Daftar Maintenance Schedule
            </h3>

        </div>


        <div class="table-wrapper">

            <table class="schedule-table">

                <thead>

                    <tr>

                        <th>No</th>
                        <th>Tanggal</th>
                        <th>ID Unit</th>
                        <th>Equipment</th>
                        <th>Jenis Maintenance</th>
                        <th>HM</th>
                        <th>Next Service</th>
                        <th>Pekerjaan</th>
                        <th>PIC / Teknisi</th>
                        <th>Status</th>
                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <?php

                            $status = $row['status'];

                            if ($status === 'Overdue') {

                                $status_class = 'status-danger';

                            } elseif (
                                $status === 'Segera Diservis' ||
                                $status === 'Akan Jatuh Tempo'
                            ) {

                                $status_class = 'status-warning';

                            } elseif ($status === 'Menunggu Spare Part') {

                                $status_class = 'status-warning';

                            } elseif ($status === 'Selesai') {

                                $status_class = 'status-success';

                            } else {

                                $status_class = 'status-normal';

                            }

                            ?>

                            <tr>

                                <td>
                                    <?= $no++; ?>
                                </td>


                                <td>

                                    <?php

                                    if (
                                        !empty($row['tanggal_jadwal']) &&
                                        $row['tanggal_jadwal'] !== '0000-00-00'
                                    ) {

                                        $timestamp = strtotime(
                                            $row['tanggal_jadwal']
                                        );

                                        if ($timestamp !== false) {

                                            echo date(
                                                'd/m/Y',
                                                $timestamp
                                            );

                                        } else {

                                            echo '-';

                                        }

                                    } else {

                                        echo '-';

                                    }

                                    ?>

                                </td>


                                <td class="unit-code">

                                    <?= htmlspecialchars(
                                        $row['equipment_code']
                                    ); ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $row['equipment_name']
                                    ); ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $row['jenis_maintenance']
                                    ); ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        $row['current_hm_km'],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>

                                </td>
                                <td>
    <?php
    if (
        isset($row['current_hm_km'], $row['remaining_hm_km']) &&
        is_numeric($row['current_hm_km']) &&
        is_numeric($row['remaining_hm_km'])
    ) {
        $next_service_hm =
            (float) $row['current_hm_km'] +
            (float) $row['remaining_hm_km'];

        echo number_format($next_service_hm, 0, ',', '.') . ' HM';
    } else {
        echo '-';
    }
    ?>
</td>


                                <td>

                                    <?= htmlspecialchars(
                                        $row['pekerjaan'] ?: '-'
                                    ); ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $row['pic_teknisi'] ?: '-'
                                    ); ?>

                                </td>


                                <td>

                                    <span class="status <?= $status_class; ?>">

                                        <?= htmlspecialchars($status); ?>

                                    </span>

                                </td>


                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="detail.php?id=<?= $row['id']; ?>"
                                            class="action-btn btn-detail"
                                        >
                                            Detail
                                        </a>


                                        <a
                                            href="edit.php?id=<?= $row['id']; ?>"
                                            class="action-btn btn-edit"
                                        >
                                            Edit
                                        </a>


                                        <a
                                            href="delete.php?id=<?= $row['id']; ?>"
                                            class="action-btn btn-delete"
                                            onclick="return confirm(
                                                'Apakah Anda yakin ingin menghapus jadwal maintenance ini?'
                                            );"
                                        >
                                            Hapus
                                        </a>


                                        <?php if ($status !== 'Selesai'): ?>

                                            <a
                                                href="complete.php?id=<?= $row['id']; ?>"
                                                class="action-btn btn-detail"
                                            >
                                                Selesaikan
                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="10">

                                <div class="empty">

                                    <div class="empty-icon">
                                        📋
                                    </div>

                                    Belum ada data Maintenance Schedule.

                                    <br>

                                    Silakan tambahkan jadwal maintenance
                                    setelah data tersedia.

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


</main>


</body>

</html>