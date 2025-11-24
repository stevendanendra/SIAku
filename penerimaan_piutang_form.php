<?php
// penerimaan_piutang_form.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan' || !isset($_GET['id_penjualan'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_penjualan = $conn->real_escape_string($_GET['id_penjualan']);
$nominal_bayar_disarankan = (int)($_GET['bayar'] ?? 0); // Nominal yang disarankan dari daftar

$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']); 
unset($_SESSION['success_message']);

$id_karyawan = $_SESSION['id_pengguna']; 
$nama_karyawan = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Kasir');
$role_login = htmlspecialchars($_SESSION['role']);

// --- LOGIKA AMBIL DATA PIUTANG ---
$AKUN_PIUTANG_USAHA = 1102; 
// FIX: Inisialisasi variabel query riwayat
$riwayat_query = null;

$sql_piutang_detail = "
    SELECT 
        t.id_penjualan,
        t.total_penjualan AS total_piutang,
        t.tgl_jatuh_tempo,
        t.jml_bulan_cicilan,
        t.metode_bayar,
        p.nama_pelanggan,
        -- Subquery untuk menghitung total yang sudah dibayar (Kredit pada Akun Piutang 1102)
        (SELECT COALESCE(SUM(j.nilai), 0) 
         FROM tr_jurnal_umum j
         WHERE j.id_akun = {$AKUN_PIUTANG_USAHA} AND j.posisi = 'K' AND j.deskripsi LIKE CONCAT('%Pelunasan Piutang ID #', t.id_penjualan, '%')) AS sudah_dibayar
    FROM tr_penjualan t
    JOIN ms_pelanggan p ON t.id_pelanggan = p.id_pelanggan
    WHERE t.id_penjualan = '$id_penjualan'
";

$piutang_detail_query = $conn->query($sql_piutang_detail);

if ($piutang_detail_query->num_rows == 0) {
    die("Detail Piutang tidak ditemukan.");
}

$data = $piutang_detail_query->fetch_assoc();
$sisa_piutang = $data['total_piutang'] - $data['sudah_dibayar'];
$jml_bulan = (int)$data['jml_bulan_cicilan'];

// --- LOGIKA HITUNG CICILAN ---
$angsuran_normal = 0;
$cicilan_ke_display = 1; 

if ($jml_bulan > 1) {
    $angsuran_normal = floor($data['total_piutang'] / $jml_bulan);
    $cicilan_ke_float = $data['sudah_dibayar'] / $angsuran_normal;
    $cicilan_ke_display = floor($cicilan_ke_float) + 1;
    $metode_display = "Cicilan (" . $jml_bulan . " Bulan)";

} else {
    $angsuran_normal = $sisa_piutang; 
    $metode_display = 'Termin Lunas';
}

// --- LOGIKA BARU: QUERY RIWAYAT PEMBAYARAN ---
// Asumsi Jurnal Umum mencatat ID Piutang di deskripsi
$sql_riwayat = "
    SELECT tgl_jurnal, no_bukti, nilai
    FROM tr_jurnal_umum
    WHERE id_akun = {$AKUN_PIUTANG_USAHA} AND posisi = 'K' AND deskripsi LIKE CONCAT('%Pelunasan Piutang ID #', '$id_penjualan', '%')
    ORDER BY tgl_jurnal DESC
";
$riwayat_query = $conn->query($sql_riwayat); // Variabel ini sekarang sudah pasti terisi

// --- LOGIKA UTAMA: ANGSURAN TERAKHIR SPESIAL (FINAL FIX) ---
// Tentukan apakah ini pembayaran terakhir
$is_last_payment = ($sisa_piutang == $nominal_bayar_disarankan);

