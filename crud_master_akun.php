<?php
// crud_master_akun.php
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

// --- LOGIKA PESAN DARI SESI (DELETE/EDIT) ---
if (isset($_SESSION['error_delete'])) {
    $error_message .= (empty($error_message) ? '' : '<br>') . $_SESSION['error_delete'];
    unset($_SESSION['error_delete']);
}
if (isset($_SESSION['success_delete'])) {
    $success_message .= (empty($success_message) ? '' : '<br>') . $_SESSION['success_delete'];
    unset($_SESSION['success_delete']);
}
if (isset($_SESSION['success_message_edit'])) { // Tambahkan pengecekan pesan dari edit
    $success_message .= (empty($success_message) ? '' : '<br>') . $_SESSION['success_message_edit'];
    unset($_SESSION['success_message_edit']);
}

// --- LOGIKA TAMBAH AKUN BARU (CREATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $id_akun = $conn->real_escape_string($_POST['id_akun']);
    $nama_akun = $conn->real_escape_string($_POST['nama_akun']);
    $tipe_akun = $conn->real_escape_string($_POST['tipe_akun']);
    $saldo_normal = $conn->real_escape_string($_POST['saldo_normal']);
    $saldo_awal = $conn->real_escape_string($_POST['saldo_awal']);
    
    if (empty($id_akun) || empty($nama_akun) || empty($tipe_akun) || empty($saldo_normal)) {
        $error_message = "Semua field wajib diisi.";
    } elseif (!is_numeric($saldo_awal) || floor($saldo_awal) != $saldo_awal) {
        $error_message = "Saldo Awal harus bilangan bulat (BIGINT).";
    } else {
        $sql = "INSERT INTO ms_akun (id_akun, nama_akun, tipe_akun, saldo_normal, saldo_awal, saldo_saat_ini) 
                VALUES ('$id_akun', '$nama_akun', '$tipe_akun', '$saldo_normal', '$saldo_awal', '$saldo_awal')";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Akun '$nama_akun' berhasil ditambahkan.";
        } else {
            $error_message = "Error: Nomor akun sudah ada atau input salah. " . $conn->error;
        }
    }
}

// --- LOGIKA TAMPIL DATA (READ) ---
$akun_query = $conn->query("SELECT * FROM ms_akun ORDER BY id_akun ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>

    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
    <div class="d-flex gap-3 mb-4 p-2 bg-light rounded shadow-sm">
        <a href="crud_master_akun.php" class="btn btn-primary btn-sm active">📊 Master Akun (COA)</a>
        <a href="crud_master_layanan.php" class="btn btn-outline-primary btn-sm">🧼 Master Layanan Jasa</a>
        <a href="crud_master_pengguna.php" class="btn btn-outline-primary btn-sm">👤 Master Pengguna & Karyawan</a>
    </div>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <h2>Kelola Akun (COA)</h2>

    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-3">
                <h3 class="card-title">Tambah Akun Baru</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="id_akun" class="form-label">No. Akun:</label>
                        <input type="number" class="form-control" name="id_akun" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama_akun" class="form-label">Nama Akun:</label>
                        <input type="text" class="form-control" name="nama_akun" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipe_akun" class="form-label">Tipe Akun:</label>
                        <select class="form-select" name="tipe_akun" required>
                            <option value="">Pilih</option>
                            <option value="Aktiva">Aktiva (Aset)</option>
                            <option value="Kewajiban">Kewajiban (Liabilitas)</option>
                            <option value="Modal">Modal (Ekuitas)</option>
                            <option value="Pendapatan">Pendapatan</option>
                            <option value="Beban">Beban</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="saldo_normal" class="form-label">Saldo Normal:</label>
                        <select class="form-select" name="saldo_normal" required>
                            <option value="">Pilih</option>
                            <option value="D">Debit (D)</option>
                            <option value="K">Kredit (K)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="saldo_awal" class="form-label">Saldo Awal (Rp):</label>
                        <input type="number" class="form-control" name="saldo_awal" value="0" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">Simpan Akun</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <h3 class="mb-3">Daftar Akun Aktif</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>No. Akun</th>
                            <th>Nama Akun</th>
                            <th>Tipe</th>
                            <th>S. Normal</th>
                            <th>Saldo Awal</th>
                            <th>Saldo Saat Ini</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $akun_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id_akun']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_akun']); ?></td>
                            <td><?php echo $row['tipe_akun']; ?></td>
                            <td><?php echo $row['saldo_normal']; ?></td>
                            <td class="text-end">Rp <?php echo number_format($row['saldo_awal'], 0, ',', '.'); ?></td>
                            <td class="text-end text-primary fw-bold">Rp <?php echo number_format($row['saldo_saat_ini'], 0, ',', '.'); ?></td>
                            <td>
                                <a href="crud_master_akun_edit.php?id=<?php echo $row['id_akun']; ?>" class="btn btn-sm btn-info text-white me-1">Ubah</a>
                                <a href="crud_master_akun_delete.php?id=<?php echo $row['id_akun']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin hapus akun? Data transaksi terkait mungkin terganggu!');">Hapus</a>
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