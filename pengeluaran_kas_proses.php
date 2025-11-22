<?php
// pengeluaran_kas_proses.php
session_start();
include 'koneksi.php'; 

// =======================================================
// KONSTANTA AKUNTANSI
// =======================================================
const AKUN_KAS_ID = '1101'; 
const AKUN_UTANG_ID = '2101'; 
const JENIS_TRANSAKSI_EXPENDITURE = 'EXP'; 
// =======================================================

// --- AUTENTIKASI DAN METODE (FIX: Izinkan Owner ATAU Karyawan) ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Karyawan' && $_SESSION['role'] !== 'Owner') || $_SERVER["REQUEST_METHOD"] !== "POST") { 
    header("Location: login.html"); 
    exit(); 
}

$redirect_url = 'pengeluaran_kas_form.php'; 

// Ambil dan sanitasi data input
$id_karyawan = $_SESSION['id_pengguna'];
$deskripsi = $conn->real_escape_string($_POST['deskripsi'] ?? '');
$id_akun_debit = $conn->real_escape_string($_POST['id_akun_debit'] ?? '');
$jumlah = (int)($conn->real_escape_string($_POST['jumlah'] ?? 0));
$metode_bayar = $_POST['metode_bayar'] ?? ''; 

// Data Kredit/Termin/Cicilan (Ambil nilai mentah)
$tgl_jatuh_tempo_post = $_POST['tgl_jatuh_tempo'] ?? null;
$jml_bulan_cicilan_post = $_POST['jml_bulan_cicilan'] ?? null; 

$tanggal_transaksi = date('Y-m-d'); 

// Validasi dasar
if (empty($id_akun_debit) || $jumlah <= 0 || empty($metode_bayar)) {
    $_SESSION['error_message'] = "Data pengeluaran tidak lengkap atau Jumlah tidak valid.";
    header("Location: $redirect_url");
    exit();
}
if (!is_numeric($jumlah) || floor($jumlah) != $jumlah || $jumlah < 1) {
    $_SESSION['error_message'] = "Jumlah pengeluaran harus bilangan bulat positif.";
    header("Location: $redirect_url");
    exit();
}

// --- LOGIKA KRUSIAL: Menentukan Akun Kredit dan Penanganan NULL/INT ---
$id_akun_kredit = '';
$deskripsi_kredit_suffix = '';
$tgl_jatuh_tempo_db = "NULL"; // Default SQL NULL
$jml_bulan_cicilan_db = "NULL"; // Default SQL NULL

if ($metode_bayar == 'Tunai') {
    $id_akun_kredit = AKUN_KAS_ID; 
    $deskripsi_kredit_suffix = "Kas (Tunai) Berkurang";
    
} elseif ($metode_bayar == 'Kredit_Termin' || $metode_bayar == 'Kredit_Cicilan') {
    $id_akun_kredit = AKUN_UTANG_ID; // Utang Usaha
    $deskripsi_kredit_suffix = "Utang Usaha Bertambah";
    
    // 1. Tanggal Jatuh Tempo (Wajib)
    if (empty($tgl_jatuh_tempo_post)) {
        $_SESSION['error_message'] = "Tanggal jatuh tempo wajib diisi untuk Kredit.";
        header("Location: $redirect_url");
        exit();
    }
    // FIX: Tgl Jatuh Tempo harus diapit kutip!
    $tgl_jatuh_tempo_db = "'" . $conn->real_escape_string($tgl_jatuh_tempo_post) . "'";

    // 2. Logika Cicilan
    if ($metode_bayar == 'Kredit_Cicilan') {
        if (empty($jml_bulan_cicilan_post) || $jml_bulan_cicilan_post < 2) {
            $_SESSION['error_message'] = "Jumlah bulan cicilan minimal 2.";
            header("Location: $redirect_url");
            exit();
        }
        // FIX: Kirim nilai cicilan sebagai integer (tanpa kutip)
        $jml_bulan_cicilan_db = $conn->real_escape_string($jml_bulan_cicilan_post);
        $deskripsi_kredit_suffix .= " ($jml_bulan_cicilan_db bulan)";
    }
    
} else {
    $_SESSION['error_message'] = "Metode pembayaran tidak valid.";
    header("Location: $redirect_url");
    exit();
}

// ----------------------------------------------------------------------------------
// 1. MULAI TRANSAKSI DATABASE
// ----------------------------------------------------------------------------------
$conn->begin_transaction();

try {
    // ----------------------------------------------------------------------------------
    // 2. CATAT TRANSAKSI UTAMA (tr_pengeluaran)
    // ----------------------------------------------------------------------------------
    $sql_pengeluaran = "INSERT INTO tr_pengeluaran 
                        (tgl_transaksi, deskripsi, jumlah, tgl_jatuh_tempo, jml_bulan_cicilan, id_akun_beban, id_akun_kas, id_karyawan)
                        VALUES ('$tanggal_transaksi', '$deskripsi', '$jumlah', $tgl_jatuh_tempo_db, $jml_bulan_cicilan_db, '$id_akun_debit', '$id_akun_kredit', '{$id_karyawan}')";
    
    if (!$conn->query($sql_pengeluaran)) {
        throw new Exception("Gagal mencatat transaksi pengeluaran: " . $conn->error);
    }
    $id_pengeluaran_baru = $conn->insert_id;
    $no_bukti_jurnal = JENIS_TRANSAKSI_EXPENDITURE . "-" . $id_pengeluaran_baru; // Nomor Bukti Transaksi
    
    // ----------------------------------------------------------------------------------
    // 3. GENERATE JURNAL DEBIT (Akun Tujuan: Beban/Aset/Utang Berkurang)
    // ----------------------------------------------------------------------------------
    $deskripsi_debit = "Debet Akun Tujuan ($id_akun_debit) untuk: " . $deskripsi;
    $sql_debit = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                  VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_debit', '$id_akun_debit', 'D', '$jumlah')";
    
    if (!$conn->query($sql_debit)) {
        throw new Exception("Gagal menjurnal Debet Akun Tujuan: " . $conn->error);
    }

    // ----------------------------------------------------------------------------------
    // 4. GENERATE JURNAL KREDIT (Kas atau Utang Usaha)
    // ----------------------------------------------------------------------------------
    $deskripsi_kredit = "Kredit Akun $id_akun_kredit ($deskripsi_kredit_suffix) untuk: " . $deskripsi;
    $sql_kredit = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                   VALUES ('$tanggal_transaksi', '$no_bukti_jurnal', '$deskripsi_kredit', '$id_akun_kredit', 'K', '$jumlah')";
    
    if (!$conn->query($sql_kredit)) {
        throw new Exception("Gagal menjurnal Kredit Kas/Utang: " . $conn->error);
    }

    // ----------------------------------------------------------------------------------
    // 5. COMMIT TRANSAKSI
    // ----------------------------------------------------------------------------------
    $conn->commit();
    $_SESSION['success_message'] = "Transaksi Pengeluaran **EXP-$id_pengeluaran_baru** berhasil dicatat ($metode_bayar) dan Jurnal Umum telah dibuat.";
    
} catch (Exception $e) {
    // 6. ROLLBACK JIKA GAGAL
    $conn->rollback();
    $_SESSION['error_message'] = "Transaksi Pengeluaran GAGAL diproses! Pesan Error: " . $e->getMessage();
}

// Tutup koneksi dan redirect
$conn->close();
header("Location: $redirect_url");
exit();
?>