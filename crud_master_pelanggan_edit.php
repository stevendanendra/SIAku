<?php
// crud_master_pelanggan_edit.php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) {
    header("Location: login.html");
    exit();
}

$id_pelanggan = $conn->real_escape_string($_GET['id']);

// --- INISIALISASI DATA LOGIN ---
$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);

// --- LOGIKA UPDATE DATA ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {

    $nama_pelanggan = $conn->real_escape_string($_POST['nama_pelanggan']);
    $no_telepon = $conn->real_escape_string($_POST['no_telepon']);
    $alamat_lengkap = $conn->real_escape_string($_POST['alamat_lengkap']);
    $email = $conn->real_escape_string($_POST['email']);

    $sql = "UPDATE ms_pelanggan SET 
                nama_pelanggan = '$nama_pelanggan',
                no_telepon = '$no_telepon',
                alamat_lengkap = '$alamat_lengkap',
                email = '$email'
            WHERE id_pelanggan = '$id_pelanggan'";

    if ($conn->query($sql) === TRUE) {
        $_SESSION['success_message'] = "Data pelanggan #$id_pelanggan berhasil diperbarui.";
        header("Location: crud_master_pelanggan.php");
        exit();
    } else {
        $error_message = "Gagal memperbarui pelanggan: " . $conn->error;
    }
}

// --- AMBIL DATA LAMA ---
$sql_data = "SELECT * FROM ms_pelanggan WHERE id_pelanggan = '$id_pelanggan'";
$result = $conn->query($sql_data);

if ($result->num_rows == 0) {
    die("Data pelanggan tidak ditemukan.");
}

$data = $result->fetch_assoc();
?>

<?php include '_header.php'; ?>

<div class="container mt-5">

    <h1 class="mb-4">Ubah Data Master Pelanggan</h1>

    <p><a href="crud_master_pelanggan.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Pelanggan</a></p>
    <hr>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm p-4">
                <h3 class="card-title mb-3">Edit Pelanggan: <strong><?php echo htmlspecialchars($data['nama_pelanggan']); ?></strong></h3>

                <?php if (!empty($error_message)) echo "<div class='alert alert-danger'>$error_message</div>"; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="edit">

                    <div class="mb-3">
                        <label class="form-label">ID Pelanggan:</label>
                        <input type="text" class="form-control" value="<?php echo $id_pelanggan; ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="nama_pelanggan" class="form-label">Nama Pelanggan:</label>
                        <input type="text" class="form-control" name="nama_pelanggan" 
                            value="<?php echo htmlspecialchars($data['nama_pelanggan']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="no_telepon" class="form-label">No. Telepon:</label>
                        <input type="text" class="form-control" name="no_telepon" 
                            value="<?php echo htmlspecialchars($data['no_telepon']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="alamat_lengkap" class="form-label">Alamat Lengkap:</label>
                        <input type="text" class="form-control" name="alamat_lengkap" 
                            value="<?php echo htmlspecialchars($data['alamat_lengkap']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" name="email" 
                            value="<?php echo htmlspecialchars($data['email']); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3">Simpan Perubahan</button>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan -->
        <div class="col-md-6">
            <div class="card shadow-sm p-4 bg-light">
                <h4 class="h5">Informasi Pelanggan</h4>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item"><strong>Tanggal Daftar:</strong> 
                        <?php echo htmlspecialchars($data['tgl_daftar']); ?></li>
                    <li class="list-group-item"><strong>Email:</strong> 
                        <?php echo htmlspecialchars($data['email']); ?></li>
                    <li class="list-group-item"><strong>No. Telepon:</strong> 
                        <?php echo htmlspecialchars($data['no_telepon']); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('access-info').innerHTML =
    'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; ?>
