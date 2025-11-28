<?php
// laporan_jurnal.php
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

$error_message = '';
$success_message = '';

// --- LOGIKA FILTER TANGGAL DINAMIS ---
$filter_condition = "1=1"; // Default: tampilkan semua
$tgl_filter = isset($_GET['tgl_filter']) ? $_GET['tgl_filter'] : '';
$bulan_filter = isset($_GET['bulan_filter']) ? $_GET['bulan_filter'] : '';
$tahun_filter = isset($_GET['tahun_filter']) ? $_GET['tahun_filter'] : '';

// Filter Cepat Flags
$hari_ini = isset($_GET['hari']) ? true : false;
$bulan_ini = isset($_GET['bulan']) ? true : false;
$tahun_ini = isset($_GET['tahun']) ? true : false;


if ($hari_ini) {
    // Filter Hari Ini (Menggunakan fungsi SQL CURDATE())
    $filter_condition = "j.tgl_jurnal = CURDATE()";
} elseif ($bulan_ini) {
    // Filter Bulan Ini (Menggunakan fungsi SQL DATE_FORMAT)
    $filter_condition = "DATE_FORMAT(j.tgl_jurnal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
} elseif ($tahun_ini) {
    // Filter Tahun Ini (Menggunakan fungsi SQL YEAR)
    $filter_condition = "YEAR(j.tgl_jurnal) = YEAR(CURDATE())";
} elseif (!empty($tgl_filter)) {
    // Filter berdasarkan Tanggal Penuh (YYYY-MM-DD)
    $tgl_filter_safe = $conn->real_escape_string($tgl_filter);
    $filter_condition = "j.tgl_jurnal = '$tgl_filter_safe'";
} elseif (!empty($bulan_filter)) {
    // Filter berdasarkan Bulan dan Tahun (YYYY-MM)
    $bulan_filter_safe = $conn->real_escape_string($bulan_filter);
    $filter_condition = "DATE_FORMAT(j.tgl_jurnal, '%Y-%m') = '$bulan_filter_safe'";
} elseif (!empty($tahun_filter)) {
    // Filter berdasarkan Tahun Saja (YYYY)
    $tahun_filter_safe = $conn->real_escape_string($tahun_filter);
    $filter_condition = "YEAR(j.tgl_jurnal) = '$tahun_filter_safe'";
}


