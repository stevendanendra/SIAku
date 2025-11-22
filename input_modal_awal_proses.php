<?php
// input_modal_awal_proses.php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.html");
    exit();
}

$jumlah_modal = (int)$_POST['jumlah_modal'];
$redirect_url = 'input_modal_awal.php'; // Default redirect saat gagal

if ($jumlah_modal <= 0) {
    $_SESSION['error_message'] = "Jumlah modal harus lebih dari nol.";
    header("Location: " . $redirect_url);
    exit();
}

// Mulai transaksi
$conn->begin_transaction();

try {
    $tgl_jurnal = date('Y-m-d');
    // FIX KRITIS: Ganti deskripsi dari 'Setoran Modal Awal' menjadi umum
    $deskripsi_jurnal = "Setoran Modal Tambahan dari Pemilik"; 
    $no_bukti_jurnal = "MDL-" . date('YmdHis'); 

    // Jurnal 1: DEBIT Kas (1101) - Aset Bertambah
    $sql_debit = "INSERT INTO tr_jurnal_umum (tgl_jurnal, id_akun, posisi, nilai, no_bukti, deskripsi) 
                  VALUES ('$tgl_jurnal', 1101, 'D', $jumlah_modal, '$no_bukti_jurnal', '$deskripsi_jurnal')";
    if (!$conn->query($sql_debit)) {
        throw new Exception("Gagal menjurnal Debet Kas: " . $conn->error);
    }

    // Jurnal 2: KREDIT Modal (3101) - Modal Bertambah
    $sql_kredit = "INSERT INTO tr_jurnal_umum (tgl_jurnal, id_akun, posisi, nilai, no_bukti, deskripsi) 
                   VALUES ('$tgl_jurnal', 3101, 'K', $jumlah_modal, '$no_bukti_jurnal', '$deskripsi_jurnal')";
    if (!$conn->query($sql_kredit)) {
        throw new Exception("Gagal menjurnal Kredit Modal: " . $conn->error);
    }

    // Commit transaksi
    $conn->commit();
    $_SESSION['success_message'] = "Setoran Modal Tambahan sebesar Rp " . number_format($jumlah_modal, 0, ',', '.') . " berhasil dicatat.";
    header("Location: dashboard_owner.php"); // Redirect ke dashboard setelah sukses
    
} catch (Exception $e) {
    // Rollback jika ada error
    $conn->rollback();
    $_SESSION['error_message'] = "Gagal mencatat Setoran Modal: " . $e->getMessage();
    header("Location: " . $redirect_url);
}

$conn->close();
?>