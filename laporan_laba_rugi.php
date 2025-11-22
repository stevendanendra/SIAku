<?php
// laporan_laba_rugi.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

// --- INISIALISASI DATA LOGIN ---
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Owner');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');

// --- LOGIKA AMBIL SALDO FINAL ---
// Ambil semua akun Pendapatan (4xxx) dan Beban (5xxx)
$laba_rugi_query = $conn->query("
    SELECT 
        id_akun, 
        nama_akun, 
        tipe_akun, 
        saldo_saat_ini 
    FROM ms_akun 
    WHERE tipe_akun IN ('Pendapatan', 'Beban') 
    ORDER BY tipe_akun DESC, id_akun ASC
");

$total_pendapatan = 0;
$total_beban = 0;
$data_pendapatan = [];
$data_beban = [];

if ($laba_rugi_query) {
    while ($row = $laba_rugi_query->fetch_assoc()) {
        $nilai_saldo = $row['saldo_saat_ini'];
        
        if ($row['tipe_akun'] == 'Pendapatan') {
            // Saldo Pendapatan normalnya Kredit. Jika saldo_saat_ini positif, itu adalah nilai Kredit/Pendapatan.
            // Jika ada nilai negatif, berarti itu mutasi Debit yang mengurangi pendapatan.
            $total_pendapatan += $nilai_saldo;
            $data_pendapatan[] = $row;
        } else {
            // Saldo Beban normalnya Debit. Mutasi Beban dicatat sebagai nilai positif (Debit).
            // Kita harus mengambil nilai absolutnya jika Saldo Normalnya Debit.
            // Kita asumsikan saldo yang tersimpan di DB sudah mewakili total Debit (Beban).
            $total_beban += $nilai_saldo;
            $data_beban[] = $row;
        }
    }
}

// Hitung Laba Bersih
$laba_bersih = $total_pendapatan - $total_beban;
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">

    <h1 class="mb-4">6. Laporan Laba Rugi Perusahaan</h1>
    
    <p class="text-muted">Akses: **<?php echo $role_login; ?>** (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)</p> 
    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>

    <div class="alert alert-warning mb-4">
        ⚠️ Laporan ini akurat HANYA JIKA Anda sudah menekan tombol **Proses Buku Besar** terbaru di Modul 5.
    </div>

    <div class="card shadow-sm p-4">
        <h2 class="text-center mb-4">Laporan Laba Rugi - ShinyHome</h2>
        
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead class="table-info">
                    <tr>
                        <th colspan="2">PENDAPATAN (REVENUE)</th>
                        <th class="text-end">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total_pendapatan = 0;
                    foreach ($data_pendapatan as $p): 
                        $grand_total_pendapatan += $p['saldo_saat_ini'];
                    ?>
                    <tr>
                        <td style="width: 10px;"></td>
                        <td><?php echo htmlspecialchars($p['nama_akun']); ?></td>
                        <td class="text-end"><?php echo number_format($p['saldo_saat_ini'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-success">
                        <td colspan="2" class="fw-bold">TOTAL PENDAPATAN</td>
                        <td class="text-end fw-bold"><?php echo number_format($grand_total_pendapatan, 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <br>
        
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead class="table-danger">
                    <tr>
                        <th colspan="2">BEBAN (EXPENSES)</th>
                        <th class="text-end">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total_beban = 0;
                    foreach ($data_beban as $b): 
                        $grand_total_beban += $b['saldo_saat_ini'];
                    ?>
                    <tr>
                        <td style="width: 10px;"></td>
                        <td><?php echo htmlspecialchars($b['nama_akun']); ?></td>
                        <td class="text-end"><?php echo number_format($b['saldo_saat_ini'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-danger">
                        <td colspan="2" class="fw-bold">TOTAL BEBAN</td>
                        <td class="text-end fw-bold"><?php echo number_format($grand_total_beban, 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <br>
        
        <div class="alert <?php echo $laba_bersih >= 0 ? 'alert-success' : 'alert-danger'; ?>">
            <h4 class="mb-0 text-center">
                LABA BERSIH PERUSAHAAN: 
                <span class="float-end">Rp <?php echo number_format($laba_bersih, 0, ',', '.'); ?></span>
            </h4>
        </div>
        
    </div>

</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?> 