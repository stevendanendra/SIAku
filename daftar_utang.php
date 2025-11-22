<?php
// daftar_utang.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan') { 
    header("Location: login.html"); 
    exit(); 
}

$id_karyawan = $_SESSION['id_pengguna']; // Ambil ID Karyawan

// Query Utang Usaha Aktif 
$sql_utang = "
    SELECT 
        e.id_pengeluaran, 
        e.deskripsi, 
        e.jumlah AS total_utang,
        e.tgl_jatuh_tempo,
        e.jml_bulan_cicilan,
        a.nama_akun AS akun_debit,
        -- Subquery untuk menghitung total yang sudah dibayar 
        (SELECT COALESCE(SUM(j.nilai), 0) FROM tr_jurnal_umum j 
         WHERE j.id_akun = 2101 AND j.posisi = 'D' AND j.no_bukti LIKE CONCAT('PAY-', e.id_pengeluaran, '-%')) AS sudah_dibayar
    FROM tr_pengeluaran e
    JOIN ms_akun a ON e.id_akun_beban = a.id_akun
    WHERE e.id_akun_kas = 2101 
    HAVING total_utang > sudah_dibayar
    ORDER BY e.tgl_jatuh_tempo ASC
";

$utang_query = $conn->query($sql_utang);
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">3. Daftar Hutang Usaha Belum Lunas</h1>
    
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark">
                <tr>
                    <th>ID Transaksi</th>
                    <th>Deskripsi Pembelian</th>
                    <th class="text-end">Total Utang Awal</th>
                    <th class="text-end">Sudah Dibayar</th>
                    <th class="text-end">Sisa Utang</th>
                    <th>Skema</th>
                    <th>Jatuh Tempo</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($utang_query->num_rows > 0): ?>
                    <?php while ($row = $utang_query->fetch_assoc()): 
                        $sisa = $row['total_utang'] - $row['sudah_dibayar'];
                        $angsuran_bulanan = ($row['jml_bulan_cicilan'] > 0) ? floor($row['total_utang'] / $row['jml_bulan_cicilan']) : $sisa;
                    ?>
                    <tr>
                        <td><?php echo "E-" . $row['id_pengeluaran']; ?></td>
                        <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                        <td class="text-end">Rp <?php echo number_format($row['total_utang'], 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($row['sudah_dibayar'], 0, ',', '.'); ?></td>
                        <td class="text-end fw-bold text-danger">Rp <?php echo number_format($sisa, 0, ',', '.'); ?></td>
                        <td><?php echo $row['jml_bulan_cicilan'] ? $row['jml_bulan_cicilan'] . ' Bulan' : 'Termin Lunas'; ?></td>
                        <td><?php echo $row['tgl_jatuh_tempo']; ?></td>
                        <td>
                            <a href="pembayaran_hutang_form.php?id_pengeluaran=<?php echo $row['id_pengeluaran']; ?>">
                                <button type="button" class="btn btn-sm btn-success">BAYAR</button>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">✔️ Tidak ada hutang usaha yang aktif.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <hr>
    <p class="text-muted">Akses: Kasir (Siska, ID <?php echo $id_karyawan; ?>)</p> 
    <p><a href='dashboard_karyawan.php' class="btn btn-sm btn-outline-primary">← Kembali ke Dashboard</a></p>

</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: Kasir (Siska, ID <?php echo $id_karyawan; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>