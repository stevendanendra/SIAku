<?php
// lupa_password_proses1.php
session_start(); // Wajib dipanggil sebelum include koneksi untuk session_start()
include 'koneksi.php'; 
require 'kirim_email.php'; // Panggil fungsi pengiriman (pastikan konfigurasi SMTP sudah benar)

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    
    // Inisialisasi variabel untuk status dan tujuan redirect
    $redirect_to = 'lupa_password.html'; // Default redirect
    $message = '';

    // 1. Cek apakah email terdaftar
    $sql_check = "SELECT id_pengguna FROM ms_pengguna WHERE email = '$email'";
    $result = $conn->query($sql_check);
    
    if ($result->num_rows == 1) {
        // Email ditemukan.
        $user = $result->fetch_assoc();
        $id_pengguna = $user['id_pengguna'];
        
        // 2. Generate Kode Verifikasi (6 digit angka acak)
        $verification_code = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        
        // 3. Simpan Kode ke Database (Menambahkan NOW() untuk waktu kedaluwarsa 3 menit)
        $sql_update = "UPDATE ms_pengguna SET 
                       kode_verifikasi = '$verification_code',
                       kode_dibuat_pada = NOW() 
                       WHERE id_pengguna = '$id_pengguna'";
        
        if ($conn->query($sql_update) === TRUE) {
            
            // 4. Panggil fungsi pengiriman email yang nyata
            if (sendVerificationCode($email, $verification_code)) {
                
                $_SESSION['reset_email'] = $email;
                $message = 'Kode verifikasi telah dikirim ke email Anda.';
                $redirect_to = 'lupa_password_proses2.php'; // Redirect Sukses ke step 2
                
            } else {
                // Email gagal terkirim (SMTP error)
                $message = "❌ Gagal mengirim email verifikasi. Periksa konfigurasi SMTP.";
            }

        } else {
            // Gagal menyimpan kode ke DB
            $message = "Error menyimpan kode verifikasi ke database: " . $conn->error;
        }
        
    } else {
        // Email tidak ditemukan. Beri pesan ambigu (untuk keamanan).
        $message = "Jika email terdaftar, kode verifikasi telah dikirim.";
    }
    
    $conn->close();
    
    // --- FINAL REDIRECT: Hanya terjadi sekali di akhir script ---
    
    // Tentukan jenis pesan yang akan dibawa oleh sesi
    if ($redirect_to === 'lupa_password_proses2.php') {
        $_SESSION['success_alert'] = $message;
    } else {
        // Jika redirect ke halaman awal, gunakan pesan sukses/error yang sesuai
        if (strpos($message, '❌') !== false) {
             $_SESSION['error_message'] = $message;
        } else {
             $_SESSION['success_alert'] = $message;
        }
    }

    header("Location: $redirect_to");
    exit();
}
?>