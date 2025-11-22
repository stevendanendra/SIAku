<?php
// payroll_komponen_edit.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_komponen = $conn->real_escape_string($_GET['id']);
$error_message = '';
$success_message = '';

// --- INISIALISASI DATA LOGIN ---
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
// --------------------------------------------------

// --- LOGIKA UPDATE DATA (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    
    $nama_komponen = $conn->real_escape_string($_POST['nama_komponen']);
    $tipe = $conn->real_escape_string($_POST['tipe']);
    $nilai_default = $conn->real_escape_string($_POST['nilai_default']);
    $is_persentase = isset($_POST['is_persentase']) ? 1 : 0;
    $id_akun_beban = $conn->real_escape_string($_POST['id_akun_beban']);
    
    // Validasi dasar
    if (empty($nama_komponen) || empty($tipe) || !is_numeric($nilai_default) || empty($id_akun_beban)) {
        $error_message = "Semua field wajib diisi dan Nilai Default harus numerik.";
    } else {
        $sql = "UPDATE ms_gaji_komponen SET 
                nama_komponen = '$nama_komponen', 
                tipe = '$tipe', 
                nilai_default = '$nilai_default', 
                is_persentase = '$is_persentase', 
                id_akun_beban = '$id_akun_beban'
                WHERE id_komponen = '$id_komponen'";
        
        if ($conn->query($sql) === TRUE) {
            // Setelah update data master, redirect dengan pesan sukses
            $_SESSION['success_message'] = "Komponen #$id_komponen berhasil diperbarui.";
            header("Location: payroll_komponen.php"); 
            exit();
        } else {
            $error_message = "Error saat memperbarui komponen: " . $conn->error;
        }
    }
}

// --- LOGIKA AMBIL DATA LAMA (READ FOR UPDATE) ---
$sql_data = "SELECT * FROM ms_gaji_komponen WHERE id_komponen = '$id_komponen'";
$result = $conn->query($sql_data);

if ($result->num_rows == 0) {
    die("Komponen gaji tidak ditemukan.");
}

$data = $result->fetch_assoc();
// Query Akun hanya untuk akun payroll: 5101, 5102, 2102
$akun_gaji_query = $conn->query("SELECT id_akun, nama_akun, tipe_akun FROM ms_akun WHERE id_akun IN (5101, 5102, 2102) ORDER BY id_akun ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Ubah Komponen Gaji (Payroll Setting)</h1>
    <div class="d-flex gap-3 mb-4 p-2 bg-light rounded shadow-sm">
        <a href="crud_master_akun.php" class="btn btn-outline-primary btn-sm">📊 Master Akun (COA)</a>
        <a href="payroll_komponen.php" class="btn btn-primary btn-sm active">💵 Payroll Setting</a>
        <a href="crud_master_pengguna.php" class="btn btn-outline-primary btn-sm">👤 Master Pengguna</a>
    </div>

    <p><a href="payroll_komponen.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Komponen</a></p>
    <hr>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm p-4">
                <h3 class="card-title mb-3">Komponen: #<?php echo $id_komponen . ' - ' . htmlspecialchars($data['nama_komponen']); ?></h3>
                
                <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
                <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="mb-3">
                        <label class="form-label">ID Komponen:</label>
                        <input type="text" class="form-control" value="<?php echo $id_komponen; ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="nama_komponen" class="form-label">Nama Komponen:</label>
                        <input type="text" class="form-control" name="nama_komponen" value="<?php echo htmlspecialchars($data['nama_komponen']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipe" class="form-label">Tipe:</label>
                        <select class="form-select" name="tipe" required>
                            <option value="Penambah" <?php echo ($data['tipe'] == 'Penambah') ? 'selected' : ''; ?>>Penambah (Gaji Kotor)</option>
                            <option value="Pengurang" <?php echo ($data['tipe'] == 'Pengurang') ? 'selected' : ''; ?>>Pengurang (Potongan)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nilai_default" class="form-label">Nilai Default:</label> 
                        <input type="number" class="form-control" name="nilai_default" value="<?php echo $data['nilai_default']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="is_persentase" class="form-label">Jenis Nilai:</label>
                        <select class="form-select" name="is_persentase" required>
                            <option value="0" <?php echo ($data['is_persentase'] == 0) ? 'selected' : ''; ?>>Nominal (Rp)</option>
                            <option value="1" <?php echo ($data['is_persentase'] == 1) ? 'selected' : ''; ?>>Persentase (%)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_akun_beban" class="form-label">Akun Beban/Utang:</label>
                        <select class="form-select" name="id_akun_beban" required>
                            <?php while ($row = $akun_gaji_query->fetch_assoc()): 
                                $selected = ($row['id_akun'] == $data['id_akun_beban']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $row['id_akun']; ?>" <?php echo $selected; ?>>
                                    <?php echo $row['id_akun'] . ' - ' . htmlspecialchars($row['nama_akun']) . ' (' . $row['tipe_akun'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Simpan Perubahan Komponen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Ambil data dari PHP
    const role = '<?php echo $role_login; ?>';
    const nama = '<?php echo $nama_login; ?>';
    const id = '<?php echo $id_login; ?>';
    
    // Update footer info
    document.getElementById('access-info').innerHTML = `Akses: **${role}** (${nama}, ID ${id})`;
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>