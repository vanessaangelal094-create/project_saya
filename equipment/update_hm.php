<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$hm_baru = isset($_POST['hm_baru']) ? (float)$_POST['hm_baru'] : -1;

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID equipment tidak valid.'
    ]);
    exit;
}

if ($hm_baru < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'HM/KM tidak boleh kurang dari 0.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil HM saat ini
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT hm_current
    FROM equipment
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    echo json_encode([
        'success' => false,
        'message' => 'Equipment tidak ditemukan.'
    ]);
    exit;
}

$equipment = $result->fetch_assoc();

$stmt->close();

$hm_lama = (float)$equipment['hm_current'];

/*
|--------------------------------------------------------------------------
| HM baru tidak boleh lebih kecil dari HM sebelumnya
|--------------------------------------------------------------------------
*/

if ($hm_baru < $hm_lama) {
    echo json_encode([
        'success' => false,
        'message' => 'HM/KM terbaru tidak boleh lebih kecil dari HM/KM saat ini.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Update HM
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE equipment
    SET hm_current = ?
    WHERE id = ?
");

$stmt->bind_param("di", $hm_baru, $id);

if ($stmt->execute()) {

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'HM/KM berhasil diperbarui.',
        'hm_baru' => $hm_baru
    ]);

    exit;

} else {

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => false,
        'message' => 'Gagal memperbarui HM/KM.'
    ]);

    exit;
}

?>