
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

function fuel_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_fuel_token'])) {
    $_SESSION['csrf_fuel_token'] = bin2hex(random_bytes(32));
}

$error = '';

$equipment_id = (int)($_POST['equipment_id'] ?? 0);
$tanggal = $_POST['tanggal'] ?? date('Y-m-d');
$jumlah_liter = $_POST['jumlah_liter'] ?? '';
$hm_km = $_POST['hm_km'] ?? '';
$keterangan = trim($_POST['keterangan'] ?? '');

/* =========================
   SIMPAN DATA BBM
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_valid = false;

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $m)) {
        $tanggal_valid = checkdate(
            (int)$m[2],
            (int)$m[3],
            (int)$m[1]
        );
    }

    $jumlah_valid = $jumlah_liter !== ''
        && is_numeric($jumlah_liter)
        && (float)$jumlah_liter > 0;

    $hm_valid = $hm_km === ''
        || (is_numeric($hm_km) && (float)$hm_km >= 0);

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_fuel_token'], $token)) {
        $error = "Form tidak berlaku. Silakan muat ulang halaman.";
    } elseif ($equipment_id <= 0) {
        $error = "Silakan pilih unit alat berat.";
    } elseif (!$tanggal_valid) {
        $error = "Tanggal pemakaian tidak valid.";
    } elseif (!$jumlah_valid) {
        $error = "Jumlah BBM harus lebih besar dari 0.";
    } elseif (!$hm_valid) {
        $error = "HM harus berupa angka 0 atau lebih.";
    } else {
        $cek = $conn->prepare(
            "SELECT id FROM equipment WHERE id = ? LIMIT 1"
        );

        $unit_ada = false;

        if ($cek) {
            $cek->bind_param("i", $equipment_id);
            $cek->execute();

            $hasil_cek = $cek->get_result();
            $unit_ada = $hasil_cek->num_rows > 0;

            $cek->close();
        }

        if (!$unit_ada) {
            $error = "Unit tidak ditemukan. Silakan pilih unit yang tersedia.";
        } else {
            $jumlah_value = (float)$jumlah_liter;
            $hm_value = ($hm_km === '')
                ? null
                : (float)$hm_km;

            $stmt = $conn->prepare("
                INSERT INTO fuel_usage
                    (equipment_id, tanggal, jumlah_liter, hm_km, keterangan)
                VALUES (?, ?, ?, ?, ?)
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "isdds",
                    $equipment_id,
                    $tanggal,
                    $jumlah_value,
                    $hm_value,
                    $keterangan
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    header(
                        "Location: index.php?saved=1&equipment_id="
                        . $equipment_id
                    );
                    exit;
                }

                $error = "Data BBM gagal disimpan. Silakan coba lagi.";
                $stmt->close();
            } else {
                $error = "Gagal menyiapkan penyimpanan data BBM.";
            }
        }
    }
}

/* =========================
   DATA EQUIPMENT
========================= */

$equipment_options = [];

