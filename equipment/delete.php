<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Ambil ID Equipment
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;


/*
|--------------------------------------------------------------------------
| Validasi ID
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    header("Location: index.php?error=invalid_id");
    exit;

}


/*
|--------------------------------------------------------------------------
| Cek apakah equipment tersedia
|--------------------------------------------------------------------------
*/

$check_sql = "SELECT id FROM equipment WHERE id = ?";

$check_stmt = $conn->prepare($check_sql);

$check_stmt->bind_param("i", $id);

$check_stmt->execute();

$check_result = $check_stmt->get_result();


if ($check_result->num_rows !== 1) {

    $check_stmt->close();

    header("Location: index.php?error=not_found");
    exit;

}

$check_stmt->close();


/*
|--------------------------------------------------------------------------
| Hapus Equipment
|--------------------------------------------------------------------------
*/

$delete_sql = "DELETE FROM equipment WHERE id = ?";

$delete_stmt = $conn->prepare($delete_sql);

$delete_stmt->bind_param("i", $id);


if ($delete_stmt->execute()) {

    $delete_stmt->close();

    $conn->close();

    header("Location: index.php?success=deleted");
    exit;

}


/*
|--------------------------------------------------------------------------
| Jika gagal
|--------------------------------------------------------------------------
*/

$delete_stmt->close();

$conn->close();

header("Location: index.php?error=delete_failed");
exit;

?>