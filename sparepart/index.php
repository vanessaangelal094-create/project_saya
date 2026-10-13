<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   FILTER EQUIPMENT
========================================================= */

$selected_equipment = isset($_GET['equipment_id'])
    ? (int) $_GET['equipment_id']
    : 0;


/* =========================================================
   DATA EQUIPMENT
========================================================= */

$equipment_result = $conn->query("
    SELECT
        id,
        equipment_code,
        equipment_name,
        equipment_type
    FROM equipment
    ORDER BY equipment_code ASC
");


/* =========================================================
   DATA SPARE PART
========================================================= */

$sparepart_data = [];

if ($selected_equipment > 0) {

    $sql = "
        SELECT
            msp.id,
            msp.nama_item,
            msp.kategori,
            msp.jenis,
            msp.spesifikasi,
            msp.jumlah,
            msp.satuan,
            msp.tanggal_order,
            msp.perkiraan_tiba,
            msp.tanggal_tiba_aktual,
            msp.status AS status_sparepart,
            msp.keterangan,

            ms.tanggal_jadwal,
            ms.jenis_maintenance,
            ms.status AS status_maintenance,
            ms.pekerjaan,

            e.equipment_code,
            e.equipment_name,
            e.equipment_type

        FROM maintenance_spare_parts msp

        INNER JOIN maintenance_schedule ms
            ON msp.maintenance_id = ms.id

        INNER JOIN equipment e
            ON ms.equipment_id = e.id

        WHERE e.id = ?
        AND msp.kategori = 'Spare Part'

        ORDER BY
            ms.tanggal_jadwal DESC,
            msp.id DESC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $selected_equipment
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $sparepart_data[] = $row;
        }

        $stmt->close();
    }
}


/* =========================================================
   DATA EQUIPMENT TERPILIH
========================================================= */

$selected_equipment_data = null;

