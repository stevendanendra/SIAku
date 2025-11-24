<?php
// lupa_password_proses2.php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: lupa_password.html");
    exit();
}

$email = $_SESSION['reset_email'];
$step = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['step'] : 'input_code';

$error = '';
$success_alert = $_SESSION['success_alert'] ?? '';
unset($_SESSION['success_alert']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // LANGKAH 1 - Verifikasi kode
    if ($step == 'verify_code') {

        $input_code = $conn->real_escape_string($_POST['code']);

        $sql_check_code = "
            SELECT id_pengguna, kode_dibuat_pada 
            FROM ms_pengguna 
            WHERE email = '$email' AND kode_verifikasi = '$input_code'
        ";

        $result = $conn->query($sql_check_code);

        if ($result->num_rows == 1) {

            $data = $result->fetch_assoc();
            $id_pengguna    = $data['id_pengguna'];
            $waktu_dibuat   = strtotime($data['kode_dibuat_pada']);
            $waktu_sekarang = time(); // <-- pastikan nama ini dipakai konsisten
            $batas_waktu    = 180; // 3 menit

            // Periksa kadaluarsa dengan variabel $waktu_sekarang (tanpa karakter tersembunyi)
            if (($waktu_sekarang - $waktu_dibuat) > $batas_waktu) {

                $error = "Kode verifikasi sudah kadaluarsa. Silakan ulangi proses reset.";

                $conn->query("
                    UPDATE ms_pengguna 
                    SET kode_verifikasi = NULL, kode_dibuat_pada = NULL
                    WHERE id_pengguna = '$id_pengguna'
                ");

                $step = 'input_code';

            } else {
                $step = 'input_new_password';
            }

        } else {
            $error = "Kode verifikasi salah atau tidak ditemukan.";
            $step = 'input_code';
        }
    }

    // LANGKAH 2 - Set password baru
    elseif ($step == 'set_new_password') {

        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error = "Konfirmasi password tidak cocok.";
            $step = 'input_new_password';

        } elseif (strlen($new_password) < 4) {
            $error = "Password minimal 4 karakter.";
            $step = 'input_new_password';

        } else {

            $hashed_password = $new_password; // plaintext untuk testing

            $sql_update = "
                UPDATE ms_pengguna 
                SET password = '$hashed_password',
                    kode_verifikasi = NULL,
                    kode_dibuat_pada = NULL
                WHERE email = '$email'
            ";

            if ($conn->query($sql_update)) {
                session_unset();
                session_destroy();
                echo "<script>alert('Password berhasil diubah! Silakan login.'); window.location.href='login.html';</script>";
                exit();
            } else {
                $error = "Gagal memperbarui password: " . $conn->error;
                $step = 'input_new_password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi & Reset Password - ShinyHome SIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { 
            background-color: #f4f7f6; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0;
        }
        .login-card { 
            width: 100%; 
            max-width: 400px; 
            padding: 30px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            border-radius: 8px; 
            background-color: white;
        }
    </style>
</head>
<body>

<div class="login-card">

    <h2 class="text-center mb-4" style="color: #1abc9c;">Verifikasi & Reset Password</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success_alert)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_alert) ?></div>
    <?php endif; ?>


    <!-- === STEP 1: INPUT KODE === -->
    <?php if ($step == 'input_code'): ?>

        <p class="text-center text-muted">Kode verifikasi telah dikirim ke:</p>
        <p class="text-center fw-bold"><?= htmlspecialchars($email) ?></p>

        <p class="text-center text-danger">
            Kode hanya berlaku <b>3 menit</b>.
        </p>

        <form action="lupa_password_proses2.php" method="POST">
            <input type="hidden" name="step" value="verify_code">

            <div class="mb-3">
                <label class="form-label">Kode Verifikasi (6 digit)</label>
                <input type="text" name="code" maxlength="6" class="form-control text-center fs-4 fw-bold"
                       required inputmode="numeric">
            </div>

            <button type="submit" class="btn w-100 mb-3"
                    style="background-color: #1abc9c; border: none; color: white;">
                Verifikasi Kode
            </button>
        </form>


    <!-- === STEP 2: PASSWORD BARU === -->
    <?php elseif ($step == 'input_new_password'): ?>

        <p class="text-center text-success fw-bold">Kode berhasil diverifikasi. Masukkan password baru Anda.</p>

        <form action="lupa_password_proses2.php" method="POST">
            <input type="hidden" name="step" value="set_new_password">

            <div class="mb-3">
                <label class="form-label">Password Baru</label>
                <input type="password" name="new_password" class="form-control" required minlength="4">
            </div>

            <div class="mb-3">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn w-100 mb-3"
                    style="background-color: #1abc9c; border: none; color: white;">
                Set Password Baru
            </button>
        </form>

    <?php endif; ?>


    <div class="text-center mt-2">
        <a href="login.html" class="text-muted">← Kembali ke Login</a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