// Jika sisa < nominal yang disarankan, sisa adalah pembayaran terakhir dan batasi input
if ($sisa_piutang < $nominal_bayar_disarankan) {
    $is_last_payment = true;
    $nominal_bayar_disarankan = $sisa_piutang; // Batasi nilai input
}
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <p><a href='daftar_piutang.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Piutang</a></p>
    <h1 class="mb-4 display-6 text-info">3. Terima Pelunasan Piutang</h1>
    <hr>

    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>
    
    <div class="row">
        
        <div class="col-md-6">
            <div class="card shadow-lg p-4 border-info">
                <h3 class="card-title h5 text-info">Detail Transaksi: P-<?php echo $id_penjualan; ?></h3>
                
                <p class="mb-1"><strong>Pelanggan:</strong> <?php echo htmlspecialchars($data['nama_pelanggan']); ?></p>
                <p class="mb-1"><strong>Metode Pembayaran:</strong> <?php echo $metode_display; ?></p>
                <p class="mb-1"><strong>Jatuh Tempo Akhir:</strong> <?php echo $data['tgl_jatuh_tempo']; ?></p>
                
                <hr>
                <p class="mb-1"><strong>Total Piutang Awal:</strong> Rp <?php echo number_format($data['total_piutang'], 0, ',', '.'); ?></p>
                <p class="mb-1"><strong>Total Sudah Dibayar:</strong> Rp <?php echo number_format($data['sudah_dibayar'], 0, ',', '.'); ?></p>
                <p class="h4 mt-2 text-success"><strong>Sisa Saldo:</strong> Rp <?php echo number_format($sisa_piutang, 0, ',', '.'); ?></p>

                <?php if ($jml_bulan > 1): ?>
                    <hr>
                    <p class="mb-1 fw-bold">Angsuran Normal: <span class="text-primary">Rp <?php echo number_format($angsuran_normal, 0, ',', '.'); ?></span></p>
                    <p class="mb-1 fw-bold text-primary">Angsuran SAAT INI: <span class="text-danger">Ke-<?php echo $cicilan_ke_display; ?></span> dari <?php echo $jml_bulan; ?> kali</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-lg p-4 border-success h-100">
                <h5 class="text-success mb-3">Form Penerimaan</h5>
                
                <?php if ($is_last_payment): ?>
                     <div class="alert alert-danger mb-3 p-2 text-center">
                         ⚠️ **ANGSURAN TERAKHIR** (Final): Masukkan saldo penuh.
                     </div>
                <?php endif; ?>

                <form action="penerimaan_piutang_proses.php" method="POST">
                    <input type="hidden" name="id_penjualan" value="<?php echo $id_penjualan; ?>">
                    <input type="hidden" name="sisa_piutang" value="<?php echo $sisa_piutang; ?>">
                    <input type="hidden" name="total_piutang_awal" value="<?php echo $data['total_piutang']; ?>">

                    <div class="mb-3">
                        <label for="jumlah_bayar" class="form-label fw-bold">Jumlah Diterima (Rp):</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control text-end fw-bold" id="jumlah_bayar" name="jumlah_bayar" 
                                   required min="1" max="<?php echo $sisa_piutang; ?>" value="<?php echo $nominal_bayar_disarankan; ?>">
                        </div>
                        <div class="form-text">Maksimal yang bisa diterima: Rp <?php echo number_format($sisa_piutang, 0, ',', '.'); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tgl_bayar" class="form-label fw-bold">Tanggal Penerimaan:</label>
                        <input type="date" class="form-control" id="tgl_bayar" name="tgl_bayar" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100 mt-4">Catat Pelunasan Piutang</button>
                </form>
            </div>
        </div>

    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card p-4 shadow-sm">
                <h5 class="card-title">Riwayat Pembayaran Angsuran</h5>
                <div class="table-responsive">
                    <?php if ($riwayat_query && $riwayat_query->num_rows > 0): ?>
                    <table class="table table-striped table-sm table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 15%;">Tanggal Bayar</th>
                                <th>No. Bukti Jurnal</th>
                                <th class="text-end" style="width: 25%;">Jumlah Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($r = $riwayat_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $r['tgl_jurnal']; ?></td>
                                <td><?php echo htmlspecialchars($r['no_bukti']); ?></td>
                                <td class="text-end text-success fw-bold">Rp <?php echo number_format($r['nilai'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="text-muted small">Belum ada riwayat pembayaran untuk transaksi ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: Karyawan (<?php echo $nama_karyawan; ?>, ID <?php echo $id_karyawan; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_karyawan; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_karyawan; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>