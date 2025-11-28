<?php
// crud_master_pelanggan.php
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

// --- LOGIKA PESAN DARI SESI (DELETE/EDIT/CREATE) ---
if (isset($_SESSION['error_message'])) {
    $error_message .= (empty($error_message) ? '' : '<br>') . $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message .= (empty($success_message) ? '' : '<br>') . $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// --- LOGIKA TAMPIL DATA (READ) ---
$pelanggan_query = $conn->query("SELECT * FROM ms_pelanggan ORDER BY id_pelanggan ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">
    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
    <?php include 'navbar_master.php'; ?>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <h2>Kelola Pelanggan</h2>

    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-4 mb-4">
                <h3 class="card-title h5">Tambah Pelanggan Baru</h3>
                <form action="crud_master_pelanggan_proses.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="nama_pelanggan" class="form-label">Nama Pelanggan:</label>
                        <input type="text" class="form-control" name="nama_pelanggan" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="no_telepon" class="form-label">No. Telepon:</label>
                        <input type="text" class="form-control" name="no_telepon">
                    </div>
                    
                    <div class="mb-3">
                        <label for="alamat_lengkap" class="form-label">Alamat Lengkap:</label>
                        <input type="text" class="form-control" name="alamat_lengkap">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">Simpan Pelanggan</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <h3 class="mb-3 h5">Daftar Pelanggan Aktif</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle" style="min-width: 800px;">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 20%;">Nama Pelanggan</th>
                            <th style="width: 15%;">No. Telepon</th>
                            <th style="width: 25%;">Alamat</th>
                            <th style="width: 15%;">Tgl. Daftar</th>
                            <th style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $pelanggan_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id_pelanggan']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_pelanggan']); ?></td>
                            <td><?php echo htmlspecialchars($row['no_telepon'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['alamat_lengkap'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['tgl_daftar']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="crud_master_pelanggan_edit.php?id=<?php echo $row['id_pelanggan']; ?>" class="btn btn-sm btn-info text-white">Ubah</a>
                                    
                                    <a href="#" 
                                       class="btn btn-sm btn-danger" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#confirmDeletePelangganModal" 
                                       data-id="<?php echo $row['id_pelanggan']; ?>" 
                                       data-nama="<?php echo htmlspecialchars($row['nama_pelanggan']); ?>">Hapus</a>
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

<div class="modal fade" id="confirmDeletePelangganModal" tabindex="-1" aria-labelledby="confirmDeletePelangganModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeletePelangganModalLabel">⚠️ Konfirmasi Penghapusan Pelanggan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Anda yakin ingin menghapus Pelanggan berikut? Tindakan ini tidak dapat dibatalkan!</p>
        <p>
            <strong>ID Pelanggan:</strong> <span id="modal-delete-pelanggan-id" class="fw-bold"></span><br>
            <strong>Nama:</strong> <span id="modal-delete-pelanggan-nama" class="fst-italic"></span>
        </p>
        <div class="alert alert-warning small" role="alert">
            **PERINGATAN KRITIS:** Penghapusan akan GAGAL jika pelanggan ini memiliki riwayat piutang atau transaksi penjualan.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="delete-pelanggan-link" class="btn btn-danger">Ya, Hapus Pelanggan Permanen</a>
      </div>
    </div>
  </div>
</div>

<script>
    // Script JS untuk mengisi data ID dan Nama ke dalam Modal
    const deletePelangganModal = document.getElementById('confirmDeletePelangganModal');
    
    deletePelangganModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; 
        const idPelanggan = button.getAttribute('data-id');
        const namaPelanggan = button.getAttribute('data-nama');
        
        const modalIdSpan = deletePelangganModal.querySelector('#modal-delete-pelanggan-id');
        const modalNamaSpan = deletePelangganModal.querySelector('#modal-delete-pelanggan-nama');
        const deleteLink = deletePelangganModal.querySelector('#delete-pelanggan-link');
        
        modalIdSpan.textContent = idPelanggan;
        modalNamaSpan.textContent = namaPelanggan;
        
        // Set link Hapus Permanen yang menunjuk ke crud_master_pelanggan_delete.php
        deleteLink.href = 'crud_master_pelanggan_delete.php?id=' + idPelanggan;
    });
    
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>