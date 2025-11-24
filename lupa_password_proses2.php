<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: lupa_password.html");
    exit();
}

$email = $conn->real_escape_string($_SESSION['reset_email']);

// ==========================
// LOGIKA VERIFIKASI KODE
// ==========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['kode'])) {

    $input_code = $_POST['kode'];

    // 0. Validasi: harus 6 digit angka
    if (!preg_match('/^[0-9]{6}$/', $input_code)) {
        $_SESSION['error_message'] = "Kode verifikasi harus 6 digit angka.";
        header("Location: lupa_password_proses2.php");
        exit();
    }

    $sql = "SELECT id_pengguna, kode_verifikasi, kode_dibuat_pada
            FROM ms_pengguna
            WHERE email = '$email'";

    $result = $conn->query($sql);

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();
        $hash = $user['kode_verifikasi'];
        $created_at_raw = $user['kode_dibuat_pada'];
        $created_at = strtotime($created_at_raw);

        // 1. Jika kode_dibuat_pada NULL
        if (!$created_at_raw || !$created_at) {
            $_SESSION['error_message'] = "Kode tidak valid atau belum dibuat. Silakan minta ulang.";
            header("Location: lupa_password_proses2.php");
            exit();
        }

        // 2. Expired setelah 180 detik (3 menit)
        if (time() - $created_at > 180) {
            $_SESSION['error_message'] = "Kode verifikasi telah kadaluarsa. Silakan minta kode baru.";
            header("Location: lupa_password_proses2.php");
            exit();
        }

        // 3. Cek hash
        if (password_verify($input_code, $hash)) {
            $_SESSION['verified_reset'] = true;
            header("Location: reset_password.php");
            exit();
        } 
        else {
            $_SESSION['error_message'] = "Kode verifikasi salah.";
        }
    } 
    else {
        $_SESSION['error_message'] = "Terjadi kesalahan sistem, coba lagi.";
    }

    header("Location: lupa_password_proses2.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Verifikasi Kode</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #e9f7f5, #c8f3ea);
    margin: 0; padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 400px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}
h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #16a085;
    font-weight: 700;
}
.code-input {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}
.code-input input {
    width: 48px;
    height: 55px;
    font-size: 24px;
    text-align: center;
    border: 1px solid #CCC;
    border-radius: 8px;
}
.btn-main {
    width: 100%;
    padding: 12px;
    background: #1abc9c;
    border: none;
    border-radius: 6px;
    color: white;
    font-size: 16px;
    margin-bottom: 10px;
}
.btn-main:hover { background: #17a589; }
.btn-secondary { background: #6c757d; width: 100%; }
.btn-secondary:disabled { background: #999; }
.count-text { text-align: center; margin-top: 15px; font-size: 14px; }
</style>
</head>
<body>

<div class="card">

    <h2>Verifikasi Kode</h2>

    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger text-center">
            <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <!-- FORM VERIFIKASI KODE -->
    <form method="POST" id="verifyForm">
        <div class="code-input">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <input type="text" maxlength="1" class="code-box" id="code<?= $i ?>" required>
            <?php endfor; ?>
        </div>
        <input type="hidden" name="kode" id="finalKode">
        <button class="btn-main">Verifikasi</button>
    </form>

    <p class="text-center mt-3">
        Kode kadaluarsa dalam <b><span id="expireCountdown">03:00</span></b>
    </p>

    <!-- RESEND BUTTON -->
    <form action="lupa_password_proses1.php" method="POST">
        <input type="hidden" name="email" value="<?= $email ?>">
        <input type="hidden" name="from_resend" value="1">
        <button id="resendBtn" class="btn btn-secondary" disabled>
            Kirim Ulang Kode (60s)
        </button>
    </form>

</div>

<script>
// ========================
// INPUT AUTO-MOVE
// ========================
const boxes = document.querySelectorAll('.code-box');
boxes.forEach((box, idx) => {
    box.addEventListener('input', () => {
        if (box.value.length === 1 && idx < 5) boxes[idx + 1].focus();
    });
    box.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && box.value === '' && idx > 0) boxes[idx - 1].focus();
    });
});

// Gabungkan kode sebelum submit
document.getElementById('verifyForm').addEventListener('submit', () => {
    let code = '';
    boxes.forEach(b => code += b.value);
    document.getElementById('finalKode').value = code;
});

// ========================
// COUNTDOWN EXPIRED 180s
// ========================
let expire = 180;
let expireTimer = setInterval(() => {
    let m = String(Math.floor(expire / 60)).padStart(2, '0');
    let s = String(expire % 60).padStart(2, '0');
    document.getElementById("expireCountdown").textContent = `${m}:${s}`;
    expire--;
    if (expire < 0) clearInterval(expireTimer);
}, 1000);

// ========================
// RESEND COUNTDOWN 60s
// ========================
let resend = 60;
let resendBtn = document.getElementById('resendBtn');

let resendTimer = setInterval(() => {
    resend--;
    resendBtn.textContent = `Kirim Ulang Kode (${resend}s)`;
    if (resend <= 0) {
        resendBtn.disabled = false;
        resendBtn.textContent = 'Kirim Ulang Kode';
        clearInterval(resendTimer);
    }
}, 1000);
</script>

</body>
</html>
