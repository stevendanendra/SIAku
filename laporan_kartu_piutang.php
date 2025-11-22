<?php
// laporan_kartu_piutang.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Owner' && $_SESSION['role'] !== 'Karyawan') || !isset($_GET['id_pelanggan'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_pelanggan = $conn->real_escape_string($_GET['id_pelanggan']);
$AKUN_PIUTANG = 1102; 

// --- AMBIL DATA PELANGGAN DAN SALDO AWAL PIUTANG ---
$pelanggan_q = $conn->query("SELECT nama_pelanggan FROM ms_pelanggan WHERE id_pelanggan = '$id_pelanggan'");
$pelanggan_data = $pelanggan_q->fetch_assoc();
$nama_pelanggan = htmlspecialchars($pelanggan_data['nama_pelanggan'] ?? 'Pelanggan Tidak Ditemukan');

$saldo_awal_piutang = 0; // Asumsi saldo awal piutang per pelanggan dihitung melalui BB
$saldo_normal_piutang = 'D'; // Piutang adalah Aktiva, Saldo Normal Debit

// --- AMBIL SEMUA MUTASI PIUTANG (DEBIT & KREDIT) ---
$sql_mutasi = "
    SELECT 
        j.tgl_jurnal, 
        j.no_bukti, 
        j.deskripsi,
        j.posisi, 
        j.nilai
    FROM tr_jurnal_umum j
    -- Gabungkan dengan tr_penjualan untuk mendapatkan semua transaksi yang terkait
    JOIN tr_penjualan t ON j.no_bukti = CONCAT('PJL-', t.id_penjualan) OR j.no_bukti LIKE CONCAT('RCV-', t.id_penjualan, '-%')
    WHERE 
        j.id_akun = {$AKUN_PIUTANG} 
        AND t.id_pelanggan = '$id_pelanggan'
    ORDER BY j.tgl_jurnal ASC, j.id_jurnal ASC
";

$mutasi_query = $conn->query($sql_mutasi);

if (!$mutasi_query) {
    die("Error Database Query Mutasi: " . $conn->error);
}

// --- INISIALISASI DATA LOGIN ---
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">
    
    <p><a href='crud_master_pelanggan.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Master Pelanggan</a></p>
    <h1 class="mb-4 display-6 text-primary">Kartu Piutang Pelanggan</h1>
    <hr>
    
    <div class="card shadow-lg p-4 mb-4">
        <h2 class="h5 mb-3">Detail Pelanggan: **<?php echo $nama_pelanggan; ?>** (ID: <?php echo $id_pelanggan; ?>)</h2>
        <div class="alert alert-info small">
            Akun Piutang: **<?php echo $AKUN_PIUTANG; ?>** | Saldo Normal: **Debit** | Saldo Awal: **Rp <?php echo number_format($saldo_awal_piutang, 0, ',', '.'); ?>**
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 10%;">Tanggal</th>
                        <th style="width: 15%;">No. Bukti</th>
                        <th>Keterangan</th>
                        <th class="text-end" style="width: 15%;">Debit (Penjualan)</th>
                        <th class="text-end" style="width: 15%;">Kredit (Pelunasan)</th>
                        <th class="text-end bg-warning text-dark" style="width: 15%;">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $saldo_akhir = $saldo_awal_piutang;
                    $saldo_akhir_display = abs($saldo_akhir);
                    
                    // Tampilkan Saldo Awal (jika tidak nol)
                    if ($saldo_awal_piutang != 0): ?>
                    <tr>
                        <td></td>
                        <td></td>
                        <td>SALDO AWAL</td>
                        <td class="text-end"></td>
                        <td class="text-end"></td>
                        <td class="text-end bg-warning text-dark fw-bold">Rp <?php echo number_format($saldo_akhir_display, 0, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php while ($row = $mutasi_query->fetch_assoc()): 
                        $nilai = $row['nilai'];
                        $posisi = $row['posisi'];
                        
                        $nilai_debit = ($posisi === 'D') ? $nilai : 0;
                        $nilai_kredit = ($posisi === 'K') ? $nilai : 0;
                        
                        // Hitung Saldo Akhir (Piutang: Debit menambah, Kredit mengurangi)
                        $saldo_akhir = $saldo_akhir + $nilai_debit - $nilai_kredit; 
                        
                        // Tampilan Saldo Akhir Mutlak dan Posisi
                        $saldo_akhir_display = abs($saldo_akhir);
                        $saldo_posisi_class = $saldo_akhir < 0 ? 'text-danger' : 'text-dark'; // Biasanya Piutang tidak boleh Kredit

                        // Tentukan Deskripsi Jurnal
                        $keterangan_display = htmlspecialchars($row['deskripsi']);
                        if (strpos($row['no_bukti'], 'PJL-') === 0 && $posisi === 'D') {
                            $keterangan_display = "Penjualan Kredit/Termin (ID Transaksi: " . substr($row['no_bukti'], 4) . ")";
                        } elseif ($posisi === 'K') {
                            $keterangan_display = "Pelunasan Piutang";
                        }
                    ?>
                    <tr>
                        <td><?php echo $row['tgl_jurnal']; ?></td>
                        <td><?php echo htmlspecialchars($row['no_bukti']); ?></td>
                        <td><?php echo $keterangan_display; ?></td>
                        <td class="text-end text-primary"><?php echo $nilai_debit > 0 ? number_format($nilai_debit, 0, ',', '.') : ''; ?></td>
                        <td class="text-end text-danger"><?php echo $nilai_kredit > 0 ? number_format($nilai_kredit, 0, ',', '.') : ''; ?></td>
                        <td class="text-end bg-warning-subtle fw-bold <?php echo $saldo_posisi_class; ?>">
                            Rp <?php echo number_format($saldo_akhir_display, 0, ',', '.'); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 d-print-none">
        <p class="text-muted small">Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)</p> 
        <a href="laporan_kartu_piutang.php?id_pelanggan=<?php echo $id_pelanggan; ?>" class="btn btn-info">Refresh Data</a>
    </div>

</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>