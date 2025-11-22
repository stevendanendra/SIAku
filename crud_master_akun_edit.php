<?php
// crud_master_akun_edit.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_akun = $conn->real_escape_string($_GET['id']);
$error_message = '';
$success_message = '';

// --- INISIALISASI DATA LOGIN ---
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
// --------------------------------------------------

// --- LOGIKA UPDATE DATA (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    
    $nama_akun = $conn->real_escape_string($_POST['nama_akun']);
    $tipe_akun = $conn->real_escape_string($_POST['tipe_akun']);
    $saldo_normal = $conn->real_escape_string($_POST['saldo_normal']);
    $saldo_awal = $conn->real_escape_string($_POST['saldo_awal']); // Saldo Awal juga bisa diubah Owner
    
    // Validasi dasar
    if (empty($nama_akun) || empty($tipe_akun) || empty($saldo_normal)) {
        $error_message = "Semua field wajib diisi.";
    } elseif (!is_numeric($saldo_awal) || floor($saldo_awal) != $saldo_awal) {
        $error_message = "Saldo Awal harus bilangan bulat (BIGINT).";
    } else {
        // Logika UPDATE Saldo Awal
        $sql = "UPDATE ms_akun SET 
                nama_akun = '$nama_akun', 
                tipe_akun = '$tipe_akun', 
                saldo_normal = '$saldo_normal', 
                saldo_awal = '$saldo_awal'
                WHERE id_akun = '$id_akun'";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Akun $id_akun berhasil diperbarui. Silakan proses Buku Besar untuk melihat perubahan saldo akhir.";
        } else {
            $error_message = "Error saat memperbarui akun: " . $conn->error;
        }
    }
}

// --- LOGIKA AMBIL DATA LAMA (READ FOR UPDATE) ---
$sql_data = "SELECT * FROM ms_akun WHERE id_akun = '$id_akun'";
$result = $conn->query($sql_data);

if ($result->num_rows == 0) {
    die("Akun tidak ditemukan.");
}

$data = $result->fetch_assoc();
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Ubah Data Master Akun (COA)</h1>
    <div class="d-flex gap-3 mb-4 p-2 bg-light rounded shadow-sm">
        <a href="crud_master_akun.php" class="btn btn-primary btn-sm active">📊 Master Akun (COA)</a>
        <a href="crud_master_layanan.php" class="btn btn-outline-primary btn-sm">🧼 Master Layanan Jasa</a>
        <a href="crud_master_pengguna.php" class="btn btn-outline-primary btn-sm">👤 Master Pengguna & Karyawan</a>
    </div>

    <p><a href="crud_master_akun.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Akun</a></p>
    <hr>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm p-4">
                <h3 class="card-title mb-3">Detail Akun: <?php echo htmlspecialchars($data['nama_akun']); ?></h3>
                
                <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
                <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="mb-3">
                        <label class="form-label">No. Akun:</label>
                        <input type="text" class="form-control" value="<?php echo $id_akun; ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="nama_akun" class="form-label">Nama Akun:</label>
                        <input type="text" class="form-control" name="nama_akun" value="<?php echo htmlspecialchars($data['nama_akun']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipe_akun" class="form-label">Tipe Akun:</label>
                        <select class="form-select" name="tipe_akun" required>
                            <?php 
                            $tipe_options = ['Aktiva', 'Kewajiban', 'Modal', 'Pendapatan', 'Beban'];
                            foreach ($tipe_options as $tipe) {
                                $selected = ($tipe == $data['tipe_akun']) ? 'selected' : '';
                                echo "<option value='$tipe' $selected>$tipe</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="saldo_normal" class="form-label">Saldo Normal:</label>
                        <select class="form-select" name="saldo_normal" required>
                            <option value="D" <?php echo ($data['saldo_normal'] == 'D') ? 'selected' : ''; ?>>Debit (D)</option>
                            <option value="K" <?php echo ($data['saldo_normal'] == 'K') ? 'selected' : ''; ?>>Kredit (K)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="saldo_awal" class="form-label">Saldo Awal (Rp):</label>
                        <input type="number" class="form-control" name="saldo_awal" value="<?php echo $data['saldo_awal']; ?>" required>
                    </div>

                    <div class="alert alert-warning mt-3">
                        ⚠️ **Peringatan:** Saldo Saat Ini: **Rp <?php echo number_format($data['saldo_saat_ini'], 0, ',', '.'); ?>**. Saldo akhir hanya berubah setelah Owner menjalankan proses Buku Besar.
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>