<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   CEK ID EQUIPMENT
========================================================= */

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die("ID equipment tidak valid.");
}


/* =========================================================
   AMBIL DATA EQUIPMENT
========================================================= */

$sql = "SELECT * FROM equipment WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Data equipment tidak ditemukan.");
}

$equipment = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   VARIABEL PESAN
========================================================= */

$error = "";


/* =========================================================
   PROSES UPDATE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $equipment_code = trim($_POST['equipment_code'] ?? '');
    $equipment_name = trim($_POST['equipment_name'] ?? '');
    $equipment_type = trim($_POST['equipment_type'] ?? '');
    $brand_model = trim($_POST['brand_model'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $capacity = trim($_POST['capacity'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $pic = trim($_POST['pic'] ?? '');
    $hm_current = trim($_POST['hm_current'] ?? '0');
    $status = trim($_POST['status'] ?? 'Standby');
    $spesifikasi = trim($_POST['spesifikasi'] ?? '');
    $jumlah = trim($_POST['jumlah'] ?? '');
    $user_equipment = trim($_POST['user_equipment'] ?? '');
    $hasil_pengecekan_fisik = trim(
        $_POST['hasil_pengecekan_fisik'] ?? ''
    );


    /* =====================================================
       VALIDASI
    ===================================================== */

    if (
        $equipment_code === '' ||
        $equipment_name === '' ||
        $equipment_type === ''
    ) {

        $error = "ID Unit, Nama Equipment, dan Jenis Alat wajib diisi.";

    }


    /* =====================================================
       CEK DUPLIKAT ID UNIT
    ===================================================== */

    if ($error === '') {

        $check_sql = "
            SELECT id
            FROM equipment
            WHERE equipment_code = ?
            AND id != ?
        ";

        $check_stmt = $conn->prepare($check_sql);

        $check_stmt->bind_param(
            "si",
            $equipment_code,
            $id
        );

        $check_stmt->execute();

        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {

            $error = "ID Unit tersebut sudah digunakan equipment lain.";

        }

        $check_stmt->close();
    }


    /* =====================================================
       FOTO
    ===================================================== */

    $gambar = $equipment['gambar'];

    if (
        $error === '' &&
        isset($_FILES['gambar']) &&
        $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['gambar']['error'] === UPLOAD_ERR_OK) {

            $upload_dir = "../assets/equipment/";

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_tmp = $_FILES['gambar']['tmp_name'];
            $file_name = $_FILES['gambar']['name'];
            $file_size = $_FILES['gambar']['size'];

            $extension = strtolower(
                pathinfo($file_name, PATHINFO_EXTENSION)
            );

            $allowed_extensions = [
                "jpg",
                "jpeg",
                "png",
                "webp"
            ];

            if (!in_array($extension, $allowed_extensions)) {

                $error = "Format foto harus JPG, JPEG, PNG, atau WEBP.";

            } elseif ($file_size > 5 * 1024 * 1024) {

                $error = "Ukuran foto maksimal 5 MB.";

            } else {

                $new_file_name =
                    "equipment_" .
                    $id .
                    "_" .
                    time() .
                    "." .
                    $extension;

                $target_file =
                    $upload_dir .
                    $new_file_name;

                if (move_uploaded_file(
                    $file_tmp,
                    $target_file
                )) {

                    /*
                     * Simpan hanya nama file ke database
                     */
                    $gambar = $new_file_name;

                    /*
                     * Hapus foto lama jika ada
                     */
                    if (
                        !empty($equipment['gambar']) &&
                        file_exists(
                            $upload_dir .
                            $equipment['gambar']
                        )
                    ) {

                        unlink(
                            $upload_dir .
                            $equipment['gambar']
                        );
                    }

                } else {

                    $error = "Foto gagal diupload.";

                }
            }

        } else {

            $error = "Terjadi kesalahan saat upload foto.";

        }
    }


    /* =====================================================
       UPDATE DATABASE
    ===================================================== */

    if ($error === '') {

        $update_sql = "
            UPDATE equipment SET

                equipment_code = ?,
                equipment_name = ?,
                equipment_type = ?,
                spesifikasi = ?,
                brand_model = ?,
                serial_number = ?,
                year = ?,
                jumlah = ?,
                user_equipment = ?,
                capacity = ?,
                location = ?,
                pic = ?,
                hm_current = ?,
                status = ?,
                hasil_pengecekan_fisik = ?,
                gambar = ?

            WHERE id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);


        $year_value =
            ($year !== '')
            ? (int) $year
            : null;

        $jumlah_value =
            ($jumlah !== '')
            ? (int) $jumlah
            : null;

        $hm_value =
            ($hm_current !== '')
            ? (float) $hm_current
            : 0;


        $update_stmt->bind_param(
            "ssssssisssssdsssi",
            $equipment_code,
            $equipment_name,
            $equipment_type,
            $spesifikasi,
            $brand_model,
            $serial_number,
            $year_value,
            $jumlah_value,
            $user_equipment,
            $capacity,
            $location,
            $pic,
            $hm_value,
            $status,
            $hasil_pengecekan_fisik,
            $gambar,
            $id
        );


        if ($update_stmt->execute()) {

            $update_stmt->close();

            $conn->close();

            header(
                "Location: detail.php?id=" .
                $id .
                "&success=updated"
            );

            exit;

        } else {

            $error =
                "Data gagal disimpan: " .
                $update_stmt->error;

            $update_stmt->close();
        }
    }


    /*
     * Jika terjadi error, tampilkan kembali
     * data yang baru dimasukkan user.
     */
    $equipment['equipment_code'] = $equipment_code;
    $equipment['equipment_name'] = $equipment_name;
    $equipment['equipment_type'] = $equipment_type;
    $equipment['spesifikasi'] = $spesifikasi;
    $equipment['brand_model'] = $brand_model;
    $equipment['serial_number'] = $serial_number;
    $equipment['year'] = $year;
    $equipment['jumlah'] = $jumlah;
    $equipment['user_equipment'] = $user_equipment;
    $equipment['capacity'] = $capacity;
    $equipment['location'] = $location;
    $equipment['pic'] = $pic;
    $equipment['hm_current'] = $hm_current;
    $equipment['status'] = $status;
    $equipment['hasil_pengecekan_fisik'] =
        $hasil_pengecekan_fisik;
    $equipment['gambar'] = $gambar;
}


