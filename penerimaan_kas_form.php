<?php
// penerimaan_kas_form.php
session_start();
include 'koneksi.php'; 

// =======================================================
// LOGIC NOTIFIKASI KRITIS: Ambil pesan dari session dan hapus
// =======================================================
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']); 
unset($_SESSION['success_message']);
// =======================================================


// Ambil data Master untuk dropdown
$pelanggan_query = $conn->query("SELECT id_pelanggan, nama_pelanggan FROM ms_pelanggan");
// Filter Layanan Aktif
$layanan_query = $conn->query("SELECT id_layanan, nama_layanan, harga_jual FROM ms_layanan WHERE is_aktif = TRUE");

// Pastikan Kasir sudah login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan') { 
    header("Location: login.html"); 
    exit(); 
}

$id_karyawan = $_SESSION['id_pengguna'];
$nama_karyawan = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Kasir');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">

    <h1 class="mb-4">1. Penerimaan Kas (POS Tunai)</h1>

    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <div class="card shadow-sm p-4">
        <h3 class="card-title h5 text-primary">Catat Penjualan Jasa</h3>
        <form action="penerimaan_kas_proses.php" method="POST">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="pelanggan" class="form-label">Pelanggan:</label>
                        <select id="pelanggan" name="id_pelanggan" class="form-select" required>
                            <?php 
                            if ($pelanggan_query->num_rows == 0) {
                                echo '<option value="">-- Tabel Pelanggan Kosong --</option>';
                            }
                            while ($row = $pelanggan_query->fetch_assoc()): ?>
                                <option value="<?php echo $row['id_pelanggan']; ?>">
                                    <?php echo htmlspecialchars($row['nama_pelanggan']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="layanan" class="form-label">Layanan (Jasa):</label>
                        <select id="layanan" name="id_layanan" class="form-select" required 
                                onchange="document.getElementById('total_jual').value = this.options[this.selectedIndex].getAttribute('data-harga');">
                            <option value="">-- Pilih Layanan --</option>
                            <?php 
                            if ($layanan_query && $layanan_query->num_rows > 0) {
                                while ($row = $layanan_query->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id_layanan']; ?>" 
                                            data-harga="<?php echo $row['harga_jual']; ?>">
                                        <?php echo htmlspecialchars($row['nama_layanan']) . ' (Rp ' . number_format($row['harga_jual'], 0, ',', '.') . ')'; ?>
                                    </option>
                                <?php endwhile; 
                            } else {
                                 echo '<option value="">-- TIDAK ADA LAYANAN AKTIF --</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="total_jual" class="form-label">Total Penjualan (BIGINT):</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" id="total_jual" name="total_jual" class="form-control" readonly required>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        Metode Pembayaran: **TUNAI** (Debit Kas 1101)
                    </div>

                    <input type="hidden" name="metode_bayar" value="Kas">
                    
                    <button type="submit" class="btn btn-success w-100 mt-4">Catat Transaksi Kas</button>
                </div>
            </div>
            
        </form>
    </div>
    
    <hr class="my-4">
    
    <p class="text-muted">Akses: Kasir (Siska, ID <?php echo $id_karyawan; ?>)</p> 
    <p><a href='dashboard_karyawan.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard</a></p>

</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_karyawan; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>