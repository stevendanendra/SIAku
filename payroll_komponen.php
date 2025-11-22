<?php
// payroll_komponen.php
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
if (isset($_GET['success']) && $_GET['success'] == 'update') {
    $success_message = "Komponen gaji berhasil diperbarui.";
}
// -----------------------------------------------------------------

// --- DEFINISI MAPPING AKUN KHUSUS PAYROLL ---
function mapPayrollAccount($id_akun, $is_liability) {
    if ($is_liability == 1) {
        return ['Kategori' => 'Kewajiban', 'SubAkun' => 'Utang (2102)'];
    }
    switch ($id_akun) {
        case 5101:
            return ['Kategori' => 'Beban', 'SubAkun' => 'Gaji Pokok (5101)'];
        case 5102:
            return ['Kategori' => 'Beban', 'SubAkun' => 'Komponen Plus (5102)'];
        case 5103:
            return ['Kategori' => 'Beban', 'SubAkun' => 'Komponen Minus (5103)'];
        case 2102:
            return ['Kategori' => 'Kewajiban', 'SubAkun' => 'Utang PPh 21 (2102)'];
        default:
            return ['Kategori' => 'Tidak Dikenal', 'SubAkun' => $id_akun];
    }
}

// --- LOGIKA TAMBAH KOMPONEN BARU (CREATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $nama_komponen = $conn->real_escape_string($_POST['nama_komponen']);
    $tipe = $conn->real_escape_string($_POST['tipe']);
    $nilai_default = $conn->real_escape_string($_POST['nilai_default']);
    
    // Mengambil nilai is_persentase langsung dari dropdown (0 atau 1)
    $is_persentase = $conn->real_escape_string($_POST['is_persentase']); 
    
    // >> START: LOGIKA OTOMATISASI AKUN (5103)
    $is_liability = isset($_POST['is_liability']) ? 1 : 0;
    
    if ($is_liability == 1) {
        $id_akun_beban = 2102; // Kewajiban
    } elseif ($tipe == 'Pengurang') {
        $id_akun_beban = 5103; // Beban Komponen Minus
    } else { // Tipe Penambah
        $id_akun_beban = 5102; // Beban Komponen Plus
    }
    // << END: LOGIKA OTOMATISASI AKUN
    
    if (empty($nama_komponen) || empty($tipe) || !is_numeric($nilai_default)) {
        $error_message = "Field utama wajib diisi dan Nilai Default harus numerik.";
    } else {
        $sql = "INSERT INTO ms_gaji_komponen (nama_komponen, tipe, nilai_default, is_persentase, id_akun_beban, is_liability) 
                VALUES ('$nama_komponen', '$tipe', '$nilai_default', '$is_persentase', '$id_akun_beban', '$is_liability')";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Komponen '$nama_komponen' berhasil ditambahkan. Akun terkait otomatis dipetakan ke $id_akun_beban.";
        } else {
            $error_message = "Error: Gagal menambahkan komponen. " . $conn->error;
        }
    }
}

