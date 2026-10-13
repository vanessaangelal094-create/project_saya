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

$search   = $_GET['search'] ?? '';
$type     = $_GET['type'] ?? '';
$location = $_GET['location'] ?? '';
$status   = $_GET['status'] ?? '';

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {

    $where[] = "(equipment_code LIKE ?
                OR equipment_name LIKE ?
                OR brand_model LIKE ?
                OR serial_number LIKE ?)";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssss";
}

if ($type !== '') {

    $where[] = "equipment_type = ?";
    $params[] = $type;
    $types .= "s";
}

if ($location !== '') {

    $where[] = "location = ?";
    $params[] = $location;
    $types .= "s";
}

if ($status !== '') {

    $where[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

$whereSQL = '';

if (!empty($where)) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}


/* =========================================================
   DATA EQUIPMENT
========================================================= */

$sql = "SELECT * FROM equipment $whereSQL ORDER BY id ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query error: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================================================
   SUMMARY
========================================================= */

$totalEquipment = 0;
$running = 0;
$standby = 0;
$maintenance = 0;
$breakdown = 0;

$selectedLocation = $_GET['location'] ?? '';

if ($selectedLocation !== '') {

    $summaryStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Running') AS running,
            SUM(status = 'Standby') AS standby,
            SUM(status = 'Maintenance') AS maintenance,
            SUM(status = 'Breakdown') AS breakdown
        FROM equipment
        WHERE location = ?
    ");

    $summaryStmt->bind_param("s", $selectedLocation);
    $summaryStmt->execute();

    $summaryQuery = $summaryStmt->get_result();

} else {

    $summaryQuery = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Running') AS running,
            SUM(status = 'Standby') AS standby,
            SUM(status = 'Maintenance') AS maintenance,
            SUM(status = 'Breakdown') AS breakdown
        FROM equipment
    ");

}

if ($summaryQuery) {

    $summary = $summaryQuery->fetch_assoc();

    $totalEquipment = (int)($summary['total'] ?? 0);
    $running = (int)($summary['running'] ?? 0);
    $standby = (int)($summary['standby'] ?? 0);
    $maintenance = (int)($summary['maintenance'] ?? 0);
    $breakdown = (int)($summary['breakdown'] ?? 0);
}


/* =========================================================
   FILTER OPTIONS
========================================================= */

$typeQuery = $conn->query("
    SELECT DISTINCT equipment_type
    FROM equipment
    WHERE equipment_type IS NOT NULL
    AND equipment_type != ''
    ORDER BY equipment_type ASC
");

$locationQuery = $conn->query("
    SELECT DISTINCT location
    FROM equipment
    WHERE location IS NOT NULL
    AND location != ''
    ORDER BY location ASC
");


/* =========================================================
   STATUS CLASS
========================================================= */

function statusClass($status)
{
    switch ($status) {

        case 'Running':
            return 'running';

        case 'Standby':
            return 'standby';

        case 'Maintenance':
            return 'maintenance';

        case 'Breakdown':
            return 'breakdown';

        default:
            return 'standby';
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Equipment Master - Monitoring Maintenance</title>

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>

        /* khusus halaman ini */

        .hm-button {
            border: 1px solid var(--line-strong);
            background: var(--surface);
            color: var(--primary);
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 7px;
            cursor: pointer;
        }

        .hm-button:hover {
            background: var(--primary-soft);
            border-color: var(--primary);
        }

        .equipment-table {
            min-width: 1200px;
        }

        /* modal update HM / KM */

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(20, 20, 43, 0.4);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            width: 420px;
            max-width: calc(100% - 40px);
            background: var(--surface);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 50px rgba(20, 20, 43, 0.25);
        }

        .modal-card h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .modal-field {
            margin-bottom: 14px;
        }

        .modal-field label {
            display: block;
            margin-bottom: 6px;
        }

        .modal-field input[readonly] {
            background: #f5f5fa;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

    </style>

</head>


<body>

    <?php
    $base   = '../';
    $active = 'equipment';
    include $base . 'includes/sidebar.php';
    ?>


    <div class="main">


        <!-- HEADER -->

        <div class="header">

            <div class="header-left">

                <h1>
                    Equipment Master
                </h1>

                <p>
                    Data master seluruh alat berat yang dimiliki perusahaan
                </p>

            </div>


            <div class="header-right">

                <a href="add.php" class="add-btn">
                    + Tambah Equipment
                </a>


                <div class="profile">

                    <div
                        class="user-info"
                        onclick="toggleProfile()"
                    >

                        <strong>
                            <?php echo htmlspecialchars($_SESSION['nama'] ?? 'User'); ?>
                        </strong>

                        ▾

                    </div>


                    <div
                        class="profile-menu"
                        id="profileMenu"
                    >

                        <div class="profile-name">

                            Login sebagai

                            <strong>
                                <?php echo htmlspecialchars($_SESSION['nama'] ?? 'User'); ?>
                            </strong>

                        </div>


                        <a
                            href="../auth/logout.php"
                            class="logout-link"
                        >
                            Logout
                        </a>

                    </div>

                </div>

            </div>

        </div>


        <!-- SUMMARY -->

        <div class="summary-grid">


            <div
                class="summary-card"
                onclick="window.location.href='index.php?location=<?php echo urlencode($selectedLocation); ?>';"
                style="cursor: pointer;"
            >

                <div class="summary-icon icon-blue">
                    🚜
                </div>

                <div>

                    <div class="summary-title">
                        Total Equipment
                    </div>

                    <div class="summary-number">
                        <?php echo $totalEquipment; ?>
                    </div>

                </div>

            </div>


            <div
                class="summary-card"
                onclick="window.location.href='index.php?location=<?php echo urlencode($selectedLocation); ?>&status=Running';"
                style="cursor: pointer;"
            >

                <div class="summary-icon icon-green">
                    ✓
                </div>

                <div>

                    <div class="summary-title">
                        Running
                    </div>

                    <div class="summary-number number-green">
                        <?php echo $running; ?>
                    </div>

                </div>

            </div>


            <div
                class="summary-card"
                onclick="window.location.href='index.php?location=<?php echo urlencode($selectedLocation); ?>&status=Standby';"
                style="cursor: pointer;"
            >

                <div class="summary-icon icon-yellow">
                    ◷
                </div>

                <div>

                    <div class="summary-title">
                        Standby
                    </div>

                    <div class="summary-number number-yellow">
                        <?php echo $standby; ?>
                    </div>

                </div>

            </div>


            <div
                class="summary-card"
                onclick="<?php
                    if ($selectedLocation !== '') {
                        echo "window.location.href='index.php?location=" . urlencode($selectedLocation) . "&status=Maintenance';";
                    } else {
                        echo "window.location.href='../maintenance/index.php';";
                    }
                ?>"
                style="cursor: pointer;"
            >

                <div class="summary-icon icon-orange">
                    🔧
                </div>

                <div>

                    <div class="summary-title">
                        Maintenance
                    </div>

                    <div class="summary-number number-orange">
                        <?php echo $maintenance; ?>
                    </div>

                </div>

            </div>


            <div
                class="summary-card"
                onclick="window.location.href='index.php?location=<?php echo urlencode($selectedLocation); ?>&status=Breakdown';"
                style="cursor: pointer;"
            >

                <div class="summary-icon icon-red">
                    !
                </div>

                <div>

                    <div class="summary-title">
                        Breakdown
                    </div>

                    <div class="summary-number number-red">
                        <?php echo $breakdown; ?>
                    </div>

                </div>

            </div>


        </div>


        <!-- FILTER -->

        <div class="filter-box">

            <div class="filter-title">
                Filter Equipment
            </div>


            <form
                method="GET"
                class="filter-form"
            >


                <div class="form-group">

                    <label>
                        Search Equipment
                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari ID unit, nama, model, serial number..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Jenis Alat
                    </label>

                    <select name="type">

                        <option value="">
                            Semua Jenis
                        </option>


                        <?php

                        if ($typeQuery) {

                            while ($typeRow = $typeQuery->fetch_assoc()) {

                                $typeValue = $typeRow['equipment_type'];

                                $selected = ($type === $typeValue)
                                    ? 'selected'
                                    : '';

                                echo '<option value="' .
                                    htmlspecialchars($typeValue) .
                                    '" ' .
                                    $selected .
                                    '>' .
                                    htmlspecialchars($typeValue) .
                                    '</option>';
                            }
                        }

                        ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Lokasi
                    </label>

                    <select name="location">

                        <option value="">
                            Semua Lokasi
                        </option>


                        <?php

                        if ($locationQuery) {

                            while ($locationRow = $locationQuery->fetch_assoc()) {

                                $locationValue = $locationRow['location'];

                                $selected = ($location === $locationValue)
                                    ? 'selected'
                                    : '';

                                echo '<option value="' .
                                    htmlspecialchars($locationValue) .
                                    '" ' .
                                    $selected .
                                    '>' .
                                    htmlspecialchars($locationValue) .
                                    '</option>';
                            }
                        }

                        ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="">
                            Semua Status
                        </option>

                        <option
                            value="Running"
                            <?php echo ($status === 'Running') ? 'selected' : ''; ?>
                        >
                            Running
                        </option>

                        <option
                            value="Standby"
                            <?php echo ($status === 'Standby') ? 'selected' : ''; ?>
                        >
                            Standby
                        </option>

                        <option
                            value="Maintenance"
                            <?php echo ($status === 'Maintenance') ? 'selected' : ''; ?>
                        >
                            Maintenance
                        </option>

                        <option
                            value="Breakdown"
                            <?php echo ($status === 'Breakdown') ? 'selected' : ''; ?>
                        >
                            Breakdown
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="filter-btn"
                >
                    Filter
                </button>


                <a
                    href="index.php"
                    class="reset-btn"
                >
                    Reset
                </a>


            </form>

        </div>


        <!-- TABLE -->

        <div class="table-box">


            <div class="table-header">

                <h2>
                    Daftar Equipment
                </h2>

            </div>


            <div class="table-wrapper">

                <table class="equipment-table">

                    <thead>

                        <tr>
                            <th>NO</th>
                            <th>ID UNIT</th>
                            <th>NAMA EQUIPMENT</th>
                            <th>JENIS ALAT</th>
                            <th>MEREK / MODEL</th>
                            <th>TAHUN</th>
                            <th>KAPASITAS</th>
                            <th>LOKASI</th>
                            <th>HM / KM SAAT INI</th>
                            <th>STATUS</th>
                            <th>AKSI</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php

                    $no = 1;

                    if ($result->num_rows > 0) {

                        while ($row = $result->fetch_assoc()) {

                    ?>

                        <tr>

                            <td>
                                <?php echo $no++; ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($row['equipment_code'] ?? '-'); ?>
                                </strong>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['equipment_name'] ?? '-'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['equipment_type'] ?? '-'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['brand_model'] ?? '-'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['year'] ?? '-'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['capacity'] ?? '-'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['location'] ?? '-'); ?>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="hm-button"
                                    onclick="openHmModal(
                                        <?= (int)$row['id']; ?>,
                                        '<?= htmlspecialchars($row['equipment_code'], ENT_QUOTES); ?>',
                                        <?= (float)($row['hm_current'] ?? 0); ?>
                                    )"
                                >
                                    <?= number_format(
                                        (float)($row['hm_current'] ?? 0),
                                        2,
                                        ',',
                                        '.'
                                    ); ?>
                                </button>
                            </td>

                            <td>
                                <span
                                    class="status <?php echo statusClass($row['status'] ?? 'Standby'); ?>"
                                >
                                    <?php echo htmlspecialchars($row['status'] ?? 'Standby'); ?>
                                </span>
                            </td>

                            <td>

                                <div class="action">

                                    <a
                                        href="detail.php?id=<?php echo $row['id']; ?>"
                                        class="view"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="edit.php?id=<?php echo $row['id']; ?>"
                                        class="edit"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete.php?id=<?php echo $row['id']; ?>"
                                        class="delete"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus equipment ini?');"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php

                        }

                    } else {

                    ?>

                        <tr>

                            <td
                                colspan="11"
                                class="empty"
                            >
                                Data equipment tidak ditemukan.
                            </td>

                        </tr>

                    <?php

                    }

                    ?>

                    </tbody>

                </table>

            </div>

        </div>


    </div>


<script>

function toggleProfile() {

    const menu = document.getElementById('profileMenu');

    menu.classList.toggle('show');

}


document.addEventListener('click', function(event) {

    const profile = document.querySelector('.profile');

    const menu = document.getElementById('profileMenu');

    if (!profile.contains(event.target)) {
        menu.classList.remove('show');
    }

});

</script>


<!-- MODAL UPDATE HM / KM -->
<div id="hmModal" class="modal-overlay">

    <div class="modal-card">

        <h2>
            Update HM / KM
        </h2>

        <div class="modal-field">
            <label>ID Unit</label>
            <input type="text" id="hmEquipmentCode" readonly>
        </div>

        <div class="modal-field">
            <label>HM / KM Saat Ini</label>
            <input type="text" id="hmCurrentValue" readonly>
        </div>

        <div class="modal-field">
            <label>HM / KM Terbaru</label>
            <input
                type="number"
                id="hmNewValue"
                step="0.01"
                min="0"
                placeholder="Masukkan HM / KM terbaru"
            >
        </div>

        <div class="modal-actions">

            <button
                type="button"
                class="btn-cancel"
                onclick="closeHmModal()"
            >
                Batal
            </button>

            <button
                type="button"
                class="btn-save"
                onclick="saveHmUpdate()"
            >
                Simpan
            </button>

        </div>

    </div>
</div>


<script>

let currentHmEquipmentId = null;


function openHmModal(id, equipmentCode, currentHm) {

    currentHmEquipmentId = id;

    document.getElementById('hmEquipmentCode').value = equipmentCode;

    document.getElementById('hmCurrentValue').value =
        new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(currentHm);

    document.getElementById('hmNewValue').value = '';

    document.getElementById('hmModal').style.display = 'flex';

    document.getElementById('hmNewValue').focus();
}


function closeHmModal() {

    document.getElementById('hmModal').style.display = 'none';

}


function saveHmUpdate() {

    const newHm = document.getElementById('hmNewValue').value;

    if (newHm === '') {
        alert('Silakan masukkan HM / KM terbaru.');
        return;
    }

    if (currentHmEquipmentId === null) {
        alert('ID equipment tidak ditemukan.');
        return;
    }

    fetch('update_hm.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body:
            'id=' + encodeURIComponent(currentHmEquipmentId) +
            '&hm_baru=' + encodeURIComponent(newHm)
    })
    .then(response => response.json())
    .then(data => {

        if (data.success) {

            alert(data.message);

            closeHmModal();

            location.reload();

        } else {

            alert(data.message);

        }

    })
    .catch(error => {

        console.error(error);

        alert('Terjadi kesalahan saat memperbarui HM/KM.');

    });

}

</script>

</body>

</html>


<?php

$stmt->close();

$conn->close();

?>