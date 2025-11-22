<?php
// verifikasi.php
session_start();
include 'koneksi.php'; // Hubungkan ke database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Ambil data input
    $user_input = $conn->real_escape_string($_POST['user_input']);
    $input_password = $_POST['password']; // Password yang belum di-hash
    
    // Query untuk mencari user berdasarkan username ATAU email DAN status aktif
    $sql = "SELECT id_pengguna, password, nama_lengkap, role, is_aktif FROM ms_pengguna 
            WHERE (username = '$user_input' OR email = '$user_input')";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Cek apakah akun tidak aktif sebelum verifikasi password
        if ($user['is_aktif'] == FALSE) {
            echo "Login Gagal. Akun Anda tidak aktif. Silakan hubungi Owner.";
            $conn->close();
            exit();
        }
        
        // --- LOGIKA VERIFIKASI PASSWORD ---
        if ($input_password === $user['password']) { 
            
            // Login Berhasil
            $_SESSION['id_pengguna'] = $user['id_pengguna'];
            $_SESSION['nama'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            
            // Pengalihan berdasarkan Role
            if ($user['role'] === 'Owner') {
                header("Location: dashboard_owner.php");
            } elseif ($user['role'] === 'Karyawan') {
                header("Location: dashboard_karyawan.php");
            } else {
                // Cleaner tidak boleh login
                echo "Akses ditolak untuk peran ini.";
            }
            exit();
            
        } else {
            // Password Salah
            echo "Login Gagal. Password salah.";
        }
        
    } else {
        // Username/Email tidak ditemukan
        echo "Login Gagal. Username atau Email tidak terdaftar.";
    }
    
    $conn->close();
}
?>