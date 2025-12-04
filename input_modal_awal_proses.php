<?php
// input_modal_awal_proses.php
session_start();
include 'koneksi.php';

// ===============================================
// VALIDASI AKSES
// ===============================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.html");
    exit();
}

$id_pengguna = $_SESSION['id_pengguna'];
$jumlah_modal = (int)($_POST['jumlah_modal'] ?? 0);
$redirect_url = 'input_modal_awal.php';

// ===============================================
// VALIDASI INPUT
// ===============================================
if ($jumlah_modal <= 0) {
    $_SESSION['error_message'] = "Jumlah modal harus lebih dari nol.";
    header("Location: " . $redirect_url);
    exit();
}

// ===============================================
// MULAI TRANSAKSI
// ===============================================
$conn->begin_transaction();

try {

    $tgl_jurnal = date('Y-m-d');
    $deskripsi_jurnal = "Setoran Modal Tambahan dari Pemilik";
    $no_bukti_jurnal = "MDL-" . date('YmdHis');

    // -------------------------------------------
    // Jurnal 1: DEBIT Kas (1101)
    // -------------------------------------------
    $sql_debit = "INSERT INTO tr_jurnal_umum 
        (tgl_jurnal, id_akun, posisi, nilai, no_bukti, deskripsi) 
        VALUES 
        ('$tgl_jurnal', 1101, 'D', $jumlah_modal, '$no_bukti_jurnal', '$deskripsi_jurnal')";

    if (!$conn->query($sql_debit)) {
        throw new Exception("Gagal menjurnal DEBIT Kas: " . $conn->error);
    }

    // -------------------------------------------
    // Jurnal 2: KREDIT Modal (3101)
    // -------------------------------------------
    $sql_kredit = "INSERT INTO tr_jurnal_umum 
        (tgl_jurnal, id_akun, posisi, nilai, no_bukti, deskripsi) 
        VALUES 
        ('$tgl_jurnal', 3101, 'K', $jumlah_modal, '$no_bukti_jurnal', '$deskripsi_jurnal')";

    if (!$conn->query($sql_kredit)) {
        throw new Exception("Gagal menjurnal KREDIT Modal: " . $conn->error);
    }

    // -------------------------------------------
    // LOG AKTIVITAS (WAJIB untuk transaksi modal)
    // -------------------------------------------
    $aksi = $conn->real_escape_string("Input Modal Tambahan sebesar Rp " . number_format($jumlah_modal));
    $sql_log = "INSERT INTO tr_log_aktivitas (tgl_waktu, id_pengguna, username, deskripsi, modul, ip_address) 
    VALUES 
    (NOW(), '$id_pengguna', '$username', '$deskripsi', 'Modul', '$ip_address')";

    if (!$conn->query($sql_log)) {
        throw new Exception("Gagal mencatat log aktivitas: " . $conn->error);
    }

    // -------------------------------------------
    // SEMUA BERHASIL → COMMIT
    // -------------------------------------------
    $conn->commit();

    $_SESSION['success_message'] = 
        "Setoran Modal Tambahan sebesar Rp " . number_format($jumlah_modal, 0, ',', '.') . " berhasil dicatat.";

    header("Location: dashboard_owner.php");
    exit();

} catch (Exception $e) {

    // Rollback
    $conn->rollback();

    $_SESSION['error_message'] = "Gagal mencatat Setoran Modal: " . $e->getMessage();
    header("Location: " . $redirect_url);
    exit();
}

$conn->close();
?>
