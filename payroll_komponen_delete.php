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

// --- VALIDASI PENTING SEBELUM HAPUS: Cek transaksi terkait di tr_gaji ---
// Kita asumsikan, saat proses gaji dilakukan, ID komponen gaji dicatat 
// di tabel detail gaji (misal: tr_gaji_detail). Karena kita tidak punya tabel detail gaji,
// kita akan membuat validasi yang sederhana namun cerdas.

// Logika Cerdas: Jika sudah ada entry di tr_gaji, JANGAN izinkan hapus. 
// Ini mencegah penghapusan komponen yang sudah pernah dipakai untuk MENGHITUNG gaji.

$sql_check_mutasi = "
    SELECT COUNT(*) AS total 
    FROM tr_gaji 
    -- Catatan: Validasi ini bersifat simplifikasi. Dalam sistem nyata, 
    -- Anda akan mengecek di tabel tr_gaji_detail yang menyimpan FK ke ms_gaji_komponen.
    -- Karena kita tidak memiliki tabel detail, kita anggap semua komponen gaji 
    -- yang digunakan dalam periode yang sudah dibayar tidak boleh dihapus.
    WHERE id_slip_gaji IN (SELECT id_slip_gaji FROM tr_gaji WHERE id_cleaner IS NOT NULL)
";

$result_check = $conn->query($sql_check_mutasi);
$data_check = $result_check->fetch_assoc();

if ($data_check['total'] > 0) {
    // Karena ID komponen tidak tercatat langsung di tr_gaji, kita berikan peringatan umum.
    // Jika sistem sudah pernah memproses gaji, komponen dasarnya tidak boleh hilang.
    // Solusi yang lebih akurat: HANYA izinkan menghapus komponen jika tr_gaji kosong.
    
    // Kita anggap: Jika tr_gaji ada isinya, JANGAN HAPUS KOMPONEN APAPUN
    $_SESSION['error_delete'] = "⚠️ Komponen TIDAK DAPAT dihapus karena sudah ada riwayat penggajian di sistem. Harap batalkan semua transaksi gaji terlebih dahulu.";
    header("Location: $redirect_url");
    exit();
}


// --- LOGIKA HAPUS (DELETE) ---
$sql_delete = "DELETE FROM ms_gaji_komponen WHERE id_komponen = '$id_komponen_to_delete'";

if ($conn->query($sql_delete) === TRUE) {
    $_SESSION['success_delete'] = "✅ Komponen #$id_komponen_to_delete berhasil dihapus.";
} else {
    $_SESSION['error_delete'] = "Error saat menghapus komponen: " . $conn->error;
}

header("Location: $redirect_url");
exit();
?>