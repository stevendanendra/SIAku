<?php
// login_proses.php
session_start();
include 'koneksi.php'; // Memuat koneksi dan fungsi logAktivitas()

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username_input = $conn->real_escape_string($_POST['username'] ?? '');
    $password_input = $_POST['password'] ?? ''; 
    
    // --- 1. Ambil data pengguna dari database ---
    $sql = "SELECT id_pengguna, username, password, nama_lengkap, role, is_aktif 
            FROM ms_pengguna 
            WHERE username = ? OR email = ?";
            
    $stmt = $conn->prepare($sql);
    
    if ($stmt === FALSE) {
        $_SESSION['login_error'] = "Error database: " . $conn->error;
        goto fail;
    }
    
    $stmt->bind_param("ss", $username_input, $username_input);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    // --- 2. Verifikasi dan Logika Akses ---
    
    if ($user_data) {
        if ($password_input === $user_data['password']) {
            
            if ($user_data['is_aktif'] == 0) {
                $_SESSION['login_error'] = "Akun Anda tidak aktif. Silakan hubungi Administrator.";
                goto fail;
            }
            
            // --- LOGIN BERHASIL ---
            
            // Set Session Data
            $_SESSION['id_pengguna'] = $user_data['id_pengguna'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['nama_lengkap'] = $user_data['nama_lengkap'];
            $_SESSION['role'] = $user_data['role'];

            // PENTING: Panggil logAktivitas() saat login berhasil
            if (function_exists('logAktivitas')) {
                
                // FIX KRITIS: Melakukan casting eksplisit untuk memastikan parameter INT terikat dengan benar
                $id_user_int = (int)$_SESSION['id_pengguna']; 

                $log_success = logAktivitas($id_user_int, 
                                             $_SESSION['username'], 
                                             "Login berhasil", 
                                             "Autentikasi");
                
                // Jika log gagal, sistem tetap berjalan normal, namun error dicatat ke log server (jika diaktifkan)
                if (!$log_success) {
                    error_log("Logging untuk user {$id_user_int} gagal.");
                }
            }
            
            // Tentukan Halaman Dashboard berdasarkan Role
            $role = $user_data['role'];
            $redirect_page = '';

            switch ($role) {
                case 'Owner':
                case 'Admin':
                    $redirect_page = 'dashboard_owner.php';
                    break;
                case 'Karyawan':
                    $redirect_page = 'penerimaan_kas_form.php'; 
                    break;
                case 'Cleaner':
                    $redirect_page = 'dashboard_karyawan.php';
                    break;
                default:
                    $redirect_page = 'login.html';
                    break;
            }

            header("Location: $redirect_page");
            exit();

        } else {
            // Password salah
            $_SESSION['login_error'] = "Username atau Password salah.";
            goto fail;
        }
    } else {
        // Username tidak ditemukan
        $_SESSION['login_error'] = "Username atau Password salah.";
        goto fail;
    }
    
    // --- LOGIN GAGAL ---
    fail:
    header("Location: login.html");
    exit();

} else {
    // Jika diakses tanpa POST
    header("Location: login.html");
    exit();
}
?>