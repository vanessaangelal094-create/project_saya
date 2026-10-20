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

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>

        /* khusus halaman tambah data BBM */

        .form-card {
            padding: 24px;
        }

        .form-title {
            margin-bottom: 6px;
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
        }

        .form-description {
            margin-bottom: 22px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .form-card .form-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 24px;
        }

        .form-group {
            min-width: 0;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .help-text {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .unit-info {
            display: none;
            margin-top: 6px;
            padding: 12px;
            border-radius: var(--radius-sm);
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 13px;
            line-height: 1.6;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
        }

        .empty-state {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        @media (max-width: 900px) {

            .form-card .form-grid {
                grid-template-columns: 1fr;
            }

            .form-card .form-group.full {
                grid-column: auto;
            }

        }

    </style>
</head>

<body>

<?php
$base   = '../';
$active = 'fuel';
include $base . 'includes/sidebar.php';
?>

<main class="main">

    <div class="breadcrumb">
        <a href="../index.php">Home</a>
        &nbsp;›&nbsp;
        <a href="index.php">Fuel Performance</a>
        &nbsp;›&nbsp;
        <span>Tambah Data BBM</span>
    </div>

    <div class="header">
        <div class="header-left">
            <h1>Tambah Data BBM</h1>

            <p>
                Masukkan catatan pemakaian bahan bakar untuk unit alat berat.
            </p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error-box">
            <?= fuel_h($error); ?>
        </div>
    <?php endif; ?>

    <section class="form-card">

        <h2 class="form-title">
            Informasi Pemakaian BBM
        </h2>

        <p class="form-description">
            Kolom bertanda
            <span class="required" style="color: var(--red);">*</span>
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

                    <a href="index.php" class="btn-cancel">
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="btn-primary"
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
                    class="btn-primary"
                >
                    Buka Equipment Master
                </a>
            </div>

        <?php endif; ?>

    </section>

</main>

<script>
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