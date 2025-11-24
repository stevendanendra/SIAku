<?php
// crud_master_supplier_proses.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || $_SERVER["REQUEST_METHOD"] !== "POST") { 
    header("Location: login.html"); 
    exit(); 
}

$redirect_url = 'crud_master_supplier.php'; 
$action = $_POST['action'] ?? '';

// --- INISIALISASI LOG DATA ---
$id_login = $_SESSION['id_pengguna'] ?? 0;
$username_login = $_SESSION['username'] ?? 'SYSTEM_USER';

// --- Ambil Data POST ---
$nama_supplier_raw = $_POST['nama_supplier'] ?? '';
$no_telepon_raw = $_POST['no_telepon'] ?? '';
$alamat_lengkap_raw = $_POST['alamat_lengkap'] ?? '';
$email_raw = $_POST['email'] ?? '';
$tgl_daftar = date('Y-m-d');

// --- PENGOLAHAN UNTUK BINDING (Sanitasi & NULL Handling) ---
$nama_supplier = $conn->real_escape_string($nama_supplier_raw); 
$no_telepon = !empty($no_telepon_raw) ? $conn->real_escape_string($no_telepon_raw) : NULL;
$alamat_lengkap = !empty($alamat_lengkap_raw) ? $conn->real_escape_string($alamat_lengkap_raw) : NULL;
$email = !empty($email_raw) ? $conn->real_escape_string($email_raw) : NULL;


// --- LOGIKA TAMBAH SUPPLIER BARU (CREATE) ---

if ($action === 'add') {
    
    if (empty($nama_supplier)) {
        $_SESSION['error_message'] = "Nama Pemasok wajib diisi.";
    } else {
        $sql = "INSERT INTO ms_supplier (nama_supplier, no_telepon, alamat_lengkap, email, tgl_daftar) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt === FALSE) {
            $_SESSION['error_message'] = "Error saat menyiapkan query: " . $conn->error;
            goto redirect;
        }

        // Binding parameters (s=string)
        $stmt->bind_param("sssss", 
            $nama_supplier, 
            $no_telepon, 
            $alamat_lengkap, 
            $email, 
            $tgl_daftar
        );
        
        if ($stmt->execute()) {
            $last_id = $stmt->insert_id;
            $_SESSION['success_message'] = "Pemasok '$nama_supplier' (ID: $last_id) berhasil ditambahkan.";
            
            // LOG AKTIVITAS
            if (function_exists('logAktivitas')) {
                 logAktivitas($id_login, $username_login, "Menambahkan pemasok baru ID: $last_id", "Master Supplier");
            }
        } else {
            $error_detail = ($conn->errno == 1062) ? "Email atau Nomor Telepon sudah terdaftar." : $conn->error;
            $_SESSION['error_message'] = "Gagal menambahkan pemasok: " . $error_detail;
            
            // LOG AKTIVITAS GAGAL
            if (function_exists('logAktivitas')) {
                 logAktivitas($id_login, $username_login, "Gagal menambahkan pemasok: $error_detail", "Master Supplier");
            }
        }
        $stmt->close();
    }
} else {
    $_SESSION['error_message'] = "Aksi tidak dikenal.";
}


redirect:
header("Location: $redirect_url");
exit();
?>