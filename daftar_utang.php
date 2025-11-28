<?php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan') { 
    header("Location: login.html"); 
    exit(); 
}

$id_karyawan = $_SESSION['id_pengguna']; 
$nama_karyawan = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Kasir');
$role_login = htmlspecialchars($_SESSION['role']);

$AKUN_UTANG_USAHA = 2101;

// ===============================================
// QUERY UTANG + SUPPLIER + TOTAL SUDAH DIBAYAR
// ===============================================
$sql_utang = "
    SELECT 
        e.id_pengeluaran, 
        e.deskripsi, 
        e.jumlah AS total_utang,
        e.tgl_jatuh_tempo,
        e.jml_bulan_cicilan,
        a.nama_akun AS akun_debit,
        s.nama_supplier,

        (SELECT COALESCE(SUM(j.nilai), 0) 
         FROM tr_jurnal_umum j 
         WHERE j.id_akun = {$AKUN_UTANG_USAHA} 
           AND j.posisi = 'D'
           AND j.deskripsi LIKE CONCAT('%Ref E-', e.id_pengeluaran, '%')
        ) AS sudah_dibayar

    FROM tr_pengeluaran e
    JOIN ms_akun a ON e.id_akun_beban = a.id_akun
    LEFT JOIN ms_supplier s ON e.id_supplier = s.id_supplier
    WHERE e.id_akun_kas = {$AKUN_UTANG_USAHA}
    HAVING total_utang > sudah_dibayar
    ORDER BY e.tgl_jatuh_tempo ASC
";

$utang_query = $conn->query($sql_utang);
?>

<?php include '_header.php'; ?>
<div class="container mt-5">

    <p><a href='dashboard_karyawan.php' class="btn btn-sm btn-outline-secondary">
        ← Kembali ke Dashboard Kasir
    </a></p>

    <h1 class="mb-3 text-warning">Daftar Hutang Usaha Belum Lunas</h1>
    <hr>

    <!-- NOTIFIKASI -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive mt-3">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Supplier</th>
                    <th>Deskripsi</th>
                    <th class="text-end">Total Utang</th>
                    <th class="text-end">Sudah Dibayar</th>
                    <th class="text-end">Sisa</th>
                    <th>Skema</th>
                    <th>Jatuh Tempo</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($utang_query->num_rows > 0): ?>
                    <?php while ($row = $utang_query->fetch_assoc()): 

                        $sisa = $row['total_utang'] - $row['sudah_dibayar'];

                        $is_jt = (strtotime($row['tgl_jatuh_tempo']) < time());
                        $jt_class = $is_jt ? 'text-danger fw-bold' : '';

                        $skema = $row['jml_bulan_cicilan'] 
                            ? $row['jml_bulan_cicilan'] . ' Bulan' 
                            : 'Termin Lunas';

                        $cicilan = $row['jml_bulan_cicilan'] > 0 
                            ? floor($row['total_utang'] / $row['jml_bulan_cicilan']) 
                            : $sisa;

                        $nominal_bayar = min($sisa, $cicilan);

                    ?>
                    <tr>
                        <td><?= "E-" . $row['id_pengeluaran']; ?></td>

                        <td>
                            <?= $row['nama_supplier'] 
                                ? htmlspecialchars($row['nama_supplier']) 
                                : '<span class="text-muted">-</span>'; ?>
                        </td>

                        <td><?= htmlspecialchars($row['deskripsi']); ?></td>

                        <td class="text-end">Rp <?= number_format($row['total_utang'], 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?= number_format($row['sudah_dibayar'], 0, ',', '.'); ?></td>

                        <td class="text-end fw-bold text-danger">
                            Rp <?= number_format($sisa, 0, ',', '.'); ?>
                        </td>

                        <td><?= $skema; ?></td>

                        <td class="<?= $jt_class; ?>"><?= $row['tgl_jatuh_tempo']; ?></td>

                        <td>
                            <a href="pembayaran_hutang_form.php?id_pengeluaran=<?= $row['id_pengeluaran']; ?>&bayar=<?= $nominal_bayar; ?>">
                                <button class="btn btn-sm btn-success w-100">BAYAR</button>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">
                            ✔️ Tidak ada hutang usaha yang aktif.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
    document.getElementById('access-info').innerHTML = 
        'Akses: <?= $role_login; ?> (<?= $nama_karyawan; ?>, ID <?= $id_karyawan; ?>)';
</script>

<input type="hidden" id="session-role" value="<?= $role_login; ?>">
<input type="hidden" id="session-nama" value="<?= $nama_karyawan; ?>">
<input type="hidden" id="session-id" value="<?= $id_karyawan; ?>">

<?php include '_footer.php'; ?>
