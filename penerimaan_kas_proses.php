<?php
// penerimaan_kas_proses.php
session_start();
include 'koneksi.php'; 

// Cek autentikasi dan metode
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan' || $_SERVER["REQUEST_METHOD"] !== "POST") { 
    header("Location: login.html"); 
    exit(); 
}

// =======================================================
// KONSTANTA AKUNTANSI
// =======================================================
const AKUN_KAS_ID = '1101'; // ID Akun Kas
// =======================================================

$id_karyawan = $_SESSION['id_pengguna'];
$tanggal_transaksi = date('Y-m-d'); 
$redirect_url = 'penerimaan_kas_form.php'; 

// Ambil dan sanitasi data input
$id_pelanggan = $conn->real_escape_string($_POST['id_pelanggan'] ?? '');
$id_layanan = $conn->real_escape_string($_POST['id_layanan'] ?? '');
$total_jual = (int)($conn->real_escape_string($_POST['total_jual'] ?? 0));
$metode_bayar = $conn->real_escape_string($_POST['metode_bayar'] ?? 'Kas');

if (empty($id_pelanggan) || empty($id_layanan) || $total_jual <= 0) {
    $_SESSION['error_message'] = "Data transaksi tidak lengkap atau Total Penjualan tidak valid.";
    header("Location: $redirect_url");
    exit();
}

// ----------------------------------------------------------------------------------
// 1. MULAI TRANSAKSI DATABASE
// ----------------------------------------------------------------------------------
$conn->begin_transaction();

try {
    // ----------------------------------------------------------------------------------
    // 2. AMBIL ID AKUN PENDAPATAN DARI MASTER LAYANAN
    // ----------------------------------------------------------------------------------
    $layanan_res = $conn->query("SELECT id_akun_pendapatan, nama_layanan FROM ms_layanan WHERE id_layanan = '$id_layanan'");
    if ($layanan_res->num_rows == 0) {
        throw new Exception("ID Layanan tidak ditemukan di Master.");
    }
    $layanan_data = $layanan_res->fetch_assoc();
    $id_akun_pendapatan = $layanan_data['id_akun_pendapatan'];
    $nama_layanan = $layanan_data['nama_layanan'];
    
    // ----------------------------------------------------------------------------------
    // 3. CATAT TRANSAKSI UTAMA (tr_penjualan)
    // ----------------------------------------------------------------------------------
    $sql_penjualan = "INSERT INTO tr_penjualan 
                      (tgl_transaksi, id_karyawan, id_pelanggan, total_penjualan, metode_bayar, status_jurnal)
                      VALUES ('$tanggal_transaksi', '$id_karyawan', '$id_pelanggan', '$total_jual', '$metode_bayar', TRUE)";
    
    if (!$conn->query($sql_penjualan)) {
        throw new Exception("Gagal mencatat penjualan: " . $conn->error);
    }
    $id_penjualan_baru = $conn->insert_id;
    $no_bukti_jurnal = "PJL-" . $id_penjualan_baru; // Nomor Bukti Transaksi
    
    // ----------------------------------------------------------------------------------
    // 4. GENERATE JURNAL DEBIT (KAS/1101)
    // ----------------------------------------------------------------------------------
    $deskripsi_debit = "Penerimaan Kas (Tunai) dari Layanan $nama_layanan";
    $sql_debit = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                  VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_debit', '" . AKUN_KAS_ID . "', 'D', '$total_jual')";
    
    if (!$conn->query($sql_debit)) {
        throw new Exception("Gagal menjurnal Debet Kas: " . $conn->error);
    }

    // ----------------------------------------------------------------------------------
    // 5. GENERATE JURNAL KREDIT (PENDAPATAN/4101)
    // ----------------------------------------------------------------------------------
    $deskripsi_kredit = "Pencatatan Pendapatan Jasa Layanan $nama_layanan";
    $sql_kredit = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                   VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_kredit', '$id_akun_pendapatan', 'K', '$total_jual')";
    
    if (!$conn->query($sql_kredit)) {
        throw new Exception("Gagal menjurnal Kredit Pendapatan: " . $conn->error);
    }

    // ----------------------------------------------------------------------------------
    // 6. COMMIT TRANSAKSI (Semua SQL berhasil)
    // ----------------------------------------------------------------------------------
    $conn->commit();
    
    // Kirim pesan sukses via SESSION
    $_SESSION['success_message'] = "Transaksi Penjualan **PJL-$id_penjualan_baru** berhasil dicatat dan Jurnal Umum telah dibuat.";
    
} catch (Exception $e) {
    // 7. ROLLBACK JIKA GAGAL
    $conn->rollback();
    $_SESSION['error_message'] = "Transaksi GAGAL diproses! Pesan Error: " . $e->getMessage();
}

// Tutup koneksi dan redirect ke form
$conn->close();
header("Location: $redirect_url");
exit();
?>