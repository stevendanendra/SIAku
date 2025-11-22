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

<div class="container mt-5">
    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
    <div class="d-flex gap-3 mb-4 border-bottom pb-3"> 
        <a href="crud_master_akun.php" class="btn btn-dark btn-sm active">📊 Master Akun (COA)</a>
        <a href="crud_master_layanan.php" class="btn btn-outline-dark btn-sm">🧼 Master Layanan Jasa</a>
        <a href="crud_master_pengguna.php" class="btn btn-outline-dark btn-sm">👤 Master Pengguna & Karyawan</a>
        <a href="crud_master_pelanggan.php" class="btn btn-outline-dark btn-sm">👥 Master Pelanggan</a>
        <a href="payroll_komponen.php" class="btn btn-outline-dark btn-sm">💵 Pengaturan Payroll</a>
    </div>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <h2>Daftar Akun (COA)</h2>

    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-4 mb-4">
                <h3 class="card-title h5">Tambah Akun Baru</h3>
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
                            <option value="Aktiva">1xxx - Aktiva (Aset)</option>
                            <option value="Kewajiban">2xxx - Kewajiban (Liabilitas)</option>
                            <option value="Modal">3xxx - Modal (Ekuitas)</option>
                            <option value="Pendapatan">4xxx - Pendapatan</option>
                            <option value="Beban">5xxx - Beban</option>
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
                        <input type="number" class="form-control text-end" name="saldo_awal" value="0" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">Simpan Akun</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <h3 class="mb-3 h5">Daftar Akun Aktif</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 10%;">No. Akun</th>
                            <th style="width: 25%;">Nama Akun</th>
                            <th style="width: 15%;">Tipe</th>
                            <th style="width: 8%;">S. Normal</th>
                            <th class="text-end" style="width: 15%;">Saldo Awal</th>
                            <th class="text-end" style="width: 15%;">Saldo Saat Ini</th>
                            <th style="width: 12%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $akun_query->fetch_assoc()): 
                             $saldo_saat_ini_val = (int)$row['saldo_saat_ini'];
                             // Warna merah jika saldo negatif
                             $saldo_color = $saldo_saat_ini_val < 0 ? 'text-danger' : 'text-primary';
                             // Saldo Saat Ini harus menggunakan nilai absolut untuk tampilan bersih
                             $saldo_saat_ini_display = abs($saldo_saat_ini_val);
                        ?>
                        <tr>
                            <td><?php echo $row['id_akun']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_akun']); ?></td>
                            <td><?php echo $row['tipe_akun']; ?></td>
                            <td><?php echo $row['saldo_normal']; ?></td>
                            <td class="text-end">Rp <?php echo number_format($row['saldo_awal'], 0, ',', '.'); ?></td>
                            <td class="text-end fw-bold <?php echo $saldo_color; ?>">
                                Rp <?php echo number_format($saldo_saat_ini_display, 0, ',', '.'); ?>
                            </td>
                            <td>
                                <a href="crud_master_akun_edit.php?id=<?php echo $row['id_akun']; ?>" class="btn btn-sm btn-info text-white me-1">Ubah</a>
                                
                                <a href="#" 
                                   class="btn btn-sm btn-danger" 
                                   data-bs-toggle="modal" 
                                   data-bs-target="#confirmDeleteAkunModal" 
                                   data-id="<?php echo $row['id_akun']; ?>" 
                                   data-nama="<?php echo htmlspecialchars($row['nama_akun']); ?>">Hapus</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteAkunModal" tabindex="-1" aria-labelledby="confirmDeleteAkunModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeleteAkunModalLabel">⚠️ Konfirmasi Penghapusan Akun</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Anda yakin ingin menghapus Akun berikut? Tindakan ini tidak dapat dibatalkan!</p>
        <p>
            <strong>No. Akun:</strong> <span id="modal-delete-akun-id" class="fw-bold"></span><br>
            <strong>Nama Akun:</strong> <span id="modal-delete-akun-nama" class="fst-italic"></span>
        </p>
        <div class="alert alert-warning small" role="alert">
            **PERINGATAN KRITIS:** Penghapusan akan GAGAL jika akun ini memiliki entri di Jurnal Umum atau terhubung ke data master lain (misal: Komponen Gaji).
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="delete-akun-link" class="btn btn-danger">Ya, Hapus Akun Permanen</a>
      </div>
    </div>
  </div>
</div>

<script>
    // Script JS untuk mengisi data ID dan Nama ke dalam Modal
    const deleteAkunModal = document.getElementById('confirmDeleteAkunModal');
    
    deleteAkunModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; 
        const idAkun = button.getAttribute('data-id');
        const namaAkun = button.getAttribute('data-nama');
        
        const modalIdSpan = deleteAkunModal.querySelector('#modal-delete-akun-id');
        const modalNamaSpan = deleteAkunModal.querySelector('#modal-delete-akun-nama');
        const deleteLink = deleteAkunModal.querySelector('#delete-akun-link');
        
        modalIdSpan.textContent = idAkun;
        modalNamaSpan.textContent = namaAkun;
        
        // Set link Hapus Permanen yang menunjuk ke crud_master_akun_delete.php
        deleteLink.href = 'crud_master_akun_delete.php?id=' + idAkun;
    });
    
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>
<?php include '_footer.php'; // Footer Bootstrap ?>