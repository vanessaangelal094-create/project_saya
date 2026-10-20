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

$selected_equipment = isset($_GET['equipment_id']) ? (int) $_GET['equipment_id'] : 0;
$selected_location = trim($_GET['location'] ?? '');

$periode = $_GET['periode'] ?? 'Bulanan';
$tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

$location_result = $conn->query("
    SELECT DISTINCT location
    FROM equipment
    WHERE location IS NOT NULL AND TRIM(location) <> ''
    ORDER BY location ASC
");
$available_locations = [];
if ($location_result) {
    while ($location_row = $location_result->fetch_assoc()) {
        $available_locations[] = $location_row['location'];
    }
}
if ($selected_location !== '' && !in_array($selected_location, $available_locations, true)) {
    $selected_location = '';
}


/* =========================================================
   DATA EQUIPMENT
========================================================= */

if ($selected_location !== '') {
    $stmt_equipment_list = $conn->prepare("
        SELECT id, equipment_code, equipment_name, equipment_type, location
        FROM equipment WHERE location = ? ORDER BY equipment_code ASC
    ");
    $stmt_equipment_list->bind_param('s', $selected_location);
    $stmt_equipment_list->execute();
    $equipment_result = $stmt_equipment_list->get_result();
} else {
    $equipment_result = $conn->query("
        SELECT id, equipment_code, equipment_name, equipment_type, location
        FROM equipment ORDER BY location ASC, equipment_code ASC
    ");
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


/* =========================================================
   DATA LUBRICANT
========================================================= */

$lubricant_data = [];

$sql_lubricant = "
    SELECT l.id, l.tanggal, l.jenis_pelumas, l.merek, l.jumlah, l.satuan,
           l.hm_km, l.keterangan, e.equipment_code, e.equipment_name,
           e.equipment_type, e.location
    FROM lubricant l
    INNER JOIN equipment e ON l.equipment_id = e.id
";

if ($selected_equipment > 0 && $selected_location !== '') {
    $stmt = $conn->prepare($sql_lubricant . " WHERE l.equipment_id = ? AND e.location = ? ORDER BY l.tanggal DESC, l.id DESC");
    $stmt->bind_param('is', $selected_equipment, $selected_location);
} elseif ($selected_equipment > 0) {
    $stmt = $conn->prepare($sql_lubricant . " WHERE l.equipment_id = ? ORDER BY l.tanggal DESC, l.id DESC");
    $stmt->bind_param('i', $selected_equipment);
} elseif ($selected_location !== '') {
    $stmt = $conn->prepare($sql_lubricant . " WHERE e.location = ? ORDER BY l.tanggal DESC, l.id DESC");
    $stmt->bind_param('s', $selected_location);
} else {
    $stmt = $conn->prepare($sql_lubricant . " ORDER BY l.tanggal DESC, l.id DESC");
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lubricant_data[] = $row;
    }
    $stmt->close();
}

/* =========================================================
   SUMMARY
========================================================= */

$total_replacement = count($lubricant_data);
$total_liter = 0;
$this_month_replacement = 0;
$last_replacement = '-';
$current_month = date('Y-m');

foreach ($lubricant_data as $item) {
    $satuan = strtolower(trim($item['satuan'] ?? ''));
    if (in_array($satuan, ['liter', 'ltr', 'l'], true)) {
        $total_liter += (float) $item['jumlah'];
    }
    if (!empty($item['tanggal']) && date('Y-m', strtotime($item['tanggal'])) === $current_month) {
        $this_month_replacement++;
    }
    if ($last_replacement === '-' && !empty($item['tanggal'])) {
        $last_replacement = date('d/m/Y', strtotime($item['tanggal']));
    }
}

/* =========================================================
      DATA GRAFIK BULANAN
========================================================= */


$monthly_usage = [];

$gunakan_harian = ($periode === 'Harian');

if ($tanggal_mulai !== '' && $tanggal_akhir !== '' && $tanggal_mulai <= $tanggal_akhir) {
    $tanggal_awal = $tanggal_mulai;
    $tanggal_batas = $tanggal_akhir;
} else {
    $tanggal_awal = date('Y-m-d', strtotime('-8 months'));
    $tanggal_batas = date('Y-m-d');
}

if ($gunakan_harian) {
    $tanggal_loop = strtotime($tanggal_awal);
    $tanggal_selesai = strtotime($tanggal_batas);

    while ($tanggal_loop <= $tanggal_selesai) {
        $key = date('Y-m-d', $tanggal_loop);
        $monthly_usage[$key] = [
            'label' => date('d M', $tanggal_loop),
            'value' => 0
        ];
        $tanggal_loop = strtotime('+1 day', $tanggal_loop);
    }
} else {
    $tanggal_loop = strtotime(date('Y-m-01', strtotime($tanggal_awal)));
    $tanggal_selesai = strtotime(date('Y-m-01', strtotime($tanggal_batas)));

    while ($tanggal_loop <= $tanggal_selesai) {
        $key = date('Y-m', $tanggal_loop);
        $monthly_usage[$key] = [
            'label' => date('M Y', $tanggal_loop),
            'value' => 0
        ];
        $tanggal_loop = strtotime('+1 month', $tanggal_loop);
    }
}

foreach ($lubricant_data as $item) {
    if (empty($item['tanggal'])) {
        continue;
    }

    if ($item['tanggal'] < $tanggal_awal || $item['tanggal'] > $tanggal_batas) {
        continue;
    }

    $key = $gunakan_harian
        ? date('Y-m-d', strtotime($item['tanggal']))
        : date('Y-m', strtotime($item['tanggal']));

    $satuan = strtolower(trim($item['satuan'] ?? ''));

    if (isset($monthly_usage[$key]) && in_array($satuan, ['liter', 'ltr', 'l'], true)) {
        $monthly_usage[$key]['value'] += (float) $item['jumlah'];
    }
}


/* =========================================================
   DATA GRAFIK BERDASARKAN JENIS
========================================================= */

$type_usage = [];

foreach ($lubricant_data as $item) {
   if (empty($item['tanggal'])) {
    continue;
} 
if ($tanggal_mulai !== '' && $item['tanggal'] < $tanggal_mulai) {
    continue;
}

if ($tanggal_akhir !== '' && $item['tanggal'] > $tanggal_akhir) {
    continue;
}
    $jenis = trim(
        $item['jenis_pelumas'] ?? ''
    );

    if ($jenis === '') {
        $jenis = 'Tidak diketahui';
    }

    if (!isset($type_usage[$jenis])) {
        $type_usage[$jenis] = 0;
    }

    $type_usage[$jenis]++;

}

/* =========================================================
   DATA UNTUK JAVASCRIPT
========================================================= */

$chart_month_labels = [];

$chart_month_values = [];

foreach ($monthly_usage as $month) {
    $chart_month_labels[] = $month['label'];
    $chart_month_values[] = $month['value'];
}

$chart_type_labels = array_keys($type_usage);
$chart_type_values = array_values($type_usage);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Lubricant - PT Biringkassi Raya</title>

    <link rel="stylesheet" href="../assets/css/app.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        /* khusus halaman Lubricant */

        .summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .summary-value {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--ink);
            line-height: 1.2;
        }

        .summary-unit {
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0;
            color: var(--muted);
            margin-left: 2px;
        }

        .filter-form {
            display: flex;
            gap: 14px;
            align-items: flex-end;
        }

        .filter-form .form-group {
            flex: 1;
        }

        .equipment-info {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
        }

        .equipment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .equipment-header h2 {
            font-size: 16px;
            font-weight: 600;
        }

        .equipment-code {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .info-item {
            background: #fafafd;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px;
        }

        .info-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--ink);
        }

        .chart-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .chart-box {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 20px;
            min-width: 0;
        }

        .chart-header {
            margin-bottom: 14px;
        }

        .chart-header h2 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .chart-header p {
            color: var(--muted);
            font-size: 13px;
        }

        .chart-container {
            position: relative;
            height: 270px;
        }

        .period-filter input,
        .period-filter select {
            width: auto;
            margin-top: 6px;
        }

        .table-header h2 {
            font-size: 16px;
            font-weight: 600;
        }

        .table-header p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 3px;
        }

        .empty h3 {
            color: var(--ink);
            margin-bottom: 6px;
        }

        @media (max-width: 1100px) {

            .equipment-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .chart-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 800px) {

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>

<?php
$base   = '../';
$active = 'lubricant';
include $base . 'includes/sidebar.php';
?>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main">


    <div class="header">

        <div class="header-left">

            <h1>
                Lubricant
            </h1>

            <p>
                Rekam jejak penggunaan pelumas pada setiap alat berat.
            </p>

        </div>

        <a href="tambah.php" class="btn-primary">
            + Tambah Data Pelumas
        </a>

    </div>


    <!-- =================================================
         FILTER EQUIPMENT
    ================================================= -->

    <section class="filter-box">

        <div class="box-title">

            <div class="filter-title" style="margin-bottom:0;">
                🔎 Filter Lokasi dan Equipment
            </div>

            <a href="javascript:history.back()" class="btn-reset">
                ← Kembali
            </a>

        </div>

        <form
            id="filterForm"
            method="GET"
            action="index.php"
            class="filter-form"
        >

            <div class="form-group">
                <label for="location">Lokasi</label>
                <select name="location" id="location" onchange="document.getElementById('equipment_id').value=''; this.form.submit();">
                    <option value="">Semua Lokasi</option>
                    <?php foreach ($available_locations as $location_option): ?>
                        <option value="<?= htmlspecialchars($location_option, ENT_QUOTES, 'UTF-8'); ?>" <?= $selected_location === $location_option ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($location_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">

                <label for="equipment_id">

                    Equipment

                </label>

                <select

                    name="equipment_id"

                    id="equipment_id"

                >

                    <option value="">

                        Pilih Equipment

                    </option>

                    <?php if ($equipment_result): ?>

                        <?php while (

                            $equipment =

                            $equipment_result->fetch_assoc()

                        ): ?>

                            <option

                                value="<?= (int) $equipment['id']; ?>"

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

                        <?php endwhile; ?>

                    <?php endif; ?>

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

    <?php if ($selected_equipment_data || $selected_location !== ''): ?>

        <!-- =================================================

             EQUIPMENT INFO

        ================================================= -->

        <?php if ($selected_equipment_data): ?>
        <section class="equipment-info">

            <div class="equipment-header">

                <h2>
                    Informasi Equipment
                </h2>

                <span class="equipment-code">

                    <?= htmlspecialchars(

                        $selected_equipment_data['equipment_code']

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

                            $selected_equipment_data['equipment_name']

                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">

                        Jenis

                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(

                            $selected_equipment_data['equipment_type']

                            ?? '-'

                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">

                        HM Saat Ini

                    </div>

                    <div class="info-value">

                        <?= number_format(

                            (float) (

                                $selected_equipment_data['hm_current']

                                ?? 0

                            ),

                            0,

                            ',',

                            '.'

                        ); ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">

                        Total Penggantian

                    </div>

                    <div class="info-value">

                        <?= $total_replacement; ?>

                        Kali

                    </div>

                </div>

            </div>

        </section>
        <?php endif; ?>


        <!-- =================================================

             SUMMARY

        ================================================= -->

        <section class="summary-grid">

            <div class="summary-card">

                <div class="summary-icon icon-blue">🛢️</div>

                <div>

                    <div class="summary-title">
                        Total Penggunaan
                    </div>

                    <div class="summary-value">

                        <?= number_format(

                            $total_liter,

                            2,

                            ',',

                            '.'

                        ); ?>

                        <span class="summary-unit">

                            Liter

                        </span>

                    </div>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon icon-yellow">🔁</div>

                <div>

                    <div class="summary-title">
                        Total Penggantian
                    </div>

                    <div class="summary-value">

                        <?= $total_replacement; ?>

                        <span class="summary-unit">

                            Kali

                        </span>

                    </div>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon icon-green">📅</div>

                <div>

                    <div class="summary-title">
                        Penggantian Bulan Ini
                    </div>

                    <div class="summary-value">

                        <?= $this_month_replacement; ?>

                        <span class="summary-unit">

                            Kali

                        </span>

                    </div>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon icon-orange">🕒</div>

                <div>

                    <div class="summary-title">
                        Penggantian Terakhir
                    </div>

                    <div class="summary-value"

                         style="font-size:20px;">

                        <?= $last_replacement; ?>

                    </div>

                </div>

            </div>

        </section>


        <!-- =================================================

             CHARTS

        ================================================= -->

        <section class="chart-grid">


            <div class="chart-box">

                <div class="chart-header">

                    <h2>
                        Tren Penggunaan Pelumas
                    </h2>

                    <p>
                        Penggunaan pelumas berdasarkan bulan.
                    </p>

                    <div class="period-filter" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin:12px 0;">
                        <div>
                            <label for="periode">Periode Grafik</label>
                            <select id="periode" name="periode" form="filterForm">
                                <option value="Harian" <?= $periode === 'Harian' ? 'selected' : ''; ?>>Harian</option>
                                <option value="Bulanan" <?= $periode === 'Bulanan' ? 'selected' : ''; ?>>Bulanan</option>
                                <option value="Khusus" <?= $periode === 'Khusus' ? 'selected' : ''; ?>>Periode Khusus</option>
                            </select>
                        </div>

                        <div>
                            <label for="tanggal_mulai">Tanggal Mulai</label>
                            <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                                   form="filterForm"
                                   value="<?= htmlspecialchars($tanggal_mulai); ?>">
                        </div>

                        <div>
                            <label for="tanggal_akhir">Tanggal Akhir</label>
                            <input type="date" id="tanggal_akhir" name="tanggal_akhir"
                                   form="filterForm"
                                   value="<?= htmlspecialchars($tanggal_akhir); ?>">
                        </div>

                        <button type="submit" form="filterForm" class="btn-filter">
                            Terapkan Periode
                        </button>
                    </div>

                </div>

                <div class="chart-container">

                    <canvas id="monthlyChart"></canvas>

                </div>

            </div>

            <div class="chart-box">

                <div class="chart-header">
                    <h2>
                        Penggantian Berdasarkan Jenis
                    </h2>

                    <p>
                        Jumlah penggantian berdasarkan jenis pelumas.
                    </p>

                </div>

                <div class="chart-container">
                    <canvas id="typeChart"></canvas>

                </div>

            </div>

        </section>


        <!-- =================================================

             TABLE

        ================================================= -->

        <section class="table-box">

            <div class="table-header">

                <div>

                    <h2>

                        Riwayat Penggunaan Pelumas

                    </h2>

                    <p>

                        Daftar penggunaan dan penggantian pelumas

                        berdasarkan equipment.

                    </p>

                </div>

            </div>


            <?php if (!empty($lubricant_data)): ?>

                <div class="table-wrapper">

                    <table style="min-width: 950px;">

                        <thead>

                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>ID Unit</th>
                                <th>Jenis Pelumas</th>
                                <th>Merek</th>
                                <th>Jumlah</th>
                                <th>Satuan</th>
                                <th>HM</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php

                            $no = 1;

                            foreach (

                                $lubricant_data

                                as $item

                            ):

                            ?>

                                <tr>

                                    <td>

                                        <?= $no++; ?>

                                    </td>


                                    <td>

                                        <?= !empty(

                                            $item['tanggal']

                                        )

                                            ? date(

                                                'd/m/Y',

                                                strtotime(

                                                    $item['tanggal']

                                                )

                                            )

                                            : '-';

                                        ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(

                                                $item['equipment_code']

                                            ); ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <div class="item-name" style="font-weight:600; color:var(--ink);">

                                            <?= htmlspecialchars(

                                                $item['jenis_pelumas']

                                            ); ?>

                                        </div>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(

                                            $item['merek']

                                            ?? '-'

                                        ); ?>

                                    </td>


                                    <td>

                                        <?= number_format(

                                            (float) (

                                                $item['jumlah']

                                                ?? 0

                                            ),

                                            2,

                                            ',',

                                            '.'

                                        ); ?>

                                    </td>


                                    <td>

                                        <span class="badge badge-blue">
                                            <?= htmlspecialchars(
                                                $item['satuan']
                                                ?? '-'
                                            ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $item['hm_km'] !== null
                                            && $item['hm_km'] !== ''
                                            ? number_format(
                                                (float)
                                                $item['hm_km'],
                                                2,
                                                ',',

                                                '.'
                                            )
                                            : '-';
                                        ?>
                                    </td>
                                    <td>

                                        <?= htmlspecialchars(
                                            $item['keterangan']
                                            ?? '-'
                                        ); ?>
                                    </td>

                                    <td>
                                        <a
                                            href="delete.php?id=<?= (int) $item['id']; ?>"
                                            onclick="return confirm('Yakin ingin menghapus data pelumas ini?');"
                                            class="action-btn btn-delete"
                                        >
                                            Hapus
                                        </a>
                                    </td>

                                </tr>
                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">
                        🛢️
                    </div>

                    <h3>
                        Belum Ada Data Pelumas
                    </h3>

                    <p>
                        Belum terdapat riwayat penggunaan pelumas untuk pilihan lokasi/equipment ini.
                    </p>
                </div>

            <?php endif; ?>
        </section>

  <?php else: ?>

<!-- =================================================
     REKAP SEMUA EQUIPMENT
================================================= -->

<?php
$stmt_all = $conn->prepare("
    SELECT
        COUNT(*) AS total_replacement,
        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(TRIM(satuan)) IN ('liter', 'ltr', 'l')
                    THEN jumlah
                    ELSE 0
                END
            ), 0
        ) AS total_liter,
        COALESCE(
            SUM(
                CASE
                    WHEN DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                    THEN 1
                    ELSE 0
                END
            ), 0
        ) AS this_month_replacement,
        MAX(tanggal) AS last_replacement
    FROM lubricant

");



$stmt_all->execute();

$all_summary = $stmt_all->get_result()->fetch_assoc();

$stmt_all->close();



$all_total_liter = (float) ($all_summary['total_liter'] ?? 0);

$all_total_replacement = (int) ($all_summary['total_replacement'] ?? 0);

$all_this_month = (int) ($all_summary['this_month_replacement'] ?? 0);



$all_last_replacement = '-';



if (!empty($all_summary['last_replacement'])) {

    $all_last_replacement = date(

        'd/m/Y',

        strtotime($all_summary['last_replacement'])

    );

}

?>



<section class="summary-grid">



    <div class="summary-card">

        <div class="summary-icon icon-blue">🛢️</div>

        <div>

            <div class="summary-title">

                Total Penggunaan

            </div>



            <div class="summary-value">

                <?= number_format($all_total_liter, 2); ?>

                <span class="summary-unit">Liter</span>

            </div>

        </div>

    </div>



    <div class="summary-card">

        <div class="summary-icon icon-yellow">🔁</div>

        <div>

            <div class="summary-title">

                Total Penggantian

            </div>



            <div class="summary-value">

                <?= $all_total_replacement; ?>

                <span class="summary-unit">Kali</span>

            </div>

        </div>

    </div>



    <div class="summary-card">

        <div class="summary-icon icon-green">📅</div>

        <div>

            <div class="summary-title">

                Penggantian Bulan Ini

            </div>



            <div class="summary-value">

                <?= $all_this_month; ?>

                <span class="summary-unit">Kali</span>

            </div>

        </div>

    </div>



    <div class="summary-card">

        <div class="summary-icon icon-orange">🕒</div>

        <div>

            <div class="summary-title">

                Penggantian Terakhir

            </div>



            <div class="summary-value" style="font-size:20px;">

                <?= $all_last_replacement; ?>

            </div>

        </div>

    </div>



</section>



<?php

/* =================================================

   DATA GRAFIK SEMUA EQUIPMENT

================================================= */

$all_monthly_usage = [];

for ($month = 1; $month <= 9; $month++) {

    $month_key = '2026-' . str_pad($month, 2, '0', STR_PAD_LEFT);

    $month_label = date(
        'M Y',
        strtotime($month_key . '-01')
    );
    $all_monthly_usage[$month_key] = [
        'label' => $month_label,
        'value' => 0
    ];
}

$all_type_usage = [];

$stmt_chart = $conn->prepare("
    SELECT
        l.tanggal,
        l.jumlah,
        l.satuan,
        l.jenis_pelumas
    FROM lubricant l
    INNER JOIN equipment e ON l.equipment_id = e.id
    WHERE LOWER(TRIM(l.satuan)) IN ('liter', 'ltr', 'l')
      AND (? = '' OR e.location = ?)
    ORDER BY l.tanggal ASC
");

$stmt_chart->bind_param(
    "ss",
    $selected_location,
    $selected_location
);

$stmt_chart->execute();
$result_chart = $stmt_chart->get_result();

if ($result_chart) {
    while ($row = $result_chart->fetch_assoc()) {
        /* Grafik bulanan */
        if (!empty($row['tanggal'])) {
            $month_key = date(
                'Y-m',
                strtotime($row['tanggal'])
            );

            if (isset($all_monthly_usage[$month_key])) {

                $satuan = strtolower(

                    trim($row['satuan'] ?? '')

                );

                if (in_array($satuan, ['liter', 'ltr', 'l'])) {

                    $all_monthly_usage[$month_key]['value']

                        += (float) $row['jumlah'];

                }

            }

        }

/* Grafik berdasarkan jenis */

$jenis = strtoupper(trim(

    $row['jenis_pelumas'] ?? ''

));

if ($jenis === '') {

    $jenis = 'TIDAK DIKETAHUI';

}

// Samakan variasi penulisan nama pelumas

$jenis_normalisasi = [

    'TURALIK 48' => 'TURALIK 48',

    'MEDITRAN S 40' => 'MEDITRAN S 40',

    'RORED HAD-90' => 'RORED HAD-90',

    'RORED 90' => 'RORED 90',

];



$jenis = $jenis_normalisasi[$jenis] ?? $jenis;



if (!isset($all_type_usage[$jenis])) {

    $all_type_usage[$jenis] = 0;

}

$all_type_usage[$jenis]++;
    }
}


$all_chart_month_labels = [];

$all_chart_month_values = [];

foreach ($all_monthly_usage as $month) {

    $all_chart_month_labels[] = $month['label'];

    $all_chart_month_values[] = $month['value'];

}

$all_chart_type_labels = array_keys(

    $all_type_usage

);


$all_chart_type_values = array_values(

    $all_type_usage

);

?>



<section class="chart-grid">



    <div class="chart-box">



        <div class="chart-header">



            <h2>

                Tren Penggunaan Pelumas

            </h2>



            <p>

                Total penggunaan pelumas seluruh equipment

                dalam 9 bulan terakhir.

            </p>



        </div>



        <div class="chart-container">

            <canvas id="allMonthlyChart"></canvas>

        </div>



    </div>





    <div class="chart-box">



        <div class="chart-header">



            <h2>

                Penggantian Berdasarkan Jenis

            </h2>



            <p>

                Jumlah penggantian berdasarkan jenis pelumas

                pada seluruh equipment.

            </p>



        </div>



        <div class="chart-container">

            <canvas id="allTypeChart"></canvas>

        </div>

    </div>

</section>

<?php endif; ?>

</main>

<script>

Chart.defaults.font.family = "Inter, Arial, sans-serif";

const chartColors = [
    '#7c5cfc', '#f59e0b', '#22c55e', '#ef4444',
    '#3b82f6', '#ec4899', '#14b8a6', '#a78bfa'
];

<?php if ($selected_equipment_data || $selected_location !== ''): ?>


/* =====================================================

   GRAFIK EQUIPMENT TERPILIH

===================================================== */

const monthlyLabels = <?= json_encode($chart_month_labels); ?>;

const monthlyValues = <?= json_encode($chart_month_values); ?>;

const monthlyCanvas = document.getElementById('monthlyChart');

if (monthlyCanvas) {

    new Chart(monthlyCanvas, {

        type: 'line',

        data: {

            labels: monthlyLabels,

            datasets: [{

                label: 'Penggunaan Pelumas (Liter)',

                data: monthlyValues,

                borderColor: '#7c5cfc',

                backgroundColor: '#7c5cfc',

                borderWidth: 3,

                tension: 0.35,

                fill: false

            }]

        },



        options: {

            responsive: true,

            maintainAspectRatio: false,



            plugins: {

                legend: {

                    display: true

                }

            },



            scales: {

                y: {

                    beginAtZero: true

                }

            }

        }

    });



}





/* =====================================================

   GRAFIK JENIS EQUIPMENT TERPILIH

===================================================== */



const typeLabels = <?= json_encode($chart_type_labels); ?>;

const typeValues = <?= json_encode($chart_type_values); ?>;



const typeCanvas = document.getElementById('typeChart');

if (typeCanvas) {



    new Chart(typeCanvas, {

        type: 'doughnut',



        data: {

            labels: typeLabels,



            datasets: [{

                label: 'Jumlah Penggantian',

                data: typeValues,

                backgroundColor: chartColors,

                borderWidth: 2

            }]

        },



        options: {

            responsive: true,

            maintainAspectRatio: false,



            plugins: {

                legend: {

                    position: 'bottom'

                }

            }

        }

    });



}





<?php else: ?>





/* =====================================================

   GRAFIK SEMUA EQUIPMENT

===================================================== */



const allMonthlyLabels =

    <?= json_encode($all_chart_month_labels); ?>;



const allMonthlyValues =

    <?= json_encode($all_chart_month_values); ?>;



const allMonthlyCanvas =

    document.getElementById('allMonthlyChart');



if (allMonthlyCanvas) {



    new Chart(allMonthlyCanvas, {

        type: 'line',



        data: {

            labels: allMonthlyLabels,



            datasets: [{

                label: 'Penggunaan Pelumas (Liter)',

                data: allMonthlyValues,

                borderColor: '#7c5cfc',

                backgroundColor: '#7c5cfc',

                borderWidth: 3,

                tension: 0.35,

                fill: false

            }]

        },



        options: {

            responsive: true,

            maintainAspectRatio: false,



            plugins: {

                legend: {

                    display: true

                }

            },



            scales: {

                y: {

                    beginAtZero: true

                }

            }

        }

    });



}





/* =====================================================

   GRAFIK JENIS SEMUA EQUIPMENT

===================================================== */



const allTypeLabels =

    <?= json_encode($all_chart_type_labels); ?>;



const allTypeValues =

    <?= json_encode($all_chart_type_values); ?>;



const allTypeCanvas =

    document.getElementById('allTypeChart');



if (allTypeCanvas) {



    new Chart(allTypeCanvas, {

        type: 'doughnut',



        data: {

            labels: allTypeLabels,



            datasets: [{

                label: 'Jumlah Penggantian',

                data: allTypeValues,

                backgroundColor: chartColors,

                borderWidth: 2

            }]

        },



        options: {

            responsive: true,

            maintainAspectRatio: false,



            plugins: {

                legend: {

                    position: 'bottom'

                }

            }
        }
    });
}

<?php endif; ?>

</script>

</body>

</html>