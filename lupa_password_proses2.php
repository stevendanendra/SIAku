<?php
// lupa_password_proses2.php
session_start(); // Wajib dipanggil di awal untuk mengakses sesi
include 'koneksi.php';

// Pastikan email ada di session
if (!isset($_SESSION['reset_email'])) {
    header("Location: lupa_password.html");
    exit();
}

$email = $_SESSION['reset_email'];

// LOGIKA KRITIS: Jika ini request awal (BUKAN POST), set step default ke 'input_code'.
$step = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['step'] : 'input_code'; 

$error = '';
// Ambil pesan sukses dari proses 1 (jika ada) dan hapus dari sesi
$success_alert = $_SESSION['success_alert'] ?? '';
unset($_SESSION['success_alert']); 


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Langkah 1: Verifikasi Kode dan Waktu ---
    if ($step == 'verify_code') {
        $input_code = $conn->real_escape_string($_POST['code']);
        
        // Query: Ambil kode_dibuat_pada dan id_pengguna
        $sql_check_code = "SELECT id_pengguna, kode_dibuat_pada FROM ms_pengguna 
                           WHERE email = '$email' AND kode_verifikasi = '$input_code'";
        
        $result = $conn->query($sql_check_code);
        
        if ($result->num_rows == 1) {
            $data = $result->fetch_assoc();
            $id_pengguna = $data['id_pengguna'];
            $waktu_dibuat = strtotime($data['kode_dibuat_pada']);
            $waktu_sekarang = time(); // Waktu saat ini dalam detik
            $batas_waktu = 180; // 3 menit = 180 detik
            
            // Cek apakah kode sudah kadaluarsa
            if (($waktu_sekarang - $waktu_dibuat) > $batas_waktu) {
                // Kode kadaluarsa!
                $error = "Kode verifikasi sudah kadaluarsa (lebih dari 3 menit). Silakan ulangi proses reset.";
                
                // Hapus kode dari DB agar tidak bisa digunakan lagi
                $conn->query("UPDATE ms_pengguna SET kode_verifikasi = NULL, kode_dibuat_pada = NULL WHERE id_pengguna = '$id_pengguna'");
                
                $step = 'input_code'; // Kembali ke input kode
            } else {
                // Kode masih berlaku, lanjut ke input password baru
                $step = 'input_new_password';
            }
        } else {
            // Kode salah
            $error = "Kode verifikasi salah atau tidak ditemukan.";
            $step = 'input_code'; // Kembali ke input kode
        }
    }
    
    // --- Langkah 2: Update Password Baru ---
    elseif ($step == 'set_new_password') {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "Konfirmasi password tidak cocok.";
            $step = 'input_new_password';
        } elseif (strlen($new_password) < 4) { 
            $error = "Password minimal 4 karakter.";
            $step = 'input_new_password';
        } else {
            // PERHATIAN: Password disimpan plaintext untuk testing
            $hashed_password = $new_password; 

            $sql_update_pass = "UPDATE ms_pengguna 
                                SET password = '$hashed_password', kode_verifikasi = NULL, kode_dibuat_pada = NULL 
                                WHERE email = '$email'"; // Reset kedua kolom keamanan
            
            if ($conn->query($sql_update_pass) === TRUE) {
                // Berhasil
                session_unset();
                session_destroy();
                $conn->close();
                echo "<script>alert('Password Anda berhasil diubah! Silakan login kembali.'); window.location.href='login.html';</script>";
                exit();
            } else {
                $error = "Error saat memperbarui password: " . $conn->error;
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
    <title>Verifikasi & Reset Password</title>
</head>
<body>
    <h1>Verifikasi & Reset Password</h1>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <?php if (isset($success_alert)) echo "<p style='color:green;'>$success_alert</p>"; ?>
    
    <?php if ($step == 'input_code'): ?>
        <p>Kode telah dikirim ke: <strong><?php echo htmlspecialchars($email); ?></strong></p>
        
        <p style="color: blue; font-weight: bold;">
            ⚠️ Kode ini hanya berlaku selama 3 menit sejak email dikirim.
        </p>
        
        <form action="lupa_password_proses2.php" method="POST">
            <input type="hidden" name="step" value="verify_code">
            <div>
                <label for="code">Kode Verifikasi:</label>
                <input type="text" id="code" name="code" required maxlength="6">
            </div>
            <button type="submit">Verifikasi Kode</button>
        </form>
    
    <?php elseif ($step == 'input_new_password'): ?>
        <p>Kode berhasil diverifikasi. Masukkan password baru Anda.</p>
        <form action="lupa_password_proses2.php" method="POST">
            <input type="hidden" name="step" value="set_new_password">
            <div>
                <label for="new_password">Password Baru:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div>
                <label for="confirm_password">Konfirmasi Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Set Password Baru</button>
        </form>
    <?php endif; ?>
</body>
</html>