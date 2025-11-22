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

// Ambil akun yang relevan untuk payroll
$akun_gaji_query = $conn->query("SELECT id_akun, nama_akun, tipe_akun FROM ms_akun WHERE id_akun IN (5101, 5102, 2102) ORDER BY id_akun ASC");

// --- LOGIKA TAMBAH KOMPONEN BARU (CREATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $nama_komponen = $conn->real_escape_string($_POST['nama_komponen']);
    $tipe = $conn->real_escape_string($_POST['tipe']);
    $nilai_default = $conn->real_escape_string($_POST['nilai_default']);
    $is_persentase = isset($_POST['is_persentase']) ? 1 : 0;
    $id_akun_beban = $conn->real_escape_string($_POST['id_akun_beban']);
    
    if (empty($nama_komponen) || empty($tipe) || !is_numeric($nilai_default) || empty($id_akun_beban)) {
        $error_message = "Field utama wajib diisi dan Nilai Default harus numerik.";
    } else {
        $sql = "INSERT INTO ms_gaji_komponen (nama_komponen, tipe, nilai_default, is_persentase, id_akun_beban) 
                VALUES ('$nama_komponen', '$tipe', '$nilai_default', '$is_persentase', '$id_akun_beban')";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Komponen '$nama_komponen' berhasil ditambahkan.";
        } else {
            $error_message = "Error: Gagal menambahkan komponen. " . $conn->error;
        }
    }
}

// --- LOGIKA TAMPIL DATA (READ) ---
// REVISI URUTAN: Diurutkan berdasarkan ID Komponen (id_komponen)
$komponen_query = $conn->query("SELECT k.*, a.nama_akun FROM ms_gaji_komponen k JOIN ms_akun a ON k.id_akun_beban = a.id_akun ORDER BY k.id_komponen ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">2. Pengaturan Komponen Gaji (Payroll Setting)</h1>
    
    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
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
                            <option value="Penambah">Penambah (Gaji Kotor)</option>
                            <option value="Pengurang">Pengurang (Potongan)</option>
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
                    
                    <div class="mb-3">
                        <label for="id_akun_beban" class="form-label">Akun Beban/Utang:</label>
                        <select class="form-select" name="id_akun_beban" required>
                            <option value="">Pilih Akun Terkait</option>
                            <?php 
                            $akun_gaji_query->data_seek(0); // Reset pointer
                            while ($row = $akun_gaji_query->fetch_assoc()): ?>
                                <option value="<?php echo $row['id_akun']; ?>">
                                    <?php echo $row['id_akun'] . ' - ' . htmlspecialchars($row['nama_akun']) . ' (' . $row['tipe_akun'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">Simpan Komponen Gaji</button>
                </form>
            </div>
        </div>
        
        <div class="col-md-7">
            <div class="card shadow-sm p-4 mb-4 bg-light">
                <h3 class="card-title h5 text-primary">Catatan Penting Payroll</h3>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item">**Akun 5101 (Beban Gaji):** Digunakan untuk Gaji Pokok, Tunjangan Istri/Anak, Tunjangan Makan, dan Potongan BPJS.</li>
                    <li class="list-group-item">**Akun 5102 (Beban Bonus):** Digunakan hanya untuk Bonus Laba Bersih Persentase.</li>
                    <li class="list-group-item">**Akun 2102 (Utang PPh 21):** Digunakan untuk Potongan PPh 21 (Kewajiban ke Negara).</li>
                    <li class="list-group-item">**Tunjangan Anak:** Nominal dihitung per anak (Maks. 2) berdasarkan data di Master Pengguna.</li>
                    <li class="list-group-item">**Bonus Laba Bersih:** Persentase ini akan digunakan untuk menghitung total bonus yang akan dibagikan di Modul Payroll Proses.</li>
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
                    <th>ID</th>
                    <th>Nama Komponen</th>
                    <th>Tipe</th>
                    <th class="text-end">Nilai Default</th>
                    <th>Jenis Nilai</th>
                    <th>Akun Terkait</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $komponen_query->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id_komponen']; ?></td>
                    <td><?php echo htmlspecialchars($row['nama_komponen']); ?></td>
                    <td><span class="badge <?php echo $row['tipe'] == 'Penambah' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $row['tipe']; ?></span></td>
                    <td class="text-end"><?php echo number_format($row['nilai_default'], 0, ',', '.'); ?></td>
                    <td><?php echo $row['is_persentase'] ? 'Persentase (%)' : 'Nominal (Rp)'; ?></td>
                    <td><?php echo $row['id_akun_beban'] . ' - ' . htmlspecialchars($row['nama_akun']); ?></td>
                    <td>
                        <a href="payroll_komponen_edit.php?id=<?php echo $row['id_komponen']; ?>" class="btn btn-sm btn-info text-white me-1">Ubah</a>
                        <a href="payroll_komponen_delete.php?id=<?php echo $row['id_komponen']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin hapus komponen gaji?');">Hapus</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
    </script>

<?php include '_footer.php'; // Footer Bootstrap ?>