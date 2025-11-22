<?php
// kirim_email.php
// Pastikan baris ini sesuai dengan lokasi folder vendor Anda
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationCode($recipientEmail, $code) {
    
    // --- KONFIGURASI SMTP ANDA (SUDAH DIISI) ---
    $smtp_host = 'smtp.gmail.com'; 
    $smtp_username = 'stevendanendra000@gmail.com'; // Email Pengirim
    $smtp_password = 'pmqt bezy uqjb pvdv'; // App Password dari Gmail
    $smtp_port = 587; 

    $mail = new PHPMailer(true);
    
    try {
        // Konfigurasi Server SMTP
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_username;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp_port;

        // Pengirim dan Penerima
        $mail->setFrom($smtp_username, 'ShinyHome Support');
        $mail->addAddress($recipientEmail);

        // Isi Email
        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi Reset Password ShinyHome';
        $mail->Body    = "Halo,<br><br>Kode verifikasi Anda untuk reset password ShinyHome adalah: 
                          <h1 style='color:#007bff;'>$code</h1><br>
                          Kode ini berlaku untuk sekali pakai (hanya 3 menit).";
        $mail->AltBody = "Kode verifikasi Anda adalah: $code";

        $mail->send();
        return true; // Berhasil
    } catch (Exception $e) {
        // Log error di sini (optional)
        // Jika gagal, pastikan App Password sudah benar dan 2FA aktif di akun Gmail Anda.
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false; // Gagal
    }
}
?>