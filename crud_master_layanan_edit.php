<?php
// crud_master_layanan_edit.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_layanan = $conn->real_escape_string($_GET['id']);
$error_message = '';
$success_message = '';

// --- INISIALISASI DATA LOGIN ---
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
// --------------------------------------------------

// --- LOGIKA UPDATE DATA (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    
    $tipe_layanan = $conn->real_escape_string($_POST['tipe_layanan']);
    $nama_layanan = $conn->real_escape_string($_POST['nama_layanan']);
    $luas_min = $conn->real_escape_string($_POST['luas_min']);
    $luas_max = $conn->real_escape_string($_POST['luas_max']);
    $harga_jual = $conn->real_escape_string($_POST['harga_jual']);
    $id_akun_pendapatan = $conn->real_escape_string($_POST['id_akun_pendapatan']);
    // PENAMBAHAN KRITIS: Ambil nilai is_aktif
    $is_aktif = isset($_POST['is_aktif']) ? 1 : 0; 
    
    // Validasi dasar
    if (empty($tipe_layanan) || empty($nama_layanan) || empty($harga_jual)) {
        $error_message = "Semua field wajib diisi.";
    } elseif (!is_numeric($harga_jual) || floor($harga_jual) != $harga_jual) {
        $error_message = "Harga Jual harus bilangan bulat (BIGINT).";
    } else {
        // SERTAKAN is_aktif dalam query UPDATE
        $sql = "UPDATE ms_layanan SET 
                tipe_layanan = '$tipe_layanan', 
                nama_layanan = '$nama_layanan', 
                luas_min = '$luas_min', 
                luas_max = '$luas_max', 
                harga_jual = '$harga_jual', 
                id_akun_pendapatan = '$id_akun_pendapatan',
                is_aktif = '$is_aktif'
                WHERE id_layanan = '$id_layanan'";
        
        if ($conn->query($sql) === TRUE) {
            // Setelah update data master, redirect dengan pesan sukses
            $_SESSION['success_message'] = "Layanan #$id_layanan berhasil diperbarui.";
            header("Location: crud_master_layanan.php"); 
            exit();
        } else {
            $error_message = "Error saat memperbarui layanan: " . $conn->error;
        }
    }
}

// --- LOGIKA AMBIL DATA LAMA (READ FOR UPDATE) ---
$sql_data = "SELECT * FROM ms_layanan WHERE id_layanan = '$id_layanan'";
$result = $conn->query($sql_data);

if ($result->num_rows == 0) {
    die("Layanan tidak ditemukan.");
}

$data = $result->fetch_assoc();
$akun_pendapatan_query = $conn->query("SELECT id_akun, nama_akun FROM ms_akun WHERE tipe_akun = 'Pendapatan' ORDER BY id_akun ASC");
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Ubah Data Master Layanan Jasa</h1>
    <div class="d-flex gap-3 mb-4 p-2 bg-light rounded shadow-sm">
        <a href="crud_master_akun.php" class="btn btn-outline-primary btn-sm">📊 Master Akun (COA)</a>
        <a href="crud_master_layanan.php" class="btn btn-primary btn-sm active">🧼 Master Layanan Jasa</a>
        <a href="crud_master_pengguna.php" class="btn btn-outline-primary btn-sm">👤 Master Pengguna & Karyawan</a>
    </div>

    <p><a href="crud_master_layanan.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Layanan</a></p>
    <hr>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm p-4">
                <h3 class="card-title mb-3">Detail Layanan: <?php echo htmlspecialchars($data['nama_layanan']); ?></h3>
                
                <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
                <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="mb-3">
                        <label class="form-label">ID Layanan:</label>
                        <input type="text" class="form-control" value="<?php echo $id_layanan; ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipe_layanan" class="form-label">Tipe Layanan:</label>
                        <select class="form-select" name="tipe_layanan" required>
                            <?php 
                            $tipe_options = ['Rumah', 'Ruangan'];
                            foreach ($tipe_options as $tipe) {
                                $selected = ($tipe == $data['tipe_layanan']) ? 'selected' : '';
                                echo "<option value='$tipe' $selected>$tipe</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama_layanan" class="form-label">Nama Layanan:</label>
                        <input type="text" class="form-control" name="nama_layanan" value="<?php echo htmlspecialchars($data['nama_layanan']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="luas_min" class="form-label">Luas Min (m²):</label>
                        <input type="number" class="form-control" name="luas_min" value="<?php echo $data['luas_min']; ?>" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="luas_max" class="form-label">Luas Max (m²):</label>
                        <input type="number" class="form-control" name="luas_max" value="<?php echo $data['luas_max']; ?>" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="harga_jual" class="form-label">Harga Jual (BIGINT):</label>
                        <input type="number" class="form-control" name="harga_jual" value="<?php echo $data['harga_jual']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_akun_pendapatan" class="form-label">Akun Pendapatan:</label>
                        <select class="form-select" name="id_akun_pendapatan" required>
                            <?php while ($row = $akun_pendapatan_query->fetch_assoc()): 
                                $selected = ($row['id_akun'] == $data['id_akun_pendapatan']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $row['id_akun']; ?>" <?php echo $selected; ?>>
                                    <?php echo $row['id_akun'] . ' - ' . htmlspecialchars($row['nama_akun']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_aktif" name="is_aktif" value="1" <?php echo $data['is_aktif'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_aktif">Status Aktif (Centang untuk Aktif)</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Simpan Perubahan Layanan</button>
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