// --- LOGIKA TAMPIL DATA (READ) ---
$komponen_query = $conn->query("SELECT k.*, a.nama_akun, a.tipe_akun FROM ms_gaji_komponen k JOIN ms_akun a ON k.id_akun_beban = a.id_akun ORDER BY k.id_komponen ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>
    
    <div class="d-flex gap-3 mb-4 border-bottom pb-3"> 
        <a href="crud_master_akun.php" class="btn btn-outline-dark btn-sm">📊 Master Akun (COA)</a>
        <a href="crud_master_layanan.php" class="btn btn-outline-dark btn-sm">🧼 Master Layanan Jasa</a>
        <a href="crud_master_pengguna.php" class="btn btn-outline-dark btn-sm">👤 Master Pengguna & Karyawan</a>
        <a href="crud_master_pelanggan.php" class="btn btn-outline-dark btn-sm">👥 Master Pelanggan</a>
        <a href="payroll_komponen.php" class="btn btn-dark btn-sm active">💵 Pengaturan Payroll</a>
    </div>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>

    <h2 class="mb-3">Pengaturan Komponen Gaji (Payroll Setting)</h2>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm p-4 mb-4">
                <h3 class="card-title h5">Tambah Komponen Baru</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="nama_komponen" class="form-label">Nama Komponen:</label>
                        <input type="text" class="form-control" name="nama_komponen" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipe" class="form-label">Tipe:</label>
                        <select class="form-select" name="tipe" required>
                            <option value="Penambah">Penambah (Akun 5102)</option>
                            <option value="Pengurang">Pengurang (Akun 5103 atau 2102)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nilai_default" class="form-label">Nilai Default:</label> 
                        <input type="number" class="form-control" name="nilai_default" value="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="is_persentase" class="form-label">Jenis Nilai:</label>
                        <select class="form-select" name="is_persentase" required>
                            <option value="0">Nominal (Rp)</option>
                            <option value="1">Persentase (%)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_liability" name="is_liability" value="1">
                        <label class="form-check-label" for="is_liability">Apakah ini **Potongan Kewajiban** (Utang/Pajak)?</label>
                        <small class="form-text text-muted d-block">
                            *Jika dicentang: Akun otomatis **2102 (Utang)**.<br>
                            *Jika tidak dicentang & Tipe Pengurang: Akun otomatis **5103 (Beban Komponen Minus)**.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Simpan Komponen Gaji</button>
                </form>
            </div>
        </div>
        
        <div class="col-md-7">
            <div class="card shadow-sm p-4 mb-4 bg-light">
                <h3 class="card-title h5 text-primary">Catatan Penting Payroll (Update Kebijakan)</h3>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item">**Akun 5101 (Beban Gaji Pokok):** HANYA digunakan untuk Gaji Pokok.</li>
                    <li class="list-group-item">**Akun 5102 (Beban Komponen Plus):** Digunakan untuk Penambah (Tunjangan, Bonus).</li>
                    <li class="list-group-item">**Akun 5103 (Beban Komponen Minus):** Digunakan untuk Pengurang Non-Utang (Potongan Keterlambatan/Denda).</li>
                    <li class="list-group-item">**Akun 2102 (Utang PPh 21):** Digunakan HANYA untuk potongan Kewajiban (PPh 21, BPJS Karyawan).</li>
                </ul>
            </div>
        </div>
    </div>
    
    <hr>
    
    <h2 class="mb-3">Daftar Komponen Gaji Aktif</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 30%;">Nama Komponen</th>
                    <th style="width: 10%;">Tipe</th>
                    <th class="text-end" style="width: 15%;">Nilai Default</th>
                    <th style="width: 10%;">Jenis Nilai</th>
                    <th style="width: 10%;">Status Akun</th>
                    <th style="width: 20%;">Sub-Akun Payroll</th> 
                    <th style="width: 10%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $komponen_query->fetch_assoc()): 
                    $akun_info = mapPayrollAccount($row['id_akun_beban'], $row['is_liability']);
                ?>
                <tr>
                    <td><?php echo $row['id_komponen']; ?></td>
                    <td><?php echo htmlspecialchars($row['nama_komponen']); ?></td>
                    <td><span class="badge <?php echo $row['tipe'] == 'Penambah' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $row['tipe']; ?></span></td>
                    <td class="text-end">Rp <?php echo number_format($row['nilai_default'], 0, ',', '.'); ?></td>
                    <td><?php echo $row['is_persentase'] ? 'Persentase (%)' : 'Nominal (Rp)'; ?></td> 
                    <td><span class="badge <?php echo $row['is_liability'] ? 'bg-warning text-dark' : 'bg-info'; ?>"><?php echo $row['is_liability'] ? 'Kewajiban' : 'Beban'; ?></span></td>
                    <td><span class="badge bg-secondary"><?php echo $akun_info['SubAkun']; ?></span></td> 
                    <td>
                        <a href="payroll_komponen_edit.php?id=<?php echo $row['id_komponen']; ?>" class="btn btn-sm btn-info text-white me-1">Ubah</a>
                        <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" 
                           data-id="<?php echo $row['id_komponen']; ?>" 
                           data-nama="<?php echo htmlspecialchars($row['nama_komponen']); ?>">Hapus</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
    </script>
    
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="confirmDeleteModalLabel">⚠️ Konfirmasi Penghapusan Komponen</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Anda yakin ingin menghapus komponen gaji berikut?</p>
            <p>
                <strong>ID Komponen:</strong> <span id="modal-delete-id" class="fw-bold"></span><br>
                <strong>Nama:</strong> <span id="modal-delete-nama" class="fst-italic"></span>
            </p>
            <div class="alert alert-warning small" role="alert">
                Penghapusan tidak dapat dibatalkan dan akan gagal jika komponen ini sudah digunakan dalam riwayat transaksi gaji.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <a href="#" id="delete-komponen-link" class="btn btn-danger">Ya, Hapus Permanen</a>
          </div>
        </div>
      </div>
    </div>

    <script>
        const deleteModal = document.getElementById('confirmDeleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; 
            const idKomponen = button.getAttribute('data-id');
            const namaKomponen = button.getAttribute('data-nama');
            const deleteLink = deleteModal.querySelector('#delete-komponen-link');
            
            deleteModal.querySelector('#modal-delete-id').textContent = idKomponen;
            deleteModal.querySelector('#modal-delete-nama').textContent = namaKomponen;
            deleteLink.href = 'payroll_komponen_delete.php?id=' + idKomponen;
        });
    </script>


<?php include '_footer.php'; // Footer Bootstrap ?>