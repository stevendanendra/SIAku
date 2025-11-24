<?php
// crud_master_supplier_proses.php
session_start();
include 'koneksi.php'; 

$action = $_POST['action'] ?? '';
$is_from_pengeluaran = isset($_POST['from_pengeluaran']) && $_POST['from_pengeluaran'] == "1";

// RULE AKSES
// OWNER: semua action boleh
// KASIR: hanya boleh ADD + harus dari modal pengeluaran
if (!isset($_SESSION['role'])) {
    header("Location: login.html");
    exit();
}

if ($_SESSION['role'] !== 'Owner') {
    if (!($action === 'add' && $is_from_pengeluaran)) {
        header("Location: login.html");
        exit();
    }
}

// ---------------------------
// SETUP VARIABEL REDIRECT
// ---------------------------
$redirect_owner = "crud_master_supplier.php";
$redirect_kasir = "pengeluaran_kas_form.php?new_supplier=success";

// ---------------------------
// LOG DATA USER
// ---------------------------
$id_login = $_SESSION['id_pengguna'] ?? 0;
$username_login = $_SESSION['username'] ?? 'SYSTEM_USER';

// ---------------------------
// AMBIL DATA POST
// ---------------------------
$nama_supplier = trim($_POST['nama_supplier'] ?? '');
$no_telepon = trim($_POST['no_telepon'] ?? '');
$alamat_lengkap = trim($_POST['alamat_lengkap'] ?? '');
$email = trim($_POST['email'] ?? '');
$tgl_daftar = date('Y-m-d');

// ---------------------------
// VALIDASI
// ---------------------------
if (empty($nama_supplier)) {
    $_SESSION['error_message'] = "Nama Pemasok wajib diisi.";

    header("Location: " . ($is_from_pengeluaran ? $redirect_kasir : $redirect_owner));
    exit();
}

// ---------------------------
// INSERT SUPPLIER BARU
// ---------------------------
$sql = "INSERT INTO ms_supplier (nama_supplier, no_telepon, alamat_lengkap, email, tgl_daftar)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $_SESSION['error_message'] = "Error Query: " . $conn->error;
    header("Location: " . ($is_from_pengeluaran ? $redirect_kasir : $redirect_owner));
    exit();
}

$stmt->bind_param(
    "sssss",
    $nama_supplier,
    $no_telepon,
    $alamat_lengkap,
    $email,
    $tgl_daftar
);

if ($stmt->execute()) {
    $last_id = $stmt->insert_id;
    $_SESSION['success_message'] = "Pemasok '$nama_supplier' (ID: $last_id) berhasil ditambahkan.";

    // LOG
    if (function_exists('logAktivitas')) {
        logAktivitas($id_login, $username_login, "Menambahkan pemasok baru ID: $last_id", "Master Supplier");
    }

} else {
    $error_detail = ($conn->errno == 1062) 
                    ? "Email atau Nomor Telepon sudah terdaftar."
                    : $conn->error;

    $_SESSION['error_message'] = "Gagal menambahkan pemasok: " . $error_detail;

    if (function_exists('logAktivitas')) {
        logAktivitas($id_login, $username_login, "Gagal menambahkan pemasok: $error_detail", "Master Supplier");
    }
}

$stmt->close();

// ---------------------------
// REDIRECT SESUAI SUMBER FORM
// ---------------------------
if ($is_from_pengeluaran) {
    // SUPPLIER ditambah dari modal kasir → kembali ke form pengeluaran kas
    header("Location: $redirect_kasir");
    exit();
}

// Jika dari halaman master supplier milik owner
header("Location: $redirect_owner");
exit();
?>
