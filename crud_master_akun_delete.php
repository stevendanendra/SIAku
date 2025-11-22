<?php
// crud_master_akun_delete.php
session_start();
include 'koneksi.php'; // Memuat fungsi logAktivitas()

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_akun_to_delete = $conn->real_escape_string($_GET['id']);
$redirect_url = 'crud_master_akun.php';

// --- INISIALISASI DATA LOG (FIX KRITIS) ---
// Pastikan variabel ada dan tidak NULL. Jika session hilang, gunakan ID/Nama default.
$id_login = $_SESSION['id_pengguna'] ?? 0; // ID default 0 (jika ada di ms_pengguna)
$username_login = $_SESSION['username'] ?? 'SYSTEM_USER'; // FIX: Nilai non-NULL wajib
$AKUN_HARUS_DIJAGA = [1101, 2102, 5101, 5102, 5103]; // Akun Kritis (Kas/Payroll)

// --- 1. VALIDASI AKUN KRITIS ---
if (in_array($id_akun_to_delete, $AKUN_HARUS_DIJAGA)) {
    $_SESSION['error_delete'] = "⛔ Gagal hapus! Akun ID {$id_akun_to_delete} adalah akun KRITIS SISTEM (Kas/Payroll) dan tidak boleh dihapus.";
    
    // LOG AKTIVITAS: Penghapusan Akun Kritis Ditolak
    if (function_exists('logAktivitas')) {
        logAktivitas($id_login, $username_login, "Gagal: Mencoba menghapus Akun Kritis ID {$id_akun_to_delete}", "Master Akun");
    }
    header("Location: $redirect_url");
    exit();
}

// --- 2. VALIDASI TRANSAKSI TERKAIT (GUARDRAIL JURNAL) ---
$sql_check_jurnal = "
    SELECT COUNT(*) AS total 
    FROM tr_jurnal_umum 
    WHERE id_akun = '$id_akun_to_delete'
";

$result_check = $conn->query($sql_check_jurnal);

if ($result_check === FALSE) {
    $_SESSION['error_delete'] = "⚠️ Gagal memeriksa transaksi terkait: " . $conn->error;
    header("Location: $redirect_url");
    exit();
}

$data_check = $result_check->fetch_assoc();
$total_transaksi = (int)$data_check['total'];

if ($total_transaksi > 0) {
    // Akun sudah digunakan dalam transaksi
    $_SESSION['error_delete'] = "⚠️ Akun GAGAL dihapus. Akun ID {$id_akun_to_delete} sudah memiliki **{$total_transaksi}** entri di Jurnal Umum.";
    
    // LOG AKTIVITAS: Penghapusan Ditolak karena Transaksi
    if (function_exists('logAktivitas')) {
        logAktivitas($id_login, $username_login, "Gagal: Ditolak hapus Akun {$id_akun_to_delete} ($total_transaksi jurnal)", "Master Akun");
    }
    header("Location: $redirect_url");
    exit();
}

// --- 3. LOGIKA HAPUS (DELETE) ---
$sql_delete = "DELETE FROM ms_akun WHERE id_akun = '$id_akun_to_delete'";

if ($conn->query($sql_delete) === TRUE) {
    
    // LOG AKTIVITAS: Penghapusan Berhasil
    if (function_exists('logAktivitas')) {
        logAktivitas($id_login, $username_login, "Berhasil menghapus Akun ID {$id_akun_to_delete}", "Master Akun");
    }
    
    $_SESSION['success_delete'] = "✅ Akun ID {$id_akun_to_delete} berhasil dihapus.";
} else {
    // Tangani potensi error Foreign Key dari tabel lain
    $error_detail = "Akun masih terhubung ke tabel master/transaksi lain.";
    $_SESSION['error_delete'] = "❌ Error saat menghapus akun: {$error_detail}. " . $conn->error;
    
    // LOG AKTIVITAS: Penghapusan Gagal karena FK
    if (function_exists('logAktivitas')) {
        logAktivitas($id_login, $username_login, "Gagal: Akun {$id_akun_to_delete} terhubung ke FK lain.", "Master Akun");
    }
}

header("Location: $redirect_url");
exit();
?>