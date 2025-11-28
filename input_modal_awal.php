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

$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">
    
    <h1 class="mb-4 text-primary">Input Setoran Modal</h1>
    <p><a href='dashboard_owner.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm p-4">
                <h5 class="card-title mb-4">Catat Peningkatan Modal</h5>
                <p class="card-text mb-4 small">Transaksi ini akan mencatat **peningkatan kas** perusahaan. Secara otomatis mendebit Akun **Kas (1101)** dan mengkredit Akun **Modal (3101)**.</p>
                
                <form action="input_modal_awal_proses.php" method="POST">
                    <div class="mb-3">
                        <label for="jumlah_modal" class="form-label fw-bold">Jumlah Setoran Modal (Rp):</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control text-end" id="jumlah_modal" name="jumlah_modal" required min="1000" placeholder="Contoh: 50000000">
                        </div>
                        <div class="form-text">Masukkan nilai minimal Rp 1.000 (BIGINT).</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mt-3">Catat Setoran Modal</button>
                </form>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow-sm p-4 h-100 bg-info-subtle">
                 <h5 class="text-info">Petunjuk Penggunaan:</h5>
                 <ul class="list-group list-group-flush small mt-3">
                     <li class="list-group-item bg-transparent">Gunakan modul ini untuk **setoran modal pertama** atau **setoran modal tambahan** dari Owner.</li>
                     <li class="list-group-item bg-transparent">Jurnal yang terbentuk adalah: 
                         <span class="fw-bold text-primary">Debit: Kas (1101)</span> dan 
                         <span class="fw-bold text-success">Kredit: Modal Pemilik (3101)</span>.</li>
                     <li class="list-group-item bg-transparent">Sistem mengasumsikan pencatatan ke Kas (1101) secara akrual.</li>
                 </ul>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_owner; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>