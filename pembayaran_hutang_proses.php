<?php
// pembayaran_hutang_proses.php
session_start();
include 'koneksi.php'; 

// =======================================================
// KONSTANTA AKUNTANSI
// =======================================================
const AKUN_KAS_ID = '1101'; 
const AKUN_UTANG_ID = '2101'; // Utang Usaha (yang di Debit saat dibayar)
const JENIS_TRANSAKSI_PAYMENT = 'PAY'; // Kode untuk Pembayaran Utang
// =======================================================

// Redirect default ke Daftar Utang
$redirect_url = 'daftar_utang.php'; 

// Cek Autentikasi dan Metode Request (POST)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan' || $_SERVER["REQUEST_METHOD"] !== "POST") { 
    header("Location: login.html"); 
    exit(); 
}

// Ambil dan sanitasi data input
$id_karyawan = $_SESSION['id_pengguna'];
$id_pengeluaran = $conn->real_escape_string($_POST['id_pengeluaran'] ?? 0);
$jumlah_bayar = (int)($conn->real_escape_string($_POST['jumlah_bayar'] ?? 0));
$sisa_utang = (int)($conn->real_escape_string($_POST['sisa_utang'] ?? 0));
$tanggal_transaksi = date('Y-m-d'); 

// Validasi
if ($jumlah_bayar <= 0 || $jumlah_bayar > $sisa_utang) {
    $_SESSION['error_message'] = "Jumlah bayar ($jumlah_bayar) melebihi sisa utang ($sisa_utang).";
    header("Location: pembayaran_hutang_form.php?id_pengeluaran=$id_pengeluaran");
    exit();
}

// ----------------------------------------------------------------------------------
// 1. MULAI TRANSAKSI DATABASE
// ----------------------------------------------------------------------------------
$conn->begin_transaction();

try {
    // Ambil deskripsi asli dari tr_pengeluaran untuk keperluan jurnal
    $sql_desc = "SELECT deskripsi FROM tr_pengeluaran WHERE id_pengeluaran = '$id_pengeluaran'";
    $result_desc = $conn->query($sql_desc);
    if ($result_desc->num_rows == 0) {
        throw new Exception("Transaksi Pengeluaran asli tidak ditemukan.");
    }
    $deskripsi_utang_asli = $result_desc->fetch_assoc()['deskripsi'];
    
    // ----------------------------------------------------------------------------------
    // 2. CATAT TRANSAKSI REFERENSI (tr_pengeluaran)
    //    Ini mencatat aliran Kas Keluar untuk pembayaran utang.
    // ----------------------------------------------------------------------------------
    $sql_pengeluaran = "INSERT INTO tr_pengeluaran 
                        (tgl_transaksi, deskripsi, jumlah, id_akun_beban, id_akun_kas, id_karyawan)
                        VALUES ('$tanggal_transaksi', 'Pembayaran Utang Ref #$id_pengeluaran', '$jumlah_bayar', '" . AKUN_UTANG_ID . "', '" . AKUN_KAS_ID . "', '$id_karyawan')";

    if (!$conn->query($sql_pengeluaran)) {
        throw new Exception("Gagal mencatat transaksi pengeluaran (referensi): " . $conn->error);
    }
    $id_transaksi_bayar = $conn->insert_id;
    $no_bukti_jurnal = JENIS_TRANSAKSI_PAYMENT . "-" . $id_pengeluaran . "-" . $id_transaksi_bayar; 
    $deskripsi_jurnal = "Bayar Utang Usaha (Angsuran) Ref Transaksi E-$id_pengeluaran: " . $deskripsi_utang_asli;


    // ----------------------------------------------------------------------------------
    // 3. GENERATE JURNAL DEBIT (Mengurangi Utang Usaha)
    //    Debit Utang Usaha (2101) = Kewajiban Berkurang
    // ----------------------------------------------------------------------------------
    $sql_debit = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                  VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_jurnal', '" . AKUN_UTANG_ID . "', 'D', '$jumlah_bayar')";
    
    if (!$conn->query($sql_debit)) {
        throw new Exception("Gagal menjurnal Debet Utang Usaha: " . $conn->error);
    }

    // ----------------------------------------------------------------------------------
    // 4. GENERATE JURNAL KREDIT (Mengurangi Kas/Aset)
    //    Kredit Kas (1101) = Aset Berkurang
    // ----------------------------------------------------------------------------------
    $sql_kredit = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                   VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_jurnal', '" . AKUN_KAS_ID . "', 'K', '$jumlah_bayar')";
    
    if (!$conn->query($sql_kredit)) {
        throw new Exception("Gagal menjurnal Kredit Kas: " . $conn->error);
    }

    // ----------------------------------------------------------------------------------
    // 5. COMMIT TRANSAKSI (Semua SQL berhasil)
    // ----------------------------------------------------------------------------------
    $conn->commit();
    
    $sisa_akhir = $sisa_utang - $jumlah_bayar;
    $status = ($sisa_akhir <= 5) ? "LUNAS" : "TERCATAT"; 

    // Kirim pesan sukses via SESSION
    $_SESSION['success_message'] = "Pembayaran Utang E-**$id_pengeluaran** berhasil **$status** sebesar Rp " . number_format($jumlah_bayar) . ". Sisa Utang: Rp " . number_format($sisa_akhir) . ".";
    
} catch (Exception $e) {
    // 6. ROLLBACK JIKA GAGAL
    $conn->rollback();
    $_SESSION['error_message'] = "Pembayaran Utang GAGAL diproses! Pesan Error: " . $e->getMessage();
}

// Tutup koneksi dan redirect
$conn->close();
header("Location: $redirect_url");
exit();
?>