$result = $conn->query("
    SELECT id, equipment_code, equipment_name, location
    FROM equipment
    ORDER BY equipment_code ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $equipment_options[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Data BBM - PT Biringkassi Raya</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f8fc;
            color: #1f2937;
            font-size: 15px;
        }

        /* =========================
           SIDEBAR - SAMA DENGAN DASHBOARD
        ========================= */

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
            background: rgba(255, 255, 255, 0.18);
        }

        .menu-dropdown > a {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-arrow {
            margin-left: 8px;
            flex-shrink: 0;
        }

        .submenu {
            list-style: none;
            display: none;
            margin-top: 3px;
            padding: 0;
        }

        .submenu li {
            margin-bottom: 3px;
        }

        .submenu a {
            padding: 10px 14px 10px 30px;
            font-size: 13px;
            background: rgba(255, 255, 255, 0.08);
        }

        /* =========================
           MAIN CONTENT
        ========================= */

        .main {
            margin-left: 240px;
            min-height: 100vh;
            padding: 32px;
        }

        .breadcrumb {
            margin-bottom: 12px;
            color: #64748b;
            font-size: 13px;
        }

        .page-header {
            margin-bottom: 22px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #0759bd;
            margin-bottom: 5px;
        }

        .page-header p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        /* =========================
           FORM TAMBAH DATA BBM
        ========================= */

        .form-card {
            max-width: 100%;
            padding: 25px;
            background: white;
            border: 1px solid #dce8f3;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        .form-title {
            margin-bottom: 8px;
            font-size: 18px;
            color: #0759bd;
        }

        .form-description {
            margin-bottom: 24px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 24px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: bold;
            color: #334155;
        }

        .required {
            color: #dc2626;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d9e2ec;
            border-radius: 8px;
            background: white;
            color: #1f2937;
            font-family: inherit;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            min-height: 48px;
        }

        .form-group textarea {
            min-height: 110px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0759bd;
            box-shadow: 0 0 0 3px rgba(7, 89, 189, 0.08);
        }

        .help-text {
            display: block;
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .unit-info {
            display: none;
            margin-top: 10px;
            padding: 12px;
            border-radius: 8px;
            background: #eef5fc;
            color: #0759bd;
            font-size: 13px;
            line-height: 1.6;
        }

        .error-message {
            margin-bottom: 18px;
            padding: 13px 15px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.6;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #edf2f7;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            color: #475569;
            font-family: inherit;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            border-color: #0759bd;
            background: #0759bd;
            color: white;
        }

        .btn-primary:hover {
            background: #06499b;
        }

        .btn-secondary:hover {
            background: #f8fafc;
        }

        .empty-state {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {
            .main {
                padding: 24px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 18px;
            }

            .page-header h1 {
                font-size: 26px;
            }

            .form-card {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<!-- =========================
     SIDEBAR
========================= -->

<aside class="sidebar">

    <div class="logo">
        <img
            src="../assets/logo.png"
            alt="Logo PT Biringkassi Raya"
        >

        <div class="logo-text">
            PT BIRINGKASSI RAYA
        </div>
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
            <a href="#">
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
            <a href="#">
                Breakdown &amp; Failure
            </a>
        </li>

        <li class="menu-dropdown">
            <a
                href="javascript:void(0);"
                onclick="
                    var submenu = document.getElementById('spareSubmenu');
                    var arrow = document.getElementById('spareArrow');

                    if (
                        submenu.style.display === 'none' ||
                        submenu.style.display === ''
                    ) {
                        submenu.style.display = 'block';
                        arrow.textContent = '▼';
                    } else {
                        submenu.style.display = 'none';
                        arrow.textContent = '▶';
                    }
                "
            >
                Spare Part &amp; Lubricant
                <span id="spareArrow" class="menu-arrow">▶</span>
            </a>

            <ul id="spareSubmenu" class="submenu">
                <li>
                    <a href="../sparepart/index.php">
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
            <a href="index.php" class="active">
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
                Kinerja &amp; Evaluasi
            </a>
        </li>

        <li>
            <a href="#">
                Laporan
            </a>
        </li>

    </ul>
</aside>

<!-- =========================
     MAIN CONTENT
========================= -->

<main class="main">

    <div class="breadcrumb">
        Home &nbsp;›&nbsp;
        <span>Fuel Performance</span>
        &nbsp;›&nbsp;
        <span>Tambah Data BBM</span>
    </div>

    <div class="page-header">
        <h1>Tambah Data BBM</h1>

        <p>
            Masukkan catatan pemakaian bahan bakar untuk unit alat berat.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error-message">
            <?= fuel_h($error); ?>
        </div>
    <?php endif; ?>

    <section class="form-card">

        <h2 class="form-title">
            Informasi Pemakaian BBM
        </h2>

        <p class="form-description">
            Kolom bertanda
            <span class="required">*</span>
            wajib diisi.
        </p>

        <?php if (count($equipment_options) > 0): ?>

            <form method="POST" action="tambah.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= fuel_h($_SESSION['csrf_fuel_token']); ?>"
                >

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
                                -- Pilih unit alat berat --
                            </option>

                            <?php foreach ($equipment_options as $unit): ?>
                                <option
                                    value="<?= (int)$unit['id']; ?>"
                                    data-name="<?= fuel_h($unit['equipment_name']); ?>"
                                    data-location="<?= fuel_h($unit['location'] ?? '-'); ?>"
                                    <?= $equipment_id === (int)$unit['id'] ? 'selected' : ''; ?>
                                >
                                    <?= fuel_h(
                                        $unit['equipment_code']
                                        . ' - '
                                        . $unit['equipment_name']
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="unit-info" id="unitInfo"></div>
                    </div>

                    <div class="form-group">
                        <label for="tanggal">
                            Tanggal Pemakaian
                            <span class="required">*</span>
                        </label>

                        <input
                            type="date"
                            name="tanggal"
                            id="tanggal"
                            value="<?= fuel_h($tanggal); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="jumlah_liter">
                            Jumlah BBM (Liter)
                            <span class="required">*</span>
                        </label>

                        <input
                            type="number"
                            name="jumlah_liter"
                            id="jumlah_liter"
                            min="0.01"
                            step="0.01"
                            placeholder="Contoh: 50.00"
                            value="<?= fuel_h($jumlah_liter); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="hm_km">
                            HM Saat Pengisian
                        </label>

                        <input
                            type="number"
                            name="hm_km"
                            id="hm_km"
                            min="0"
                            step="0.01"
                            placeholder="Contoh: 2150"
                            value="<?= fuel_h($hm_km); ?>"
                        >

                        <span class="help-text">
                            Boleh dikosongkan jika data belum tersedia.
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="keterangan">
                            Keterangan
                        </label>

                        <textarea
                            name="keterangan"
                            id="keterangan"
                            placeholder="Keterangan pemakaian BBM"
                        ><?= fuel_h($keterangan); ?></textarea>
                    </div>

                </div>

                <div class="form-actions">

                    <a href="index.php" class="btn btn-secondary">
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Simpan Data BBM
                    </button>

                </div>

            </form>

        <?php else: ?>

            <p class="empty-state">
                Belum ada unit yang terdaftar pada Equipment Master.
                Tambahkan unit terlebih dahulu sebelum memasukkan data BBM.
            </p>

            <div class="form-actions">
                <a
                    href="../equipment/index.php"
                    class="btn btn-primary"
                >
                    Buka Equipment Master
                </a>
            </div>

        <?php endif; ?>

    </section>

</main>

<script>
function toggleSubmenu() {
    const submenu = document.getElementById('spareSubmenu');
    const arrow = document.getElementById('spareArrow');

    if (!submenu || !arrow) {
        return;
    }

    if (
        submenu.style.display === 'none' ||
        submenu.style.display === ''
    ) {
        submenu.style.display = 'block';
        arrow.textContent = '▼';
    } else {
        submenu.style.display = 'none';
        arrow.textContent = '▶';
    }
}

/* Informasi unit yang dipilih */
const equipmentSelect = document.getElementById('equipment_id');
const unitInfo = document.getElementById('unitInfo');

function updateUnitInfo() {
    if (!equipmentSelect || !unitInfo) {
        return;
    }

    const option = equipmentSelect.options[
        equipmentSelect.selectedIndex
    ];

    if (!option || !option.value) {
        unitInfo.style.display = 'none';
        unitInfo.textContent = '';
        return;
    }

    unitInfo.textContent =
        'Nama alat: ' + (option.dataset.name || '-') +
        ' | Lokasi: ' + (option.dataset.location || '-');

    unitInfo.style.display = 'block';
}

if (equipmentSelect) {
    equipmentSelect.addEventListener('change', updateUnitInfo);
    updateUnitInfo();
}
</script>

</body>
</html>
