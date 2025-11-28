<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.html");
    exit();
}

$AKUN_PIUTANG = 1102;

// ==============================
// AMBIL DAFTAR PELANGGAN
// ==============================
$pelanggan_q = $conn->query("
    SELECT id_pelanggan, nama_pelanggan
    FROM ms_pelanggan
    ORDER BY nama_pelanggan ASC
");

$id_pelanggan = isset($_GET['pelanggan']) && $_GET['pelanggan'] !== ''
    ? $conn->real_escape_string($_GET['pelanggan'])
    : '';

$ledger = [];
$nama_pelanggan = "";

// ==========================================================
// MODE : PELANGGAN DIPILIH → TAMPILKAN KARTU PIUTANG DETAIL
// ==========================================================
if (!empty($id_pelanggan)) {

    // Nama pelanggan
    $p = $conn->query("SELECT nama_pelanggan FROM ms_pelanggan WHERE id_pelanggan='$id_pelanggan' LIMIT 1");
    if ($p && $p->num_rows) {
        $nama_pelanggan = $p->fetch_assoc()['nama_pelanggan'];
    }

    // AMBIL SEMUA MUTASI PIUTANG LANGSUNG DARI JURNAL
    $sql = "
        SELECT 
            j.tgl_jurnal,
            j.no_bukti,
            j.deskripsi,
            j.posisi,
            j.nilai
        FROM tr_jurnal_umum j

        JOIN tr_penjualan p 
              ON j.no_bukti LIKE CONCAT('PJL-', p.id_penjualan, '%')
              OR  j.no_bukti LIKE CONCAT('RCV-', p.id_penjualan, '%')

        WHERE j.id_akun = {$AKUN_PIUTANG}
          AND p.id_pelanggan = '{$id_pelanggan}'
          AND p.metode_bayar = 'Cicilan'

        ORDER BY j.tgl_jurnal ASC, j.id_jurnal ASC
    ";

    $rows = $conn->query($sql);

    while ($r = $rows->fetch_assoc()) {

        // LABEL OTOMATIS
        if ($r['posisi'] === 'D') {
            $ket = "Penjualan Cicilan (Piutang Bertambah)";
        } else {
            $ket = "Pembayaran Cicilan (Piutang Berkurang)";
        }

        $ledger[] = [
            'tanggal'   => $r['tgl_jurnal'],
            'no_bukti'  => $r['no_bukti'],
            'keterangan'=> $ket,
            'debit'     => $r['posisi'] === 'D' ? (int)$r['nilai'] : 0,
            'kredit'    => $r['posisi'] === 'K' ? (int)$r['nilai'] : 0
        ];
    }
}

// ==========================================================
// MODE : DEFAULT (TAMPIL SEMUA PIUTANG BELUM LUNAS)
// ==========================================================
$default_piutang = [];

if (empty($id_pelanggan)) {

    $sql = "
        SELECT 
            p.id_penjualan,
            p.tgl_transaksi,
            p.id_pelanggan,
            c.nama_pelanggan,

            -- total piutang (debit)
            (
                SELECT COALESCE(SUM(j.nilai),0)
                FROM tr_jurnal_umum j
                WHERE j.id_akun = {$AKUN_PIUTANG}
                  AND j.posisi='D'
                  AND j.no_bukti LIKE CONCAT('PJL-', p.id_penjualan, '%')
            ) AS total_tagihan,

            -- total pembayaran (kredit)
            (
                SELECT COALESCE(SUM(j.nilai),0)
                FROM tr_jurnal_umum j
                WHERE j.id_akun = {$AKUN_PIUTANG}
                  AND j.posisi='K'
                  AND j.no_bukti LIKE CONCAT('RCV-', p.id_penjualan, '%')
            ) AS sudah_bayar

        FROM tr_penjualan p
        LEFT JOIN ms_pelanggan c ON p.id_pelanggan = c.id_pelanggan
        WHERE p.metode_bayar='Cicilan'

        HAVING total_tagihan > sudah_bayar
        ORDER BY p.tgl_transaksi ASC
    ";

    $q = $conn->query($sql);

    while ($r = $q->fetch_assoc()) {
        $r['sisa'] = $r['total_tagihan'] - $r['sudah_bayar'];
        $default_piutang[] = $r;
    }
}

include '_header.php';
?>

<div class="container mt-5">
    <h1 class="mb-3">Laporan Kartu Piutang Pelanggan</h1>
    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>

    <!-- FILTER -->
    <form class="row g-3 mb-4" method="GET">
        <div class="col-md-6">
            <label class="form-label">Pilih Pelanggan</label>
            <select name="pelanggan" class="form-select select2">
                <option value="">-- Semua Pelanggan --</option>
                <?php 
                $pelanggan_q->data_seek(0);
                while ($p = $pelanggan_q->fetch_assoc()):
                ?>
                    <option value="<?= $p['id_pelanggan']; ?>"
                        <?= ($p['id_pelanggan']==$id_pelanggan?'selected':''); ?>>
                        <?= htmlspecialchars($p['nama_pelanggan']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100">Tampilkan</button>
        </div>
    </form>

    <?php if (empty($id_pelanggan)): ?>

        <h4>Daftar Piutang Belum Lunas</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Pelanggan</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Sudah Dibayar</th>
                    <th class="text-end">Sisa</th>
                </tr>
                </thead>

                <tbody>
                <?php if (count($default_piutang)): ?>
                    <?php foreach ($default_piutang as $r): ?>
                        <tr>
                            <td><?= 'PJL-'.$r['id_penjualan']; ?></td>
                            <td><?= htmlspecialchars($r['nama_pelanggan']); ?></td>
                            <td class="text-end">Rp <?= number_format($r['total_tagihan'],0,',','.'); ?></td>
                            <td class="text-end">Rp <?= number_format($r['sudah_bayar'],0,',','.'); ?></td>
                            <td class="text-end fw-bold text-danger">Rp <?= number_format($r['sisa'],0,',','.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted">Tidak ada piutang aktif.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <h4>Kartu Piutang — Pelanggan: <?= htmlspecialchars($nama_pelanggan); ?></h4>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-dark">
                <tr>
                    <th>Tanggal</th>
                    <th>No. Bukti</th>
                    <th>Keterangan</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Kredit</th>
                    <th class="text-end">Saldo</th>
                </tr>
                </thead>

                <tbody>
                <?php 
                if (!count($ledger)): 
                    echo '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada transaksi.</td></tr>';
                else:
                    $running = 0;
                    foreach ($ledger as $e):
                        $running += ($e['debit'] - $e['kredit']);
                ?>
                    <tr>
                        <td><?= $e['tanggal']; ?></td>
                        <td><?= $e['no_bukti']; ?></td>
                        <td><?= htmlspecialchars($e['keterangan']); ?></td>
                        <td class="text-end text-primary"><?= $e['debit'] ? number_format($e['debit'],0,',','.') : ''; ?></td>
                        <td class="text-end text-danger"><?= $e['kredit'] ? number_format($e['kredit'],0,',','.') : ''; ?></td>
                        <td class="text-end bg-warning fw-bold">
                            Rp <?= number_format($running,0,',','.'); ?> (D)
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    $('.select2').select2({ width:'100%' });
});
</script>

<?php include '_footer.php'; ?>
