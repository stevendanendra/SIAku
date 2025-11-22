<?php
// dashboard_owner.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

// --- AMBIL DAN TAMPILKAN PESAN DARI SESI (PAYROLL JURNAL) ---
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']); 
unset($_SESSION['success_message']);

$nama_owner = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);
?>

<?php include '_header.php'; // Header Bootstrap ?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard_owner.php">ShinyHome Owner Panel</a>
        <span class="navbar-text text-white">
            Selamat Datang, <?php echo $nama_owner; ?>!
        </span>
    </div>
</nav>

<div style="padding-top: 60px;">

    <h1 class="mb-4">Pusat Kendali Akuntansi 👑</h1>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">TRANSAKSI GAGAL: <?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($success_message): ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-info text-white">⚙️ Pengaturan & Data Master</div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-4 g-4">
                
                <div class="col">
                    <div class="card h-100 text-center border-success">
                        <div class="card-body">
                            <a href="input_modal_awal.php" class="text-decoration-none">
                                <h5 class="card-title text-success">💰 Modal Awal</h5>
                                <p class="card-text text-muted">Catat Setoran Modal Pertama/Tambahan.</p>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <a href="pengeluaran_kas_form.php" class="text-decoration-none">
                                <h5 class="card-title">💸 Pengeluaran & Prive</h5>
                                <p class="card-text text-muted">Catat Prive (Penarikan) dan Kas Operasional.</p>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <a href="crud_master_akun.php" class="text-decoration-none">
                                <h5 class="card-title">👤 Menu Kendali</h5>
                                <p class="card-text text-muted">Kelola Akun, Layanan, dan Pengguna.</p>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <a href="payroll_komponen.php" class="text-decoration-none">
                                <h5 class="card-title">💵 Payroll Setting</h5>
                                <p class="card-text text-muted">Atur Komponen Gaji & PPh 21.</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-secondary text-white">🧮 Pemrosesan & Pelaporan Akuntansi</div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-4 g-4">
                
                <div class="col-md-3">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <a href="payroll_proses_form.php" class="text-decoration-none">
                                <h5 class="card-title">💳 Pemrosesan Payroll</h5>
                                <p class="card-text text-muted">Hitung dan Jurnalkan Gaji Bulanan.</p>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <a href="laporan_jurnal.php" class="text-decoration-none">
                                <h5 class="card-title">📘 Jurnal Umum</h5>
                                <p class="card-text text-muted">Lihat semua mutasi Debit/Kredit.</p>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <a href="laporan_buku_besar.php" class="text-decoration-none">
                                <h5 class="card-title">📚 Buku Besar (BB)</h5>
                                <p class="card-text text-muted">Proses Posting & Lihat Saldo Akun.</p>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card h-100 text-center border-warning">
                        <div class="card-body">
                            <a href="laporan_laba_rugi.php" class="text-decoration-none">
                                <h5 class="card-title text-warning">📈 Laporan Laba Rugi</h5>
                                <p class="card-text text-muted">Analisis Pendapatan dan Beban.</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4 text-center">
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const infoElement = document.getElementById('access-info');
        if (infoElement) {
            infoElement.innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_owner; ?>, ID <?php echo $id_login; ?>)';
        }
    });
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>