/* =========================================================
   DATA DROPDOWN JENIS ALAT
========================================================= */

$type_result = $conn->query("
    SELECT DISTINCT equipment_type
    FROM equipment
    WHERE equipment_type IS NOT NULL
    AND equipment_type != ''
    ORDER BY equipment_type ASC
");


/* =========================================================
   DATA DROPDOWN LOKASI
========================================================= */

$location_result = $conn->query("
    SELECT DISTINCT location
    FROM equipment
    WHERE location IS NOT NULL
    AND location != ''
    ORDER BY location ASC
");


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
        Edit Equipment |
        <?php
        echo htmlspecialchars(
            $equipment['equipment_code']
        );
        ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            font-family: Arial, sans-serif;

            background: #f5f7fb;

            color: #1f2937;
        }


        .sidebar {
            position: fixed;

            left: 0;
            top: 0;

            width: 240px;
            height: 100vh;

            background: #0759bd;

            color: white;

            padding: 25px 15px;
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
        }


        .menu {
            list-style: none;
        }


        .menu li {
            margin-bottom: 8px;
        }


        .menu a {
            display: block;

            padding: 13px 15px;

            border-radius: 8px;

            color: white;

            text-decoration: none;

            font-size: 14px;
        }


        .menu a:hover,
        .menu .active {
            background: rgba(
                255,
                255,
                255,
                0.18
            );
        }


        .main {
            margin-left: 240px;

            padding: 30px;
        }


        .top {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;
        }


        .top h1 {
            font-size: 28px;

            margin-bottom: 7px;
        }


        .top p {
            color: #6b7280;

            font-size: 14px;
        }


        .breadcrumb {
            margin-bottom: 18px;

            color: #6b7280;

            font-size: 13px;
        }


        .breadcrumb a {
            color: #0759bd;

            text-decoration: none;
        }


        .form-box {
            background: white;

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 20px;
        }


        .section-title {
            font-size: 17px;

            font-weight: bold;

            color: #0759bd;

            margin-bottom: 20px;

            padding-bottom: 12px;

            border-bottom: 1px solid #e5e7eb;
        }


        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;
        }


        .form-group {
            display: flex;

            flex-direction: column;

            gap: 7px;
        }


        .form-group.full {
            grid-column: 1 / -1;
        }


        label {
            font-size: 13px;

            font-weight: bold;

            color: #374151;
        }


        label span {
            color: #dc2626;
        }


        input,
        select,
        textarea {
            width: 100%;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            padding: 11px 12px;

            font-family: inherit;

            font-size: 13px;

            outline: none;

            background: white;
        }


        input:focus,
        select:focus,
        textarea:focus {
            border-color: #0759bd;

            box-shadow:
                0 0 0 3px
                rgba(
                    7,
                    89,
                    189,
                    0.10
                );
        }


        textarea {
            min-height: 100px;

            resize: vertical;
        }


        .photo-section {
            display: grid;

            grid-template-columns: 220px 1fr;

            gap: 25px;

            align-items: start;
        }


        .current-photo {
            width: 220px;

            height: 160px;

            background: #eef5fc;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;

            color: #6b7280;

            font-size: 13px;

            text-align: center;

            padding: 15px;
        }


        .current-photo img {
            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .photo-info {
            font-size: 12px;

            color: #6b7280;

            margin-top: 8px;

            line-height: 1.5;
        }


        .error {
            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #b91c1c;

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 13px;
        }


        .required-info {
            font-size: 12px;

            color: #6b7280;

            margin-bottom: 20px;
        }


        .buttons {
            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 25px;

            padding-top: 20px;

            border-top: 1px solid #e5e7eb;
        }


        .btn {
            border: none;

            border-radius: 8px;

            padding: 11px 20px;

            font-size: 13px;

            font-weight: bold;

            cursor: pointer;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;
        }


        .btn-cancel {
            background: #f3f4f6;

            color: #374151;
        }


        .btn-cancel:hover {
            background: #e5e7eb;
        }


        .btn-save {
            background: #0759bd;

            color: white;
        }


        .btn-save:hover {
            background: #064da5;
        }


        .hint {
            font-size: 11px;

            color: #6b7280;

            margin-top: 3px;
        }


        @media (max-width: 900px) {

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .photo-section {
                grid-template-columns: 1fr;
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
            }

            .top {
                flex-direction: column;

                align-items: flex-start;

                gap: 10px;
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
                <a
                    href="index.php"
                    class="active"
                >
                    Equipment Master
                </a>
            </li>


            <li>
                <a href="#">
                    Operation
                </a>
            </li>


            <li>
                <a href="#">
                    Maintenance Schedule
                </a>
            </li>


            <li>
                <a href="#">
                    Maintenance History
                </a>
            </li>


            <li>
                <a href="#">
                    Spare Part & Lubricant
                </a>
            </li>


            <li>
                <a href="#">
                    Fuel Performance
                </a>
            </li>


            <li>
                <a href="#">
                    Maintenance Cost
                </a>
            </li>


            <li>
                <a href="#">
                    Breakdown & Downtime
                </a>
            </li>


            <li>
                <a href="#">
                    Report
                </a>
            </li>


        </ul>


    </aside>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">


        <div class="breadcrumb">

            <a href="../index.php">
                Home
            </a>

            &nbsp;›&nbsp;

            <a href="index.php">
                Equipment Master
            </a>

            &nbsp;›&nbsp;

            Edit Equipment

        </div>


        <div class="top">

            <div>

                <h1>
                    Edit Equipment
                </h1>

                <p>
                    Ubah data equipment dan simpan perubahan
                    ke dalam sistem.
                </p>

            </div>

        </div>


        <?php if ($error !== ''): ?>

            <div class="error">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <!-- =================================================
                 DATA UTAMA
            ================================================== -->

            <section class="form-box">


                <div class="section-title">
                    Informasi Umum
                </div>


                <div class="required-info">
                    Field dengan tanda
                    <span style="color:#dc2626;">
                        *
                    </span>
                    wajib diisi.
                </div>


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            ID Unit
                            <span>*</span>
                        </label>

                        <input
                            type="text"
                            name="equipment_code"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['equipment_code']
                            );
                            ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Nama Equipment
                            <span>*</span>
                        </label>

                        <input
                            type="text"
                            name="equipment_name"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['equipment_name']
                            );
                            ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Jenis Alat
                            <span>*</span>
                        </label>

                        <input
                            list="equipment-types"
                            type="text"
                            name="equipment_type"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['equipment_type']
                            );
                            ?>"
                            required
                        >


                        <datalist id="equipment-types">

                            <?php
                            if ($type_result):
                            ?>

                                <?php
                                while (
                                    $type_row =
                                    $type_result->fetch_assoc()
                                ):
                                ?>

                                    <option
                                        value="<?php
                                        echo htmlspecialchars(
                                            $type_row[
                                                'equipment_type'
                                            ]
                                        );
                                        ?>"
                                    ></option>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </datalist>

                    </div>


                    <div class="form-group">

                        <label>
                            Merek / Model
                        </label>

                        <input
                            type="text"
                            name="brand_model"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['brand_model'] ?? ''
                            );
                            ?>"
                            placeholder="Contoh: Komatsu PC210"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Serial Number
                        </label>

                        <input
                            type="text"
                            name="serial_number"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['serial_number'] ?? ''
                            );
                            ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tahun
                        </label>

                        <input
                            type="number"
                            name="year"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['year'] ?? ''
                            );
                            ?>"
                            min="1900"
                            max="2100"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Kapasitas
                        </label>

                        <input
                            type="text"
                            name="capacity"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['capacity'] ?? ''
                            );
                            ?>"
                            placeholder="Contoh: 1,2 m3 / 20 Ton"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Jumlah
                        </label>

                        <input
                            type="number"
                            name="jumlah"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['jumlah'] ?? ''
                            );
                            ?>"
                            min="0"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            User / Pengguna
                        </label>

                        <input
                            type="text"
                            name="user_equipment"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment[
                                    'user_equipment'
                                ] ?? ''
                            );
                            ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Lokasi
                        </label>

                        <input
                            list="equipment-locations"
                            type="text"
                            name="location"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['location'] ?? ''
                            );
                            ?>"
                        >


                        <datalist id="equipment-locations">

                            <?php
                            if ($location_result):
                            ?>

                                <?php
                                while (
                                    $location_row =
                                    $location_result->fetch_assoc()
                                ):
                                ?>

                                    <option
                                        value="<?php
                                        echo htmlspecialchars(
                                            $location_row[
                                                'location'
                                            ]
                                        );
                                        ?>"
                                    ></option>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </datalist>

                    </div>


                    <div class="form-group">

                        <label>
                            PIC
                        </label>

                        <input
                            type="text"
                            name="pic"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['pic'] ?? ''
                            );
                            ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            HM / KM Saat Ini
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="hm_current"
                            value="<?php
                            echo htmlspecialchars(
                                $equipment['hm_current'] ?? 0
                            );
                            ?>"
                            min="0"
                        >

                        <div class="hint">
                            Masukkan angka HM/KM terbaru.
                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Status
                        </label>

                        <select name="status">


                            <option
                                value="Running"
                                <?php
                                echo
                                (
                                    $equipment['status']
                                    === 'Running'
                                )
                                ? 'selected'
                                : '';
                                ?>
                            >
                                Running
                            </option>


                            <option
                                value="Standby"
                                <?php
                                echo
                                (
                                    $equipment['status']
                                    === 'Standby'
                                )
                                ? 'selected'
                                : '';
                                ?>
                            >
                                Standby
                            </option>


                            <option
                                value="Maintenance"
                                <?php
                                echo
                                (
                                    $equipment['status']
                                    === 'Maintenance'
                                )
                                ? 'selected'
                                : '';
                                ?>
                            >
                                Maintenance
                            </option>


                            <option
                                value="Breakdown"
                                <?php
                                echo
                                (
                                    $equipment['status']
                                    === 'Breakdown'
                                )
                                ? 'selected'
                                : '';
                                ?>
                            >
                                Breakdown
                            </option>


                        </select>

                    </div>


                    <div class="form-group full">

                        <label>
                            Spesifikasi
                        </label>

                        <textarea
                            name="spesifikasi"
                            placeholder="Masukkan spesifikasi equipment..."
                        ><?php
                        echo htmlspecialchars(
                            $equipment['spesifikasi'] ?? ''
                        );
                        ?></textarea>

                    </div>


                    <div class="form-group full">

                        <label>
                            Hasil Pengecekan Fisik /
                            Kondisi Fisik
                        </label>

                        <textarea
                            name="hasil_pengecekan_fisik"
                            placeholder="Masukkan hasil pengecekan fisik..."
                        ><?php
                        echo htmlspecialchars(
                            $equipment[
                                'hasil_pengecekan_fisik'
                            ] ?? ''
                        );
                        ?></textarea>

                    </div>


                </div>


            </section>


            <!-- =================================================
                 FOTO
            ================================================== -->

            <section class="form-box">


                <div class="section-title">
                    Foto Equipment
                </div>


                <div class="photo-section">


                    <div>

                        <div class="current-photo">


                            <?php
                            if (
                                !empty(
                                    $equipment['gambar']
                                ) &&
                                file_exists(
                                    "../assets/equipment/" .
                                    $equipment['gambar']
                                )
                            ):
                            ?>

                                <img
                                    src="../assets/equipment/<?php
                                    echo htmlspecialchars(
                                        $equipment['gambar']
                                    );
                                    ?>"
                                    alt="Foto Equipment"
                                >

                            <?php else: ?>

                                <span>
                                    Foto equipment belum tersedia
                                </span>

                            <?php endif; ?>


                        </div>


                        <div class="photo-info">

                            Foto saat ini.

                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Ganti Foto Equipment
                        </label>

                        <input
                            type="file"
                            name="gambar"
                            accept=".jpg,.jpeg,.png,.webp"
                        >

                        <div class="hint">

                            Format:
                            JPG, JPEG, PNG, WEBP.

                            Maksimal 5 MB.

                            Kosongkan jika tidak ingin
                            mengganti foto.

                        </div>

                    </div>


                </div>


            </section>


            <!-- =================================================
                 BUTTON
            ================================================== -->

            <section class="form-box">


                <div class="buttons">


                    <a
                        href="detail.php?id=<?php
                        echo $id;
                        ?>"
                        class="btn btn-cancel"
                    >
                        Batal
                    </a>


                    <button
                        type="submit"
                        class="btn btn-save"
                    >
                        Simpan Perubahan
                    </button>


                </div>


            </section>


        </form>


    </main>


</body>

</html>


<?php

$conn->close();

?>