// --- LOGIKA TAMPIL DATA JURNAL UMUM (READ) ---
// Memasukkan kondisi WHERE ke dalam query
$jurnal_query = $conn->query("
    SELECT 
        j.tgl_jurnal, 
        j.no_bukti, 
        j.deskripsi,
        j.posisi, 
        j.nilai,
        j.id_akun,
        a.nama_akun
    FROM tr_jurnal_umum j
    JOIN ms_akun a ON j.id_akun = a.id_akun
    WHERE $filter_condition 
    ORDER BY j.id_jurnal ASC 
");

// Cek jika query gagal
if (!$jurnal_query) {
    $error_message = "Error mengambil data jurnal: " . $conn->error;
}
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5" style="padding-top: 20px;">
    
    <h1 class="mb-4">Laporan Jurnal Umum (Semua Transaksi)</h1>
    
    <p class="text-muted"><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <h2>1. Filter Laporan</h2>
    
    <div class="mb-3 d-flex gap-2">
        <a href="laporan_jurnal.php?hari=true"><button type="button" class="btn btn-sm btn-outline-info">Hari Ini</button></a>
        <a href="laporan_jurnal.php?bulan=true"><button type="button" class="btn btn-sm btn-outline-info">Bulan Ini</button></a>
        <a href="laporan_jurnal.php?tahun=true"><button type="button" class="btn btn-sm btn-outline-info">Tahun Ini</button></a>
        <a href="laporan_jurnal.php"><button type="button" class="btn btn-sm btn-secondary">Reset Filter</button></a>
    </div>
    
    <div class="card p-3 mb-4 shadow-sm">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="tgl_filter" class="form-label">Filter Tanggal Penuh:</label>
                <input type="date" class="form-control" name="tgl_filter" value="<?php echo htmlspecialchars($tgl_filter); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter Tanggal</button>
            </div>
            
            <div class="col-md-1 text-center">
                <p class="mb-0">ATAU</p>
            </div>

            <div class="col-md-3">
                <label for="bulan_filter" class="form-label">Filter Bulan & Tahun:</label>
                <input type="month" class="form-control" name="bulan_filter" value="<?php echo htmlspecialchars($bulan_filter); ?>">
                <button type="submit" class="btn btn-primary btn-sm mt-1 w-100">Filter Bulan</button>
            </div>
            
            <div class="col-md-2">
                <label for="tahun_filter" class="form-label">Filter Tahun:</label>
                <input type="number" class="form-control" name="tahun_filter" value="<?php echo htmlspecialchars($tahun_filter); ?>" placeholder="YYYY">
                <button type="submit" class="btn btn-primary btn-sm mt-1 w-100">Filter Tahun</button>
            </div>
        </form>
    </div>
    
    <h2>2. Rincian Jurnal</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-dark">
                <tr>
                    <th style="width: 10%;">Tanggal</th>
                    <th style="width: 15%;">No. Bukti</th>
                    <th style="width: 30%;">Deskripsi Transaksi</th>
                    <th style="width: 10%;">No. Akun</th>
                    <th style="width: 15%;">Nama Akun</th>
                    <th class="text-end" style="width: 10%;">Debit (D)</th>
                    <th class="text-end" style="width: 10%;">Kredit (K)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_no_bukti = null;
                $total_debit_transaksi = 0;
                $total_kredit_transaksi = 0;
                $first_row_processed = false;

                while ($row = $jurnal_query->fetch_assoc()): 
                    
                    $nilai_debit = ($row['posisi'] == 'D') ? $row['nilai'] : 0;
                    $nilai_kredit = ($row['posisi'] == 'K') ? $row['nilai'] : 0;
                    
                    // --- LOGIKA GROUPING DAN TOTAL ---
                    if ($current_no_bukti !== $row['no_bukti'] && $first_row_processed) {
                        // 1. Tampilkan Total Transaksi Sebelumnya
                        // FIX UI: Gunakan background netral
                        echo '<tr class="bg-light">';
                        echo '<td colspan="5" class="text-end fw-bold">TOTAL TRANSAKSI ' . htmlspecialchars($current_no_bukti) . '</td>';
                        echo '<td class="text-end fw-bold">' . number_format($total_debit_transaksi, 0, ',', '.') . '</td>';
                        echo '<td class="text-end fw-bold">' . number_format($total_kredit_transaksi, 0, ',', '.') . '</td>';
                        echo '</tr>';

                        // 2. Tampilkan Status Keseimbangan
                        if ($total_debit_transaksi == $total_kredit_transaksi) {
                            // FIX UI: Ganti warna success menjadi netral/minimalis
                            echo '<tr class="bg-success-subtle"><td colspan="7" class="text-center fw-bold text-success">✔️ JURNAL SEIMBANG</td></tr>';
                        } else {
                            // FIX UI: Ganti warna danger menjadi lebih tegas
                            $selisih = abs($total_debit_transaksi - $total_kredit_transaksi);
                            echo '<tr class="bg-danger-subtle"><td colspan="7" class="text-center fw-bold text-danger">❌ JURNAL TIDAK SEIMBANG! SELISIH: Rp ' . number_format($selisih, 0, ',', '.') . '</td></tr>';
                        }
                        
                        // 3. Reset akumulator
                        $total_debit_transaksi = 0;
                        $total_kredit_transaksi = 0;
                        
                        // Tambahkan baris pemisah antar transaksi
                        echo '<tr><td colspan="7" style="height: 15px; background-color: #f7f7f7; border: none;"></td></tr>';
                    }
                    
                    $current_no_bukti = $row['no_bukti'];
                    $total_debit_transaksi += $nilai_debit;
                    $total_kredit_transaksi += $nilai_kredit;
                    $first_row_processed = true;
                ?>
                <tr>
                    <td><?php echo $row['tgl_jurnal']; ?></td>
                    <td><?php echo htmlspecialchars($row['no_bukti']); ?></td>
                    <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                    <td><?php echo $row['id_akun']; ?></td>
                    <td><?php echo htmlspecialchars($row['nama_akun']); ?></td>
                    <td class="text-end text-primary fw-bold"><?php echo $nilai_debit > 0 ? number_format($nilai_debit, 0, ',', '.') : ''; ?></td>
                    <td class="text-end text-danger fw-bold"><?php echo $nilai_kredit > 0 ? number_format($nilai_kredit, 0, ',', '.') : ''; ?></td>
                </tr>
                <?php endwhile; ?>

                <?php 
                // 4. Tampilkan Total Transaksi TERAKHIR
                if ($first_row_processed) {
                    echo '<tr class="bg-light">';
                    echo '<td colspan="5" class="text-end fw-bold">TOTAL TRANSAKSI ' . htmlspecialchars($current_no_bukti) . '</td>';
                    echo '<td class="text-end fw-bold">' . number_format($total_debit_transaksi, 0, ',', '.') . '</td>';
                    echo '<td class="text-end fw-bold">' . number_format($total_kredit_transaksi, 0, ',', '.') . '</td>';
                    echo '</tr>';
                    // Tampilkan Status Keseimbangan Terakhir
                    if ($total_debit_transaksi == $total_kredit_transaksi) {
                        echo '<tr class="bg-success-subtle"><td colspan="7" class="text-center fw-bold text-success">✔️ JURNAL SEIMBANG</td></tr>';
                    } else {
                        $selisih = abs($total_debit_transaksi - $total_kredit_transaksi);
                        echo '<tr class="bg-danger-subtle"><td colspan="7" class="text-center fw-bold text-danger">❌ JURNAL TIDAK SEIMBANG! SELISIH: Rp ' . number_format($selisih, 0, ',', '.') . '</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <script>
        document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
    </script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>