<?php
// daftar_utang.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan') { 
    header("Location: login.html"); 
    exit(); 
}

$id_karyawan = $_SESSION['id_pengguna']; 
$nama_karyawan = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Kasir');
$role_login = htmlspecialchars($_SESSION['role']);

// --- Query Utang Usaha Aktif ---
// Utang Usaha dicatat di tr_pengeluaran dengan id_akun_kas = 2101
$AKUN_UTANG_USAHA = 2101; 

$sql_utang = "
    SELECT 
        e.id_pengeluaran, 
        e.deskripsi, 
        e.jumlah AS total_utang,
        e.tgl_jatuh_tempo,
        e.jml_bulan_cicilan,
        a.nama_akun AS akun_debit,
        -- Subquery untuk menghitung total yang sudah dibayar (posisi D pada Akun Utang)
        (SELECT COALESCE(SUM(j.nilai), 0) FROM tr_jurnal_umum j 
         WHERE j.id_akun = {$AKUN_UTANG_USAHA} AND j.posisi = 'D' AND j.deskripsi LIKE CONCAT('%Ref Transaksi E-', e.id_pengeluaran, ':%')) AS sudah_dibayar
    FROM tr_pengeluaran e
    JOIN ms_akun a ON e.id_akun_beban = a.id_akun
    -- FIX KRITIS: Memfilter transaksi yang menambah Utang Usaha (id_akun_kas = 2101)
    WHERE e.id_akun_kas = {$AKUN_UTANG_USAHA} 
    HAVING total_utang > sudah_dibayar
    ORDER BY e.tgl_jatuh_tempo ASC
";

$utang_query = $conn->query($sql_utang);
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <p><a href='dashboard_karyawan.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Kasir</a></p>
    <h1 class="mb-4 display-6 text-warning">4. Daftar Hutang Usaha Belum Lunas</h1>
    <hr>
    
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width: 10%;">ID Transaksi</th>
                    <th style="width: 25%;">Deskripsi Pembelian</th>
                    <th class="text-end" style="width: 12%;">Total Utang Awal</th>
                    <th class="text-end" style="width: 12%;">Sudah Dibayar</th>
                    <th class="text-end" style="width: 12%;">Sisa Utang</th>
                    <th style="width: 8%;">Skema</th>
                    <th style="width: 10%;">Jatuh Tempo</th>
                    <th style="width: 10%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($utang_query->num_rows > 0): ?>
                    <?php while ($row = $utang_query->fetch_assoc()): 
                        $sisa = $row['total_utang'] - $row['sudah_dibayar'];
                        $is_jatuh_tempo = (strtotime($row['tgl_jatuh_tempo']) < time());
                        $jatuh_tempo_class = $is_jatuh_tempo ? 'text-danger fw-bold' : '';
                        $skema = $row['jml_bulan_cicilan'] ? $row['jml_bulan_cicilan'] . ' Bulan' : 'Termin Lunas';
                        
                        // Hitung angsuran yang disarankan (untuk cicilan)
                        $cicilan_per_bulan = ($row['jml_bulan_cicilan'] > 0) ? floor($row['total_utang'] / $row['jml_bulan_cicilan']) : $sisa;
                        $nominal_bayar = min($sisa, $cicilan_per_bulan);
                    ?>
                    <tr>
                        <td><?php echo "E-" . $row['id_pengeluaran']; ?></td>
                        <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                        <td class="text-end">Rp <?php echo number_format($row['total_utang'], 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($row['sudah_dibayar'], 0, ',', '.'); ?></td>
                        <td class="text-end fw-bold text-danger">Rp <?php echo number_format($sisa, 0, ',', '.'); ?></td>
                        <td><?php echo $skema; ?></td>
                        <td class="<?php echo $jatuh_tempo_class; ?>"><?php echo $row['tgl_jatuh_tempo']; ?></td>
                        <td>
                            <a href="pembayaran_hutang_form.php?id_pengeluaran=<?php echo $row['id_pengeluaran']; ?>&bayar=<?php echo $nominal_bayar; ?>">
                                <button type="button" class="btn btn-sm btn-success w-100">BAYAR</button>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">✔️ Tidak ada hutang usaha yang aktif.</td>
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