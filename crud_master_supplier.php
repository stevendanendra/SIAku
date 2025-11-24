<?php
// crud_master_supplier.php
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

$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);

// --- LOGIKA TAMPIL DATA (READ) ---
$supplier_query = $conn->query("SELECT * FROM ms_supplier ORDER BY id_supplier ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">
    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
  <?php include 'navbar_master.php'; ?>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <h2>Kelola Pemasok (Supplier)</h2>

    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-4 mb-4">
                <h3 class="card-title h5">Tambah Supplier Baru</h3>
                <form action="crud_master_supplier_proses.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="nama_supplier" class="form-label">Nama Pemasok:</label>
                        <input type="text" class="form-control" name="nama_supplier" required>
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
                    
                    <button type="submit" class="btn btn-success w-100">Simpan Pemasok</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <h3 class="mb-3 h5">Daftar Pemasok Aktif</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle" style="min-width: 800px;">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 20%;">Nama Pemasok</th>
                            <th style="width: 15%;">No. Telepon</th>
                            <th style="width: 30%;">Alamat</th>
                            <th style="width: 15%;">Tgl. Daftar</th>
                            <th style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $supplier_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id_supplier']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_supplier']); ?></td>
                            <td><?php echo htmlspecialchars($row['no_telepon'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['alamat_lengkap'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['tgl_daftar']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="crud_master_supplier_edit.php?id=<?php echo $row['id_supplier']; ?>" class="btn btn-sm btn-info text-white">Ubah</a>
                                    
                                    <a href="#" 
                                       class="btn btn-sm btn-danger" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#confirmDeleteSupplierModal" 
                                       data-id="<?php echo $row['id_supplier']; ?>" 
                                       data-nama="<?php echo htmlspecialchars($row['nama_supplier']); ?>">Hapus</a>
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

<div class="modal fade" id="confirmDeleteSupplierModal" tabindex="-1" aria-labelledby="confirmDeleteSupplierModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeleteSupplierModalLabel">⚠️ Konfirmasi Penghapusan Pemasok</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Anda yakin ingin menghapus Pemasok berikut? Tindakan ini tidak dapat dibatalkan!</p>
        <p>
            <strong>ID Pemasok:</strong> <span id="modal-delete-supplier-id" class="fw-bold"></span><br>
            <strong>Nama:</strong> <span id="modal-delete-supplier-nama" class="fst-italic"></span>
        </p>
        <div class="alert alert-warning small" role="alert">
            **PERINGATAN KRITIS:** Penghapusan akan GAGAL jika pemasok ini memiliki riwayat hutang (tercatat di `tr_pengeluaran`).
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="delete-supplier-link" class="btn btn-danger">Ya, Hapus Pemasok Permanen</a>
      </div>
    </div>
  </div>
</div>

<script>
    // Script JS untuk mengisi data ID dan Nama ke dalam Modal
    const deleteSupplierModal = document.getElementById('confirmDeleteSupplierModal');
    
    deleteSupplierModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; 
        const idSupplier = button.getAttribute('data-id');
        const namaSupplier = button.getAttribute('data-nama');
        
        const modalIdSpan = deleteSupplierModal.querySelector('#modal-delete-supplier-id');
        const modalNamaSpan = deleteSupplierModal.querySelector('#modal-delete-supplier-nama');
        const deleteLink = deleteSupplierModal.querySelector('#delete-supplier-link');
        
        modalIdSpan.textContent = idSupplier;
        modalNamaSpan.textContent = namaSupplier;
        
        // Set link Hapus Permanen yang menunjuk ke crud_master_supplier_delete.php
        deleteLink.href = 'crud_master_supplier_delete.php?id=' + idSupplier;
    });
    
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>