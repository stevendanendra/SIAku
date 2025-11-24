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

// --- FILTER & LOGIKA POSTING BB ---
$filter_akun_id = isset($_GET['akun']) ? $_GET['akun'] : ''; // Filter akun yang dipilih
$current_saldo_awal = 0;
$current_saldo_normal = '';
$current_account_name = 'Semua Akun';

// Logika Proses Posting BB (Wajib Dijalankan Owner)
if (isset($_POST['action']) && $_POST['action'] == 'process_bb') {
    
    // FIX KRITIS: Query SQL untuk UPDATE ms_akun (Menggunakan Logika Explicit Net Mutasi)
    $sql_update_bb = "
        UPDATE ms_akun m
        SET m.saldo_saat_ini = m.saldo_awal + 
        COALESCE(
            (SELECT 
                -- Hitung total yang menambah saldo (posisi sama dengan saldo normal)
                SUM(CASE WHEN j.posisi = m.saldo_normal THEN j.nilai ELSE 0 END) - 
                -- Kurangi total yang mengurangi saldo (posisi berlawanan)
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

// --- LOGIKA TAMPIL DATA BB (MASTER) ---
$master_akun_query = $conn->query("SELECT id_akun, nama_akun, saldo_awal, saldo_normal, saldo_saat_ini FROM ms_akun ORDER BY id_akun ASC");

// --- LOGIKA TAMPIL DETAIL MUTASI (Jika ada filter akun) ---
$mutasi_query = null;
if (!empty($filter_akun_id)) {
    // 1. Ambil detail akun yang difilter
    $detail_akun_q = $conn->query("SELECT id_akun, nama_akun, saldo_awal, saldo_normal FROM ms_akun WHERE id_akun = '$filter_akun_id'");
    if ($detail_akun_q->num_rows > 0) {
        $detail_akun_data = $detail_akun_q->fetch_assoc();
        $current_saldo_awal = $detail_akun_data['saldo_awal'];
        $current_saldo_normal = $detail_akun_data['saldo_normal'];
        $current_account_name = $detail_akun_data['id_akun'] . ' - ' . $detail_akun_data['nama_akun'];
    }
    
    // 2. Ambil semua jurnal untuk akun tersebut
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

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1>5. Modul Buku Besar & Update Saldo</h1>
    
    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="card p-3 shadow-sm mb-4 bg-light">
        <h2 class="card-title h5">1. Proses Posting Buku Besar</h2>
        <form method="POST" class="d-flex align-items-center">
            <input type="hidden" name="action" value="process_bb">
            <p class="mb-0 me-3">Tekan tombol ini untuk memperbarui kolom **Saldo Saat Ini** di Master Akun.</p>
            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Yakin ingin memproses Buku Besar? Ini akan memperbarui semua Saldo Saat Ini.');">
                Proses Buku Besar Sekarang
            </button>
        </form>
    </div>
    
    
    <h2>2. Filter Akun Buku Besar</h2>
    <form method="GET" class="row g-3 align-items-center mb-4">
        <div class="col-auto">
            <label for="filter_akun" class="col-form-label">Pilih Akun:</label>
        </div>
        <div class="col-md-5">
            <select name="akun" id="filter_akun" class="form-select" required>
                <option value="">-- Pilih Akun --</option>
                <?php 
                $master_akun_query->data_seek(0); // Reset pointer
                while ($row = $master_akun_query->fetch_assoc()): ?>
                    <option value="<?php echo $row['id_akun']; ?>" <?php echo ($row['id_akun'] == $filter_akun_id) ? 'selected' : ''; ?>>
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
                <a href="laporan_buku_besar.php" class="btn btn-outline-danger">Tampilkan Semua Saldo</a>
            </div>
        <?php endif; ?>
    </form>
    
    <hr>
    
    <?php if (!empty($filter_akun_id) && $mutasi_query): // Tampilkan detail BB per akun ?>
        
        <h2 class="mb-3">Buku Besar Akun: <?php echo htmlspecialchars($current_account_name); ?></h2>
        <div class="alert alert-info">
            Saldo Normal: **<?php echo $current_saldo_normal === 'D' ? 'Debit' : 'Kredit'; ?>** | Saldo Awal: **Rp <?php echo number_format($current_saldo_awal, 0, ',', '.'); ?>**
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 10%;">Tanggal</th>
                        <th style="width: 15%;">No. Bukti</th>
                        <th style="width: 30%;">Keterangan</th>
                        <th class="text-end" style="width: 10%;">Debit (D)</th>
                        <th class="text-end" style="width: 10%;">Kredit (K)</th>
                        <th class="text-end bg-light text-dark fw-bold" style="width: 15%;">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $saldo_akhir = $current_saldo_awal; // Mulai dari Saldo Awal
                    
                    // Baris Saldo Awal (Opsional tapi membantu)
                    if ($current_saldo_awal != 0) {
                        echo '<tr>';
                        echo '<td></td>';
                        echo '<td></td>';
                        echo '<td>SALDO AWAL</td>';
                        echo '<td class="text-primary text-end"></td>';
                        echo '<td class="text-danger text-end"></td>';
                        echo '<td class="text-end bg-light fw-bold">Rp ' . number_format(abs($saldo_akhir), 0, ',', '.') . ' (' . $current_saldo_normal . ')</td>';
                        echo '</tr>';
                    }

                    while ($row = $mutasi_query->fetch_assoc()): 
                        $nilai = $row['nilai'];
                        $posisi = $row['posisi'];
                        
                        // Hitung Saldo Akhir
                        if ($posisi === $current_saldo_normal) {
                            $saldo_akhir += $nilai; // Tambah jika posisi sama dengan saldo normal
                        } else {
                            $saldo_akhir -= $nilai; // Kurang jika posisi berlawanan
                        }
                        
                        // FIX LOGIKA SALDO: Menampilkan Saldo Akhir Mutlak dan Posisi Saldo Normal
                        $saldo_tampil = abs($saldo_akhir);
                        $saldo_posisi = ($saldo_akhir >= 0) ? $current_saldo_normal : ($current_saldo_normal === 'D' ? 'K' : 'D');
                    ?>
                    <tr>
                        <td><?php echo $row['tgl_jurnal']; ?></td>
                        <td><?php echo htmlspecialchars($row['no_bukti']); ?></td>
                        <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                        <td class="text-end text-primary"><?php echo $posisi === 'D' ? number_format($nilai, 0, ',', '.') : ''; ?></td>
                        <td class="text-end text-danger"><?php echo $posisi === 'K' ? number_format($nilai, 0, ',', '.') : ''; ?></td>
                        <td class="text-end bg-light fw-bold">
                            Rp <?php echo number_format($saldo_tampil, 0, ',', '.'); ?> (<?php echo $saldo_posisi; ?>)
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php else: // Tampilkan daftar saldo akhir (BB summary) ?>
        
        <h2 class="mb-3">3. Ringkasan Saldo Akhir (Hasil Posting)</h2>
        <p class="text-muted">Klik tombol "Proses Buku Besar Sekarang" untuk memperbarui saldo, lalu pilih akun di filter atas untuk melihat detail mutasi.</p>
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
                        <th class="text-end bg-light text-dark fw-bold">Saldo Saat Ini</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $master_akun_query->data_seek(0); // Reset pointer
                    while ($row = $master_akun_query->fetch_assoc()): 
                    
                    // Query Mutasi Sederhana untuk Summary (Mencari mutasi dari tr_jurnal_umum)
                    $sql_mutasi = "SELECT COALESCE(SUM(CASE WHEN posisi='D' THEN nilai ELSE 0 END), 0) AS total_debit, 
                                         COALESCE(SUM(CASE WHEN posisi='K' THEN nilai ELSE 0 END), 0) AS total_kredit 
                                    FROM tr_jurnal_umum WHERE id_akun = {$row['id_akun']}";
                    $mutasi_result = $conn->query($sql_mutasi)->fetch_assoc();
                    
                    // FIX LOGIKA SALDO SAAT INI (Tampil Mutlak)
                    $saldo_saat_ini_mutlak = abs($row['saldo_saat_ini']);
                    $current_net = ($row['saldo_normal'] == 'D') ? ($row['saldo_saat_ini'] - $row['saldo_awal']) : ($row['saldo_awal'] - $row['saldo_saat_ini']); // Hanya untuk cek posisi

                    $final_saldo_posisi = ($row['saldo_saat_ini'] >= 0) ? $row['saldo_normal'] : ($row['saldo_normal'] == 'D' ? 'K' : 'D');
                    ?>
                    <tr>
                        <td><?php echo $row['id_akun']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_akun']); ?></td>
                        <td><?php echo $row['saldo_normal']; ?></td>
                        <td class="text-end">Rp <?php echo number_format($row['saldo_awal'], 0, ',', '.'); ?></td>
                        <td class="text-end text-primary">Rp <?php echo number_format($mutasi_result['total_debit'], 0, ',', '.'); ?></td>
                        <td class="text-end text-danger">Rp <?php echo number_format($mutasi_result['total_kredit'], 0, ',', '.'); ?></td>
                        <td class="text-end fw-bold bg-light">
                            Rp <?php echo number_format($saldo_saat_ini_mutlak, 0, ',', '.'); ?> (<?php echo $final_saldo_posisi; ?>)
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
    
</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>