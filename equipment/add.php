<?php

session_start();

/* =========================
   CEK LOGIN
========================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}


/* =========================
   KONEKSI DATABASE
========================= */

require_once "../config/database.php";


$errors = [];


/* =========================
   PROSES SIMPAN
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =========================
       AMBIL DATA FORM
    ========================= */

    $equipment_code = trim(
        $_POST['equipment_code'] ?? ''
    );

    $equipment_name = trim(
        $_POST['equipment_name'] ?? ''
    );

    $equipment_type = trim(
        $_POST['equipment_type'] ?? ''
    );

    $brand = trim(
        $_POST['brand'] ?? ''
    );

    $model = trim(
        $_POST['model'] ?? ''
    );

    $serial_number = trim(
        $_POST['serial_number'] ?? ''
    );

    $year = trim(
        $_POST['year'] ?? ''
    );

    $capacity = trim(
        $_POST['capacity'] ?? ''
    );

    $location = trim(
        $_POST['location'] ?? ''
    );

    $hm_current = trim(
        $_POST['hm_current'] ?? ''
    );

    $status = trim(
        $_POST['status'] ?? ''
    );

    $pic = trim(
        $_POST['pic'] ?? ''
    );


    /* =========================
       VALIDASI
    ========================= */

    if ($equipment_code === '') {
        $errors[] = "ID Unit wajib diisi.";
    }

    if ($equipment_name === '') {
        $errors[] = "Nama Equipment wajib diisi.";
    }

    if ($equipment_type === '') {
        $errors[] = "Jenis Alat wajib dipilih.";
    }

    if ($brand === '') {
        $errors[] = "Merek wajib diisi.";
    }

    if ($model === '') {
        $errors[] = "Model wajib diisi.";
    }

    if ($serial_number === '') {
        $errors[] = "Serial Number wajib diisi.";
    }

    if ($year === '') {
        $errors[] = "Tahun wajib diisi.";
    }

    if ($capacity === '') {
        $errors[] = "Kapasitas wajib diisi.";
    }

    if ($location === '') {
        $errors[] = "Lokasi / Unit Kerja wajib dipilih.";
    }

    if ($hm_current === '') {
        $errors[] = "HM Saat Ini wajib diisi.";
    }

    if ($status === '') {
        $errors[] = "Status wajib dipilih.";
    }

    if ($pic === '') {
        $errors[] = "Operator / PIC wajib diisi.";
    }


    /* =========================
       GABUNG MEREK + MODEL
       KE brand_model
    ========================= */

    $brand_model = '';

    if ($brand !== '' || $model !== '') {

        $brand_model =
            $brand . " / " . $model;
    }


    /* =========================
       CEK ID UNIT
    ========================= */

    if (empty($errors)) {

        $check = $conn->prepare(
            "SELECT id
             FROM equipment
             WHERE equipment_code = ?"
        );

        if ($check) {

            $check->bind_param(
                "s",
                $equipment_code
            );

            $check->execute();

            $check_result =
                $check->get_result();

            if (
                $check_result->num_rows > 0
            ) {

                $errors[] =
                    "ID Unit tersebut sudah digunakan.";
            }

            $check->close();

        } else {

            $errors[] =
                "Gagal memeriksa ID Unit.";
        }
    }


    /* =========================
       UPLOAD FOTO
    ========================= */

    $gambar = null;


    if (
        empty($errors)
        &&
        isset($_FILES['gambar'])
        &&
        $_FILES['gambar']['error']
            !== UPLOAD_ERR_NO_FILE
    ) {


        $file = $_FILES['gambar'];


        if (
            $file['error']
            !== UPLOAD_ERR_OK
        ) {

            $errors[] =
                "Foto equipment gagal diupload.";

        } else {


            /* FORMAT YANG DIIZINKAN */

            $allowed_extensions = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];


            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );


            if (
                !in_array(
                    $extension,
                    $allowed_extensions
                )
            ) {

                $errors[] =
                    "Format foto harus JPG, JPEG, PNG, atau WEBP.";

            }


            /* UKURAN MAKSIMAL 5 MB */

            if (
                $file['size']
                > 5 * 1024 * 1024
            ) {

                $errors[] =
                    "Ukuran foto maksimal 5 MB.";
            }


            /* SIMPAN FOTO */

            if (empty($errors)) {


                $upload_dir =
                    "../assets/equipment/";


                if (
                    !is_dir(
                        $upload_dir
                    )
                ) {

                    mkdir(
                        $upload_dir,
                        0777,
                        true
                    );
                }


                $safe_code =
                    preg_replace(
                        '/[^a-zA-Z0-9_-]/',
                        '_',
                        $equipment_code
                    );


                $new_filename =
                    $safe_code
                    . "_"
                    . time()
                    . "."
                    . $extension;


                $target_file =
                    $upload_dir
                    . $new_filename;


                if (
                    move_uploaded_file(
                        $file['tmp_name'],
                        $target_file
                    )
                ) {

                    $gambar =
                        "assets/equipment/"
                        . $new_filename;

                } else {

                    $errors[] =
                        "Foto gagal disimpan.";
                }
            }
        }
    }


    /* =========================
       SIMPAN KE DATABASE
    ========================= */

    if (empty($errors)) {


        $sql = "

            INSERT INTO equipment (

                equipment_code,
                equipment_name,
                equipment_type,
                brand_model,
                serial_number,
                year,
                capacity,
                location,
                pic,
                hm_current,
                status,
                gambar

            )

            VALUES (

                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?

            )

        ";


        $stmt =
            $conn->prepare($sql);


        if ($stmt) {


            $year_int =
                (int) $year;


            $hm_float =
                (float) $hm_current;


            $stmt->bind_param(

                "sssssisssdss",

                $equipment_code,
                $equipment_name,
                $equipment_type,
                $brand_model,
                $serial_number,
                $year_int,
                $capacity,
                $location,
                $pic,
                $hm_float,
                $status,
                $gambar

            );


            if ($stmt->execute()) {


                $stmt->close();

                $conn->close();


                header(
                    "Location: index.php?success=added"
                );

                exit;


            } else {


                $errors[] =
                    "Data gagal disimpan: "
                    . $stmt->error;


                $stmt->close();
            }


        } else {


            $errors[] =
                "Query database gagal dibuat.";
        }
    }
}


