<?php
// pembayaran_hutang_form.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan' || !isset($_GET['id_pengeluaran'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_pengeluaran = $conn->real_escape_string($_GET['id_pengeluaran']);

// --- LOGIKA PENANGANAN PESAN KRITIS ---
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
// ------------------------------------

// Re-Query data utang spesifik
$sql_detail = "
    SELECT 
        e.id_pengeluaran, 
        e.deskripsi, 
        e.jumlah AS total_utang,
        e.tgl_jatuh_tempo,
        e.jml_bulan_cicilan,
        -- Menghitung total nilai yang sudah dibayar
        (SELECT COALESCE(SUM(j.nilai), 0) FROM tr_jurnal_umum j 
         WHERE j.id_akun = 2101 AND j.posisi = 'D' AND j.no_bukti LIKE CONCAT('PAY-', e.id_pengeluaran, '-%')) AS sudah_dibayar,
        -- Logika baru: Menghitung berapa kali pembayaran sudah dilakukan
        (SELECT COUNT(DISTINCT j.no_bukti) FROM tr_jurnal_umum j 
         WHERE j.id_akun = 2101 AND j.posisi = 'D' AND j.no_bukti LIKE CONCAT('PAY-', e.id_pengeluaran, '-%')) AS jumlah_pembayaran_sudah
    FROM tr_pengeluaran e
    WHERE e.id_pengeluaran = '$id_pengeluaran'
";

$result = $conn->query($sql_detail);
if ($result->num_rows == 0) {
    die("Transaksi utang tidak ditemukan atau sudah lunas.");
}

$data = $result->fetch_assoc();
$sisa = $data['total_utang'] - $data['sudah_dibayar'];
$angsuran_bulanan = ($data['jml_bulan_cicilan'] > 0) ? floor($data['total_utang'] / $data['jml_bulan_cicilan']) : $sisa;

// LOGIKA STATUS ANGSURAN
$angsuran_dibayar = (int)$data['jumlah_pembayaran_sudah'];
$angsuran_ke_sekarang = $angsuran_dibayar + 1;

// --- LOGIKA UTAMA: ANGSURAN TERAKHIR SPESIAL (FINAL FIX) ---
$jumlah_default_bayar = $angsuran_bulanan; 
$is_last_payment = false;
$tolerance = 5; // Toleransi Rupiah (untuk mengcover pembulatan di akhir)

if ($data['jml_bulan_cicilan'] > 0) {
    
    // Logika Angsuran Terakhir: Jika Sisa Utang sudah SAMA DENGAN 1x angsuran normal atau kurang.
    if ($sisa <= $angsuran_bulanan + $tolerance) { 
        $jumlah_default_bayar = $sisa; // <-- SET DEFAULT KE SISA SALDO (FIX)
        $is_last_payment = true;
    } else {
        $jumlah_default_bayar = $angsuran_bulanan; // Jika bukan terakhir, bayar normal
    }
} else {
    // Jika Termin Lunas (bukan cicilan), default tetap SISA
    $jumlah_default_bayar = $sisa; 
}
// Pastikan jumlah default bayar tidak nol atau negatif
if ($jumlah_default_bayar <= 0) {
    $jumlah_default_bayar = $sisa;
}

// --- LOGIKA BARU: QUERY RIWAYAT PEMBAYARAN ---
$sql_riwayat = "
    SELECT tgl_jurnal, no_bukti, nilai
    FROM tr_jurnal_umum
    WHERE id_akun = 2101 AND posisi = 'D' AND no_bukti LIKE CONCAT('PAY-', '$id_pengeluaran', '-%')
    ORDER BY tgl_jurnal DESC
";
$riwayat_query = $conn->query($sql_riwayat);

$nama_karyawan = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Kasir');
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">

    <h1 class="mb-4">Konfirmasi Pembayaran Hutang Usaha</h1>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>
    
    <div class="card p-4 shadow-sm mb-4">
        <div class="row">
            <div class="col-md-6 border-end">
                <h5 class="text-primary mb-3">Detail Transaksi Utang</h5>
                <p><strong>ID Transaksi Pembelian:</strong> <span class="badge bg-secondary">E-<?php echo $id_pengeluaran; ?></span></p>
                <p><strong>Deskripsi Pembelian:</strong> <?php echo htmlspecialchars($data['deskripsi']); ?></p>
                
                <?php if ($data['jml_bulan_cicilan'] > 0): ?>
                    <p><strong>Skema Cicilan:</strong> <span class="fw-bold"><?php echo $data['jml_bulan_cicilan']; ?> Bulan</span></p>
                    <p style="color: darkgreen;">
                        Angsuran Sudah Dibayar: **<?php echo $angsuran_dibayar; ?>** kali. <br>
                        **Angsuran SAAT INI adalah ke-<?php echo $angsuran_ke_sekarang; ?>**
                    </p>
                    <p><strong>Angsuran Normal:</strong> Rp <?php echo number_format($angsuran_bulanan, 0, ',', '.'); ?></p>
                <?php endif; ?>
                
                <hr>
                <p class="mb-1"><strong>Total Utang Awal:</strong> Rp <?php echo number_format($data['total_utang'], 0, ',', '.'); ?></p>
                <p class="mb-1"><strong>Sudah Dibayar:</strong> Rp <?php echo number_format($data['sudah_dibayar'], 0, ',', '.'); ?></p>
                <p class="h4 mt-2"><strong>Sisa Saldo:</strong> <span class="text-danger">Rp <?php echo number_format($sisa, 0, ',', '.'); ?></span></p>
            </div>

            <div class="col-md-6">
                <h5 class="text-success mb-3">Form Pembayaran</h5>
                <form action="pembayaran_hutang_proses.php" method="POST">
                    <input type="hidden" name="id_pengeluaran" value="<?php echo $id_pengeluaran; ?>">
                    <input type="hidden" name="sisa_utang" value="<?php echo $sisa; ?>">

                    <?php if ($is_last_payment): ?>
                        <div class="alert alert-danger mb-3 p-2">
                            ⚠️ **ANGSURAN TERAKHIR:** Masukkan saldo penuh.
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="jumlah_bayar" class="form-label fw-bold">Jumlah Bayar Saat Ini (BIGINT):</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="jumlah_bayar" name="jumlah_bayar" required 
                                min="1" max="<?php echo $sisa; ?>" value="<?php echo $jumlah_default_bayar; ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100 mt-3">Konfirmasi Pembayaran</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card p-4 shadow-sm mt-4">
        <h5 class="card-title">Riwayat Pembayaran Angsuran (Jurnal)</h5>
        <div class="table-responsive">
            <?php if ($riwayat_query->num_rows > 0): ?>
            <table class="table table-striped table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal Bayar</th>
                        <th>No. Bukti Jurnal</th>
                        <th class="text-end">Jumlah Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($r = $riwayat_query->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $r['tgl_jurnal']; ?></td>
                        <td><?php echo htmlspecialchars($r['no_bukti']); ?></td>
                        <td class="text-end text-success">Rp <?php echo number_format($r['nilai'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p class="text-muted">Belum ada riwayat pembayaran untuk transaksi ini.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-4 d-flex justify-content-between">
        <a href="daftar_utang.php" class="btn btn-outline-secondary">← Kembali ke Daftar Hutang</a>
        <a href='dashboard_karyawan.php' class="btn btn-outline-primary">Kembali ke Dashboard</a>
    </div>
</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_karyawan; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>