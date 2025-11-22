<?php
// crud_master_layanan.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

// --- INISIALISASI DATA LOGIN ---
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
// --------------------------------------------------

$error_message = '';
$success_message = '';
// Ambil pesan dari sesi setelah proses delete/edit
if (isset($_SESSION['error_delete'])) {
    $error_message .= (empty($error_message) ? '' : '<br>') . $_SESSION['error_delete'];
    unset($_SESSION['error_delete']);
}
if (isset($_SESSION['success_message'])) {
    $success_message .= (empty($success_message) ? '' : '<br>') . $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$akun_pendapatan_query = $conn->query("SELECT id_akun, nama_akun FROM ms_akun WHERE tipe_akun = 'Pendapatan' ORDER BY id_akun ASC");

// --- LOGIKA TAMBAH LAYANAN BARU (CREATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $tipe_layanan = $conn->real_escape_string($_POST['tipe_layanan']);
    $nama_layanan = $conn->real_escape_string($_POST['nama_layanan']);
    $luas_min = $conn->real_escape_string($_POST['luas_min']);
    $luas_max = $conn->real_escape_string($_POST['luas_max']);
    $harga_jual = $conn->real_escape_string($_POST['harga_jual']);
    $id_akun_pendapatan = $conn->real_escape_string($_POST['id_akun_pendapatan']);
    $is_aktif = isset($_POST['is_aktif']) ? 1 : 0; 
    
    if (empty($tipe_layanan) || empty($nama_layanan) || empty($harga_jual)) {
        $error_message = "Field utama wajib diisi.";
    } elseif (!is_numeric($harga_jual) || floor($harga_jual) != $harga_jual) {
        $error_message = "Harga Jual harus bilangan bulat (BIGINT).";
    } else {
        $sql = "INSERT INTO ms_layanan (tipe_layanan, nama_layanan, luas_min, luas_max, harga_jual, id_akun_pendapatan, is_aktif) 
                VALUES ('$tipe_layanan', '$nama_layanan', '$luas_min', '$luas_max', '$harga_jual', '$id_akun_pendapatan', '$is_aktif')";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Layanan '$nama_layanan' berhasil ditambahkan.";
        } else {
            $error_message = "Error: Gagal menambahkan layanan. " . $conn->error;
        }
    }
}

// --- LOGIKA TAMPIL DATA (READ) ---
$layanan_query = $conn->query("SELECT l.*, a.nama_akun FROM ms_layanan l JOIN ms_akun a ON l.id_akun_pendapatan = a.id_akun ORDER BY l.id_layanan ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>

    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
    <div class="d-flex gap-3 mb-4 p-2 bg-light rounded shadow-sm">
        <a href="crud_master_akun.php" class="btn btn-outline-primary btn-sm">📊 Master Akun (COA)</a>
        <a href="crud_master_layanan.php" class="btn btn-primary btn-sm active">🧼 Master Layanan Jasa</a>
        <a href="crud_master_pengguna.php" class="btn btn-outline-primary btn-sm">👤 Master Pengguna & Karyawan</a>
    </div>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <h2>Kelola Layanan Jasa</h2>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-3">
                <h3 class="card-title">Tambah Layanan Baru</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="tipe_layanan" class="form-label">Tipe Layanan:</label>
                        <select class="form-select" name="tipe_layanan" required>
                            <option value="Rumah">Rumah</option>
                            <option value="Ruangan">Ruangan</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama_layanan" class="form-label">Nama Layanan:</label>
                        <input type="text" class="form-control" name="nama_layanan" required>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <label for="luas_min" class="form-label">Luas Min (m²):</label>
                            <input type="number" class="form-control" name="luas_min" min="0">
                        </div>
                        <div class="col">
                            <label for="luas_max" class="form-label">Luas Max (m²):</label>
                            <input type="number" class="form-control" name="luas_max" min="0">
                        </div>
                    </div>
                    <br>
                    
                    <div class="mb-3">
                        <label for="harga_jual" class="form-label">Harga Jual (Rp):</label>
                        <input type="number" class="form-control" name="harga_jual" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_akun_pendapatan" class="form-label">Akun Pendapatan:</label>
                        <select class="form-select" name="id_akun_pendapatan" required>
                            <?php 
                            $akun_pendapatan_query->data_seek(0);
                            while ($row = $akun_pendapatan_query->fetch_assoc()): ?>
                                <option value="<?php echo $row['id_akun']; ?>"><?php echo $row['id_akun'] . ' - ' . htmlspecialchars($row['nama_akun']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_aktif" name="is_aktif" value="1" checked>
                        <label class="form-check-label" for="is_aktif">Status Aktif (Centang untuk Aktif)</label>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">Simpan Layanan</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <h3 class="mb-3">Daftar Layanan</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nama Layanan</th>
                            <th>Tipe</th>
                            <th>Luas (Min-Max)</th>
                            <th>Harga Jual</th>
                            <th>Aktif</th>
                            <th>Akun Pendapatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $layanan_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id_layanan']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_layanan']); ?></td>
                            <td><?php echo $row['tipe_layanan']; ?></td>
                            <td><?php echo $row['luas_min'] . ' - ' . $row['luas_max']; ?></td>
                            <td class="text-end">Rp <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?></td>
                            <td><?php echo $row['is_aktif'] ? 'AKTIF' : 'NON-AKTIF'; ?></td>
                            <td><?php echo $row['id_akun_pendapatan'] . ' - ' . htmlspecialchars($row['nama_akun']); ?></td>
                            <td>
                                <a href="crud_master_layanan_edit.php?id=<?php echo $row['id_layanan']; ?>" class="btn btn-sm btn-info text-white">Ubah / Status</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
    </script>

<?php include '_footer.php'; // Footer Bootstrap ?>