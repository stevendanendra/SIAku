<?php
// crud_master_pelanggan_proses.php
session_start();
include 'koneksi.php'; // Memuat koneksi dan fungsi logAktivitas()

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] === 'Cleaner' ||
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {
    $redirect_url = 'login.html';
    header("Location: $redirect_url");
    exit();
}

$redirect_url = 'penerimaan_kas_form.php'; // Default redirect ke POS
$action = $_POST['action'] ?? '';

// --- Ambil Data POST Mentah ---
$nama_pelanggan_raw  = $_POST['nama_pelanggan'] ?? '';
$no_telepon_raw      = $_POST['no_telepon'] ?? '';
$alamat_lengkap_raw  = $_POST['alamat_lengkap'] ?? '';
$email_raw           = $_POST['email'] ?? '';
$tgl_daftar          = date('Y-m-d');

// --- PENGOLAHAN UNTUK BINDING (Sanitasi & NULL Handling) ---

// 1. Sanitasi wajib untuk nama_pelanggan (NOT NULL)
$nama_pelanggan  = $conn->real_escape_string($nama_pelanggan_raw);

// 2. Kolom opsional → jika kosong: set NULL, jika isi: sanitasi
$no_telepon      = !empty($no_telepon_raw) ? $conn->real_escape_string($no_telepon_raw) : NULL;
$alamat_lengkap  = !empty($alamat_lengkap_raw) ? $conn->real_escape_string($alamat_lengkap_raw) : NULL;
$email           = !empty($email_raw) ? $conn->real_escape_string($email_raw) : NULL;

// --- LOGIKA TAMBAH PELANGGAN BARU (CREATE) ---
if ($action === 'add_from_pos' || $action === 'add') {

    // Jika dari master data → redirect ke halaman master
    if ($action === 'add') {
        $redirect_url = 'crud_master_pelanggan.php';
    }

    if (empty($nama_pelanggan)) {
        $_SESSION['error_message'] = "Nama Pelanggan wajib diisi.";
    } else {

        // Query INSERT
        $sql = "INSERT INTO ms_pelanggan (
                    nama_pelanggan, no_telepon, alamat_lengkap, email, tgl_daftar
                ) VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt === FALSE) {
            $_SESSION['error_message'] = "Error saat menyiapkan query: " . $conn->error;
            goto redirect;
        }

        // Binding parameters (semua string, NULL akan ditangani MySQL)
        $stmt->bind_param(
            "sssss",
            $nama_pelanggan,
            $no_telepon,
            $alamat_lengkap,
            $email,
            $tgl_daftar
        );

        if ($stmt->execute()) {

            $last_id = $stmt->insert_id;
            $_SESSION['success_message'] =
                "Pelanggan baru '$nama_pelanggan' (ID: $last_id) berhasil ditambahkan.";

            // --- LOG AKTIVITAS (SUCCESS) ---
            if (function_exists('logAktivitas')) {
                $user_id   = $_SESSION['id_pengguna'] ?? 0;
                $username  = $_SESSION['username'] ?? 'SYSTEM_USER';
                $modul_name = ($action === 'add') ? "Master Pelanggan" : "POS Penjualan";

                logAktivitas(
                    $user_id,
                    $username,
                    "Menambahkan pelanggan baru ID: $last_id",
                    $modul_name
                );
            }

        } else {

            $error_detail = ($conn->errno == 1062)
                ? "Email atau Nomor Telepon sudah terdaftar."
                : $conn->error;

            $_SESSION['error_message'] = "Gagal menambahkan pelanggan: " . $error_detail;

            // --- LOG AKTIVITAS (FAILURE) ---
            if (function_exists('logAktivitas')) {
                $user_id   = $_SESSION['id_pengguna'] ?? 0;
                $username  = $_SESSION['username'] ?? 'SYSTEM_USER';
                $modul_name = ($action === 'add') ? "Master Pelanggan" : "POS Penjualan";

                logAktivitas(
                    $user_id,
                    $username,
                    "Gagal menambahkan pelanggan: " . $error_detail,
                    $modul_name
                );
            }
        }

        $stmt->close();
    }

} else {
    $_SESSION['error_message'] = "Aksi tidak dikenal.";
}

// --- REDIRECT KEMBALI ---
redirect:
header("Location: $redirect_url");
exit();
