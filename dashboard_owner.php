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

<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard_owner.php">👑 Owner Panel - ShinyHome Accounting</a>
        <span class="navbar-text text-light small">
            Anda login sebagai: <?php echo $nama_owner; ?>
        </span>
    </div>
</nav>

<div class="container mt-5" style="padding-top: 25px;">
    
    <h1 class="display-5 mb-3 border-bottom pb-2 text-dark">Pusat Kendali Akuntansi</h1>
    <p class="text-muted mb-4">Gunakan menu di bawah untuk mengelola data, memproses transaksi, dan melihat laporan keuangan perusahaan.</p>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>TRANSAKSI GAGAL:</strong> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Berhasil!</strong> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-5 shadow-lg border-primary">
        <div class="card-header bg-primary text-white fw-bold h5">
            <span class="me-2">🛠️</span> 1. Pengaturan Awal & Input Transaksi Dasar
        </div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-3 g-4"> 
                
                <div class="col">
                    <a href="input_modal_awal.php" class="text-decoration-none">
                        <div class="card h-100 text-center shadow-sm hover-grow border-success border-2">
                            <div class="card-body">
                                <h2 class="display-6 text-success mb-2">💰</h2>
                                <h5 class="card-title text-success">1. Input Modal Awal</h5>
                                <p class="card-text text-muted small">Catat setoran modal pertama atau tambahan.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col">
                    <a href="pengeluaran_kas_form.php" class="text-decoration-none">
                        <div class="card h-100 text-center shadow-sm hover-grow border-danger border-2">
                            <div class="card-body">
                                <h2 class="display-6 text-danger mb-2">💸</h2>
                                <h5 class="card-title text-danger">2. Pengeluaran & Prive</h5>
                                <p class="card-text text-muted small">Jurnal kas keluar untuk operasional dan penarikan pribadi (Prive).</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col">
                    <a href="crud_master_akun.php" class="text-decoration-none">
                        <div class="card h-100 text-center shadow-sm hover-grow border-dark border-2">
                            <div class="card-body">
                                <h2 class="display-6 text-dark mb-2">📑</h2>
                                <h5 class="card-title text-dark">3. Master Data & Payroll</h5>
                                <p class="card-text text-muted small">Kelola COA, Layanan, Pengguna, dan Komponen Gaji.</p>
                            </div>
                        </div>
                    </a>
                </div>
                
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-lg border-secondary">
        <div class="card-header bg-secondary text-white fw-bold h5">
            <span class="me-2">📈</span> 2. Pemrosesan Akuntansi & Laporan Keuangan
        </div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-3 g-4">
                
                <div class="col">
                    <a href="payroll_proses_form.php" class="text-decoration-none">
                        <div class="card h-100 text-center shadow-sm hover-grow">
                            <div class="card-body">
                                <h2 class="display-6 text-primary mb-2">💳</h2>
                                <h5 class="card-title text-primary small">4. Pemrosesan Payroll</h5>
                                <p class="card-text text-muted xsmall">Hitung dan Jurnalkan gaji bulanan karyawan.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col">
                    <a href="laporan_jurnal.php" class="text-decoration-none">
                        <div class="card h-100 text-center shadow-sm hover-grow">
                            <div class="card-body">
                                <h2 class="display-6 text-info mb-2">📘</h2>
                                <h5 class="card-title text-info small">5. Jurnal Umum</h5>
                                <p class="card-text text-muted xsmall">Lihat semua mutasi Debit/Kredit yang tercatat.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col">
                    <a href="laporan_buku_besar.php" class="text-decoration-none">
                        <div class="card h-100 text-center shadow-sm hover-grow">
                            <div class="card-body">
                                <h2 class="display-6 text-warning mb-2">📚</h2>
                                <h5 class="card-title text-warning small">6. Buku Besar (BB)</h5>
                                <p class="card-text text-muted xsmall">Proses Posting & Lihat Saldo Akun.</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col">
                    <a href="laporan_laba_rugi.php" class="text-decoration-none">
                        <div class="card h-100 text-center shadow-sm hover-grow border-success">
                            <div class="card-body">
                                <h2 class="display-6 text-success mb-2">📊</h2>
                                <h5 class="card-title text-success small">7. Laporan Laba Rugi</h5>
                                <p class="card-text text-muted xsmall">Analisis pendapatan dan beban perusahaan.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col">
                    <a href="laporan_log_aktivitas.php" class="text-decoration-none">
                        <div class="card h-100 text-center shadow-sm hover-grow border-secondary">
                            <div class="card-body">
                                <h2 class="display-6 text-secondary mb-2">📜</h2>
                                <h5 class="card-title text-secondary small">8. Laporan Log Aktivitas</h5>
                                <p class="card-text text-muted xsmall">Jejak audit dan pemantauan aktivitas pengguna.</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col">
                    <div class="card h-100 text-center bg-white border-0 shadow-none">
                        </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <div class="mt-5 mb-5 text-center">
        <a href="logout.php" class="btn btn-lg btn-outline-danger shadow">Logout dari Panel Owner</a>
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
.xsmall {
    font-size: 0.75rem;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const infoElement = document.getElementById('access-info');
        if (infoElement) {
            infoElement.innerHTML = 'Akses: **<?php echo $role_login; ?>** (<?php echo $nama_owner; ?>, ID <?php echo $id_login; ?>)';
        }
    });
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>