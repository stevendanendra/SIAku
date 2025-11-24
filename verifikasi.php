<?php
session_start();
include 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Ambil input
    $user_input = trim($_POST['user_input']);
    $input_password = trim($_POST['password']);

    // Cek apakah input kosong
    if ($user_input === "" || $input_password === "") {
        echo "Input tidak boleh kosong.";
        exit();
    }

    // --- PREPARED STATEMENT UNTUK CEK USER ---
    $sql = "SELECT id_pengguna, password, nama_lengkap, role, is_aktif 
            FROM ms_pengguna
            WHERE username = ? OR email = ? 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $user_input, $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    // Cek user ditemukan
    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        // Cek status aktif
        if (!$user['is_aktif']) {
            echo "Login Gagal. Akun Anda tidak aktif. Silakan hubungi Owner.";
            exit();
        }

        // --- VERIFIKASI PASSWORD HASH ---
        if (password_verify($input_password, $user['password'])) {

            // Login OK → Set session
            $_SESSION['id_pengguna'] = $user['id_pengguna'];
            $_SESSION['nama'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];

            // Redirect berdasarkan role
            if ($user['role'] === 'Owner') {
                header("Location: dashboard_owner.php");
            } elseif ($user['role'] === 'Karyawan') {
                header("Location: dashboard_karyawan.php");
            } else {
                echo "Akses ditolak untuk peran ini.";
            }
            exit();

        } else {
            echo "Login Gagal. Password salah.";
            exit();
        }

    } else {
        echo "Login Gagal. Username atau Email tidak terdaftar.";
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