if ($selected_equipment > 0) {

    $stmt_equipment = $conn->prepare("
        SELECT
            id,
            equipment_code,
            equipment_name,
            equipment_type,
            hm_current
        FROM equipment
        WHERE id = ?
    ");

    if ($stmt_equipment) {

        $stmt_equipment->bind_param(
            "i",
            $selected_equipment
        );

        $stmt_equipment->execute();

        $equipment_selected_result =
            $stmt_equipment->get_result();

        $selected_equipment_data =
            $equipment_selected_result->fetch_assoc();

        $stmt_equipment->close();
    }
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

    <title>Spare Part - PT Biringkassi Raya</title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;

            color: #172033;

        }

/* =================================================
   SIDEBAR - STANDARD
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

.brand {
    display: flex;
    align-items: center;

    gap: 12px;

    margin-bottom: 35px;
    padding-left: 10px;
}

.brand img {
    width: 42px;
    height: 42px;

    object-fit: contain;
}

.brand span {
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

    color: white;
    text-decoration: none;

    font-size: 13px;

    transition: 0.2s;
}

.menu a:hover {
    background: rgba(255, 255, 255, 0.12);
}

.menu a.active {
    background: rgba(255, 255, 255, 0.18);
}

.menu-dropdown > a {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.submenu {
    list-style: none;
    display: block;
    margin-top: 3px;
}

.submenu a {
    padding: 10px 14px 10px 30px;
    font-size: 13px;
    background: rgba(255,255,255,0.08);
    margin-bottom: 3px;
}

.submenu a.active {
    background: rgba(255,255,255,0.18);
}

        /* =================================================
           CONTENT
        ================================================= */

        .main {

            margin-left: 240px;

            min-height: 100vh;

            padding: 35px 40px;

        }


        .breadcrumb {

            color: #64748b;

            font-size: 14px;

            margin-bottom: 10px;

        }


        .breadcrumb span {

            color: #0759bd;

            font-weight: 600;

        }


        h1 {

            color: #123b6d;

            font-size: 30px;

            margin-bottom: 8px;

        }


        .description {

            color: #64748b;

            margin-bottom: 28px;

        }


        /* =================================================
           FILTER
        ================================================= */

        .filter-box {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 25px;

        }


        .filter-title {

            font-size: 18px;

            font-weight: 700;

            margin-bottom: 18px;

            color: #172033;

        }


        .filter-form {

            display: flex;

            gap: 15px;

            align-items: end;

        }


        .form-group {

            flex: 1;

        }


        .form-group label {

            display: block;

            font-size: 13px;

            font-weight: 600;

            color: #475569;

            margin-bottom: 7px;

        }


        .form-group select {

            width: 100%;

            height: 44px;

            padding: 0 13px;

            border: 1px solid #d5dee9;

            border-radius: 8px;

            background: white;

            color: #334155;

            font-size: 14px;

            outline: none;

        }


        .form-group select:focus {

            border-color: #0759bd;

            box-shadow:
                0 0 0 3px
                rgba(7,89,189,0.08);

        }


        .btn-filter {

            height: 44px;

            padding: 0 24px;

            border: none;

            border-radius: 8px;

            background: #0759bd;

            color: white;

            font-weight: 600;

            cursor: pointer;

        }


        .btn-filter:hover {

            background: #064b9f;

        }


        .btn-reset {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 44px;

            padding: 0 20px;

            border: 1px solid #d5dee9;

            border-radius: 8px;

            color: #334155;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            background: white;

        }


        /* =================================================
           EQUIPMENT INFO
        ================================================= */

        .equipment-info {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 25px;

        }


        .equipment-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

        }


        .equipment-header h2 {

            font-size: 20px;

            color: #123b6d;

        }


        .equipment-code {

            background: #eaf3ff;

            color: #0759bd;

            padding: 7px 13px;

            border-radius: 7px;

            font-weight: 700;

            font-size: 13px;

        }


        .equipment-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

        }


        .info-item {

            background: #f8fafc;

            border-radius: 9px;

            padding: 15px;

        }


        .info-label {

            color: #64748b;

            font-size: 12px;

            margin-bottom: 5px;

        }


        .info-value {

            font-size: 15px;

            font-weight: 600;

            color: #172033;

        }


        /* =================================================
           TABLE
        ================================================= */

        .table-box {

            background: white;

            border: 1px solid #e1e8f0;

            border-radius: 14px;

            overflow: hidden;

        }


        .table-header {

            padding: 22px 25px;

            border-bottom: 1px solid #e8edf3;

        }


        .table-header h2 {

            font-size: 20px;

            color: #172033;

        }


        .table-header p {

            color: #64748b;

            font-size: 13px;

            margin-top: 5px;

        }


        .table-wrapper {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1100px;

        }


        th {

            background: #f5f8fc;

            color: #64748b;

            font-size: 12px;

            text-align: left;

            padding: 14px 16px;

            white-space: nowrap;

        }


        td {

            padding: 15px 16px;

            border-top: 1px solid #edf1f5;

            font-size: 13px;

            color: #334155;

            vertical-align: top;

        }


        tbody tr:hover {

            background: #fafcff;

        }


        .item-name {

            font-weight: 700;

            color: #172033;

        }


        .spec {

            color: #64748b;

            margin-top: 4px;

            font-size: 12px;

        }


        .badge {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;

        }


        .badge-wait {

            background: #fff4df;

            color: #b45309;

        }


        .badge-order {

            background: #eaf3ff;

            color: #0759bd;

        }


        .badge-delivery {

            background: #f1eafe;

            color: #7c3aed;

        }


        .badge-arrived {

            background: #e8f8ee;

            color: #15803d;

        }


        .badge-default {

            background: #eef2f7;

            color: #475569;

        }


        .empty {

            padding: 55px 20px;

            text-align: center;

            color: #64748b;

        }


        .empty-icon {

            font-size: 40px;

            margin-bottom: 12px;

        }


        .empty h3 {

            color: #334155;

            margin-bottom: 7px;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1100px) {

            .equipment-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 800px) {

            .sidebar {

                width: 210px;

            }

            .main {

                margin-left: 210px;

                padding: 25px;

            }

            .filter-form {

                flex-direction: column;

                align-items: stretch;

            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="brand">

        <img
            src="../assets/logo.png"
            alt="Logo PT Biringkassi Raya"
        >

        <span>
            PT BIRINGKASSI<br>
            RAYA
        </span>

    </div>


    <ul class="menu">

        <li>
            <a href="../index.php">
                Dashboard
            </a>
        </li>


        <li>
            <a href="../equipment/index.php">
                Equipment Master
            </a>
        </li>


        <li>
            <a href="../equipment/index.php">
                Status Unit
            </a>
        </li>


        <li>
            <a href="../maintenance/index.php">
                Jadwal Pemeliharaan
            </a>
        </li>


        <li>
            <a href="../maintenance/history/index.php">
                Histori Pemeliharaan
            </a>
        </li>


        <li>
            <a href="../breakdown/index.php">
                Breakdown & Failure
            </a>
        </li>


        <li class="menu-dropdown">

            <a href="javascript:void(0);">
                Spare Part & Lubricant
                <span>▴</span>
            </a>


            <ul class="submenu">

                <li>
                    <a
                        href="index.php"
                        class="active"
                    >
                        Spare Part
                    </a>
                </li>


                <li>
                    <a href="../lubricant/index.php">
                        Lubricant
                    </a>
                </li>

            </ul>

        </li>


        <li>
            <a href="../fuel/index.php">
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
     MAIN CONTENT
===================================================== -->

<main class="main">


    <div class="breadcrumb">

        Home
        &nbsp;›&nbsp;
        <span>Spare Part</span>

    </div>


    <h1>
        Spare Part
    </h1>


    <p class="description">
        Rekam jejak penggunaan spare part pada setiap alat berat.
    </p>


    <!-- =================================================
         FILTER EQUIPMENT
    ================================================= -->

    <section class="filter-box">

        <div class="filter-title">
            🔎 Pilih Equipment
        </div>


        <form
            method="GET"
            action="index.php"
            class="filter-form"
        >

            <div class="form-group">

                <label for="equipment_id">
                    Equipment
                </label>


                <select
                    name="equipment_id"
                    id="equipment_id"
                    required
                >

                    <option value="">
                        Pilih Equipment
                    </option>


                    <?php

                    if ($equipment_result):

                        while (
                            $equipment =
                            $equipment_result->fetch_assoc()
                        ):

                    ?>

                        <option
                            value="<?= (int)$equipment['id']; ?>"
                            <?= (
                                $selected_equipment ==
                                $equipment['id']
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?= htmlspecialchars(
                                $equipment['equipment_code']
                            ); ?>

                            -
                            <?= htmlspecialchars(
                                $equipment['equipment_name']
                            ); ?>

                        </option>

                    <?php

                        endwhile;

                    endif;

                    ?>

                </select>

            </div>


            <button
                type="submit"
                class="btn-filter"
            >
                Tampilkan
            </button>


            <a
                href="index.php"
                class="btn-reset"
            >
                Reset
            </a>

        </form>

    </section>


    <?php if ($selected_equipment_data): ?>


        <!-- =============================================
             EQUIPMENT INFO
        ============================================== -->

        <section class="equipment-info">

            <div class="equipment-header">

                <h2>
                    Informasi Equipment
                </h2>


                <span class="equipment-code">

                    <?= htmlspecialchars(
                        $selected_equipment_data[
                            'equipment_code'
                        ]
                    ); ?>

                </span>

            </div>


            <div class="equipment-grid">


                <div class="info-item">

                    <div class="info-label">
                        Nama Equipment
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $selected_equipment_data[
                                'equipment_name'
                            ]
                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Jenis
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $selected_equipment_data[
                                'equipment_type'
                            ] ?? '-'
                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        HM Saat Ini
                    </div>

                    <div class="info-value">

                        <?= number_format(
                            (float)(
                                $selected_equipment_data[
                                    'hm_current'
                                ] ?? 0
                            ),
                            0,
                            ',',
                            '.'
                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Total Spare Part
                    </div>

                    <div class="info-value">

                        <?= count($sparepart_data); ?>
                        Item

                    </div>

                </div>


            </div>

        </section>


        <!-- =============================================
             TABLE SPARE PART
        ============================================== -->

        <section class="table-box">

            <div class="table-header">

                <h2>
                    Riwayat Penggunaan Spare Part
                </h2>

                <p>
                    Daftar spare part yang digunakan
                    berdasarkan riwayat maintenance equipment.
                </p>

            </div>


            <?php if (!empty($sparepart_data)): ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    No
                                </th>

                                <th>
                                    Tanggal Maintenance
                                </th>

                                <th>
                                    Nama Spare Part
                                </th>

                                <th>
                                    Jenis
                                </th>

                                <th>
                                    Spesifikasi
                                </th>

                                <th>
                                    Jumlah
                                </th>

                                <th>
                                    Tanggal Order
                                </th>

                                <th>
                                    Perkiraan Tiba
                                </th>

                                <th>
                                    Status Spare Part
                                </th>

                                <th>
                                    Pekerjaan
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php

                            $no = 1;

                            foreach (
                                $sparepart_data
                                as $item
                            ):

                                $status =
                                    $item[
                                        'status_sparepart'
                                    ] ?? '';

                                $status_class =
                                    'badge-default';


                                if (
                                    $status ===
                                    'Menunggu Order'
                                ) {

                                    $status_class =
                                        'badge-wait';

                                } elseif (
                                    $status ===
                                    'Sudah Diorder'
                                ) {

                                    $status_class =
                                        'badge-order';

                                } elseif (
                                    $status ===
                                    'Dalam Pengiriman'
                                ) {

                                    $status_class =
                                        'badge-delivery';

                                } elseif (
                                    $status ===
                                    'Sudah Tiba'
                                ) {

                                    $status_class =
                                        'badge-arrived';

                                }

                            ?>

                                <tr>

                                    <td>
                                        <?= $no++; ?>
                                    </td>


                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $item[
                                                    'tanggal_jadwal'
                                                ]
                                            )
                                        ) {

                                            echo date(
                                                'd/m/Y',
                                                strtotime(
                                                    $item[
                                                        'tanggal_jadwal'
                                                    ]
                                                )
                                            );

                                        } else {

                                            echo '-';

                                        }

                                        ?>

                                    </td>


                                    <td>

                                        <div class="item-name">

                                            <?= htmlspecialchars(
                                                $item[
                                                    'nama_item'
                                                ]
                                            ); ?>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $item[
                                                    'keterangan'
                                                ]
                                            )
                                        ): ?>

                                            <div class="spec">

                                                <?= htmlspecialchars(
                                                    $item[
                                                        'keterangan'
                                                    ]
                                                ); ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $item[
                                                'jenis'
                                            ] ?? '-'
                                        ); ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $item[
                                                'spesifikasi'
                                            ] ?? '-'
                                        ); ?>

                                    </td>


                                    <td>

                                        <?= number_format(
                                            (float)(
                                                $item[
                                                    'jumlah'
                                                ] ?? 0
                                            ),
                                            2,
                                            ',',
                                            '.'
                                        ); ?>

                                        <?= htmlspecialchars(
                                            $item[
                                                'satuan'
                                            ] ?? ''
                                        ); ?>

                                    </td>


                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $item[
                                                    'tanggal_order'
                                                ]
                                            )
                                        ) {

                                            echo date(
                                                'd/m/Y',
                                                strtotime(
                                                    $item[
                                                        'tanggal_order'
                                                    ]
                                                )
                                            );

                                        } else {

                                            echo '-';

                                        }

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $item[
                                                    'perkiraan_tiba'
                                                ]
                                            )
                                        ) {

                                            echo date(
                                                'd/m/Y',
                                                strtotime(
                                                    $item[
                                                        'perkiraan_tiba'
                                                    ]
                                                )
                                            );

                                        } else {

                                            echo '-';

                                        }

                                        ?>

                                    </td>


                                    <td>

                                        <span
                                            class="
                                                badge
                                                <?= $status_class; ?>
                                            "
                                        >

                                            <?= htmlspecialchars(
                                                $status ?: '-'
                                            ); ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $item[
                                                'pekerjaan'
                                            ] ?? '-'
                                        ); ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-icon">
                        📦
                    </div>


                    <h3>
                        Belum Ada Data Spare Part
                    </h3>


                    <p>
                        Belum terdapat riwayat penggunaan
                        spare part untuk equipment ini.
                    </p>

                </div>


            <?php endif; ?>

        </section>


    <?php else: ?>


        <!-- =============================================
             BELUM PILIH EQUIPMENT
        ============================================== -->

        <section class="table-box">

            <div class="empty">

                <div class="empty-icon">
                    🔧
                </div>


                <h3>
                    Pilih Equipment Terlebih Dahulu
                </h3>


                <p>
                    Pilih equipment pada filter di atas
                    untuk melihat riwayat penggunaan
                    spare part.
                </p>

            </div>

        </section>


    <?php endif; ?>


</main>


</body>

</html>