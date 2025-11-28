<?php
// laporan_buku_besar.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

// --- INISIALISASI DATA LOGIN ---
$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// ===============================
// 1. HIDE akun 5101, 5102, 5103 (Payroll Legacy)
// ===============================
$AKUN_HIDE = "5101,5102,5103";

// ===============================
// 2. FILTER & LOGIKA POSTING BB
// ===============================
$filter_akun_id = isset($_GET['akun']) ? $_GET['akun'] : '';
$current_saldo_awal = 0;
$current_saldo_normal = '';
$current_account_name = 'Semua Akun';

// Jika user mengakses akun lama 5101–5103 → tolak
if (in_array($filter_akun_id, ['5101','5102','5103'])) {
    $error_message = "Akun 5101–5103 sudah digabung ke 5100 dan tidak dapat ditampilkan.";
    $filter_akun_id = '';
}

// ===============================
// 3. Proses Posting BB
// ===============================
if (isset($_POST['action']) && $_POST['action'] == 'process_bb') {

    $sql_update_bb = "
        UPDATE ms_akun m
        SET m.saldo_saat_ini = m.saldo_awal + 
        COALESCE(
            (SELECT 
                SUM(CASE WHEN j.posisi = m.saldo_normal THEN j.nilai ELSE 0 END) - 
                SUM(CASE WHEN j.posisi != m.saldo_normal THEN j.nilai ELSE 0 END)
            FROM tr_jurnal_umum j
            WHERE j.id_akun = m.id_akun),
            0 
        );
    ";
    
    if ($conn->query($sql_update_bb) === TRUE) {
        $_SESSION['success_message'] = "✅ Proses Buku Besar berhasil! Semua Saldo Saat Ini telah diperbarui.";
        header("Location: laporan_buku_besar.php");
        exit();
    } else {
        $error_message = "❌ Error saat memproses Buku Besar: " . $conn->error;
    }
}

