<?php
session_start();
include 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request");
}

$user_input = trim($_POST['user_input']);
$input_password = trim($_POST['password']);

if ($user_input === "" || $input_password === "") {
    exit("Input tidak boleh kosong.");
}

$sql = "SELECT id_pengguna, password, nama_lengkap, role, is_aktif 
        FROM ms_pengguna
        WHERE username = ? OR email = ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $user_input, $user_input);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    $conn->close();
    exit("Login Gagal. Username atau Email tidak terdaftar.");
}

$user = $result->fetch_assoc();

if (!$user['is_aktif']) {
    $stmt->close();
    $conn->close();
    exit("Login Gagal. Akun Anda tidak aktif. Silakan hubungi Owner.");
}

if (!password_verify($input_password, $user['password'])) {
    $stmt->close();
    $conn->close();
    exit("Login Gagal. Password salah.");
}

// ✔ LOGIN OK
$_SESSION['id_pengguna'] = $user['id_pengguna'];
$_SESSION['nama'] = $user['nama_lengkap'];
$_SESSION['role'] = $user['role'];

$stmt->close();
$conn->close();

if ($user['role'] === 'Owner') {
    header("Location: dashboard_owner.php");
} elseif ($user['role'] === 'Karyawan') {
    header("Location: dashboard_karyawan.php");
} else {
    exit("Akses ditolak untuk peran ini.");
}
exit;
?>
