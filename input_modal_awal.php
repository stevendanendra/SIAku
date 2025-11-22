<?php
// input_modal_awal.php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

$nama_owner = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Owner');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">
    
    <h1 class="mb-4 text-primary">Input Setoran Modal (Setoran Berulang)</h1>
    <p><a href='dashboard_owner.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm p-4">
                <p class="card-text mb-4">Transaksi ini akan mencatat peningkatan kas perusahaan. Secara otomatis mendebit Akun **Kas (1101)** dan mengkredit Akun **Modal (3101)**.</p>
                
                <form action="input_modal_awal_proses.php" method="POST">
                    <div class="mb-3">
                        <label for="jumlah_modal" class="form-label fw-bold">Jumlah Setoran Modal (BIGINT):</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="jumlah_modal" name="jumlah_modal" required min="1000" placeholder="Contoh: 50000000">
                        </div>
                        <div class="form-text">Masukkan nilai tanpa titik atau koma.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mt-3">Catat Setoran Modal</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-info mt-3 mt-md-0">
                <h5>Petunjuk Penting:</h5>
                <ul>
                    <li>Gunakan modul ini untuk **setoran modal pertama** atau **setoran modal tambahan** dari Owner.</li>
                    <li>Sistem ini mengasumsikan kas perusahaan menggunakan sistem akrual (pencatatan langsung ke akun 1101).</li>
                    <li>Untuk menghindari kesalahan, nilai minimum setoran adalah Rp 1.000.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_owner; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>