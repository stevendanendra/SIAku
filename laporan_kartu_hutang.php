<?php
// laporan_kartu_hutang.php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.html");
    exit();
}

$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);

// KONSTANTA
$AKUN_UTANG_USAHA = 2101; // pastikan ini benar sesuai COA-mu

// Ambil daftar supplier
$supplier_q = $conn->query("SELECT id_supplier, nama_supplier FROM ms_supplier ORDER BY nama_supplier ASC");

// Supplier terpilih (boleh id atau empty)
$id_supplier = isset($_GET['supplier']) && $_GET['supplier'] !== '' ? $conn->real_escape_string($_GET['supplier']) : '';

// Jika supplier dipilih: ambil semua pengeluaran (hutang) untuk supplier tersebut
$pengeluarans = [];
if (!empty($id_supplier)) {
    $sql = "
        SELECT id_pengeluaran, tgl_transaksi, deskripsi, jumlah, tgl_jatuh_tempo
        FROM tr_pengeluaran
        WHERE id_supplier = '{$id_supplier}'
          AND id_akun_kas = {$AKUN_UTANG_USAHA}
        ORDER BY tgl_transaksi ASC, id_pengeluaran ASC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $pengeluarans[] = $r;
        }
    }
}

// Fungsi bantu untuk ambil pembayaran (jurnal) terkait sebuah pengeluaran (Ref E-<id_pengeluaran>)
function getPaymentsForPengeluaran($conn, $pengeluaran_id, $akun_utang) {
    $id = (int)$pengeluaran_id;
    $sql = "
        SELECT id_jurnal, tgl_jurnal, no_bukti, deskripsi, nilai
        FROM tr_jurnal_umum
        WHERE id_akun = {$akun_utang}
          AND posisi = 'D'
          AND deskripsi LIKE '%Ref E-{$id}%'
        ORDER BY tgl_jurnal ASC, id_jurnal ASC
    ";
    $rows = [];
    $q = $conn->query($sql);
    if ($q) {
        while ($r = $q->fetch_assoc()) $rows[] = $r;
    }
    return $rows;
}

// Jika supplier dipilih -> bangun ledger (array entri) dari pengeluaran + pembayaran
$ledger = [];
$nama_supplier = '';
if (!empty($id_supplier)) {
    // nama supplier
    $srow = $conn->query("SELECT nama_supplier FROM ms_supplier WHERE id_supplier = '{$id_supplier}' LIMIT 1");
    if ($srow && $srow->num_rows) $nama_supplier = $srow->fetch_assoc()['nama_supplier'];

    foreach ($pengeluarans as $p) {
        // 1) tambahkan entry pengeluaran sebagai KREDIT (menambah utang)
        $ledger[] = [
            'tanggal' => $p['tgl_transaksi'],
            'no_bukti' => 'E-' . $p['id_pengeluaran'],
            'keterangan' => $p['deskripsi'],
            'debit' => 0,
            'kredit' => (int)$p['jumlah'],
            'source' => 'pengeluaran',
            'ref_id' => $p['id_pengeluaran']
        ];

        // 2) ambil semua pembayaran yang mereferensikan Ref E-<id_pengeluaran>
        $payments = getPaymentsForPengeluaran($conn, $p['id_pengeluaran'], $AKUN_UTANG_USAHA);
        foreach ($payments as $pay) {
            $ledger[] = [
                'tanggal' => $pay['tgl_jurnal'],
                'no_bukti' => $pay['no_bukti'],
                'keterangan' => $pay['deskripsi'],
                'debit' => (int)$pay['nilai'],
                'kredit' => 0,
                'source' => 'pembayaran',
                'ref_id' => $p['id_pengeluaran']
            ];
        }
    }

    // Sort ledger by tanggal asc, and ensure payments on same day keep insertion order
    usort($ledger, function($a, $b){
        if ($a['tanggal'] === $b['tanggal']) return 0;
        return strtotime($a['tanggal']) < strtotime($b['tanggal']) ? -1 : 1;
    });
}

