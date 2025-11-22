<?php
// crud_master_pengguna_delete.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_pengguna_to_delete = $conn->real_escape_string($_GET['id']);
$redirect_url = 'crud_master_pengguna.php';

// --- 1. VALIDASI AKUN KRITIS (Owner) ---
// Akun Owner (ID 1) biasanya harus dijaga agar tidak terhapus.
$AKUN_KRITIS_DIJAGA = 1; 

if ($id_pengguna_to_delete == $AKUN_KRITIS_DIJAGA) {
    $_SESSION['error_delete'] = "⛔ Gagal hapus! Akun ID {$id_pengguna_to_delete} (Owner Utama) tidak boleh dihapus.";
    header("Location: $redirect_url");
    exit();
}

// --- 2. VALIDASI TRANSAKSI TERKAIT (GUARDRAIL) ---
$total_transaksi = 0;
$error_source = '';

// a. Cek Riwayat Gaji (tr_gaji)
$sql_check_gaji = "SELECT COUNT(*) AS total FROM tr_gaji WHERE id_cleaner = '$id_pengguna_to_delete'";
$result_gaji = $conn->query($sql_check_gaji);
if ($result_gaji === FALSE) {
    $error_source = "gaji";
    goto check_failed;
}
$data_gaji = $result_gaji->fetch_assoc();
$total_transaksi += (int)$data_gaji['total'];

// b. Cek Transaksi Penjualan (tr_penjualan) - Jika pengguna adalah karyawan/kasir
$sql_check_penjualan = "SELECT COUNT(*) AS total FROM tr_penjualan WHERE id_karyawan = '$id_pengguna_to_delete'";
$result_penjualan = $conn->query($sql_check_penjualan);
if ($result_penjualan === FALSE) {
    $error_source = "penjualan";
    goto check_failed;
}
$data_penjualan = $result_penjualan->fetch_assoc();
$total_transaksi += (int)$data_penjualan['total'];

// c. Cek Transaksi Pengeluaran (tr_pengeluaran) - Jika pengguna mencatat pengeluaran/prive
$sql_check_pengeluaran = "SELECT COUNT(*) AS total FROM tr_pengeluaran WHERE id_karyawan = '$id_pengguna_to_delete'";
$result_pengeluaran = $conn->query($sql_check_pengeluaran);
if ($result_pengeluaran === FALSE) {
    $error_source = "pengeluaran";
    goto check_failed;
}
$data_pengeluaran = $result_pengeluaran->fetch_assoc();
$total_transaksi += (int)$data_pengeluaran['total'];


if ($total_transaksi > 0) {
    // Pengguna sudah memiliki riwayat transaksi di salah satu tabel
    $_SESSION['error_delete'] = "⚠️ Pengguna GAGAL dihapus. ID {$id_pengguna_to_delete} sudah memiliki **{$total_transaksi}** riwayat transaksi (Gaji, Penjualan, atau Pengeluaran). Integritas data harus dijaga.";
    header("Location: $redirect_url");
    exit();
}


// --- 3. LOGIKA HAPUS (DELETE) ---
$sql_delete = "DELETE FROM ms_pengguna WHERE id_pengguna = '$id_pengguna_to_delete'";

if ($conn->query($sql_delete) === TRUE) {
    $_SESSION['success_message'] = "✅ Pengguna ID {$id_pengguna_to_delete} berhasil dihapus.";
} else {
    // Tangani potensi error Foreign Key yang tidak terdeteksi di awal
    $_SESSION['error_delete'] = "❌ Error saat menghapus pengguna: Pengguna masih terhubung ke tabel master/transaksi lain yang tidak terdeteksi. " . $conn->error;
}

header("Location: $redirect_url");
exit();

// Label untuk lompatan goto
check_failed:
$_SESSION['error_delete'] = "❌ Error saat memeriksa tabel {$error_source}: " . $conn->error;
header("Location: $redirect_url");
exit();
?>