<?php
// pembayaran_hutang_proses.php
session_start();
include 'koneksi.php'; 

// =======================================================
// KONSTANTA AKUNTANSI
// =======================================================
const AKUN_KAS_ID = '1101'; 
const AKUN_UTANG_ID = '2101'; 
const JENIS_TRANSAKSI_PAYMENT = 'PAY';
// =======================================================

// Redirect default ke Daftar Utang
$redirect_url = 'daftar_utang.php'; 

// Autentikasi
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan' || $_SERVER["REQUEST_METHOD"] !== "POST") { 
    header("Location: login.html"); 
    exit(); 
}

// Ambil input
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

// Mulai transaksi
$conn->begin_transaction();

try {

    // Ambil deskripsi asli pengeluaran
    $sql_desc = "SELECT deskripsi FROM tr_pengeluaran WHERE id_pengeluaran = '$id_pengeluaran'";
    $result_desc = $conn->query($sql_desc);

    if ($result_desc->num_rows == 0) {
        throw new Exception("Transaksi Pengeluaran asli tidak ditemukan.");
    }

    $deskripsi_utang_asli = $result_desc->fetch_assoc()['deskripsi'];

    // 2. Catat transaksi referensi ke tr_pengeluaran
    $sql_pengeluaran = "INSERT INTO tr_pengeluaran 
                        (tgl_transaksi, deskripsi, jumlah, id_akun_beban, id_akun_kas, id_karyawan)
                        VALUES ('$tanggal_transaksi', 'Pembayaran Utang Ref #$id_pengeluaran', 
                                '$jumlah_bayar', '" . AKUN_UTANG_ID . "', '" . AKUN_KAS_ID . "', '$id_karyawan')";

    if (!$conn->query($sql_pengeluaran)) {
        throw new Exception("Gagal mencatat transaksi pengeluaran (referensi): " . $conn->error);
    }

    $id_transaksi_bayar = $conn->insert_id;
    $no_bukti_jurnal = JENIS_TRANSAKSI_PAYMENT . "-" . $id_pengeluaran . "-" . $id_transaksi_bayar;
    $deskripsi_jurnal = "Bayar Utang Usaha Ref E-$id_pengeluaran: " . $deskripsi_utang_asli;

    // 3. Jurnal Debit Utang
    $sql_debit = "INSERT INTO tr_jurnal_umum 
                  (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                  VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_jurnal', 
                          '" . AKUN_UTANG_ID . "', 'D', '$jumlah_bayar')";

    if (!$conn->query($sql_debit)) {
        throw new Exception("Gagal menjurnal Debet Utang Usaha: " . $conn->error);
    }

    // 4. Jurnal Kredit Kas
    $sql_kredit = "INSERT INTO tr_jurnal_umum 
                   (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                   VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_jurnal', 
                           '" . AKUN_KAS_ID . "', 'K', '$jumlah_bayar')";

    if (!$conn->query($sql_kredit)) {
        throw new Exception("Gagal menjurnal Kredit Kas: " . $conn->error);
    }

    // Semua OK → commit
    $conn->commit();

    $sisa_akhir = $sisa_utang - $jumlah_bayar;
    $status_akhir = ($sisa_akhir <= 5) ? "LUNAS" : "TERCATAT";

    $_SESSION['success_message'] = "Pembayaran Utang E-**$id_pengeluaran** berhasil **$status_akhir** sebesar Rp " . 
                                   number_format($jumlah_bayar) . 
                                   ". Sisa Utang: Rp " . number_format($sisa_akhir) . ".";

    // =======================================================
    // LOG AKTIVITAS — BERHASIL
    // =======================================================
    $aksi = "Pembayaran Utang E-$id_pengeluaran";
    $status_log = "Berhasil ($status_akhir)";

    $log_sql = "INSERT INTO aktivitas_log (id_pengguna, aksi, status, waktu)
                VALUES ('$id_karyawan', '$aksi', '$status_log', NOW())";
    $conn->query($log_sql);

} catch (Exception $e) {

    // Rollback
    $conn->rollback();

    $_SESSION['error_message'] = "Pembayaran Utang GAGAL diproses! Pesan Error: " . $e->getMessage();

    // =======================================================
    // LOG AKTIVITAS — GAGAL
    // =======================================================
    $aksi = "Pembayaran Utang E-$id_pengeluaran (GAGAL)";
    $status_log = $conn->real_escape_string($e->getMessage());

    $log_sql = "INSERT INTO aktivitas_log (id_pengguna, aksi, status, waktu)
                VALUES ('$id_karyawan', '$aksi', '$status_log', NOW())";
    $conn->query($log_sql);
}

$conn->close();
header("Location: $redirect_url");
exit();
?>
