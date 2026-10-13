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

$search = $_GET['search'] ?? '';
$jenis = $_GET['jenis'] ?? '';

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
    INNER JOIN equipment e
        ON h.equipment_id = e.id
    WHERE 1=1
";

$params = [];
$types = "";

/* =========================================================
   SEARCH
========================================================= */

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

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssss";
}

/* =========================================================
   FILTER JENIS MAINTENANCE
========================================================= */

if ($jenis !== '') {

    $sql .= "
        AND h.jenis_maintenance = ?
    ";

    $params[] = $jenis;

    $types .= "s";
}

$sql .= "
    ORDER BY
        h.tanggal_selesai DESC,
        h.id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$result = $stmt->get_result();

/* =========================================================
   SUMMARY
========================================================= */

$total_history = 0;
$total_preventive = 0;
$total_corrective = 0;

$summaryQuery = $conn->query("
    SELECT
        COUNT(*) AS total,

        SUM(
            jenis_maintenance = 'Preventive'
        ) AS preventive,

        SUM(
            jenis_maintenance = 'Corrective'
        ) AS corrective

    FROM maintenance_history
");

if ($summaryQuery) {

    $summary = $summaryQuery->fetch_assoc();

    $total_history =
        (int) ($summary['total'] ?? 0);

    $total_preventive =
        (int) ($summary['preventive'] ?? 0);

    $total_corrective =
        (int) ($summary['corrective'] ?? 0);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Histori Pemeliharaan - PT Biringkassi Raya
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;

            color: #172b4d;
        }


        /* =================================================
           SIDEBAR
        ================================================= */

        .sidebar {

            position: fixed;

            left: 0;

            top: 0;

            width: 240px;

            height: 100vh;

            background: #0759bd;

            color: white;

            padding: 25px 15px;

            overflow-y: auto;

            z-index: 1000;
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

            margin: 0;

            padding: 0;
        }

        .menu li {

            margin-bottom: 7px;
        }

        .menu a {

            display: block;

            padding: 12px 14px;

            border-radius: 8px;

            color: white;

            text-decoration: none;

            font-size: 13px;

            transition: 0.2s;
        }

        .menu a:hover,
        .menu a.active {

            background:
                rgba(255,255,255,0.18);
        }


        /* =================================================
           MAIN
        ================================================= */

        .main {

            margin-left: 240px;

            min-height: 100vh;

            padding:
                28px
                32px
                45px;
        }

        .breadcrumb {

            font-size: 13px;

            color: #7c8da6;

            margin-bottom: 10px;
        }

        .breadcrumb span {

            color: #0759bd;

            font-weight: 600;
        }

        .page-title {

            font-size: 28px;

            margin-bottom: 8px;

            color: #122b52;
        }

        .page-subtitle {

            color: #7c8da6;

            font-size: 14px;

            margin-bottom: 25px;
        }


        /* =================================================
           SUMMARY
        ================================================= */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;

            margin-bottom: 22px;
        }

        .summary-card {

            background: white;

            border:
                1px solid
                #e2e8f0;

            border-radius: 12px;

            padding: 20px;

            display: flex;

            align-items: center;

            gap: 15px;

            box-shadow:
                0 2px 8px
                rgba(20, 40, 70, 0.04);
        }

        .summary-icon {

            width: 48px;

            height: 48px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 22px;

            background: #eaf2ff;
        }

        .summary-text small {

            display: block;

            color: #7c8da6;

            font-size: 12px;

            margin-bottom: 5px;
        }

        .summary-text strong {

            font-size: 24px;

            color: #0759bd;
        }


        /* =================================================
           FILTER
        ================================================= */

        .filter-card {

            background: white;

            border:
                1px solid
                #e2e8f0;

            border-radius: 12px;

            padding: 20px;

            margin-bottom: 22px;
        }

        .filter-title {

            font-size: 15px;

            font-weight: bold;

            margin-bottom: 15px;

            color: #172b4d;
        }

        .filter-form {

            display: grid;

            grid-template-columns:
                2fr 1fr auto auto;

            gap: 12px;

            align-items: end;
        }

        .form-group label {

            display: block;

            font-size: 12px;

            color: #667892;

            margin-bottom: 7px;
        }

        .form-group input,
        .form-group select {

            width: 100%;

            height: 42px;

            border:
                1px solid
                #d8e0eb;

            border-radius: 8px;

            padding:
                0 12px;

            font-size: 13px;

            outline: none;

            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {

            border-color: #0759bd;
        }

        .btn {

            height: 42px;

            border-radius: 8px;

            padding:
                0 18px;

            border: none;

            cursor: pointer;

            font-size: 13px;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;
        }

        .btn-filter {

            background: #0759bd;

            color: white;
        }

        .btn-reset {

            background: #eef2f7;

            color: #475569;
        }

        .btn:hover {

            opacity: 0.9;
        }


        /* =================================================
           TABLE
        ================================================= */

        .table-card {

            background: white;

            border:
                1px solid
                #e2e8f0;

            border-radius: 12px;

            overflow: hidden;

            box-shadow:
                0 2px 8px
                rgba(20, 40, 70, 0.04);
        }

        .table-header {

            padding:
                20px 22px;

            border-bottom:
                1px solid
                #e7edf5;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }

        .table-header h2 {

            font-size: 17px;

            color: #172b4d;
        }

        .table-header span {

            font-size: 12px;

            color: #7c8da6;
        }

        .table-wrapper {

            width: 100%;

            overflow-x: auto;
        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1180px;
        }

        th {

            background: #f7f9fc;

            color: #64748b;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            padding:
                14px 12px;

            text-align: left;

            border-bottom:
                1px solid
                #e5eaf1;

            white-space: nowrap;
        }

        td {

            padding:
                14px 12px;

            border-bottom:
                1px solid
                #edf1f6;

            font-size: 13px;

            color: #334155;

            vertical-align: middle;
        }

        tbody tr:hover {

            background: #fafcff;
        }

        .unit-code {

            color: #0759bd;

            font-weight: bold;
        }

        .equipment-name {

            font-weight: 600;

            color: #172b4d;
        }

        .maintenance-badge {

            display: inline-block;

            padding:
                5px 9px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;
        }

        .preventive {

            background: #e7f1ff;

            color: #0759bd;
        }

        .corrective {

            background: #fff0e6;

            color: #d97706;
        }

        .other {

            background: #f0f2f5;

            color: #64748b;
        }

        .hm-value {

            font-weight: 600;

            color: #172b4d;
        }

        .next-hm {

            font-weight: 700;

            color: #0759bd;
        }

        .empty {

            text-align: center;

            padding:
                50px 20px;

            color: #94a3b8;
        }


        /* =================================================
           ACTION BUTTONS
        ================================================= */

        .action-buttons {

            display: flex;

            gap: 7px;

            flex-wrap: wrap;
        }

        .action-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 34px;

            padding: 0 11px;

            border-radius: 7px;

            font-size: 11px;

            font-weight: 600;

            text-decoration: none;

            white-space: nowrap;
        }

        .btn-detail {

            color: #0759bd;

            border: 1px solid #0759bd;

            background: white;
        }

        .btn-cancel {

            color: #d64545;

            border: 1px solid #d64545;

            background: white;
        }

        .btn-cancel:hover {

            background: #fff1f1;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1000px) {

            .summary-grid {

                grid-template-columns:
                    1fr 1fr;
            }

            .filter-form {

                grid-template-columns:
                    1fr 1fr;
            }
        }

        @media (max-width: 800px) {

            .sidebar {

                width: 200px;
            }

            .main {

                margin-left: 200px;

                padding: 20px;
            }

            .summary-grid {

                grid-template-columns:
                    1fr;
            }

            .filter-form {

                grid-template-columns:
                    1fr;
            }
        }

    </style>

</head>

<body>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">

    <div class="logo">

        <img
            src="../../assets/logo.png"
            alt="Logo PT Biringkassi Raya"
        >

        <div class="logo-text">

            PT BIRINGKASSI RAYA

        </div>

    </div>

    <ul class="menu">

        <li>

            <a href="../../index.php">

                Dashboard

            </a>

        </li>

        <li>

         <a href="http://localhost/Web%20Monitoring%20Alat%20Berat/equipment/index.php">
    Equipment Master
</a>
        </li>

        <li>

            <a href="../../equipment/index.php">
                Status Unit
            </a>

        </li>

        <li>

            <a href="../index.php">

                Jadwal Pemeliharaan

            </a>

        </li>

        <li>

            <a
                href="index.php"
                class="active"
            >

                Histori Pemeliharaan

            </a>

        </li>

        <li>

            <a href="#">

                Breakdown & Failure

            </a>

        </li>

     <li class="menu-dropdown">
    <a href="javascript:void(0);" onclick="
        var submenu = document.getElementById('spareSubmenu');
        var arrow = document.getElementById('spareArrow');

        if (submenu.style.display === 'none' || submenu.style.display === '') {
            submenu.style.display = 'block';
            arrow.textContent = '▼';
        } else {
            submenu.style.display = 'none';
            arrow.textContent = '▶';
        }
    ">
        Spare Part & Lubricant
        <span id="spareArrow">▶</span>
    </a>

    <ul id="spareSubmenu" class="submenu" style="display: none;">
        <li>
            <a href="../../sparepart/index.php">
                Spare Part
            </a>
        </li>

        <li>
            <a href="../../lubricant/index.php">
                Lubricant
            </a>
        </li>
    </ul>
</li>

        <li>

            <a href="../../fuel/index.php">

                Fuel Performance

            </a>

        </li>

        <li>

            <a href="#">

                Biaya Pemeliharaan

            </a>

        </li>

        <li>

            <a href="#">

                Kinerja & Evaluasi

            </a>

        </li>

        <li>

            <a href="#">

                Laporan

            </a>

        </li>

    </ul>

</aside>


<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">

    <div class="breadcrumb">

        Home

        &nbsp;›&nbsp;

        <span>

            Histori Pemeliharaan

        </span>

    </div>

    <h1 class="page-title">

        Histori Pemeliharaan

    </h1>

    <p class="page-subtitle">

        Riwayat pemeliharaan alat berat yang telah selesai dilakukan.

    </p>


    <!-- =================================================
         SUMMARY
    ================================================== -->

    <div class="summary-grid">

        <div class="summary-card">

            <div class="summary-icon">

                📋

            </div>

            <div class="summary-text">

                <small>

                    Total Histori

                </small>

                <strong>

                    <?= $total_history; ?>

                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">

                🔧

            </div>

            <div class="summary-text">

                <small>

                    Preventive Maintenance

                </small>

                <strong>

                    <?= $total_preventive; ?>

                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">

                🛠️

            </div>

            <div class="summary-text">

                <small>

                    Corrective Maintenance

                </small>

                <strong>

                    <?= $total_corrective; ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- =================================================
         FILTER
    ================================================== -->

    <div class="filter-card">

        <div class="filter-title">

            Filter Histori

        </div>

        <form
            method="GET"
            class="filter-form"
        >

            <div class="form-group">

                <label>

                    Cari Equipment

                </label>

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search); ?>"
                    placeholder="ID Unit, nama equipment, pekerjaan, teknisi..."
                >

            </div>


            <div class="form-group">

                <label>

                    Jenis Maintenance

                </label>

                <select name="jenis">

                    <option value="">

                        Semua Jenis

                    </option>

                    <option
                        value="Preventive"
                        <?= $jenis === 'Preventive'
                            ? 'selected'
                            : ''; ?>
                    >

                        Preventive

                    </option>

                    <option
                        value="Corrective"
                        <?= $jenis === 'Corrective'
                            ? 'selected'
                            : ''; ?>
                    >

                        Corrective

                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="btn btn-filter"
            >

                Filter

            </button>


            <a
                href="index.php"
                class="btn btn-reset"
            >

                Reset

            </a>

        </form>

    </div>


    <!-- =================================================
         TABLE
    ================================================== -->

    <div class="table-card">

        <div class="table-header">

            <h2>

                Riwayat Maintenance

            </h2>

            <span>

                Data maintenance yang telah selesai

            </span>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>

                            No

                        </th>

                        <th>

                            ID Unit

                        </th>

                        <th>

                            Equipment

                        </th>

                        <th>

                            Jenis

                        </th>

                        <th>

                            Tanggal Selesai

                        </th>

                        <th>

                            HM Saat Maintenance

                        </th>

                        <th>

                            Next HM

                        </th>

                        <th>

                            Pekerjaan

                        </th>

                        <th>

                            PIC / Teknisi

                        </th>

                        <th>

                            Aksi

                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php

                        $no = 1;

                        ?>


                        <?php while ($row = $result->fetch_assoc()): ?>

                            <?php

                            $jenisClass = 'other';

                            if (

                                $row['jenis_maintenance']

                                === 'Preventive'

                            ) {

                                $jenisClass =

                                    'preventive';

                            } elseif (

                                $row['jenis_maintenance']

                                === 'Corrective'

                            ) {

                                $jenisClass =

                                    'corrective';

                            }


                            $tanggalSelesai = '-';

                            if (

                                !empty(

                                    $row['tanggal_selesai']

                                )

                                &&

                                $row['tanggal_selesai']

                                !== '0000-00-00'

                            ) {

                                $tanggalSelesai =

                                    date(

                                        'd/m/Y',

                                        strtotime(

                                            $row['tanggal_selesai']

                                        )

                                    );

                            }

                            ?>


                            <tr>

                                <td>

                                    <?= $no++; ?>

                                </td>


                                <td>

                                    <a

                                        href="detail.php?id=<?= $row['id']; ?>"

                                        class="unit-code"

                                    >

                                        <?= htmlspecialchars(

                                            $row['equipment_code']

                                        ); ?>

                                    </a>

                                </td>


                                <td>

                                    <div

                                        class="equipment-name"

                                    >

                                        <?= htmlspecialchars(

                                            $row['equipment_name']

                                        ); ?>

                                    </div>


                                    <div

                                        style="

                                            font-size:11px;

                                            color:#94a3b8;

                                            margin-top:3px;

                                        "

                                    >

                                        <?= htmlspecialchars(

                                            $row['equipment_type']

                                            ?? '-'

                                        ); ?>

                                    </div>

                                </td>


                                <td>

                                    <span

                                        class="

                                            maintenance-badge

                                            <?= $jenisClass; ?>

                                        "

                                    >

                                        <?= htmlspecialchars(

                                            $row['jenis_maintenance']

                                        ); ?>

                                    </span>

                                </td>


                                <td>

                                    <?= $tanggalSelesai; ?>

                                </td>


                                <td>

                                    <span class="hm-value">

                                        <?= number_format(

                                            (float)

                                            $row[

                                                'hm_saat_maintenance'

                                            ],

                                            0,

                                            ',',

                                            '.'

                                        ); ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="next-hm">

                                        <?= number_format(

                                            (float)

                                            $row['next_hm'],

                                            0,

                                            ',',

                                            '.'

                                        ); ?>

                                    </span>

                                </td>


                                <td>

                                    <?= !empty(

                                        $row['pekerjaan']

                                    )

                                        ? htmlspecialchars(

                                            $row['pekerjaan']

                                        )

                                        : '-'; ?>

                                </td>


                                <td>

                                    <?= !empty(

                                        $row['pic_teknisi']

                                    )

                                        ? htmlspecialchars(

                                            $row['pic_teknisi']

                                        )

                                        : '-'; ?>

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

                                            href="../cancel_complete.php?id=<?= $row['id']; ?>"

                                            class="action-btn btn-cancel"

                                            onclick="return confirm('Apakah Anda yakin ingin membatalkan penyelesaian maintenance ini?');"

                                        >

                                            Batalkan

                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>


                    <?php else: ?>

                        <tr>

                            <td

                                colspan="10"

                                class="empty"

                            >

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