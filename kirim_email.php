<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendVerificationCode($email, $kode) {
    $mail = new PHPMailer(true);

    try {
        // SMTP / Server pengirim
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // GANTI EMAILMU
        $mail->Username   = 'stevendanendra000@gmail.com';

        // GANTI APP PASSWORD 16 DIGIT (HARUS TANPA SPASI!)
        // Contoh yang benar: pmqtbezyuqjbpvdv
        $mail->Password   = 'pmqtbezyuqjbpvdv';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Set pengirim
        $mail->setFrom('stevendanendra000@gmail.com', 'ShinyHome SIA');

        // Set penerima
        $mail->addAddress($email);

        // Email format
        $mail->isHTML(true);
        $mail->Subject = 'Kode Reset Password Anda';
        $mail->Body    = "
            <h3>Kode Verifikasi Reset Password</h3>
            <p>Kode verifikasi Anda adalah:</p>
            <h2>$kode</h2>
            <p>Kode ini berlaku selama <b>5 menit</b>.</p>
        ";
        $mail->AltBody = "Kode verifikasi Anda: $kode (berlaku 5 menit)";

        // Kirim email
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return false;
    }
}
