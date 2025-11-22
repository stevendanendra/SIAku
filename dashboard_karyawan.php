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

<div class="container mt-5">

    <div class="dashboard-header">
        <h1 style="color: #1abc9c;">Selamat Datang, Kasir <?php echo $nama_karyawan; ?>! 👋</h1>
        <p class="text-muted">Pusat Akses Modul Transaksi POS (Point of Sale).</p>
    </div>
    <hr>
    
    <div class="modul-group">
        <h2>Akses Cepat Modul Transaksi</h2>
        
        <div class="row row-cols-1 row-cols-md-3 g-4 mt-3">
            
            <div class="col">
                <div class="card h-100 shadow-sm text-center">
                    <div class="card-body">
                        <a href="penerimaan_kas_form.php" class="text-decoration-none text-dark">
                            <h3 class="card-title">💰 1. Penerimaan Kas</h3>
                            <p class="card-text">Catat Penjualan Jasa Tunai (Revenue)</p>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card h-100 shadow-sm text-center">
                    <div class="card-body">
                        <a href="pengeluaran_kas_form.php" class="text-decoration-none text-dark">
                            <h3 class="card-title">🛍️ 2. Pengeluaran Kas</h3>
                            <p class="card-text">Catat Pembelian Operasional/Beban</p>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card h-100 shadow-sm text-center">
                    <div class="card-body">
                        <a href="daftar_utang.php" class="text-decoration-none text-dark">
                            <h3 class="card-title">📝 3. Pembayaran Hutang</h3>
                            <p class="card-text">Proses Angsuran Utang Usaha</p>
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <div class="mt-5 text-center">
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const infoElement = document.getElementById('access-info');
        if (infoElement) {
            infoElement.innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_karyawan; ?>, ID <?php echo $id_karyawan; ?>)';
        }
    });
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>