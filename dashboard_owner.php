<?php
// dashboard_owner.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

$error_message   = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';

unset($_SESSION['error_message']); 
unset($_SESSION['success_message']);

$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);
?>

<?php include '_header.php'; ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard_owner.php">👑 Owner Panel - ShinyHome Accounting</a>
        <span class="navbar-text text-light small">
            Login sebagai: <?php echo $nama_owner; ?>
        </span>
    </div>
</nav>

<div class="container mt-5" style="padding-top: 25px;">
    
    <h1 class="display-5 mb-3 border-bottom pb-2 text-dark">Pusat Kendali Akuntansi</h1>
    <p class="text-muted mb-4">Akses modul pencatatan transaksi, laporan, dan pengaturan sistem.</p>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>Gagal:</strong> <?= htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <strong>Berhasil!</strong> <?= htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ======================== -->
    <!-- PANEL PENGATURAN OWNER -->
    <!-- ======================== -->
    <div class="card mb-5 shadow-lg border-primary">
        <div class="card-header bg-primary text-white fw-bold h5">
            🛠️ Pengaturan & Input Transaksi Owner
        </div>

        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-3 g-4">
                
                <!-- Input Modal -->
                <div class="col">
                    <a href="input_modal_awal.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow border-success border-2">
                            <div class="card-body">
                                <h2 class="text-success">💰</h2>
                                <h5 class="card-title text-success">Input Modal</h5>
                                <p class="text-muted small">Pencatatan modal awal & tambahan modal.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Penarikan Prive -->
                <div class="col">
                    <a href="pengeluaran_kas_form.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow border-danger border-2">
                            <div class="card-body">
                                <h2 class="text-danger">💸</h2>
                                <h5 class="card-title text-danger">Penarikan Prive</h5>
                                <p class="text-muted small">Pencatatan prive & kas keluar operasional.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Master Data -->
                <div class="col">
                    <a href="crud_master_akun.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow border-dark border-2">
                            <div class="card-body">
                                <h2 class="text-dark">📑</h2>
                                <h5 class="card-title text-dark">Master Data Sistem</h5>
                                <p class="text-muted small">COA, Layanan, Pelanggan, Supplier & Payroll.</p>
                            </div>
                        </div>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- ======================== -->
    <!-- PANEL PROSES & LAPORAN -->
    <!-- ======================== -->
    <div class="card mb-4 shadow-lg border-secondary">
        <div class="card-header bg-secondary text-white fw-bold h5">
            📈 Pemrosesan Akuntansi & Laporan
        </div>

        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-3 g-4">

                <!-- Payroll -->
                <div class="col">
                    <a href="payroll_proses_form.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow">
                            <div class="card-body">
                                <h2 class="text-primary">💳</h2>
                                <h5 class="card-title text-primary">Pemrosesan Payroll</h5>
                                <p class="text-muted small">Hitung & jurnalkan gaji karyawan.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Jurnal Umum -->
                <div class="col">
                    <a href="laporan_jurnal.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow">
                            <div class="card-body">
                                <h2 class="text-info">📘</h2>
                                <h5 class="card-title text-info">Jurnal Umum</h5>
                                <p class="text-muted small">Daftar seluruh mutasi debit/kredit.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Buku Besar -->
                <div class="col">
                    <a href="laporan_buku_besar.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow">
                            <div class="card-body">
                                <h2 class="text-warning">📚</h2>
                                <h5 class="card-title text-warning">Buku Besar</h5>
                                <p class="text-muted small">Posting & saldo setiap akun.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Laba Rugi -->
                <div class="col">
                    <a href="laporan_laba_rugi.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow border-success">
                            <div class="card-body">
                                <h2 class="text-success">📊</h2>
                                <h5 class="card-title text-success">Laporan Laba Rugi</h5>
                                <p class="text-muted small">Analisis pendapatan, beban, & profit.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Log Aktivitas -->
                <div class="col">
                    <a href="laporan_log_aktivitas.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow border-secondary">
                            <div class="card-body">
                                <h2 class="text-secondary">📜</h2>
                                <h5 class="card-title text-secondary">Log Aktivitas</h5>
                                <p class="text-muted small">Audit trail & tracking user.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- == NEW MODULES ADDED BELOW == -->

                <!-- Kartu Hutang Supplier -->
                <div class="col">
                    <a href="laporan_kartu_hutang.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow border-danger border-2">
                            <div class="card-body">
                                <h2 class="text-danger">📕</h2>
                                <h5 class="card-title text-danger">Kartu Hutang Supplier</h5>
                                <p class="text-muted small">Riwayat utang & pembayaran ke supplier.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Kartu Piutang Pelanggan -->
                <div class="col">
                    <a href="laporan_kartu_piutang.php" class="text-decoration-none">
                        <div class="card h-100 text-center hover-grow border-primary border-2">
                            <div class="card-body">
                                <h2 class="text-primary">📗</h2>
                                <h5 class="card-title text-primary">Kartu Piutang Pelanggan</h5>
                                <p class="text-muted small">Riwayat tagihan & pelunasan pelanggan.</p>
                            </div>
                        </div>
                    </a>
                </div>

            </div>
        </div>
    </div>
    
    <div class="mt-5 mb-5 text-center">
        <a href="logout.php" class="btn btn-lg btn-danger shadow">Logout</a>
    </div>

</div>

<style>
.hover-grow {
    transition: .2s ease;
}
.hover-grow:hover {
    transform: translateY(-6px);
    box-shadow: 0 0.75rem 1.25rem rgba(0,0,0,0.15);
}
</style>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; ?>
