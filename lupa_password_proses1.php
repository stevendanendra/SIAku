<?php
session_start();
include 'koneksi.php';
include 'kirim_email.php'; // ← TAMBAHKAN INI

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: lupa_password.html");
    exit();
}

$email = $conn->real_escape_string($_POST['email']);

// CEK EMAIL
$sql = "SELECT id_pengguna FROM ms_pengguna WHERE email = '$email'";
$result = $conn->query($sql);

if ($result->num_rows != 1) {
    $_SESSION['error_message'] = "Email tidak ditemukan.";
    header("Location: lupa_password.html");
    exit();
}

// ANTI SPAM
$cek = $conn->query("
    SELECT TIMESTAMPDIFF(SECOND, kode_dibuat_pada, NOW()) AS selisih
    FROM ms_pengguna 
    WHERE email = '$email'
");

$cekData = $cek->fetch_assoc();

if ($cekData['selisih'] !== null && $cekData['selisih'] < 60) {
    $_SESSION['error_message'] = "Tunggu " . (60 - $cekData['selisih']) . " detik untuk meminta kode baru.";
    header("Location: lupa_password.html");
    exit();
}

// GENERATE KODE
$kode = rand(100000, 999999);
$kode_hash = password_hash($kode, PASSWORD_DEFAULT);

// SIMPAN KODE
$update = $conn->query("
    UPDATE ms_pengguna 
    SET kode_verifikasi = '$kode_hash',
        kode_dibuat_pada = NOW()
    WHERE email = '$email'
");

if (!$update) {
    error_log("ERROR UPDATE KODE: " . $conn->error);
    $_SESSION['error_message'] = "Terjadi kesalahan server. Coba lagi.";
    header("Location: lupa_password.html");
    exit();
}

// SIMPAN EMAIL KE SESSION
$_SESSION['reset_email'] = $email;

// KIRIM EMAIL (PERBAIKAN PENTING!)
sendVerificationCode($email, $kode);

// DEBUG
error_log("KODE RESET UNTUK $email ADALAH $kode");

// REDIRECT
header("Location: lupa_password_proses2.php");
exit();
?>
