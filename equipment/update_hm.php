
<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$hm_baru = isset($_POST['hm_baru'])
    ? (float) $_POST['hm_baru']
    : -1;

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
        'message' => 'HM tidak boleh kurang dari 0.'
    ]);
    exit;
}

/* Ambil HM saat ini */
$stmt = $conn->prepare("
    SELECT hm_current
    FROM equipment
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memeriksa data equipment.'
    ]);
    exit;
}

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
$hm_lama = (float) $equipment['hm_current'];

$stmt->close();

/* HM baru tidak boleh lebih kecil dari HM sebelumnya */
if ($hm_baru < $hm_lama) {
    echo json_encode([
        'success' => false,
        'message' => 'HM terbaru tidak boleh lebih kecil dari HM saat ini.'
    ]);
    exit;
}

try {
    $conn->begin_transaction();

    /* 1. Perbarui HM terbaru di Equipment Master */
    $stmt = $conn->prepare("
        UPDATE equipment
        SET hm_current = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Gagal menyiapkan update equipment.');
    }

    $stmt->bind_param("di", $hm_baru, $id);

    if (!$stmt->execute()) {
        throw new Exception('Gagal memperbarui HM equipment.');
    }

    $stmt->close();

    /*
     * 2. Hitung ulang seluruh jadwal yang belum selesai
     * untuk unit ini.
     */
    $schedule_stmt = $conn->prepare("
        UPDATE maintenance_schedule
        SET
            current_hm_km = ?,
            remaining_hm_km = next_service - ?,
            status = CASE
                WHEN status = 'Menunggu Spare Part'
                    THEN status
                WHEN (next_service - ?) < 0
                    THEN 'Overdue'
                WHEN (next_service - ?) <= 100
                    THEN 'Segera Diservis'
                WHEN (next_service - ?) <= 250
                    THEN 'Akan Jatuh Tempo'
                ELSE 'Normal'
            END
        WHERE equipment_id = ?
          AND status <> 'Selesai'
    ");

    if (!$schedule_stmt) {
        throw new Exception('Gagal menyiapkan update jadwal maintenance.');
    }

    $schedule_stmt->bind_param(
        "dddddi",
        $hm_baru,
        $hm_baru,
        $hm_baru,
        $hm_baru,
        $hm_baru,
        $id
    );

    if (!$schedule_stmt->execute()) {
        throw new Exception('Gagal memperbarui jadwal maintenance.');
    }

    $schedule_stmt->close();

    /* Simpan kedua pembaruan sekaligus */
    $conn->commit();
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'HM dan jadwal maintenance berhasil diperbarui.',
        'hm_baru' => $hm_baru
    ]);

    exit;

} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // Tidak ada tindakan tambahan jika rollback gagal.
    }

    error_log('Update HM: ' . $e->getMessage());

    $conn->close();

    echo json_encode([
        'success' => false,
        'message' => 'Pembaruan HM atau jadwal maintenance gagal. Silakan periksa kembali.'
    ]);

    exit;
}
?>
