<?php
// crud_master_supplier_edit.php
session_start();
include 'koneksi.php'; // Memuat fungsi logAktivitas()

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_supplier = $conn->real_escape_string($_GET['id']);
$error_message = '';
$success_message = '';

// --- INISIALISASI DATA LOG (FIX KRITIS) ---
// Memastikan semua variabel yang akan di-log dan ditampilkan bernilai non-NULL
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
$username_login = htmlspecialchars($_SESSION['username'] ?? 'SYSTEM_USER'); // FIX: Inisialisasi aman
// --------------------------------------------------

// --- LOGIKA UPDATE DATA (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    
    // Ambil data POST
    $nama_supplier_raw = $_POST['nama_supplier'] ?? '';
    $no_telepon_raw = $_POST['no_telepon'] ?? '';
    $alamat_lengkap_raw = $_POST['alamat_lengkap'] ?? '';
    $email_raw = $_POST['email'] ?? '';
    
    // Sanitasi dan Konversi empty string ke NULL
    $nama_supplier = $conn->real_escape_string($nama_supplier_raw); 
    $no_telepon = !empty($no_telepon_raw) ? $conn->real_escape_string($no_telepon_raw) : NULL;
    $alamat_lengkap = !empty($alamat_lengkap_raw) ? $conn->real_escape_string($alamat_lengkap_raw) : NULL;
    $email = !empty($email_raw) ? $conn->real_escape_string($email_raw) : NULL;

    // Validasi dasar
    if (empty($nama_supplier)) {
        $error_message = "Nama Pemasok wajib diisi.";
    } else {
        $sql = "UPDATE ms_supplier SET 
                nama_supplier = ?, 
                no_telepon = ?, 
                alamat_lengkap = ?, 
                email = ? 
                WHERE id_supplier = ?";
        
        $stmt = $conn->prepare($sql);
        
        // Binding parameter: ssssi (untuk string dan integer terakhir)
        $stmt->bind_param("ssssi", 
            $nama_supplier, 
            $no_telepon, 
            $alamat_lengkap, 
            $email, 
            $id_supplier
        );
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data Pemasok #$id_supplier berhasil diperbarui.";
            
            // LOG AKTIVITAS: Menggunakan variabel yang sudah diinisialisasi dengan aman
            if (function_exists('logAktivitas')) {
                 logAktivitas($id_login, $username_login, 
                              "Mengubah detail pemasok ID $id_supplier", 
                              "Master Supplier");
            }
            
            header("Location: crud_master_supplier.php"); 
            exit();
        } else {
            $error_message = "Error saat memperbarui pemasok (Email/Telepon mungkin sudah terdaftar): " . $conn->error;
        }
        $stmt->close();
    }
}

// --- LOGIKA AMBIL DATA LAMA (READ FOR UPDATE) ---
$sql_data = "SELECT * FROM ms_supplier WHERE id_supplier = '$id_supplier'";
$result = $conn->query($sql_data);

if ($result->num_rows == 0) {
    die("Pemasok tidak ditemukan.");
}

$data = $result->fetch_assoc();
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Ubah Data Master Pemasok</h1>
    
    <p><a href="crud_master_supplier.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Pemasok</a></p>
    <hr>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm p-4">
                <h3 class="card-title mb-3">Edit Pemasok: **<?php echo htmlspecialchars($data['nama_supplier']); ?>**</h3>
                
                <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
                <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="mb-3">
                        <label class="form-label">ID Pemasok:</label>
                        <input type="text" class="form-control" value="<?php echo $id_supplier; ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="nama_supplier" class="form-label">Nama Pemasok:</label>
                        <input type="text" class="form-control" name="nama_supplier" value="<?php echo htmlspecialchars($data['nama_supplier']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="no_telepon" class="form-label">No. Telepon:</label>
                        <input type="text" class="form-control" name="no_telepon" value="<?php echo htmlspecialchars($data['no_telepon']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="alamat_lengkap" class="form-label">Alamat Lengkap:</label>
                        <input type="text" class="form-control" name="alamat_lengkap" value="<?php echo htmlspecialchars($data['alamat_lengkap']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($data['email']); ?>">
                    </div>
                    
                    <div class="alert alert-info small mt-3">
                         Tanggal Daftar: **<?php echo htmlspecialchars($data['tgl_daftar']); ?>**
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Simpan Perubahan Pemasok</button>
                </form>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_login; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>