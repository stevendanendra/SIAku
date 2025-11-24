<?php
session_start();
include 'koneksi.php'; // Sudah ada logAktivitas()

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

// --- FIX: paksa ke integer agar in_array berjalan normal
$id_akun_to_delete = (int) $_GET['id'];
$redirect_url = 'crud_master_akun.php';

// --- INISIALISASI DATA LOG (FIX sesuai nama kolom tabel)
$id_login = $_SESSION['id_pengguna'] ?? 0;
$username_login = $_SESSION['username'] ?? 'SYSTEM_USER';

// Akun yang tidak boleh dihapus
$AKUN_HARUS_DIJAGA = [1101, 2102, 5101, 5102, 5103];

// --- 1. VALIDASI AKUN KRITIS ---
if (in_array($id_akun_to_delete, $AKUN_HARUS_DIJAGA)) {
    $_SESSION['error_delete'] = "⛔ Akun ID {$id_akun_to_delete} adalah akun KRITIS dan tidak boleh dihapus.";

    logAktivitas($id_login, $username_login, 
        "Gagal: Mencoba menghapus Akun Kritis ID {$id_akun_to_delete}", 
        "Master Akun"
    );

    header("Location: $redirect_url");
    exit();
}

// --- 2. CEK apakah akun sudah dipakai di jurnal ---
$sql_check = "
    SELECT COUNT(*) AS total 
    FROM tr_jurnal_umum 
    WHERE id_akun = $id_akun_to_delete
";

$res = $conn->query($sql_check);
$data = $res->fetch_assoc();

if ($data['total'] > 0) {

    $_SESSION['error_delete'] = 
        "⚠️ Akun ID {$id_akun_to_delete} sudah memiliki {$data['total']} transaksi jurnal.";

    logAktivitas($id_login, $username_login, 
        "Gagal: Akun {$id_akun_to_delete} memiliki {$data['total']} jurnal", 
        "Master Akun"
    );

    header("Location: $redirect_url");
    exit();
}

// --- 3. PROSES DELETE ---
$sql_delete = "DELETE FROM ms_akun WHERE id_akun = $id_akun_to_delete";

if ($conn->query($sql_delete)) {

    logAktivitas($id_login, $username_login, 
        "Berhasil menghapus Akun ID {$id_akun_to_delete}", 
        "Master Akun"
    );

    $_SESSION['success_delete'] = 
        "✅ Akun ID {$id_akun_to_delete} berhasil dihapus.";
} 
else {

    $_SESSION['error_delete'] = "❌ Error: " . $conn->error;

    logAktivitas($id_login, $username_login, 
        "Gagal menghapus Akun ID {$id_akun_to_delete} (FK error)", 
        "Master Akun"
    );
}

header("Location: $redirect_url");
exit();
?>
