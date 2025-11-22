<?php
// crud_master_akun_delete.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_akun = $conn->real_escape_string($_GET['id']);
$redirect_url = 'crud_master_akun.php';

// --- VALIDASI PENTING SEBELUM HAPUS ---
// 1. Akun Inti (Kas, Utang, Piutang) tidak boleh dihapus
$akun_inti = [1101, 1102, 2101, 3101, 4101, 5101]; 
if (in_array($id_akun, $akun_inti)) {
    $_SESSION['error_delete'] = "🚫 Akun $id_akun adalah AKUN INTI sistem dan TIDAK BOLEH dihapus.";
    header("Location: $redirect_url");
    exit();
}

// 2. Cek apakah Akun sudah memiliki mutasi di Jurnal Umum
$sql_check_mutasi = "SELECT COUNT(*) AS total FROM tr_jurnal_umum WHERE id_akun = '$id_akun'";
$result_check = $conn->query($sql_check_mutasi);
$data_check = $result_check->fetch_assoc();

if ($data_check['total'] > 0) {
    $_SESSION['error_delete'] = "⚠️ Akun $id_akun TIDAK DAPAT dihapus karena sudah memiliki $data_check[total] mutasi transaksi di Jurnal Umum. Akun yang sudah bertransaksi harus tetap ada.";
    header("Location: $redirect_url");
    exit();
}

// --- LOGIKA HAPUS (DELETE) ---
$sql_delete = "DELETE FROM ms_akun WHERE id_akun = '$id_akun'";

if ($conn->query($sql_delete) === TRUE) {
    $_SESSION['success_delete'] = "✅ Akun $id_akun berhasil dihapus.";
} else {
    $_SESSION['error_delete'] = "Error saat menghapus akun: " . $conn->error;
}

header("Location: $redirect_url");
exit();
?>