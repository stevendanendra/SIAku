<?php
// crud_master_layanan_delete.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_layanan_to_delete = $conn->real_escape_string($_GET['id']);
$redirect_url = 'crud_master_layanan.php';

// --- 1. VALIDASI TRANSAKSI TERKAIT (GUARDRAIL) ---
// FIX: Query disesuaikan untuk mencari referensi di tabel tr_penjualan_detail yang baru dibuat
$sql_check_transaksi = "
    SELECT COUNT(*) AS total 
    FROM tr_penjualan_detail 
    WHERE id_layanan = '$id_layanan_to_delete'
";

$result_check = $conn->query($sql_check_transaksi);

if ($result_check === FALSE) {
    $_SESSION['error_delete'] = "⚠️ Gagal memeriksa tabel detail transaksi. Harap pastikan tabel tr_penjualan_detail sudah dibuat dan terisi.";
    header("Location: $redirect_url");
    exit();
}

$data_check = $result_check->fetch_assoc();
$total_transaksi = (int)$data_check['total'];

if ($total_transaksi > 0) {
    // Layanan sudah digunakan dalam transaksi
    $_SESSION['error_delete'] = "⚠️ Layanan GAGAL dihapus. Layanan ID {$id_layanan_to_delete} sudah memiliki **{$total_transaksi}** riwayat transaksi penjualan. Integritas data harus dijaga.";
    header("Location: $redirect_url");
    exit();
}

// --- 2. LOGIKA HAPUS (DELETE) ---
$sql_delete = "DELETE FROM ms_layanan WHERE id_layanan = '$id_layanan_to_delete'";

if ($conn->query($sql_delete) === TRUE) {
    $_SESSION['success_message'] = "✅ Layanan ID {$id_layanan_to_delete} berhasil dihapus.";
} else {
    // Tangani potensi error Foreign Key yang tidak terdeteksi di awal
    $_SESSION['error_delete'] = "❌ Error saat menghapus layanan: Layanan masih terhubung ke tabel transaksi lain yang tidak terdeteksi. " . $conn->error;
}

header("Location: $redirect_url");
exit();
?>