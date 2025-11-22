<?php
// payroll_komponen_delete.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_komponen_to_delete = $conn->real_escape_string($_GET['id']);
$redirect_url = 'payroll_komponen.php';

// --- VALIDASI PENTING SEBELUM HAPUS: Cek transaksi terkait di tr_gaji_detail ---

$sql_check_mutasi = "
    SELECT COUNT(*) AS total 
    FROM tr_gaji_detail 
    WHERE id_komponen = '$id_komponen_to_delete'
";

$result_check = $conn->query($sql_check_mutasi);

if ($result_check === FALSE) {
     // Penanganan jika query check gagal (misalnya, tabel tr_gaji_detail tidak ada)
    $_SESSION['error_delete'] = "⚠️ Gagal memeriksa transaksi terkait: " . $conn->error;
    header("Location: $redirect_url");
    exit();
}

$data_check = $result_check->fetch_assoc();
$total_transaksi = (int)$data_check['total'];

if ($total_transaksi > 0) {
    // Komponen sudah digunakan dalam perhitungan gaji yang tersimpan.
    $_SESSION['error_delete'] = "⚠️ Komponen Gaji #$id_komponen_to_delete GAGAL dihapus. Komponen ini sudah digunakan dalam **$total_transaksi** riwayat transaksi gaji.";
    header("Location: $redirect_url");
    exit();
}


// --- LOGIKA HAPUS (DELETE) ---
$sql_delete = "DELETE FROM ms_gaji_komponen WHERE id_komponen = '$id_komponen_to_delete'";

if ($conn->query($sql_delete) === TRUE) {
    $_SESSION['success_delete'] = "✅ Komponen #$id_komponen_to_delete berhasil dihapus.";
} else {
    // Ini mungkin menangkap error Foreign Key dari tabel lain, tapi jarang terjadi jika validasi tr_gaji_detail sudah benar
    $_SESSION['error_delete'] = "Error saat menghapus komponen: " . $conn->error;
}

header("Location: $redirect_url");
exit();
?>