/* =========================
   DATA JENIS ALAT
========================= */

$type_result = $conn->query(

    "SELECT DISTINCT equipment_type

     FROM equipment

     WHERE equipment_type IS NOT NULL

     AND equipment_type != ''

     ORDER BY equipment_type ASC"

);


/* =========================
   DATA LOKASI
========================= */

$location_result = $conn->query(

    "SELECT DISTINCT location

     FROM equipment

     WHERE location IS NOT NULL

     AND location != ''

     ORDER BY location ASC"

);

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
        Tambah Equipment - Monitoring Maintenance
    </title>


    <style>


        * {

            box-sizing: border-box;

        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f3f8fd;

            color: #111f68;

        }


        .page {

            min-height: 100vh;

        }


        /* =========================
           TOP BAR
        ========================= */

        .topbar {

            height: 54px;

            background:
                linear-gradient(
                    90deg,
                    #dceeff,
                    #edf6ff
                );

            display: flex;

            align-items: center;

            padding: 0 18px;

            gap: 15px;

        }


        .menu-button {

            width: 30px;

            height: 30px;

            border: none;

            background: white;

            border-radius: 5px;

            color: #0757d5;

            font-size: 18px;

            cursor: pointer;

        }


        .search {

            width: 360px;

            height: 32px;

            background: white;

            border-radius: 5px;

            display: flex;

            align-items: center;

            padding: 0 12px;

            color: #8b9bb8;

            font-size: 12px;

        }


        .top-right {

            margin-left: auto;

            display: flex;

            align-items: center;

            gap: 18px;

        }


        .bell {

            font-size: 20px;

            color: #064cd3;

        }


        .profile {

            display: flex;

            align-items: center;

            gap: 8px;

            font-size: 12px;

            font-weight: bold;

        }


        .profile-circle {

            width: 30px;

            height: 30px;

            border-radius: 50%;

            background: #0874e8;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

        }


        /* =========================
           CONTENT
        ========================= */

        .content {

            padding:
                10px 16px 20px;

        }


        .breadcrumb {

            font-size: 12px;

            margin-bottom: 5px;

            color: #173fbd;

        }


        .breadcrumb span {

            margin: 0 7px;

            color: #6d7ea5;

        }


        .breadcrumb a {

            text-decoration: none;

            color: #173fbd;

        }


        .page-header {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 10px;

        }


        .back-button {

            width: 40px;

            height: 40px;

            background: white;

            border:
                1px solid
                #d7e3f3;

            border-radius: 5px;

            color: #075ed8;

            font-size: 22px;

            cursor: pointer;

        }


        .page-title h1 {

            margin: 0;

            font-size: 22px;

            color: #061b9a;

        }


        .page-title p {

            margin:
                2px 0 0;

            font-size: 13px;

            color: #243a9e;

        }


        /* =========================
           ERROR
        ========================= */

        .error-box {

            background: #fff0f1;

            border:
                1px solid
                #f0a4aa;

            color: #b4232e;

            border-radius: 5px;

            padding:
                8px 12px;

            margin-bottom: 8px;

            font-size: 11px;

        }


        .error-box ul {

            margin: 0;

            padding-left: 18px;

        }


        /* =========================
           FORM CARD
        ========================= */

        .form-card {

            background: white;

            border-radius: 5px;

            padding:
                8px 11px 12px;

            margin-bottom: 8px;

            box-shadow:
                0 1px 5px
                rgba(
                    30,
                    80,
                    150,
                    0.06
                );

        }


        .section-title {

            height: 28px;

            background:
                linear-gradient(
                    90deg,
                    #eaf5ff,
                    #f4f9ff
                );

            display: flex;

            align-items: center;

            gap: 9px;

            padding: 0 10px;

            border-radius: 4px;

            color: #064bd1;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 9px;

        }


        .section-icon {

            font-size: 17px;

        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap:
                8px 13px;

        }


        .form-group {

            display: flex;

            flex-direction: column;

        }


        .form-group.full {

            grid-column:
                span 2;

        }


        label {

            font-size: 11px;

            font-weight: bold;

            color: #1c328f;

            margin-bottom: 4px;

        }


        label span {

            color: #e22b45;

        }


        input,
        select {

            width: 100%;

            height: 26px;

            border:
                1px solid
                #cfdff2;

            border-radius: 4px;

            padding: 0 9px;

            font-size: 11px;

            color: #253b85;

            background: white;

            outline: none;

        }


        input:focus,
        select:focus {

            border-color: #2780e8;

            box-shadow:
                0 0 0 2px
                rgba(
                    39,
                    128,
                    232,
                    0.08
                );

        }


        select {

            cursor: pointer;

        }


        /* =========================
           PHOTO
        ========================= */

        .photo-area {

            display: flex;

            gap: 12px;

            align-items: stretch;

        }


        .upload-box {

            width: 310px;

            min-height: 85px;

            border:
                1px dashed
                #9bbde7;

            border-radius: 5px;

            background: #fbfdff;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            color: #7892b8;

            font-size: 10px;

            cursor: pointer;

        }


        .upload-box:hover {

            background: #f4f9ff;

            border-color: #3984df;

        }


        .upload-icon {

            font-size: 28px;

            color: #8aa6d0;

            margin-bottom: 3px;

        }


        .upload-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 80px;

            height: 22px;

            border:
                1px solid
                #3182e5;

            border-radius: 4px;

            color: #0765d8;

            background: white;

            font-size: 10px;

            font-weight: bold;

            margin: 3px 0;

        }


        .upload-info {

            font-size: 8px;

            color: #7e94b5;

        }


        #gambar {

            display: none;

        }


        .preview-container {

            display: flex;

            gap: 8px;

        }


        .preview-box {

            width: 92px;

            height: 85px;

            border-radius: 5px;

            background: #edf2f8;

            overflow: hidden;

            display: flex;

            align-items: center;

            justify-content: center;

        }


        .preview-box img {

            width: 100%;

            height: 100%;

            object-fit: cover;

        }


        .preview-placeholder {

            font-size: 32px;

            color: #c3cedd;

        }


        /* =========================
           BUTTON
        ========================= */

        .form-footer {

            display: flex;

            justify-content: flex-end;

            gap: 8px;

            margin-top: 8px;

        }


        .btn {

            height: 28px;

            padding:
                0 14px;

            border-radius: 4px;

            font-size: 11px;

            cursor: pointer;

            font-weight: bold;

        }


        .btn-cancel {

            background: white;

            color: #1265dc;

            border:
                1px solid
                #2b83e9;

        }


        .btn-save {

            background: #0875e8;

            color: white;

            border:
                1px solid
                #0875e8;

            min-width: 140px;

        }


        .btn-save:hover {

            background: #0566d0;

        }


        .btn-cancel:hover {

            background: #f2f8ff;

        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .form-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .form-group.full {

                grid-column:
                    span 2;

            }


            .search {

                width: 250px;

            }

        }


        @media (max-width: 650px) {

            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .form-group.full {

                grid-column:
                    span 1;

            }


            .search {

                display: none;

            }


            .photo-area {

                flex-direction: column;

            }


            .upload-box {

                width: 100%;

            }

        }


    </style>


