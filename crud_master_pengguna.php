<?php
// crud_master_pengguna.php
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
        $sql = "INSERT INTO ms_pengguna (username, email, password, nama_lengkap, role, is_menikah, jumlah_anak, is_aktif, tgl_daftar) 
                VALUES ('$username', '$email', '$password', '$nama_lengkap', '$role', '$is_menikah', '$jumlah_anak', '$is_aktif', CURDATE())";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Pengguna '$username' berhasil ditambahkan.";
        } else {
            $error_message = "Error: Username atau Email sudah terdaftar. " . $conn->error;
        }
    }
}

// --- LOGIKA TAMPIL DATA (READ) ---
$pengguna_query = $conn->query("SELECT id_pengguna, username, email, password, nama_lengkap, role, is_menikah, jumlah_anak, is_aktif, tgl_daftar FROM ms_pengguna ORDER BY id_pengguna ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
    <?php include 'navbar_master.php'; ?>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <h2>Kelola Pengguna & Karyawan</h2>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-4 mb-4">
                <h3 class="card-title h5">Tambah Pengguna Baru</h3>
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
                            <option value="Admin">Admin</option> 
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
            <h3 class="mb-3 h5">Daftar Pengguna Aktif</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle" style="min-width: 1000px;">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 10%;">Username</th>
                            <th style="width: 15%;">Nama Lengkap</th> 
                            <th style="width: 20%;">Email</th>         
                            <th style="width: 10%;">Role</th>
                            <th style="width: 8%;">Menikah</th>
                            <th style="width: 8%;">Anak</th>
                            <th style="width: 10%;">Tgl. Daftar</th> 
                            <th style="width: 8%;">Aktif</th> 
                            <th style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $pengguna_query->fetch_assoc()): 
                            $status_class = $row['is_aktif'] ? 'badge bg-success' : 'badge bg-danger';
                        ?>
                        <tr>
                            <td><?php echo $row['id_pengguna']; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td> 
                            <td><?php echo htmlspecialchars($row['email']); ?></td>       
                            <td><?php echo $row['role']; ?></td>
                            <td><?php echo $row['is_menikah'] ? 'Ya' : 'Tidak'; ?></td>
                            <td><?php echo $row['jumlah_anak']; ?></td>
                            <td><?php echo $row['tgl_daftar']; ?></td>                     
                            <td><?php echo $row['is_aktif'] ? '<span class="badge bg-success">AKTIF</span>' : '<span class="badge bg-danger">NON-AKTIF</span>'; ?></td> 
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="crud_master_pengguna_edit.php?id=<?php echo $row['id_pengguna']; ?>" class="btn btn-sm btn-info text-white">Ubah</a>
                                    
                                    <a href="#" 
                                       class="btn btn-sm btn-danger" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#confirmDeletePenggunaModal" 
                                       data-id="<?php echo $row['id_pengguna']; ?>" 
                                       data-nama="<?php echo htmlspecialchars($row['username']); ?>">Hapus</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeletePenggunaModal" tabindex="-1" aria-labelledby="confirmDeletePenggunaModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeletePenggunaModalLabel">⚠️ Konfirmasi Penghapusan Pengguna</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Anda yakin ingin menghapus Pengguna berikut? Tindakan ini tidak dapat dibatalkan!</p>
        <p>
            <strong>ID Pengguna:</strong> <span id="modal-delete-pengguna-id" class="fw-bold"></span><br>
            <strong>Username:</strong> <span id="modal-delete-pengguna-nama" class="fst-italic"></span>
        </p>
        <div class="alert alert-warning small" role="alert">
            **PERINGATAN KRITIS:** Penghapusan akan GAGAL jika pengguna ini memiliki riwayat transaksi gaji (`tr_gaji`) atau transaksi penjualan (`tr_penjualan`).
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="delete-pengguna-link" class="btn btn-danger">Ya, Hapus Pengguna Permanen</a>
      </div>
    </div>
  </div>
</div>

<script>
    // Script JS untuk mengisi data ID dan Nama ke dalam Modal
    const deletePenggunaModal = document.getElementById('confirmDeletePenggunaModal');
    
    deletePenggunaModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; 
        const idPengguna = button.getAttribute('data-id');
        const namaPengguna = button.getAttribute('data-nama');
        
        const modalIdSpan = deletePenggunaModal.querySelector('#modal-delete-pengguna-id');
        const modalNamaSpan = deletePenggunaModal.querySelector('#modal-delete-pengguna-nama');
        const deleteLink = deletePenggunaModal.querySelector('#delete-pengguna-link');
        
        modalIdSpan.textContent = idPengguna;
        modalNamaSpan.textContent = namaPengguna;
        
        // Set link Hapus Permanen yang menunjuk ke crud_master_pengguna_delete.php
        deleteLink.href = 'crud_master_pengguna_delete.php?id=' + idPengguna;
    });
    
    document.getElementById('access-info').innerHTML = 'Akses: **<?php echo $role_login; ?>** (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>