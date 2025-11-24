<?php
session_start();
require 'koneksi.php';

// Jika email reset belum ada di session → kembali ke lupa password
if (!isset($_SESSION['reset_email'])) {
    header("Location: lupa_password.html");
    exit();
}

$email = $_SESSION['reset_email'];
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // ❗ Validasi
    if (empty($password) || empty($confirm)) {
        $error = "Semua field wajib diisi!";
    } elseif ($password !== $confirm) {
        $error = "Password dan konfirmasi tidak sama!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Hash password baru
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // ❗ UPDATE MENGGUNAKAN TABEL YANG BENAR: ms_pengguna
        $query = $conn->prepare("UPDATE ms_pengguna SET password = ? WHERE email = ?");
        $query->bind_param("ss", $hash, $email);

        if ($query->execute()) {
            // Hapus session reset email
            unset($_SESSION['reset_email']);
            unset($_SESSION['verified_reset']);

            $success = "Password berhasil direset! Silakan login kembali.";
        } else {
            $error = "Terjadi kesalahan database: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Reset Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #e9f7f5, #c8f3ea);
    height: 100vh;
    margin: 0; 
    display: flex;
    justify-content: center;
    align-items: center;
}
.card {
    width: 380px;
    padding: 30px;
    border-radius: 12px;
    background: white;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}
h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #16a085;
    font-weight: 700;
}
.btn-main {
    background: #1abc9c;
    border: none;
    width: 100%;
    padding: 12px;
    font-size: 16px;
    border-radius: 6px;
    color: white;
}
.btn-main:hover { background: #17a589; }
</style>
</head>
<body>

<div class="card">

    <h2>Reset Password</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success text-center">
            <?= $success ?><br><br>
            <a href="login.html" class="btn btn-success w-100">Kembali ke Login</a>
        </div>
    <?php else: ?>

    <!-- Jika belum sukses, tampilkan form -->
    <form method="POST">
        <div class="mb-3">
            <label>Password Baru</label>
            <input type="password" name="password" class="form-control" required minlength="6">
        </div>

        <div class="mb-3">
            <label>Konfirmasi Password</label>
            <input type="password" name="confirm" class="form-control" required minlength="6">
        </div>

        <button class="btn-main">Reset Password</button>
    </form>

    <?php endif; ?>

</div>

</body>
</html>
