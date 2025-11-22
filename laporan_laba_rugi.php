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
            $total_pendapatan += $nilai_saldo;
            $data_pendapatan[] = $row;
        } else {
            $total_beban += $nilai_saldo;
            $data_beban[] = $row;
        }
    }
}

// Hitung Laba Bersih
$laba_bersih = $total_pendapatan - $total_beban;
$laba_bersih_status = $laba_bersih >= 0 ? 'Laba Bersih' : 'Rugi Bersih';
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">

    <h1 class="mb-4 d-print-none">6. Laporan Laba Rugi Perusahaan</h1>
    <p class="text-muted d-print-none">Akses: **<?php echo $role_login; ?>** (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)</p> 
    <p class="d-print-none"><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr class="d-print-none">

    <div class="alert alert-warning mb-4 d-print-none">
        ⚠️ Laporan ini akurat HANYA JIKA Anda sudah menekan tombol **Proses Buku Besar** terbaru di Modul 5.
    </div>

    <div class="card shadow-sm p-4" id="print-area"> 
        <h2 class="text-center mb-4">Laporan Laba Rugi - ShinyHome</h2>
        
        <div class="table-responsive">
            <table class="table table-bordered table-sm" style="width: 100%;">
                
                <thead class="bg-primary text-white">
                    <tr>
                        <th style="width: 5%;"></th>
                        <th>PENDAPATAN (REVENUE)</th>
                        <th class="text-end" style="width: 25%;">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total_pendapatan = 0;
                    foreach ($data_pendapatan as $p): 
                        $grand_total_pendapatan += $p['saldo_saat_ini'];
                    ?>
                    <tr>
                        <td style="width: 5%;"></td>
                        <td class="fw-medium"><?php echo htmlspecialchars($p['nama_akun']); ?></td>
                        <td class="text-end"><?php echo number_format($p['saldo_saat_ini'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-info-subtle">
                        <td colspan="2" class="fw-bold">TOTAL PENDAPATAN</td>
                        <td class="text-end fw-bold"><?php echo number_format($grand_total_pendapatan, 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <br>
        
        <div class="table-responsive">
            <table class="table table-bordered table-sm" style="width: 100%;">
                
                <thead class="bg-danger text-white">
                    <tr>
                        <th style="width: 5%;"></th>
                        <th>BEBAN (EXPENSES)</th>
                        <th class="text-end" style="width: 25%;">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total_beban = 0;
                    foreach ($data_beban as $b): 
                        $grand_total_beban += $b['saldo_saat_ini'];
                    ?>
                    <tr>
                        <td style="width: 5%;"></td>
                        <td class="fw-medium"><?php echo htmlspecialchars($b['nama_akun']); ?></td>
                        <td class="text-end"><?php echo number_format($b['saldo_saat_ini'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-danger-subtle">
                        <td colspan="2" class="fw-bold">TOTAL BEBAN</td>
                        <td class="text-end fw-bold"><?php echo number_format($grand_total_beban, 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <br>
        
        <div class="alert <?php echo $laba_bersih >= 0 ? 'alert-success' : 'alert-danger'; ?> fw-bold">
            <h4 class="mb-0">
                <?php echo strtoupper($laba_bersih_status); ?>: 
                <span class="float-end">Rp <?php echo number_format(abs($laba_bersih), 0, ',', '.'); ?></span>
            </h4>
        </div>
        
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm mt-3 d-print-none">
             🖨️ Cetak Laporan / Simpan sebagai PDF
        </button>
        </div>

</div>

<script>
    // Tambahkan style print untuk menyembunyikan elemen non-laporan
    const style = document.createElement('style');
    style.innerHTML = '@media print { .d-print-none { display: none !important; } #print-area { margin: 0; padding: 0; box-shadow: none; } }';
    document.head.appendChild(style);
    
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>