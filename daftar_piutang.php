<?php
// daftar_piutang.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan') { 
    header("Location: login.html"); 
    exit(); 
}

$id_karyawan = $_SESSION['id_pengguna']; 
$nama_karyawan = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Kasir');
$role_login = htmlspecialchars($_SESSION['role']);

// --- Query Piutang Usaha Aktif ---
$AKUN_PIUTANG_USAHA = 1102; 

$sql_piutang = "
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
    WHERE t.metode_bayar IN ('Termin', 'Cicilan') AND t.is_lunas = 0
    HAVING total_piutang > sudah_dibayar
    ORDER BY t.tgl_jatuh_tempo ASC
";

$piutang_query = $conn->query($sql_piutang);

if (!$piutang_query) {
    die("Error Database Query Piutang: " . $conn->error);
}
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <p><a href='dashboard_karyawan.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Kasir</a></p>
    <h1 class="mb-4 display-6 text-info">3. Daftar Piutang Usaha Belum Lunas</h1>
    <hr>
    
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 25%;">Pelanggan</th>
                    <th class="text-end" style="width: 15%;">Total Piutang</th>
                    <th class="text-end" style="width: 15%;">Sudah Dibayar</th>
                    <th class="text-end" style="width: 15%;">Sisa Piutang</th>
                    <th style="width: 12%;">Skema</th>
                    <th style="width: 10%;">Jatuh Tempo</th>
                    <th style="width: 10%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($piutang_query->num_rows > 0): ?>
                    <?php while ($row = $piutang_query->fetch_assoc()): 
                        $sisa = $row['total_piutang'] - $row['sudah_dibayar'];
                        $is_jatuh_tempo = (strtotime($row['tgl_jatuh_tempo']) < time());
                        $jatuh_tempo_class = $is_jatuh_tempo ? 'text-danger fw-bold' : '';
                        
                        $jml_bulan = (int)$row['jml_bulan_cicilan'];
                        
                        // 1. Hitung Nominal Angsuran Normal
                        $angsuran_normal = ($jml_bulan > 1) ? floor($row['total_piutang'] / $jml_bulan) : $sisa;
                        
                        // 2. Tentukan Skema Display
                        $skema_display = ($jml_bulan > 1) ? $jml_bulan . ' Bulan' : 'Termin';
                        
                        // 3. Tentukan Cicilan Ke-
                        $cicilan_ke = ($jml_bulan > 1 && $angsuran_normal > 0) ? ($row['sudah_dibayar'] / $angsuran_normal) : 0;
                        $cicilan_ke_display = ($jml_bulan > 1) ? floor($cicilan_ke) + 1 : 1; 

                        // 4. Angsuran yang disarankan (menggunakan logika penyeimbang)
                        $nominal_bayar = min($sisa, $angsuran_normal); 
                        
                        // FIX KRITIS: Override jika pembayaran sebelumnya tidak genap (tidak nol dan bukan kelipatan angsuran normal)
                        // Logika ini memaksa rekomendasi menjadi Sisa Piutang jika saldo tidak teratur
                        if ($row['sudah_dibayar'] > 0 && ($row['sudah_dibayar'] % $angsuran_normal != 0)) {
                             $nominal_bayar = $sisa;
                        }
                        // Pastikan nominal bayar tidak lebih dari sisa
                        $nominal_bayar = min($nominal_bayar, $sisa); 

                    ?>
                    <tr>
                        <td><?php echo "P-" . $row['id_penjualan']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_pelanggan']); ?></td>
                        <td class="text-end">Rp <?php echo number_format($row['total_piutang'], 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($row['sudah_dibayar'], 0, ',', '.'); ?></td>
                        <td class="text-end fw-bold text-success">Rp <?php echo number_format($sisa, 0, ',', '.'); ?></td>
                        
                        <td><?php echo $skema_display; ?></td>
                        
                        <td class="<?php echo $jatuh_tempo_class; ?>"><?php echo $row['tgl_jatuh_tempo']; ?></td>
                        
                        <td>
                            <a href="penerimaan_piutang_form.php?id_penjualan=<?php echo $row['id_penjualan']; ?>&bayar=<?php echo $nominal_bayar; ?>">
                                <button type="button" class="btn btn-sm btn-info w-100">TERIMA</button>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">✔️ Tidak ada piutang usaha yang aktif.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_karyawan; ?>, ID <?php echo $id_karyawan; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_karyawan; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_karyawan; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>