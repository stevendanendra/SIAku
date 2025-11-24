<?php
// payroll_proses_form.php
session_start(); // HARUS DITEMPATKAN PALING ATAS
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

// --- INISIALISASI DATA LOGIN ---
$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);
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

    $periode_lengkap = $periode_gaji . '-01'; 

    // 1. Ambil Komponen Gaji dari Master Data
    $komponen_q = $conn->query("SELECT nama_komponen, nilai_default, is_persentase, tipe, id_akun_beban FROM ms_gaji_komponen");
    $komponen_gaji_master = [];
    $komponen_gaji = []; 
    $komponen_sudah_dihitung = []; 
    $gaji_pokok_by_role = []; 

    while ($k = $komponen_q->fetch_assoc()) {
        $komponen_gaji_master[] = $k; 
        $komponen_gaji[$k['nama_komponen']] = $k; 

        // Identifikasi Gaji Pokok berdasarkan Akun 5101
        if ($k['id_akun_beban'] == 5101) {
            if (preg_match('/Gaji Pokok - (.+)/i', $k['nama_komponen'], $matches)) {
                $role_identifier = trim($matches[1]);
                if (strpos($role_identifier, '(') !== false) {
                    $role_name = trim(strstr($role_identifier, '(', true));
                } else {
                    $role_name = $role_identifier;
                }
                $gaji_pokok_by_role[$role_name] = $k['nilai_default'];
            }
        }
    }

    // 2. Tentukan Laba Bersih Bulan Lalu (Dasar Bonus)
    $bulan_lalu = date('Y-m', strtotime('-1 month', strtotime($periode_lengkap))); 
    $sql_laba = "
        SELECT 
            (SELECT COALESCE(SUM(nilai), 0) FROM tr_jurnal_umum WHERE id_akun = 4101 AND posisi = 'K' AND DATE_FORMAT(tgl_jurnal, '%Y-%m') = '$bulan_lalu') AS pendapatan,
            (SELECT COALESCE(SUM(nilai), 0) FROM tr_jurnal_umum WHERE id_akun IN (5101, 5102, 5201, 5301, 5401) AND posisi = 'D' AND DATE_FORMAT(tgl_jurnal, '%Y-%m') = '$bulan_lalu') AS beban
    ";
    $res_laba = $conn->query($sql_laba);

    if ($res_laba === FALSE) {
        $error_message = "Error saat menghitung laba bulan lalu: " . $conn->error;
        goto end_of_post; 
    }
    $res_laba = $res_laba->fetch_assoc();
    $laba_bersih_bulan_lalu = $res_laba['pendapatan'] - $res_laba['beban'];

    // 3. Hitung Alokasi Bonus
    $NAMA_BONUS = 'Bonus Laba Bersih Persentase'; // Nama LENGKAP DB
    $persentase_bonus = $komponen_gaji[$NAMA_BONUS]['nilai_default'] ?? 0;
    
    if ($laba_bersih_bulan_lalu > 0) {
        $total_alokasi_bonus = floor($laba_bersih_bulan_lalu * ($persentase_bonus / 100));
    } else {
        $total_alokasi_bonus = 0; 
    }
    
    $komponen_sudah_dihitung[$NAMA_BONUS] = true; 

    // 4. Ambil Daftar Karyawan yang Digaji (DINAMIS SEMUA ROLE KECUALI OWNER)
    $pegawai_q = $conn->query("SELECT id_pengguna, nama_lengkap, role, is_menikah, jumlah_anak FROM ms_pengguna 
                                 WHERE role != 'Owner' AND is_aktif = TRUE ORDER BY id_pengguna ASC"); 
    $total_pegawai_digaji = $pegawai_q->num_rows;
    $bonus_per_pegawai = ($total_alokasi_bonus > 0) ? floor($total_alokasi_bonus / $total_pegawai_digaji) : 0;
    
    // Hitung Sisa Bonus
    $total_bonus_dibagikan = $bonus_per_pegawai * $total_pegawai_digaji;
    $sisa_bonus = $total_alokasi_bonus - $total_bonus_dibagikan;

    // --- Konstanta PTKP Simulasi (Bulanan) ---
    $PTKP_DASAR = 375000; 
    $PTKP_ISTRI = 375000;
    $PTKP_ANAK_PER_UNIT = 375000; 
    $TARIF_PPH21 = 0.05; 
    
    // TANDAI NAMA LENGKAP KOMPONEN HARDCODED
    $NAMA_TUNJANGAN_ISTRI = 'Tunjangan Istri';
    $NAMA_TUNJANGAN_ANAK_DB = 'Tunjangan Anak'; 
    $NAMA_TUNJANGAN_MAKAN = 'Tunjangan Makan';
    $NAMA_POTONGAN_BPJS = 'Potongan BPJS';
    $NAMA_POTONGAN_PPH21 = 'Potongan PPh 21';
    
    // Asumsi komponen Beban BPJS Perusahaan sudah ditambahkan
    $NAMA_BEBAN_BPJS_PER = 'Beban BPJS Ditanggung Perusahaan'; 
    $komponen_sudah_dihitung[$NAMA_BEBAN_BPJS_PER] = true;

    $komponen_sudah_dihitung[$NAMA_TUNJANGAN_ISTRI] = true;
    $komponen_sudah_dihitung[$NAMA_TUNJANGAN_ANAK_DB] = true;
    $komponen_sudah_dihitung[$NAMA_TUNJANGAN_MAKAN] = true;
    $komponen_sudah_dihitung[$NAMA_POTONGAN_BPJS] = true;
    $komponen_sudah_dihitung[$NAMA_POTONGAN_PPH21] = true;
    
    // Ambil persentase/nilai BPJS dari Master Komponen
    $PER_BPJS_PERUSAHAAN = ($komponen_gaji[$NAMA_BEBAN_BPJS_PER]['is_persentase'] ?? 0) ? 
                           ($komponen_gaji[$NAMA_BEBAN_BPJS_PER]['nilai_default'] / 100) : 0;
    $PER_BPJS_KARYAWAN = ($komponen_gaji[$NAMA_POTONGAN_BPJS]['is_persentase'] ?? 0) ? 
                         ($komponen_gaji[$NAMA_POTONGAN_BPJS]['nilai_default'] / 100) : 0;
    $POTONGAN_BPJS_FIXED = (!$PER_BPJS_KARYAWAN && ($komponen_gaji[$NAMA_POTONGAN_BPJS]['nilai_default'] ?? 0) > 0) ? 
                           $komponen_gaji[$NAMA_POTONGAN_BPJS]['nilai_default'] : 0;

    // 5. Cek Status Pemrosesan Saat Ini
    $processed_q = $conn->query("SELECT id_cleaner FROM tr_gaji WHERE DATE_FORMAT(periode_gaji, '%Y-%m') = '$periode' AND status_jurnal = 1");
    
    if ($processed_q === FALSE) {
        $error_message = "Error saat memeriksa status pemrosesan: " . $conn->error;
        goto end_of_post;
    }
    
    $processed_ids = [];
    while($row = $processed_q->fetch_assoc()) {
        $processed_ids[$row['id_cleaner']] = true;
    }

    $pegawai_q->data_seek(0); 
    while ($pegawai = $pegawai_q->fetch_assoc()) {
        $role = $pegawai['role'];
        $rinci_penambah = [];
        $rinci_pengurang = [];
        $gaji_kotor_pph = 0; 

        // A. PENAMBAH - Gaji Pokok (DINAMIS BERDASARKAN ROLE)
        $gaji_pokok = $gaji_pokok_by_role[$role] ?? 0; 
        
        if ($gaji_pokok == 0) {
             $error_message = "Gagal: Gaji Pokok untuk Role '{$role}' belum diatur di Master Komponen (Akun 5101).";
             goto end_of_post;
        }

        $rinci_penambah['Gaji Pokok'] = $gaji_pokok; 
        
        // Tunjangan Istri
        $tunj_istri = 0;
        if ($pegawai['is_menikah']) {
            $tunj_istri = $komponen_gaji[$NAMA_TUNJANGAN_ISTRI]['nilai_default'] ?? 0;
            $rinci_penambah[$NAMA_TUNJANGAN_ISTRI] = $tunj_istri; 
        }

        // Tunjangan Anak
        $jml_anak = min(2, $pegawai['jumlah_anak']);
        $tunj_anak = ($komponen_gaji[$NAMA_TUNJANGAN_ANAK_DB]['nilai_default'] ?? 0) * $jml_anak;
        if ($tunj_anak > 0) {
            $rinci_penambah[$NAMA_TUNJANGAN_ANAK_DB] = $tunj_anak; 
        }
        
        // Tunjangan Makan
        $tunj_makan = $komponen_gaji[$NAMA_TUNJANGAN_MAKAN]['nilai_default'] ?? 0;
        $rinci_penambah[$NAMA_TUNJANGAN_MAKAN] = $tunj_makan; 
        
        // Bonus
        $bonus = $bonus_per_pegawai; 
        $rinci_penambah[$NAMA_BONUS] = $bonus; 
        
        // ----------------------------------------------------
        // BEBAN BPJS DITANGGUNG PERUSAHAAN (4% dari Gaji Pokok)
        $beban_bpjs_perusahaan_calculated = floor($gaji_pokok * $PER_BPJS_PERUSAHAAN);
        
        // Potongan BPJS Karyawan (1% atau Fixed)
        $pot_bpjs = $POTONGAN_BPJS_FIXED;
        if ($PER_BPJS_KARYAWAN > 0) {
             $pot_bpjs = floor($gaji_pokok * $PER_BPJS_KARYAWAN);
        }
        
        // CAPPING LOGIC: Beban BPJS Perusahaan TIDAK BOLEH melebihi Potongan BPJS Karyawan
        if ($beban_bpjs_perusahaan_calculated > $pot_bpjs) {
            $beban_bpjs_final = $pot_bpjs; // CAPPED TO EMPLOYEE DEDUCTION
        } else {
            $beban_bpjs_final = $beban_bpjs_perusahaan_calculated;
        }
        
        $rinci_penambah[$NAMA_BEBAN_BPJS_PER] = $beban_bpjs_final;
        // ----------------------------------------------------

        // B. PENGURANG HARDCODED
        $rinci_pengurang[$NAMA_POTONGAN_BPJS] = $pot_bpjs; 


        // C. HITUNG KOMPONEN BARU (Tambahan Dinamis - Non-Hardcoded)
        foreach ($komponen_gaji_master as $k) {
            $k_nama = $k['nama_komponen'];
            
            // Lompati komponen Gaji Pokok (akun 5101) dan komponen hardcoded lainnya
            if ($k['id_akun_beban'] == 5101 || isset($komponen_sudah_dihitung[$k_nama])) {
                continue;
            }
            
            $nilai_komp = $k['nilai_default']; 
            
            if ($k['tipe'] == 'Penambah') {
                $rinci_penambah[$k_nama] = $nilai_komp;
            } else { // Tipe Pengurang (5103 atau 2102)
                $rinci_pengurang[$k_nama] = $nilai_komp;
            }
        }


        $total_penambah = array_sum($rinci_penambah);
        $gaji_kotor_pph = $total_penambah;

        // D. PPh 21 (Simulasi Akurat)
        $pot_pph21 = 0;
        $PTKP_PEG = $PTKP_DASAR;
        if ($pegawai['is_menikah']) {
            $PTKP_PEG += $PTKP_ISTRI; 
        }
        $PTKP_PEG += ($jml_anak > 0) ? $PTKP_ANAK_PER_UNIT * $jml_anak : 0; 
        
        $PKP = $gaji_kotor_pph - $PTKP_PEG; 
        
        if ($PKP > 0) {
            $pot_pph21 = floor($PKP * $TARIF_PPH21);
        }
        $rinci_pengurang[$NAMA_POTONGAN_PPH21] = $pot_pph21; 

        $total_pengurang = array_sum($rinci_pengurang);

        // E. Gaji Bersih
        $gaji_bersih = $total_penambah - $total_pengurang;
        $total_gaji_bersih += $gaji_bersih;

        // F. SIMPAN HASIL PREVIEW
        $id_pegawai = $pegawai['id_pengguna'];
        $is_processed = isset($processed_ids[$id_pegawai]);

        $preview_results[] = [
            'id_pengguna' => $id_pegawai,
            'nama' => $pegawai['nama_lengkap'],
            'role' => $role,
            'rinci_penambah' => $rinci_penambah,
            'rinci_pengurang' => $rinci_pengurang,
            'total_penambah' => $total_penambah,
            'total_pengurang' => $total_pengurang,
            'gaji_bersih' => $gaji_bersih,
            'ptkp_pegawai' => $PTKP_PEG,
            'is_processed' => $is_processed
        ];
    }
    
    end_of_post:

}
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">
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
                <input type="month" class="form-control" id="periode_gaji" name="periode_gaji" value="<?php echo htmlspecialchars($periode ?: date('Y-m')); ?>" required>
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
            <table class="table table-bordered table-sm align-middle" style="min-width: 1300px;"> 
                <thead class="bg-dark text-white">
                    <tr>
                        <th rowspan="2" class="text-center align-middle">Status</th>
                        <th rowspan="2" class="align-middle" style="width: 12%;">Nama (Role)</th>
                        <th rowspan="2" class="text-end align-middle" style="width: 8%;">PTKP</th>
                        
                        <th colspan="2" class="text-center align-middle">Penambah (Gaji Kotor)</th>
                        <th colspan="2" class="text-center align-middle">Pengurang</th>
                        
                        <th rowspan="2" class="text-end bg-success text-white align-middle" style="width: 10%;">Gaji Bersih</th>
                        <th rowspan="2" class="text-center align-middle" style="min-width: 100px; width: 8%;">Aksi</th>
                    </tr>
                    <tr>
                        <th style="min-width: 250px; width: 25%;" class="text-start">Rincian Komponen</th> 
                        <th class="text-end" style="width: 10%;">Total Kotor</th>
                        <th style="min-width: 250px; width: 25%;" class="text-start">Rincian Potongan</th>
                        <th class="text-end" style="width: 10%;">Total Potongan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview_results as $p): 
                        $is_disabled = $p['is_processed'] ? 'disabled' : '';
                        $btn_style = $p['is_processed'] ? 'btn-secondary' : 'btn-success'; 
                        $gaji_bersih_class = $p['gaji_bersih'] > 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
                        $row_bg = $p['is_processed'] ? 'bg-light' : '';
                    ?>
                    <tr class="<?php echo $row_bg; ?>">
                        <td class="text-center">
                            <?php if ($p['is_processed']): ?>
                                <span class="badge bg-success">PROSES</span>
                            <?php else: ?>
                                <span class="badge bg-danger">PENDING</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['nama']) . ' (' . $p['role'] . ')'; ?></td>
                        
                        <td class="text-end small">Rp <?php echo number_format($p['ptkp_pegawai'], 0, ',', '.'); ?></td>
                        
                        <td class="text-start"> 
                            <?php foreach ($p['rinci_penambah'] as $komp => $nilai): 
                                $nilai_class = $nilai > 0 ? 'text-success' : 'text-secondary';
                            ?>
                                <div class="d-flex justify-content-between">
                                    <span class="text-truncate" style="max-width: 60%;"><?php echo $komp; ?>:</span> 
                                    <span class="fw-medium <?php echo $nilai_class; ?>">Rp <?php echo number_format($nilai, 0, ',', '.'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        
                        <td class="text-end">Rp <?php echo number_format($p['total_penambah'], 0, ',', '.'); ?></td>
                        
                        <td class="text-start">
                            <?php foreach ($p['rinci_pengurang'] as $komp => $nilai): 
                                $nilai_class = $nilai > 0 ? 'text-danger' : 'text-secondary';
                            ?>
                                <div class="d-flex justify-content-between">
                                    <span class="text-truncate" style="max-width: 60%;"><?php echo $komp; ?>:</span> 
                                    <span class="fw-medium <?php echo $nilai_class; ?>">Rp <?php echo number_format($nilai, 0, ',', '.'); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <small class="text-muted d-block mt-1">Dasar PPh: (Gaji Kotor - PTKP)</small>
                        </td>
                        
                        <td class="text-end">Rp <?php echo number_format($p['total_pengurang'], 0, ',', '.'); ?></td>
                        
                        <td class="text-end bg-success-subtle <?php echo $gaji_bersih_class; ?>">Rp <?php echo number_format($p['gaji_bersih'], 0, ',', '.'); ?></td>

                        <td class="text-center">
                            <form action="payroll_proses_eksekutor.php" method="POST" onsubmit="return confirm('Yakin ingin memproses gaji <?php echo htmlspecialchars($p['nama']); ?> untuk periode <?php echo $periode; ?>?');">
                                <input type="hidden" name="periode" value="<?php echo $periode; ?>">
                                <input type="hidden" name="id_pengguna_single" value="<?php echo $p['id_pengguna']; ?>"> 
                                <input type="hidden" name="data_payroll_single" value="<?php echo htmlspecialchars(json_encode([$p])); ?>">
                                <input type="hidden" name="sisa_bonus" value="0">
                                
                                <button type="submit" class="btn btn-sm <?php echo $btn_style; ?> w-100" <?php echo $is_disabled; ?>>
                                    <?php echo $p['is_processed'] ? 'Diproses' : 'Proses Gaji'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-light text-dark">
                        <td colspan="7" class="text-end fw-bold align-middle">TOTAL KAS KELUAR (Gaji Bersih)</td>
                        <td class="text-end fw-bold align-middle">Rp <?php echo number_format($total_gaji_bersih, 0, ',', '.'); ?></td>
                        <td class="text-center p-1">
                            <form action="payroll_proses_eksekutor.php" method="POST">
                                <input type="hidden" name="periode" value="<?php echo $periode; ?>">
                                <input type="hidden" name="data_payroll_massal" value="<?php echo htmlspecialchars(json_encode($preview_results)); ?>">
                                <input type="hidden" name="sisa_bonus" value="<?php echo $sisa_bonus; ?>">
                                
                                <button type="submit" class="btn btn-sm btn-info w-100" onclick="return confirm('Yakin ingin memproses dan mencatat SEMUA gaji yang PENDING untuk periode <?php echo $periode; ?>?');">
                                    Proses Semua PENDING
                                </button>
                            </form>
                        </td> 
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_login; ?>)';
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>