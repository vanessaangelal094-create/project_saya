<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

try {
    // Mulai transaksi agar penghapusan tetap konsisten
    $conn->begin_transaction();

    // Pastikan jadwal tersedia
    $stmt = $conn->prepare("
        SELECT id
        FROM maintenance_schedule
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $jadwal_ada = $result->num_rows > 0;
    $stmt->close();

    if (!$jadwal_ada) {
        $conn->rollback();
        $conn->close();

        header("Location: index.php");
        exit;
    }

    // Hapus riwayat yang terkait dengan jadwal ini
    $stmt = $conn->prepare("
        DELETE FROM maintenance_history
        WHERE maintenance_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Hapus jadwal maintenance
    $stmt = $conn->prepare("
        DELETE FROM maintenance_schedule
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Simpan perubahan
    $conn->commit();
    $conn->close();

    header("Location: index.php");
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();

    echo "Jadwal belum berhasil dihapus. Periksa hubungan data di database.";
    exit;
}
?>