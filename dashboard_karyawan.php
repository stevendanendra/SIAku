<?php
// dashboard_karyawan.php
session_start();
include 'koneksi.php'; 

// Verifikasi Role (Hanya Karyawan yang Boleh Akses)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan') { 
    // Jika tidak login atau bukan Karyawan, kembalikan ke login
    header("Location: login.html"); 
    exit(); 
}

$nama_karyawan = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Kasir');
$id_karyawan = $_SESSION['id_pengguna'];
$role_login = htmlspecialchars($_SESSION['role']);
?>

<?php include '_header.php'; // Header Bootstrap ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard_karyawan.php">👤 Kasir Panel - ShinyHome</a>
        <span class="navbar-text text-light small">
            Anda login sebagai: <?php echo $nama_karyawan; ?>
        </span>
    </div>
</nav>

<div class="container mt-5" style="padding-top: 25px;">

    <div class="dashboard-header">
        <h1 class="display-5 mb-3 border-bottom pb-2 text-primary">Pusat Transaksi Kasir</h1>
        <p class="text-muted">Selamat datang, **<?php echo $nama_karyawan; ?>**! Silakan pilih modul transaksi yang ingin diakses.</p>
    </div>
    <hr>
    
    <div class="modul-group">
        <h2 class="h5 fw-bold mb-4">Akses Cepat Modul Transaksi</h2>
        
        <div class="row row-cols-1 row-cols-md-4 g-4 mt-3">
            
            <div class="col">
                <a href="penerimaan_kas_form.php" class="text-decoration-none">
                    <div class="card h-100 shadow-sm text-center border-success hover-grow">
                        <div class="card-body">
                            <h2 class="display-6 text-success mb-2">💰</h2>
                            <h5 class="card-title text-success">1. Penerimaan Kas</h5>
                            <p class="card-text small text-muted">Catat Penjualan Jasa Tunai (POS).</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="pengeluaran_kas_form.php" class="text-decoration-none">
                    <div class="card h-100 shadow-sm text-center border-danger hover-grow">
                        <div class="card-body">
                            <h2 class="display-6 text-danger mb-2">🛍️</h2>
                            <h5 class="card-title text-danger">2. Pengeluaran Kas</h5>
                            <p class="card-text small text-muted">Catat Pembelian Operasional/Beban.</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="daftar_piutang.php" class="text-decoration-none">
                    <div class="card h-100 shadow-sm text-center border-info hover-grow">
                        <div class="card-body">
                            <h2 class="display-6 text-info mb-2">📥</h2>
                            <h5 class="card-title text-info">3. Penerimaan Piutang</h5>
                            <p class="card-text small text-muted">Catat Pelunasan Tagihan Pelanggan.</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="daftar_utang.php" class="text-decoration-none">
                    <div class="card h-100 shadow-sm text-center border-warning hover-grow">
                        <div class="card-body">
                            <h2 class="display-6 text-warning mb-2">📤</h2>
                            <h5 class="card-title text-warning">4. Pembayaran Hutang</h5>
                            <p class="card-text small text-muted">Proses Angsuran Utang Usaha (AP).</p>
                        </div>
                    </div>
                </a>
            </div>
            
        </div>
    </div>
    
    <div class="mt-5 mb-5 text-center">
        <a href="logout.php" class="btn btn-lg btn-danger shadow">Logout</a>
    </div>

</div>

<style>
.hover-grow {
    transition: transform 0.2s, box-shadow 0.2s;
}
.hover-grow:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
.small {
    font-size: 0.85rem;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const infoElement = document.getElementById('access-info');
        if (infoElement) {
            infoElement.innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_karyawan; ?>, ID <?php echo $id_karyawan; ?>)';
        }
    });
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>