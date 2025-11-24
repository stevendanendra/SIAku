<?php
// crud_master_layanan.php
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
            $_SESSION['success_message'] = "Layanan '$nama_layanan' berhasil ditambahkan.";
        } else {
            $error_message = "Error: Gagal menambahkan layanan. " . $conn->error;
        }
    }
}

// --- LOGIKA TAMPIL DATA (READ) ---
$layanan_query = $conn->query("SELECT l.*, a.nama_akun FROM ms_layanan l JOIN ms_akun a ON l.id_akun_pendapatan = a.id_akun ORDER BY l.id_layanan ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">
    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
    <?php include 'navbar_master.php'; ?>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <h2>Kelola Layanan Jasa</h2>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-4 mb-4">
                <h3 class="card-title h5">Tambah Layanan Baru</h3>
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
                    
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label for="luas_min" class="form-label">Luas Min (m²):</label>
                            <input type="number" class="form-control text-end" name="luas_min" min="0" value="0">
                        </div>
                        <div class="col-sm-6">
                            <label for="luas_max" class="form-label">Luas Max (m²):</label>
                            <input type="number" class="form-control text-end" name="luas_max" min="0" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="harga_jual" class="form-label">Harga Jual (Rp):</label>
                        <input type="number" class="form-control text-end" name="harga_jual" required>
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
                        <label class="form-check-label" for="is_aktif">Status Aktif</label>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">Simpan Layanan</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <h3 class="mb-3 h5">Daftar Layanan Aktif</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 25%;">Nama Layanan</th>
                            <th style="width: 10%;">Tipe</th>
                            <th style="width: 15%;">Luas (Min-Max)</th>
                            <th class="text-end" style="width: 15%;">Harga Jual</th>
                            <th style="width: 10%;">Aktif</th>
                            <th style="width: 15%;">Akun Pendapatan</th>
                            <th style="width: 10%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $layanan_query->fetch_assoc()): 
                            $status_class = $row['is_aktif'] ? 'badge bg-success' : 'badge bg-danger';
                        ?>
                        <tr>
                            <td><?php echo $row['id_layanan']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_layanan']); ?></td>
                            <td><?php echo $row['tipe_layanan']; ?></td>
                            <td class="text-end"><?php echo $row['luas_min'] . ' - ' . $row['luas_max']; ?> m²</td>
                            <td class="text-end fw-bold">Rp <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?></td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo $row['is_aktif'] ? 'AKTIF' : 'NON-AKTIF'; ?></span></td>
                            <td><?php echo $row['id_akun_pendapatan'] . ' - ' . htmlspecialchars($row['nama_akun']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="crud_master_layanan_edit.php?id=<?php echo $row['id_layanan']; ?>" class="btn btn-sm btn-info text-white">Ubah</a>
                                    
                                    <a href="#" 
                                       class="btn btn-sm btn-danger" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#confirmDeleteLayananModal" 
                                       data-id="<?php echo $row['id_layanan']; ?>" 
                                       data-nama="<?php echo htmlspecialchars($row['nama_layanan']); ?>">Hapus</a>
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

<div class="modal fade" id="confirmDeleteLayananModal" tabindex="-1" aria-labelledby="confirmDeleteLayananModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeleteLayananModalLabel">⚠️ Konfirmasi Penghapusan Layanan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Anda yakin ingin menghapus Layanan berikut? Tindakan ini tidak dapat dibatalkan!</p>
        <p>
            <strong>ID Layanan:</strong> <span id="modal-delete-layanan-id" class="fw-bold"></span><br>
            <strong>Nama Layanan:</strong> <span id="modal-delete-layanan-nama" class="fst-italic"></span>
        </p>
        <div class="alert alert-warning small" role="alert">
            **PERINGATAN KRITIS:** Penghapusan akan GAGAL jika Layanan ini memiliki entri di Transaksi Penjualan atau Jurnal.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="delete-layanan-link" class="btn btn-danger">Ya, Hapus Layanan Permanen</a>
      </div>
    </div>
  </div>
</div>

<script>
    // Script JS untuk mengisi data ID dan Nama ke dalam Modal
    const deleteLayananModal = document.getElementById('confirmDeleteLayananModal');
    
    deleteLayananModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; 
        const idLayanan = button.getAttribute('data-id');
        const namaLayanan = button.getAttribute('data-nama');
        
        const modalIdSpan = deleteLayananModal.querySelector('#modal-delete-layanan-id');
        const modalNamaSpan = deleteLayananModal.querySelector('#modal-delete-layanan-nama');
        const deleteLink = deleteLayananModal.querySelector('#delete-layanan-link');
        
        modalIdSpan.textContent = idLayanan;
        modalNamaSpan.textContent = namaLayanan;
        
        // Set link Hapus Permanen yang menunjuk ke crud_master_layanan_delete.php
        deleteLink.href = 'crud_master_layanan_delete.php?id=' + idLayanan;
    });
    
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>