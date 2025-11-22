<?php
// crud_master_pelanggan_delete.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_pelanggan_to_delete = $conn->real_escape_string($_GET['id']);
$redirect_url = 'crud_master_pelanggan.php';

// --- 1. VALIDASI TRANSAKSI TERKAIT (GUARDRAIL) ---
$sql_check_transaksi = "
    SELECT COUNT(*) AS total 
    FROM tr_penjualan 
    WHERE id_pelanggan = '$id_pelanggan_to_delete'
";

$result_check = $conn->query($sql_check_transaksi);

if ($result_check === FALSE) {
    $_SESSION['error_message'] = "⚠️ Gagal memeriksa tabel transaksi penjualan: " . $conn->error;
    header("Location: $redirect_url");
    exit();
}

$data_check = $result_check->fetch_assoc();
$total_transaksi = (int)$data_check['total'];

if ($total_transaksi > 0) {
    // Pelanggan sudah digunakan dalam transaksi penjualan (piutang atau kas)
    $_SESSION['error_message'] = "⚠️ Pelanggan GAGAL dihapus. ID {$id_pelanggan_to_delete} sudah memiliki **{$total_transaksi}** riwayat transaksi penjualan. Integritas data harus dijaga.";
    header("Location: $redirect_url");
    exit();
}

// --- 2. LOGIKA HAPUS (DELETE) ---
// Gunakan TRANSACTION untuk memastikan operasi aman (walaupun hanya satu tabel)
$conn->begin_transaction();

try {
    $sql_delete = "DELETE FROM ms_pelanggan WHERE id_pelanggan = '$id_pelanggan_to_delete'";

    if ($conn->query($sql_delete) === TRUE) {
        $conn->commit();
        $_SESSION['success_message'] = "✅ Pelanggan ID {$id_pelanggan_to_delete} berhasil dihapus.";
    } else {
        throw new Exception("Gagal menghapus data: " . $conn->error);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "❌ Error saat menghapus pelanggan: Pelanggan masih terhubung ke tabel master lain. " . $e->getMessage();
}

header("Location: $redirect_url");
exit();
?>