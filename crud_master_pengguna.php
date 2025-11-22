<?php
// crud_master_pengguna.php
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
if (isset($_SESSION['success_message'])) {
    $success_message .= (empty($success_message) ? '' : '<br>') . $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
// Tambahkan pengecekan pesan sukses dari edit
if (isset($_SESSION['success_message_edit'])) {
    $success_message .= (empty($success_message) ? '' : '<br>') . $_SESSION['success_message_edit'];
    unset($_SESSION['success_message_edit']);
}

// --- LOGIKA TAMBAH PENGGUNA BARU (CREATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $role = $conn->real_escape_string($_POST['role']);
    $is_menikah = isset($_POST['is_menikah']) ? 1 : 0;
    $jumlah_anak = $conn->real_escape_string($_POST['jumlah_anak']);
    $is_aktif = isset($_POST['is_aktif']) ? 1 : 0;
    
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error_message = "Field utama wajib diisi.";
    } else {
        $sql = "INSERT INTO ms_pengguna (username, email, password, nama_lengkap, role, is_menikah, jumlah_anak, is_aktif) 
                VALUES ('$username', '$email', '$password', '$nama_lengkap', '$role', '$is_menikah', '$jumlah_anak', '$is_aktif')";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Pengguna '$username' berhasil ditambahkan.";
        } else {
            $error_message = "Error: Username atau Email sudah terdaftar. " . $conn->error;
        }
    }
}

// --- LOGIKA TAMPIL DATA (READ) ---
$pengguna_query = $conn->query("SELECT * FROM ms_pengguna ORDER BY id_pengguna ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
    <div class="d-flex gap-3 mb-4 p-2 bg-light rounded shadow-sm">
        <a href="crud_master_akun.php" class="btn btn-outline-primary btn-sm">📊 Master Akun (COA)</a>
        <a href="crud_master_layanan.php" class="btn btn-outline-primary btn-sm">🧼 Master Layanan Jasa</a>
        <a href="crud_master_pengguna.php" class="btn btn-primary btn-sm active">👤 Master Pengguna & Karyawan</a>
    </div>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <h2>Kelola Pengguna & Karyawan</h2>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-3">
                <h3 class="card-title">Tambah Pengguna Baru</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username:</label> 
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label> 
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password:</label> 
                        <input type="text" class="form-control" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama_lengkap" class="form-label">Nama Lengkap:</label> 
                        <input type="text" class="form-control" name="nama_lengkap" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role:</label>
                        <select class="form-select" name="role" required>
                            <option value="Owner">Owner</option>
                            <option value="Karyawan">Karyawan (Kasir)</option>
                            <option value="Cleaner">Cleaner (Penerima Gaji)</option>
                        </select>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_menikah" name="is_menikah" value="1">
                        <label class="form-check-label" for="is_menikah">Status Menikah</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jumlah_anak" class="form-label">Jumlah Anak (Maks 2 yang Ditunjang):</label> 
                        <input type="number" class="form-control" name="jumlah_anak" min="0" value="0">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_aktif" name="is_aktif" value="1" checked>
                        <label class="form-check-label" for="is_aktif">Status Aktif (Login/Payroll)</label>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">Simpan Pengguna</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <h3 class="mb-3">Daftar Pengguna Aktif</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Menikah</th>
                            <th>Anak</th>
                            <th>Aktif</th> 
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $pengguna_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id_pengguna']; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo $row['role']; ?></td>
                            <td><?php echo $row['is_menikah'] ? 'Ya' : 'Tidak'; ?></td>
                            <td><?php echo $row['jumlah_anak']; ?></td>
                            <td><?php echo $row['is_aktif'] ? '<span class="badge bg-success">AKTIF</span>' : '<span class="badge bg-danger">NON-AKTIF</span>'; ?></td> 
                            <td>
                                <a href="crud_master_pengguna_edit.php?id=<?php echo $row['id_pengguna']; ?>" class="btn btn-sm btn-info text-white">Ubah / Status</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('access-info').innerHTML = 'Akses: **<?php echo $role_login; ?>** (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
    </script>

<?php include '_footer.php'; // Footer Bootstrap ?>