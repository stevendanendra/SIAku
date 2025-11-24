<?php
// laporan_log_aktivitas.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

// --- INISIALISASI DATA LOGIN ---
$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);

// --- LOGIKA TAMPIL DATA LOG ---
$log_query = $conn->query("
    SELECT tgl_waktu, id_pengguna, username, deskripsi, modul, ip_address
    FROM tr_log_aktivitas 
    ORDER BY tgl_waktu DESC 
");

if (!$log_query) {
    die("Error Database Query Log: " . $conn->error);
}
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">
    
    <h1 class="mb-4 display-6 text-dark">Laporan Jejak Audit (Log Aktivitas)</h1>
    <p><a href='dashboard_owner.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <div class="alert alert-info small">
        Laporan ini mencatat setiap tindakan penting yang terjadi di sistem (Login, Tambah/Ubah/Hapus Master Data, Pemrosesan Jurnal).
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm table-hover align-middle" style="min-width: 900px;">
            <thead class="table-dark">
                <tr>
                    <th style="width: 15%;">Waktu</th>
                    <th style="width: 10%;">User ID</th>
                    <th style="width: 20%;">Modul</th>
                    <th>Deskripsi Aktivitas</th>
                    <th style="width: 10%;">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($log_query->num_rows > 0): ?>
                    <?php while($row = $log_query->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['tgl_waktu']; ?></td>
                        <td><?php echo $row['id_pengguna']; ?></td>
                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($row['modul']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                        <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">Belum ada aktivitas tercatat.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>