<?php
// penerimaan_piutang_proses.php
session_start();
include 'koneksi.php'; 

// =======================================================
// KONSTANTA AKUNTANSI
// =======================================================
const AKUN_KAS_ID = 1101; 
const AKUN_PIUTANG_ID = 1102; 
const JENIS_TRANSAKSI_PELUNASAN = 'RCV';
// =======================================================

// Redirect default ke Daftar Piutang
$redirect_url = 'daftar_piutang.php'; 

// Autentikasi
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan' || $_SERVER["REQUEST_METHOD"] !== "POST") { 
    header("Location: login.html"); 
    exit(); 
}

// Ambil input
$id_karyawan = $_SESSION['id_pengguna'];
$id_penjualan = $conn->real_escape_string($_POST['id_penjualan'] ?? 0);
$jumlah_diterima = (int)($conn->real_escape_string($_POST['jumlah_bayar'] ?? 0));
$sisa_piutang = (int)($conn->real_escape_string($_POST['sisa_piutang'] ?? 0));
$total_piutang_awal = (int)($conn->real_escape_string($_POST['total_piutang_awal'] ?? 0));
$tanggal_transaksi = $conn->real_escape_string($_POST['tgl_bayar'] ?? date('Y-m-d'));

// Validasi
if ($jumlah_diterima <= 0 || $jumlah_diterima > $sisa_piutang) {
    $_SESSION['error_message'] = "Jumlah yang diterima ($jumlah_diterima) tidak valid atau melebihi sisa piutang ($sisa_piutang).";
    header("Location: penerimaan_piutang_form.php?id_penjualan=$id_penjualan");
    exit();
}

// Mulai transaksi
$conn->begin_transaction();

try {

    // Generate No Bukti
    $no_bukti_jurnal = JENIS_TRANSAKSI_PELUNASAN . "-" . $id_penjualan . "-" . time();
    $deskripsi_jurnal = "Pelunasan Piutang ID #$id_penjualan (Angsuran).";

    // Jurnal Debit Kas
    $sql_debit = "INSERT INTO tr_jurnal_umum 
                  (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                  VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_jurnal', 
                          " . AKUN_KAS_ID . ", 'D', '$jumlah_diterima')";

    if (!$conn->query($sql_debit)) {
        throw new Exception("Gagal menjurnal Debet Kas: " . $conn->error);
    }

    // Jurnal Kredit Piutang
    $sql_kredit = "INSERT INTO tr_jurnal_umum 
                   (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                   VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_jurnal', 
                           " . AKUN_PIUTANG_ID . ", 'K', '$jumlah_diterima')";
    
    if (!$conn->query($sql_kredit)) {
        throw new Exception("Gagal menjurnal Kredit Piutang Usaha: " . $conn->error);
    }

    // Update status lunas
    $sisa_akhir = $sisa_piutang - $jumlah_diterima;
    $new_is_lunas = ($sisa_akhir <= 0) ? 1 : 0;

    $sql_update_lunas = "UPDATE tr_penjualan 
                         SET is_lunas = $new_is_lunas 
                         WHERE id_penjualan = '$id_penjualan'";

    if (!$conn->query($sql_update_lunas)) {
        throw new Exception("Gagal memperbarui status lunas: " . $conn->error);
    }

    // Commit transaksi
    $conn->commit();

    // ========================================================
    // LOG AKTIVITAS — BERHASIL
    // ========================================================
    $aksi = "Penerimaan Piutang P-$id_penjualan";
    $status = ($new_is_lunas == 1) 
                ? "Berhasil (LUNAS PENUH)" 
                : "Berhasil (Angsuran)";

    $log_sql = "INSERT INTO aktivitas_log (id_pengguna, aksi, status, waktu)
                VALUES ('$id_karyawan', '$aksi', '$status', NOW())";
    $conn->query($log_sql);

    // Pesan sukses
    $_SESSION['success_message'] = "Penerimaan Piutang P-**$id_penjualan** berhasil **$status** sebesar Rp " . 
                                   number_format($jumlah_diterima) . 
                                   ". Sisa Piutang: Rp " . number_format(max(0, $sisa_akhir)) . ".";

} catch (Exception $e) {

    // Rollback
    $conn->rollback();

    // ========================================================
    // LOG AKTIVITAS — GAGAL
    // ========================================================
    $aksi = "Penerimaan Piutang (GAGAL) P-$id_penjualan";
    $status = $conn->real_escape_string($e->getMessage());

    $log_sql = "INSERT INTO aktivitas_log (id_pengguna, aksi, status, waktu)
                VALUES ('$id_karyawan', '$aksi', '$status', NOW())";
    $conn->query($log_sql);

    $_SESSION['error_message'] = "Pelunasan Piutang GAGAL diproses! Pesan Error: " . $e->getMessage();
}

$conn->close();
header("Location: $redirect_url");
exit();
?>