// ===============================
// 4. LOAD MASTER AKUN (5101–5103 DISKIP)
// ===============================
$master_akun_query = $conn->query("
    SELECT id_akun, nama_akun, saldo_awal, saldo_normal, saldo_saat_ini
    FROM ms_akun
    WHERE id_akun NOT IN ($AKUN_HIDE)
    ORDER BY id_akun ASC
");

// ===============================
// 5. TAMPILKAN MUTASI DETAIL (Jika filter dipilih)
// ===============================
$mutasi_query = null;

if (!empty($filter_akun_id)) {

    $detail_akun_q = $conn->query("
        SELECT id_akun, nama_akun, saldo_awal, saldo_normal 
        FROM ms_akun 
        WHERE id_akun = '$filter_akun_id'
        AND id_akun NOT IN ($AKUN_HIDE)
    ");

    if ($detail_akun_q->num_rows > 0) {
        $detail_akun_data = $detail_akun_q->fetch_assoc();
        $current_saldo_awal = $detail_akun_data['saldo_awal'];
        $current_saldo_normal = $detail_akun_data['saldo_normal'];
        $current_account_name = $detail_akun_data['id_akun'] . ' - ' . $detail_akun_data['nama_akun'];
    }

    // Mutasi jurnal dari akun tsb
    $mutasi_query = $conn->query("
        SELECT 
            tgl_jurnal, 
            no_bukti, 
            deskripsi, 
            posisi, 
            nilai 
        FROM tr_jurnal_umum 
        WHERE id_akun = '$filter_akun_id'
        ORDER BY tgl_jurnal ASC, id_jurnal ASC
    ");
}

?>

<?php include '_header.php'; ?>

<div class="container mt-5">
    
    <h1>5. Modul Buku Besar & Update Saldo</h1>
    
    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <!-- PROSES POSTING BB -->
    <div class="card p-3 shadow-sm mb-4 bg-light">
        <h2 class="card-title h5">1. Proses Posting Buku Besar</h2>
        <form method="POST" class="d-flex align-items-center">
            <input type="hidden" name="action" value="process_bb">
            <p class="mb-0 me-3">Tekan tombol ini untuk memperbarui kolom <b>Saldo Saat Ini</b>.</p>
            <button type="submit" class="btn btn-success btn-sm" 
                onclick="return confirm('Yakin ingin memproses Buku Besar?');">
                Proses Buku Besar Sekarang
            </button>
        </form>
    </div>

    <!-- FILTER AKUN -->
    <h2>2. Filter Akun Buku Besar</h2>
    <form method="GET" class="row g-3 align-items-center mb-4">
        <div class="col-auto">
            <label for="filter_akun" class="col-form-label">Pilih Akun:</label>
        </div>
        <div class="col-md-5">
            <select name="akun" id="filter_akun" class="form-select" required>
                <option value="">-- Pilih Akun --</option>
                <?php 
                $master_akun_query->data_seek(0);
                while ($row = $master_akun_query->fetch_assoc()): ?>
                    <option value="<?php echo $row['id_akun']; ?>" 
                        <?php echo ($row['id_akun'] == $filter_akun_id) ? 'selected' : ''; ?>>
                        <?php echo $row['id_akun'] . ' - ' . htmlspecialchars($row['nama_akun']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Tampilkan Buku Besar</button>
        </div>

        <?php if (!empty($filter_akun_id)): ?>
            <div class="col-auto">
                <a href="laporan_buku_besar.php" class="btn btn-outline-danger">Reset</a>
            </div>
        <?php endif; ?>
    </form>
    
    <hr>

    <?php if (!empty($filter_akun_id) && $mutasi_query): ?>

        <!-- DETAIL MUTASI AKUN -->
        <h2 class="mb-3">Buku Besar Akun: <?php echo htmlspecialchars($current_account_name); ?></h2>

        <div class="alert alert-info">
            Saldo Normal: <b><?php echo $current_saldo_normal === 'D' ? 'Debit' : 'Kredit'; ?></b> |
            Saldo Awal: <b>Rp <?php echo number_format($current_saldo_awal, 0, ',', '.'); ?></b>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Tanggal</th>
                        <th>No. Bukti</th>
                        <th>Keterangan</th>
                        <th class="text-end">Debit (D)</th>
                        <th class="text-end">Kredit (K)</th>
                        <th class="text-end bg-light fw-bold">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $saldo_akhir = $current_saldo_awal;

                    while ($row = $mutasi_query->fetch_assoc()):
                        $nilai = $row['nilai'];
                        $posisi = $row['posisi'];

                        if ($posisi === $current_saldo_normal) {
                            $saldo_akhir += $nilai;
                        } else {
                            $saldo_akhir -= $nilai;
                        }

                        $saldo_tampil = abs($saldo_akhir);
                        $saldo_posisi = ($saldo_akhir >= 0) ? $current_saldo_normal : 
                            ($current_saldo_normal === 'D' ? 'K' : 'D');
                    ?>
                    <tr>
                        <td><?php echo $row['tgl_jurnal']; ?></td>
                        <td><?php echo htmlspecialchars($row['no_bukti']); ?></td>
                        <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                        <td class="text-end text-primary"><?php echo $posisi === 'D' ? number_format($nilai) : ''; ?></td>
                        <td class="text-end text-danger"><?php echo $posisi === 'K' ? number_format($nilai) : ''; ?></td>
                        <td class="text-end bg-light fw-bold">
                            Rp <?php echo number_format($saldo_tampil, 0, ',', '.'); ?> (<?php echo $saldo_posisi; ?>)
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <!-- SUMMARY SALDO (5101–5103 sudah disembunyikan) -->
        <h2 class="mb-3">3. Ringkasan Saldo Akhir</h2>
        <p class="text-muted">Tekan tombol posting BB untuk update saldo, lalu pilih akun untuk lihat detail.</p>

        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>No. Akun</th>
                        <th>Nama Akun</th>
                        <th>S. Normal</th>
                        <th class="text-end">Saldo Awal</th>
                        <th class="text-end">Mutasi Debit</th>
                        <th class="text-end">Mutasi Kredit</th>
                        <th class="text-end bg-light fw-bold">Saldo Saat Ini</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $master_akun_query->data_seek(0);
                    while ($row = $master_akun_query->fetch_assoc()):
                        
                        $sql_mutasi = "
                            SELECT 
                                COALESCE(SUM(CASE WHEN posisi='D' THEN nilai ELSE 0 END), 0) AS total_debit,
                                COALESCE(SUM(CASE WHEN posisi='K' THEN nilai ELSE 0 END), 0) AS total_kredit
                            FROM tr_jurnal_umum
                            WHERE id_akun = {$row['id_akun']}
                        ";
                        $mutasi_result = $conn->query($sql_mutasi)->fetch_assoc();

                        $saldo_tampil = abs($row['saldo_saat_ini']);
                        $saldo_posisi = ($row['saldo_saat_ini'] >= 0)
                            ? $row['saldo_normal']
                            : ($row['saldo_normal'] == 'D' ? 'K' : 'D');
                    ?>
                    <tr>
                        <td><?php echo $row['id_akun']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_akun']); ?></td>
                        <td><?php echo $row['saldo_normal']; ?></td>
                        <td class="text-end">Rp <?php echo number_format($row['saldo_awal']); ?></td>
                        <td class="text-end text-primary">Rp <?php echo number_format($mutasi_result['total_debit']); ?></td>
                        <td class="text-end text-danger">Rp <?php echo number_format($mutasi_result['total_kredit']); ?></td>
                        <td class="text-end bg-light fw-bold">
                            Rp <?php echo number_format($saldo_tampil); ?> (<?php echo $saldo_posisi; ?>)
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<script>
    document.getElementById('access-info').innerHTML =
        'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; ?>