// DEFAULT VIEW (jika supplier tidak dipilih) -> tampilkan daftar hutang belum lunas semua supplier, urut by jatuh tempo
$default_outstanding = [];
if (empty($id_supplier)) {
    // Kita gunakan logic yang sama seperti daftar_utang.php: ambil pengeluaran dimana total > sudah_dibayar
    $sql_all = "
        SELECT 
            e.id_pengeluaran,
            e.tgl_transaksi,
            e.deskripsi,
            e.jumlah AS total_utang,
            e.tgl_jatuh_tempo,
            s.id_supplier,
            s.nama_supplier,
            (
                SELECT COALESCE(SUM(j.nilai),0)
                FROM tr_jurnal_umum j
                WHERE j.id_akun = {$AKUN_UTANG_USAHA}
                  AND j.posisi = 'D'
                  AND j.deskripsi LIKE CONCAT('%Ref E-', e.id_pengeluaran, '%')
            ) AS sudah_dibayar
        FROM tr_pengeluaran e
        LEFT JOIN ms_supplier s ON e.id_supplier = s.id_supplier
        WHERE e.id_akun_kas = {$AKUN_UTANG_USAHA}
        HAVING total_utang > sudah_dibayar
        ORDER BY e.tgl_jatuh_tempo ASC, e.tgl_transaksi ASC
    ";
    $qall = $conn->query($sql_all);
    if ($qall) {
        while ($r = $qall->fetch_assoc()) {
            $r['sisa'] = (int)$r['total_utang'] - (int)$r['sudah_dibayar'];
            $default_outstanding[] = $r;
        }
    }
}

include '_header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container mt-5">
    <h1 class="mb-3">Laporan Kartu Hutang Supplier</h1>
    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>

    <!-- filter -->
    <form method="GET" class="row g-3 align-items-end mb-4">
        <div class="col-md-6">
            <label class="form-label">Pilih Supplier</label>
            <select name="supplier" class="form-select select2" id="supplierSelect">
                <option value="">-- Semua Supplier (Default) --</option>
                <?php
                $supplier_q->data_seek(0);
                while ($s = $supplier_q->fetch_assoc()):
                ?>
                    <option value="<?= htmlspecialchars($s['id_supplier']); ?>"
                        <?= ($s['id_supplier'] == $id_supplier) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nama_supplier']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100">Tampilkan</button>
        </div>
    </form>

    <?php if (empty($id_supplier)): ?>
        <!-- DEFAULT: daftar hutang belum lunas semua supplier (urut jatuh tempo terdekat) -->
        <h4>Daftar Hutang Belum Lunas (urut: jatuh tempo terdekat)</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Supplier</th>
                        <th>Deskripsi</th>
                        <th class="text-end">Total Utang</th>
                        <th class="text-end">Sudah Dibayar</th>
                        <th class="text-end">Sisa</th>
                        <th class="text-end">Jatuh Tempo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($default_outstanding) > 0): ?>
                        <?php foreach ($default_outstanding as $row): ?>
                            <tr>
                                <td><?= 'E-' . $row['id_pengeluaran']; ?></td>
                                <td><?= htmlspecialchars($row['nama_supplier'] ?: '-'); ?></td>
                                <td><?= htmlspecialchars($row['deskripsi']); ?></td>
                                <td class="text-end">Rp <?= number_format($row['total_utang'],0,',','.'); ?></td>
                                <td class="text-end">Rp <?= number_format($row['sudah_dibayar'],0,',','.'); ?></td>
                                <td class="text-end fw-bold text-danger">Rp <?= number_format($row['sisa'],0,',','.'); ?></td>
                                <td class="text-end"><?= $row['tgl_jatuh_tempo']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Tidak ada hutang belum lunas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <!-- KARTU HUTANG PER SUPPLIER -->
        <h4>Kartu Hutang — Supplier: <?= htmlspecialchars($nama_supplier); ?></h4>

        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-dark">
                    <tr>
                        <th style="width:12%;">Tanggal</th>
                        <th style="width:12%;">No. Bukti</th>
                        <th>Keterangan</th>
                        <th class="text-end" style="width:12%;">Debit</th>
                        <th class="text-end" style="width:12%;">Kredit</th>
                        <th class="text-end" style="width:12%;">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($ledger) === 0) {
                        echo '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada transaksi untuk supplier ini.</td></tr>';
                    } else {
                        $running = 0;
                        foreach ($ledger as $entry) {
                            $running += ($entry['kredit'] - $entry['debit']);
                            $debit = $entry['debit'] ? number_format($entry['debit'],0,',','.') : '';
                            $kredit = $entry['kredit'] ? number_format($entry['kredit'],0,',','.') : '';
                            $saldo_display = number_format($running,0,',','.');
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['tanggal']); ?></td>
                            <td><?= htmlspecialchars($entry['no_bukti']); ?></td>
                            <td><?= htmlspecialchars($entry['keterangan']); ?></td>
                            <td class="text-end text-primary"><?= $debit; ?></td>
                            <td class="text-end text-danger"><?= $kredit; ?></td>
                            <td class="text-end bg-light fw-bold">Rp <?= $saldo_display; ?> (K)</td>
                        </tr>
                    <?php
                        } // endforeach
                    } // endif ledger
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- scripts for select2 (searchable dropdown) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script>
$(function(){
    $('.select2').select2({
        width: '100%',
        placeholder: '-- Pilih Supplier --',
        allowClear: true
    });
});
</script>

<?php include '_footer.php'; ?>
