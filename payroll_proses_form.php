<?php
// payroll_proses_form.php
session_start(); // HARUS DITEMPATKAN PALING ATAS
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

// --- INISIALISASI DATA LOGIN ---
$role_login = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User');
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? 'N/A');
// --------------------------------------------------

$error_message = '';
$success_message = '';
$preview_results = [];
$total_gaji_bersih = 0;
$laba_bersih_bulan_lalu = 0;
$total_alokasi_bonus = 0;
$sisa_bonus = 0;
$periode = '';

// --- AKUN BARU YANG DIBUTUHKAN UNTUK JURNAL ---
$AKUN_PENDAPATAN_LAIN = 4200; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'preview') {
    
    $periode_gaji = $conn->real_escape_string($_POST['periode_gaji']);
    $periode = $periode_gaji;

    // 1. Ambil Komponen Gaji dari Master Data
    $komponen_q = $conn->query("SELECT nama_komponen, nilai_default, is_persentase, tipe, id_akun_beban FROM ms_gaji_komponen");
    $komponen_gaji = [];
    while ($k = $komponen_q->fetch_assoc()) {
        $komponen_gaji[$k['nama_komponen']] = $k;
    }

    // 2. Tentukan Laba Bersih Bulan Lalu (Dasar Bonus)
    $bulan_lalu = date('Y-m', strtotime('-1 month', strtotime($periode_gaji . '-01')));
    $sql_laba = "
        SELECT 
            (SELECT COALESCE(SUM(nilai), 0) FROM tr_jurnal_umum WHERE id_akun = 4101 AND posisi = 'K' AND DATE_FORMAT(tgl_jurnal, '%Y-%m') = '$bulan_lalu') AS pendapatan,
            (SELECT COALESCE(SUM(nilai), 0) FROM tr_jurnal_umum WHERE id_akun IN (5101, 5102, 5201, 5301, 5401) AND posisi = 'D' AND DATE_FORMAT(tgl_jurnal, '%Y-%m') = '$bulan_lalu') AS beban
    ";
    $res_laba = $conn->query($sql_laba)->fetch_assoc();
    $laba_bersih_bulan_lalu = $res_laba['pendapatan'] - $res_laba['beban'];

    // 3. Hitung Alokasi Bonus
    $persentase_bonus = $komponen_gaji['Bonus Laba Bersih Persentase']['nilai_default'] ?? 0;
    $total_alokasi_bonus = floor($laba_bersih_bulan_lalu * ($persentase_bonus / 100));

    // 4. Ambil Daftar Karyawan yang Digaji (Kasir + Cleaner) DAN AKTIF
    $pegawai_q = $conn->query("SELECT id_pengguna, nama_lengkap, role, is_menikah, jumlah_anak FROM ms_pengguna 
                                 WHERE role IN ('Karyawan', 'Cleaner') AND is_aktif = TRUE"); // <-- Filter is_aktif
    $total_pegawai_digaji = $pegawai_q->num_rows;
    $bonus_per_pegawai = ($total_pegawai_digaji > 0) ? floor($total_alokasi_bonus / $total_pegawai_digaji) : 0;
    
    // Hitung Sisa Bonus
    $total_bonus_dibagikan = $bonus_per_pegawai * $total_pegawai_digaji;
    $sisa_bonus = $total_alokasi_bonus - $total_bonus_dibagikan;

    // --- Konstanta PTKP Simulasi (Bulanan) ---
    $PTKP_DASAR = 375000; 
    $PTKP_ISTRI = 375000;
    $TARIF_PPH21 = 0.05; 
    $PTKP_KETERANGAN = 'TK/0: Rp ' . number_format($PTKP_DASAR, 0, ',', '.') . ' | K/0: + Rp ' . number_format($PTKP_ISTRI, 0, ',', '.'); 

    
    while ($pegawai = $pegawai_q->fetch_assoc()) {
        $role = $pegawai['role'];
        $rinci_penambah = [];
        $rinci_pengurang = [];
        $gaji_kotor_pph = 0; 

        // A. PENAMBAH (Detail & Total)
        $gaji_pokok_key = 'Gaji Pokok - ' . ($role == 'Karyawan' ? 'Karyawan (Kasir)' : 'Cleaner');
        $gaji_pokok = $komponen_gaji[$gaji_pokok_key]['nilai_default'] ?? 0;
        $rinci_penambah['Gaji Pokok'] = $gaji_pokok;

        $tunj_istri = 0;
        if ($pegawai['is_menikah']) {
            $tunj_istri = $komponen_gaji['Tunjangan Istri']['nilai_default'] ?? 0;
            $rinci_penambah['Tunjangan Istri'] = $tunj_istri;
        }

        $jml_anak = min(2, $pegawai['jumlah_anak']);
        $tunj_anak = ($komponen_gaji['Tunjangan Anak (per anak)']['nilai_default'] ?? 0) * $jml_anak;
        if ($tunj_anak > 0) {
            $rinci_penambah['Tunjangan Anak'] = $tunj_anak;
        }
        
        $tunj_makan = $komponen_gaji['Tunjangan Makan']['nilai_default'] ?? 0;
        $rinci_penambah['Tunjangan Makan'] = $tunj_makan;
        
        $bonus = $bonus_per_pegawai;
        $rinci_penambah['Bonus Laba Bersih'] = $bonus;

        $total_penambah = array_sum($rinci_penambah);
        $gaji_kotor_pph = $total_penambah;

        // B. PENGURANG (Detail & Total)
        $pot_bpjs = $komponen_gaji['Potongan BPJS']['nilai_default'] ?? 0;
        $rinci_pengurang['Potongan BPJS'] = $pot_bpjs;

        // PPh 21 (Simulasi Akurat)
        $pot_pph21 = 0;
        $PTKP_PEG = $PTKP_DASAR;
        if ($pegawai['is_menikah']) {
            $PTKP_PEG += $PTKP_ISTRI; 
        }
        $PTKP_PEG += ($jml_anak > 0) ? $PTKP_DASAR * $jml_anak : 0; 
        
        $PKP = $gaji_kotor_pph - $PTKP_PEG; 
        
        if ($PKP > 0) {
            $pot_pph21 = floor($PKP * $TARIF_PPH21);
        }
        $rinci_pengurang['Potongan PPh 21'] = $pot_pph21; 

        $total_pengurang = array_sum($rinci_pengurang);

        // C. Gaji Bersih
        $gaji_bersih = $total_penambah - $total_pengurang;
        $total_gaji_bersih += $gaji_bersih;

        // D. SIMPAN HASIL PREVIEW
        $preview_results[] = [
            'id_pengguna' => $pegawai['id_pengguna'],
            'nama' => $pegawai['nama_lengkap'],
            'role' => $role,
            'rinci_penambah' => $rinci_penambah,
            'rinci_pengurang' => $rinci_pengurang,
            'total_penambah' => $total_penambah,
            'total_pengurang' => $total_pengurang,
            'gaji_bersih' => $gaji_bersih,
            'ptkp_pegawai' => $PTKP_PEG,
        ];
    }
}
?>

<?php include '_header.php'; // Header Bootstrap ?>

    <h1 class="mb-4">3. Pemrosesan Gaji Bulanan (Payroll)</h1>
    
    <p class="text-muted"><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>

    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>
    
    <div class="card shadow-sm p-4 mb-4">
        <h2 class="card-title h5">1. Pilih Periode Gaji & Hitung Preview</h2>
        <form method="POST" class="row g-3 align-items-center">
            <input type="hidden" name="action" value="preview">
            
            <div class="col-md-4">
                <label for="periode_gaji" class="form-label">Periode Pembayaran:</label>
                <input type="month" class="form-control" id="periode_gaji" name="periode_gaji" value="<?php echo date('Y-m'); ?>" required>
            </div>
            
            <div class="col-md-3 mt-4">
                <button type="submit" class="btn btn-primary w-100">Lihat Preview Gaji</button>
            </div>
        </form>
    </div>

    <?php if ($preview_results): ?>
    <hr>
    <div class="card shadow-sm p-4">
        <h2 class="card-title h5">2. Hasil Kalkulasi Gaji (Periode Pembayaran: <?php echo $periode; ?>)</h2>
        
        <div class="alert alert-info small mt-3">
            <p class="mb-1"><strong>Informasi Keuangan Periode Lalu:</strong></p>
            <p class="mb-0">Laba Bersih Bulan Lalu: **Rp <?php echo number_format($laba_bersih_bulan_lalu, 0, ',', '.'); ?>** | 
            Total Alokasi Bonus (<?php echo $persentase_bonus; ?>%): **Rp <?php echo number_format($total_alokasi_bonus, 0, ',', '.'); ?>** | 
            Bonus per Pegawai (Rata): **Rp <?php echo number_format($bonus_per_pegawai, 0, ',', '.'); ?>** |
            Sisa Bonus (Pendapatan Lain-lain): **Rp <?php echo number_format($sisa_bonus, 0, ',', '.'); ?>**</p>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-bordered table-striped table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th rowspan="2">Nama (Role)</th>
                        <th rowspan="2" class="text-end">PTKP Pegawai</th>
                        <th colspan="2" class="text-center">Penambah (Gaji Kotor)</th>
                        <th colspan="2" class="text-center">Pengurang</th>
                        <th rowspan="2" class="text-end bg-success text-white">Gaji Bersih</th>
                    </tr>
                    <tr>
                        <th>Rincian Komponen</th>
                        <th class="text-end">Total Kotor</th>
                        <th>Rincian Potongan</th>
                        <th class="text-end">Total Potongan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview_results as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['nama']) . ' (' . $p['role'] . ')'; ?></td>
                        
                        <td class="text-end small">Rp <?php echo number_format($p['ptkp_pegawai'], 0, ',', '.'); ?></td>
                        
                        <td>
                            <ul class="rinci-list">
                            <?php foreach ($p['rinci_penambah'] as $komp => $nilai): ?>
                                <li><?php echo $komp; ?>: Rp <?php echo number_format($nilai, 0, ',', '.'); ?></li>
                            <?php endforeach; ?>
                            </ul>
                        </td>
                        
                        <td class="text-end fw-bold">Rp <?php echo number_format($p['total_penambah'], 0, ',', '.'); ?></td>
                        
                        <td>
                            <ul class="rinci-list">
                            <?php foreach ($p['rinci_pengurang'] as $komp => $nilai): ?>
                                <li><?php echo $komp; ?>: Rp <?php echo number_format($nilai, 0, ',', '.'); ?></li>
                            <?php endforeach; ?>
                            </ul>
                            <small class="text-muted">Dasar PPh: (Gaji Kotor - PTKP)</small>
                        </td>
                        
                        <td class="text-end">Rp <?php echo number_format($p['total_pengurang'], 0, ',', '.'); ?></td>
                        
                        <td class="text-end fw-bold bg-success-subtle">Rp <?php echo number_format($p['gaji_bersih'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <td colspan="6" class="text-end fw-bold">TOTAL KAS KELUAR (Gaji Bersih)</td>
                        <td class="text-end fw-bold">Rp <?php echo number_format($total_gaji_bersih, 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <form action="payroll_proses_jurnal.php" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="periode" value="<?php echo $periode; ?>">
            <input type="hidden" name="data_payroll" value="<?php echo htmlspecialchars(json_encode($preview_results)); ?>">
            <input type="hidden" name="sisa_bonus" value="<?php echo $sisa_bonus; ?>">
            
            <button type="submit" class="btn btn-lg btn-danger w-100" onclick="return confirm('Yakin ingin memproses dan mencatat gaji untuk periode <?php echo $periode; ?>? Aksi ini akan membuat jurnal akuntansi!');">
                3. EKSEKUSI & JURNALKAN GAJI SEKARANG
            </button>
        </form>
    </div>
    <?php endif; ?>

    <script>
        document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
    </script>

<?php include '_footer.php'; // Footer Bootstrap ?>