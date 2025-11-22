<?php
// crud_master_pengguna_edit.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_pengguna = $conn->real_escape_string($_GET['id']);
$error_message = '';
$success_message = '';

// --- INISIALISASI DATA LOGIN ---
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
// --------------------------------------------------

// --- LOGIKA UPDATE DATA (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $new_password = $_POST['password']; // Cek jika ada password baru
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $role = $conn->real_escape_string($_POST['role']);
    $is_menikah = isset($_POST['is_menikah']) ? 1 : 0;
    $jumlah_anak = $conn->real_escape_string($_POST['jumlah_anak']);
    $is_aktif = isset($_POST['is_aktif']) ? 1 : 0; // Ambil nilai is_aktif
    
    // Query Dasar UPDATE
    $sql = "UPDATE ms_pengguna SET 
            username = '$username', 
            email = '$email', 
            nama_lengkap = '$nama_lengkap', 
            role = '$role', 
            is_menikah = '$is_menikah', 
            jumlah_anak = '$jumlah_anak',
            is_aktif = '$is_aktif' 
            ";
            
    // Tambahkan password jika ada input baru
    if (!empty($new_password)) {
        $sql .= ", password = '$new_password'";
        $success_message = " dan password";
    }

    $sql .= " WHERE id_pengguna = '$id_pengguna'";
    
    if ($conn->query($sql) === TRUE) {
        // Setelah update data master, redirect dengan pesan sukses
        $_SESSION['success_message'] = "Data pengguna #$id_pengguna berhasil diperbarui" . $success_message . ".";
        header("Location: crud_master_pengguna.php"); 
        exit();
    } else {
        $error_message = "Error saat memperbarui pengguna (Username/Email mungkin sudah terdaftar): " . $conn->error;
    }
}

// --- LOGIKA AMBIL DATA LAMA (READ FOR UPDATE) ---
$sql_data = "SELECT * FROM ms_pengguna WHERE id_pengguna = '$id_pengguna'";
$result = $conn->query($sql_data);

if ($result->num_rows == 0) {
    die("Pengguna tidak ditemukan.");
}

$data = $result->fetch_assoc();
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Ubah Data Master Pengguna & Karyawan</h1>
    <div class="d-flex gap-3 mb-4 p-2 bg-light rounded shadow-sm">
        <a href="crud_master_akun.php" class="btn btn-outline-primary btn-sm">📊 Master Akun (COA)</a>
        <a href="crud_master_layanan.php" class="btn btn-outline-primary btn-sm">🧼 Master Layanan Jasa</a>
        <a href="crud_master_pengguna.php" class="btn btn-primary btn-sm active">👤 Master Pengguna & Karyawan</a>
    </div>

    <p><a href="crud_master_pengguna.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Pengguna</a></p>
    <hr>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm p-4">
                <h3 class="card-title mb-3">Detail Pengguna: <?php echo htmlspecialchars($data['nama_lengkap']); ?></h3>
                
                <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
                <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="mb-3">
                        <label class="form-label">ID Pengguna:</label>
                        <input type="text" class="form-control" value="<?php echo $id_pengguna; ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username:</label>
                        <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($data['username']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($data['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label fw-bold">Password Baru:</label> 
                        <input type="text" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin diubah">
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama_lengkap" class="form-label">Nama Lengkap:</label>
                        <input type="text" class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($data['nama_lengkap']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role:</label>
                        <select class="form-select" name="role" required>
                            <?php 
                            $role_options = ['Owner', 'Karyawan', 'Cleaner'];
                            foreach ($role_options as $role) {
                                $selected = ($role == $data['role']) ? 'selected' : '';
                                echo "<option value='$role' $selected>$role</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_menikah" name="is_menikah" value="1" <?php echo $data['is_menikah'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_menikah">Status Menikah</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jumlah_anak" class="form-label">Jumlah Anak (Maks 2 yang Ditunjang):</label> 
                        <input type="number" class="form-control" name="jumlah_anak" min="0" value="<?php echo $data['jumlah_anak']; ?>">
                    </div> 
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_aktif" name="is_aktif" value="1" <?php echo $data['is_aktif'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_aktif">Status Aktif (Login/Payroll)</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Simpan Perubahan Pengguna</button>
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