</head>


<body>


<div class="page">


    <!-- =========================
         TOP BAR
    ========================= -->

    <div class="topbar">


        <button
            type="button"
            class="menu-button"
        >
            ☰
        </button>


        <div class="search">

            🔍
            &nbsp;&nbsp;
            Cari ID unit, merek, atau lokasi...

        </div>


        <div class="top-right">


            <div class="bell">
                ♧
            </div>


            <div class="profile">


                <div class="profile-circle">

                    VL

                </div>


                <span>

                    <?php

                    echo htmlspecialchars(
                        $_SESSION['nama']
                        ?? 'User'
                    );

                    ?>

                </span>


                <span>
                    ⌄
                </span>


            </div>


        </div>


    </div>



    <!-- =========================
         CONTENT
    ========================= -->

    <div class="content">


        <!-- BREADCRUMB -->

        <div class="breadcrumb">


            <a href="../index.php">
                Home
            </a>


            <span>
                ›
            </span>


            <a href="index.php">
                Equipment Master
            </a>


            <span>
                ›
            </span>


            <strong>
                Tambah Equipment
            </strong>


        </div>



        <!-- PAGE HEADER -->

        <div class="page-header">


            <button
                type="button"
                class="back-button"
                onclick="
                    window.location.href='index.php'
                "
            >
                ←
            </button>


            <div class="page-title">


                <h1>
                    Tambah Equipment
                </h1>


                <p>
                    Tambahkan data alat berat baru ke dalam sistem.
                </p>


            </div>


        </div>



        <!-- =========================
             ERROR
        ========================= -->

        <?php if (!empty($errors)): ?>


            <div class="error-box">


                <ul>


                    <?php foreach ($errors as $error): ?>


                        <li>

                            <?php

                            echo htmlspecialchars(
                                $error
                            );

                            ?>

                        </li>


                    <?php endforeach; ?>


                </ul>


            </div>


        <?php endif; ?>



        <!-- =========================
             FORM
        ========================= -->

        <form
            action=""
            method="POST"
            enctype="multipart/form-data"
        >



            <!-- =========================
                 INFORMASI EQUIPMENT
            ========================= -->

            <div class="form-card">


                <div class="section-title">


                    <span class="section-icon">
                        ▣
                    </span>


                    Informasi Equipment


                </div>



                <div class="form-grid">


                    <!-- ID UNIT -->

                    <div class="form-group">


                        <label>

                            ID Unit
                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="equipment_code"
                            placeholder="Contoh: EXC-001"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'equipment_code'
                                    ] ?? ''
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- NAMA -->

                    <div class="form-group">


                        <label>

                            Nama Equipment
                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="equipment_name"
                            placeholder="Contoh: Excavator"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'equipment_name'
                                    ] ?? ''
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- JENIS -->

                    <div class="form-group">


                        <label>

                            Jenis Alat
                            <span>*</span>

                        </label>


                        <select
                            name="equipment_type"
                            required
                        >


                            <option value="">
                                Pilih Jenis Alat
                            </option>


                            <?php

                            if ($type_result):

                                while (
                                    $type =
                                    $type_result
                                    ->fetch_assoc()
                                ):

                            ?>


                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $type[
                                                'equipment_type'
                                            ]
                                        );
                                    ?>"
                                    <?php

                                    echo (
                                        (
                                            $_POST[
                                                'equipment_type'
                                            ] ?? ''
                                        )
                                        ===
                                        $type[
                                            'equipment_type'
                                        ]
                                    )
                                    ? 'selected'
                                    : '';

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $type[
                                            'equipment_type'
                                        ]
                                    );

                                    ?>

                                </option>


                            <?php

                                endwhile;

                            endif;

                            ?>


                        </select>


                    </div>



                    <!-- MEREK -->

                    <div class="form-group">


                        <label>

                            Merek
                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="brand"
                            placeholder="Contoh: Komatsu"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'brand'
                                    ] ?? ''
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- MODEL -->

                    <div class="form-group">


                        <label>

                            Model
                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="model"
                            placeholder="Contoh: PC210-10"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'model'
                                    ] ?? ''
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- SERIAL -->

                    <div class="form-group">


                        <label>

                            Serial Number
                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="serial_number"
                            placeholder="Contoh: KMTPC21010-12345"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'serial_number'
                                    ] ?? ''
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- TAHUN -->

                    <div class="form-group">


                        <label>

                            Tahun
                            <span>*</span>

                        </label>


                        <input
                            type="number"
                            name="year"
                            placeholder="Contoh: 2020"
                            min="1900"
                            max="2100"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'year'
                                    ] ?? ''
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- KAPASITAS -->

                    <div class="form-group full">


                        <label>

                            Kapasitas
                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="capacity"
                            placeholder="Contoh: 1.0 m³ / 20 Ton / 3.2 m³"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'capacity'
                                    ] ?? ''
                                );
                            ?>"
                            required
                        >


                    </div>


                </div>


            </div>



            <!-- =========================
                 INFORMASI OPERASIONAL
            ========================= -->

            <div class="form-card">


                <div class="section-title">


                    <span class="section-icon">
                        ⚙
                    </span>


                    Informasi Operasional


                </div>



                <div class="form-grid">


                    <!-- LOKASI -->

                    <div class="form-group full">


                        <label>

                            Lokasi / Unit Kerja
                            <span>*</span>

                        </label>


                        <select
                            name="location"
                            required
                        >


                            <option value="">
                                Pilih Lokasi / Unit Kerja
                            </option>


                            <?php

                            if ($location_result):

                                while (
                                    $location_row =
                                    $location_result
                                    ->fetch_assoc()
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
                                    <?php

                                    echo (
                                        (
                                            $_POST[
                                                'location'
                                            ] ?? ''
                                        )
                                        ===
                                        $location_row[
                                            'location'
                                        ]
                                    )
                                    ? 'selected'
                                    : '';

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $location_row[
                                            'location'
                                        ]
                                    );

                                    ?>

                                </option>


                            <?php

                                endwhile;

                            endif;

                            ?>


                        </select>


                    </div>



                    <!-- HM -->

                    <div class="form-group">


                        <label>

                            HM Saat Ini
                            <span>*</span>

                        </label>


                        <input
                            type="number"
                            name="hm_current"
                            placeholder="Contoh: 0"
                            min="0"
                            step="0.01"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'hm_current'
                                    ] ?? '0'
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- STATUS -->

                    <div class="form-group">


                        <label>

                            Status
                            <span>*</span>

                        </label>


                        <select
                            name="status"
                            required
                        >


                            <option value="">
                                Pilih Status
                            </option>


                            <option
                                value="Running"
                                <?php

                                echo (
                                    (
                                        $_POST[
                                            'status'
                                        ] ?? ''
                                    )
                                    ===
                                    'Running'
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

                                echo (
                                    (
                                        $_POST[
                                            'status'
                                        ] ?? 'Standby'
                                    )
                                    ===
                                    'Standby'
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

                                echo (
                                    (
                                        $_POST[
                                            'status'
                                        ] ?? ''
                                    )
                                    ===
                                    'Maintenance'
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

                                echo (
                                    (
                                        $_POST[
                                            'status'
                                        ] ?? ''
                                    )
                                    ===
                                    'Breakdown'
                                )
                                ? 'selected'
                                : '';

                                ?>
                            >
                                Breakdown
                            </option>


                        </select>


                    </div>



                    <!-- PIC -->

                    <div class="form-group full">


                        <label>

                            Operator / PIC
                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="pic"
                            placeholder="Contoh: Andi Rahman"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST[
                                        'pic'
                                    ] ?? ''
                                );
                            ?>"
                            required
                        >


                    </div>


                </div>


            </div>



            <!-- =========================
                 FOTO EQUIPMENT
            ========================= -->

            <div class="form-card">


                <div class="section-title">


                    <span class="section-icon">
                        ▣
                    </span>


                    Foto Equipment


                </div>



                <div class="photo-area">


                    <!-- UPLOAD BOX -->

                    <label
                        for="gambar"
                        class="upload-box"
                    >


                        <div class="upload-icon">
                            ☁
                        </div>


                        <div>
                            Drag & drop gambar di sini
                        </div>


                        <div>
                            atau
                        </div>


                        <div class="upload-button">
                            Pilih Gambar
                        </div>


                        <div class="upload-info">

                            Format:
                            JPG, JPEG, PNG, WEBP
                            (Maks. 5 MB)

                        </div>


                    </label>



                    <input
                        type="file"
                        name="gambar"
                        id="gambar"
                        accept="
                            image/jpeg,
                            image/png,
                            image/jpg,
                            image/webp
                        "
                    >



                    <!-- PREVIEW -->

                    <div class="preview-container">


                        <div class="preview-box">


                            <div
                                class="preview-placeholder"
                                id="placeholder1"
                            >
                                ▧
                            </div>


                            <img
                                id="preview1"
                                style="display:none;"
                                alt="Preview Equipment"
                            >


                        </div>


                        <div class="preview-box">

                            <div class="preview-placeholder">
                                ▧
                            </div>

                        </div>


                        <div class="preview-box">

                            <div class="preview-placeholder">
                                ▧
                            </div>

                        </div>


                    </div>


                </div>



                <!-- BUTTON -->

                <div class="form-footer">


                    <button
                        type="button"
                        class="btn btn-cancel"
                        onclick="
                            window.location.href='index.php'
                        "
                    >
                        Batal
                    </button>


                    <button
                        type="submit"
                        class="btn btn-save"
                    >
                        💾
                        &nbsp;
                        Simpan Equipment
                    </button>


                </div>


            </div>


        </form>


    </div>


</div>



<script>


    /* =========================
       PREVIEW FOTO
    ========================= */


    const gambarInput =
        document.getElementById(
            'gambar'
        );


    const preview =
        document.getElementById(
            'preview1'
        );


    const placeholder =
        document.getElementById(
            'placeholder1'
        );


    gambarInput.addEventListener(
        'change',
        function () {


            const file =
                this.files[0];


            if (!file) {
                return;
            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {


                    preview.src =
                        event.target.result;


                    preview.style.display =
                        'block';


                    placeholder.style.display =
                        'none';

                };


            reader.readAsDataURL(
                file
            );


        }
    );



    /* =========================
       DRAG & DROP
    ========================= */


    const uploadBox =
        document.querySelector(
            '.upload-box'
        );


    uploadBox.addEventListener(
        'dragover',
        function (event) {


            event.preventDefault();


            this.style.borderColor =
                '#2780e8';


            this.style.background =
                '#f3f9ff';

        }
    );


    uploadBox.addEventListener(
        'dragleave',
        function () {


            this.style.borderColor =
                '#9bbde7';


            this.style.background =
                '#fbfdff';

        }
    );


    uploadBox.addEventListener(
        'drop',
        function (event) {


            event.preventDefault();


            this.style.borderColor =
                '#9bbde7';


            this.style.background =
                '#fbfdff';


            const files =
                event.dataTransfer.files;


            if (files.length > 0) {


                gambarInput.files =
                    files;


                const file =
                    files[0];


                const reader =
                    new FileReader();


                reader.onload =
                    function (event) {


                        preview.src =
                            event.target.result;


                        preview.style.display =
                            'block';


                        placeholder.style.display =
                            'none';

                    };


                reader.readAsDataURL(
                    file
                );

            }

        }
    );


</script>


</body>

</html>


<?php

$conn->close();

?>