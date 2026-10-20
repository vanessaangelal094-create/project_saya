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


/* =========================================================
   FORMAT TANGGAL
   (tanggal kosong ditampilkan sebagai "-")
========================================================= */

function formatTanggal($value)
{
    if (!empty($value)) {
        return date('d/m/Y', strtotime($value));
    }

    return '-';
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

    <!-- Terapkan tema tersimpan sebelum halaman tampil -->
    <script>
        try {
            document.documentElement.setAttribute(
                'data-theme',
                localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'
            );
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>

        /* khusus halaman ini */

        .filter-form.cols-3 {
            grid-template-columns: 1fr auto auto;
        }

        .title-with-icon {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .title-with-icon svg {
            color: var(--primary);
        }

        .equipment-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .equipment-top h2 {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.2px;
            color: var(--ink);
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .info-item {
            background: var(--th-bg);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .info-item .info-label {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .info-item .info-value {
            font-size: 15px;
        }

        .table-sub {
            margin-top: 2px;
            font-size: 12px;
            font-weight: 400;
            color: var(--muted);
        }

        .sparepart-table {
            min-width: 1100px;
        }

        td.wrap {
            white-space: normal;
            min-width: 170px;
        }

        .item-name {
            font-weight: 600;
            color: var(--ink);
        }

        .spec {
            margin-top: 3px;
            font-size: 12px;
            color: var(--muted);
        }

        .empty svg {
            display: block;
            margin: 0 auto 10px;
            color: var(--muted);
        }

        .empty h3 {
            margin-bottom: 4px;
            font-size: 15px;
            font-weight: 600;
            color: var(--ink);
        }

        @media (max-width: 1100px) {

            .equipment-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 650px) {

            .equipment-grid {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>


<body>

    <?php
    $base   = '../';
    $active = 'sparepart';
    include $base . 'includes/sidebar.php';
    ?>


    <main class="main">


        <!-- HEADER -->

        <div class="header">

            <div class="header-left">

                <div class="breadcrumb">

                    <a href="../index.php">
                        Home
                    </a>

                    &nbsp;›&nbsp;

                    <span>Spare Part</span>

                </div>

                <h1>
                    Spare Part
                </h1>

                <p>
                    Rekam jejak penggunaan spare part pada setiap alat berat.
                </p>

            </div>

        </div>


        <!-- =================================================
             FILTER EQUIPMENT
        ================================================= -->

        <section class="filter-box">

            <div class="filter-title title-with-icon">

                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"
                     fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m20 20-3.5-3.5"/>
                </svg>

                Pilih Equipment

            </div>


            <form
                method="GET"
                action="index.php"
                class="filter-form cols-3"
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

            <section class="box">

                <div class="equipment-top">

                    <h2>
                        Informasi Equipment
                    </h2>


                    <span class="badge badge-blue">

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

                    <div>

                        <h2>
                            Riwayat Penggunaan Spare Part
                        </h2>

                        <p class="table-sub">
                            Daftar spare part yang digunakan
                            berdasarkan riwayat maintenance equipment.
                        </p>

                    </div>

                </div>


                <?php if (!empty($sparepart_data)): ?>


                    <div class="table-wrapper">

                        <table class="sparepart-table">

                            <thead>

                                <tr>

                                    <th>No</th>

                                    <th>Tanggal Maintenance</th>

                                    <th>Nama Spare Part</th>

                                    <th>Jenis</th>

                                    <th>Spesifikasi</th>

                                    <th>Jumlah</th>

                                    <th>Tanggal Order</th>

                                    <th>Perkiraan Tiba</th>

                                    <th>Status Spare Part</th>

                                    <th>Pekerjaan</th>

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
                                        'badge';


                                    if (
                                        $status ===
                                        'Menunggu Order'
                                    ) {

                                        $status_class =
                                            'badge badge-yellow';

                                    } elseif (
                                        $status ===
                                        'Sudah Diorder'
                                    ) {

                                        $status_class =
                                            'badge badge-blue';

                                    } elseif (
                                        $status ===
                                        'Dalam Pengiriman'
                                    ) {

                                        $status_class =
                                            'badge badge-purple';

                                    } elseif (
                                        $status ===
                                        'Sudah Tiba'
                                    ) {

                                        $status_class =
                                            'badge badge-green';

                                    }

                                ?>

                                    <tr>

                                        <td>
                                            <?= $no++; ?>
                                        </td>


                                        <td>
                                            <?= formatTanggal(
                                                $item['tanggal_jadwal']
                                            ); ?>
                                        </td>


                                        <td class="wrap">

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


                                        <td class="wrap">

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
                                            <?= formatTanggal(
                                                $item['tanggal_order']
                                            ); ?>
                                        </td>


                                        <td>
                                            <?= formatTanggal(
                                                $item['perkiraan_tiba']
                                            ); ?>
                                        </td>


                                        <td>

                                            <span class="<?= $status_class; ?>">

                                                <?= htmlspecialchars(
                                                    $status ?: '-'
                                                ); ?>

                                            </span>

                                        </td>


                                        <td class="wrap">

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

                        <svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"
                             fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/>
                            <path d="m3 8 9 5 9-5M12 13v8"/>
                        </svg>


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

                    <svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"
                         fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a4 4 0 0 0-5.4 5.1L3.5 17.2a2 2 0 0 0 2.8 2.8l5.8-5.8a4 4 0 0 0 5.1-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6Z"/>
                    </svg>


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