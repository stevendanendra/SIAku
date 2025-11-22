<?php
// payroll_proses_jurnal.php
session_start(); 
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || $_SERVER["REQUEST_METHOD"] !== "POST") { 
    header("Location: login.html"); 
    exit(); 
}

// --- INISIALISASI VARIABEL DAN AKUN ---
$periode = $conn->real_escape_string($_POST['periode']);
$periode_db = $periode . '-01'; 
$data_payroll_json = $_POST['data_payroll'];
$sisa_bonus = (int)$_POST['sisa_bonus'];
$data_payroll = json_decode(htmlspecialchars_decode($data_payroll_json), true);
$tgl_jurnal = date('Y-m-d');

// Akun ID (Berdasarkan setup Master Akun)
$AKUN_KAS = 1101;
$AKUN_BEBAN_GAJI = 5101;
$AKUN_BEBAN_BONUS = 5102;
$AKUN_UTANG_PPH21 = 2102;
$AKUN_PENDAPATAN_LAIN = 4200; 

// Ambil mapping komponen gaji
$komponen_map_query = $conn->query("SELECT id_komponen, nama_komponen, id_akun_beban FROM ms_gaji_komponen");
$komponen_map = [];
while ($row = $komponen_map_query->fetch_assoc()) {
    $komponen_map[$row['nama_komponen']] = [
        'id_komponen' => $row['id_komponen'],
        'id_akun_beban' => (int)$row['id_akun_beban']
    ];
}

// Perhitungan Total Beban Debit (Gaji Kotor) - Diambil dari Payload
$Total_Beban_Debit = 0;
foreach ($data_payroll as $pegawai) {
    $Total_Beban_Debit += (int)$pegawai['total_penambah']; // Total Gaji Kotor
}


// Cek duplikasi
$check_duplicate_q = $conn->query("SELECT id_slip_gaji FROM tr_gaji WHERE DATE_FORMAT(periode_gaji, '%Y-%m') = '$periode' LIMIT 1");
if ($check_duplicate_q->num_rows > 0) {
    $_SESSION['error_message'] = "⚠️ Gagal! Payroll untuk periode $periode sudah pernah diproses sebelumnya.";
    header("Location: dashboard_owner.php");
    exit();
}


// --- START DATABASE TRANSACTION ---
$conn->begin_transaction();
try {
    
    // Inisialisasi Akumulator di dalam try block
    $Total_Beban_Gaji_5101 = 0;
    $Total_Beban_Bonus_5102 = 0;
    $Total_Utang_Pajak_BPJS = 0; 
    $Total_Kas_Keluar = 0;

    $no_bukti_transaksi = "PAYROLL-" . $periode . "-" . time(); 
    $memo_jurnal = "Pencatatan Gaji Karyawan periode $periode";

    // 1. LOOP DAN MASUKKAN DATA DETAIL PER PEGAWAI
    foreach ($data_payroll as $pegawai) {
        $id_pengguna = (int)$pegawai['id_pengguna'];
        $gaji_bersih = (int)$pegawai['gaji_bersih'];
        $total_penambah = (int)$pegawai['total_penambah']; // Gaji Kotor
        $total_pengurang = (int)$pegawai['total_pengurang'];

        // Ambil Gaji Pokok (digunakan untuk kolom tr_gaji)
        $gaji_pokok_value = (int)($pegawai['rinci_penambah']['Gaji Pokok - Karyawan (Kasir)'] ?? $pegawai['rinci_penambah']['Gaji Pokok - Cleaner'] ?? 0);
        
        // A. Insert ke tr_gaji (Slip Gaji Utama) 
        $sql_gaji = "INSERT INTO tr_gaji (id_cleaner, periode_gaji, tgl_proses, gaji_pokok, gaji_bersih, total_penambah, total_pengurang) 
                     VALUES ($id_pengguna, '$periode_db', '$tgl_jurnal', $gaji_pokok_value, $gaji_bersih, $total_penambah, $total_pengurang)";
        if (!$conn->query($sql_gaji)) throw new Exception("Gagal insert tr_gaji: " . $conn->error);
        $id_slip_gaji = $conn->insert_id;

        // B. Insert Detail dan AKUMULASI JURNAL DEBIT & KREDIT
        
        // Penambah (Beban) - AKUMULASI BEBAN DEBIT
        foreach ($pegawai['rinci_penambah'] as $nama_komp => $nilai) {
            $nilai = (int)$nilai;
            $komp_data = $komponen_map[$nama_komp] ?? null;

            if ($komp_data) {
                $id_komponen = $komp_data['id_komponen'];
                $sql_detail = "INSERT INTO tr_gaji_detail (id_slip_gaji, id_komponen, nilai_dihitung) VALUES ($id_slip_gaji, $id_komponen, $nilai)";
                if (!$conn->query($sql_detail)) throw new Exception("Gagal insert detail penambah: " . $conn->error);
                
                // Grouping untuk Jurnal (Debit/Beban)
                
                // FIX KRITIS: Akumulasi berdasarkan ID Akun dari Master Komponen
                if ($komp_data['id_akun_beban'] == $AKUN_BEBAN_GAJI) {
                    $Total_Beban_Gaji_5101 += $nilai; 
                } elseif ($komp_data['id_akun_beban'] == $AKUN_BEBAN_BONUS) {
                    $Total_Beban_Bonus_5102 += $nilai; 
                }
            }
        }
        
        // Pengurang (Utang) - AKUMULASI UTANG KREDIT
        foreach ($pegawai['rinci_pengurang'] as $nama_komp => $nilai) {
            $nilai = (int)$nilai;
            $komp_data = $komponen_map[$nama_komp] ?? null;

            if ($komp_data) {
                $id_komponen = $komp_data['id_komponen'];
                $sql_detail = "INSERT INTO tr_gaji_detail (id_slip_gaji, id_komponen, nilai_dihitung) VALUES ($id_slip_gaji, $id_komponen, $nilai)";
                if (!$conn->query($sql_detail)) throw new Exception("Gagal insert detail pengurang: " . $conn->error);
                
                $Total_Utang_Pajak_BPJS += $nilai; // Semua potongan masuk ke total Utang
            }
        }
        
        $Total_Kas_Keluar += $gaji_bersih; // AKUMULATOR KREDIT 1101
    }

    // 2. MASUKKAN JURNAL UMUM DETAIL 
    $Total_Kredit = 0;
    
    // FIX KRITIS: Hitung Beban Gaji (5101) dari Selisih
    $Total_Beban_Gaji_5101 = $Total_Beban_Debit - $Total_Beban_Bonus_5102; 
    
    // Debit 1: Beban Gaji (5101) - MASUKKAN TOTAL BEBAN Gaji (Gaji Pokok + Tunjangan)
    if ($Total_Beban_Gaji_5101 > 0) {
        $sql_debit_gaji = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                           VALUES ('$tgl_jurnal', '$no_bukti_transaksi', '$memo_jurnal', $AKUN_BEBAN_GAJI, 'D', $Total_Beban_Gaji_5101)";
        if (!$conn->query($sql_debit_gaji)) throw new Exception("Gagal insert Debit Beban Gaji: " . $conn->error);
    }

    // Debit 2: Beban Bonus (5102) - MASUKKAN TOTAL AKUMULASI 5102
    if ($Total_Beban_Bonus_5102 > 0) {
        $sql_debit_bonus = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                           VALUES ('$tgl_jurnal', '$no_bukti_transaksi', '$memo_jurnal', $AKUN_BEBAN_BONUS, 'D', $Total_Beban_Bonus_5102)";
        if (!$conn->query($sql_debit_bonus)) throw new Exception("Gagal insert Debit Beban Bonus: " . $conn->error);
    }
    
    // Kredit 1: Kas (1101)
    if ($Total_Kas_Keluar > 0) {
        $sql_kredit_kas = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                           VALUES ('$tgl_jurnal', '$no_bukti_transaksi', 'Pembayaran Gaji Bersih', $AKUN_KAS, 'K', $Total_Kas_Keluar)";
        if (!$conn->query($sql_kredit_kas)) throw new Exception("Gagal insert Kredit Kas: " . $conn->error);
        $Total_Kredit += $Total_Kas_Keluar;
    }

    // Kredit 2: Utang PPh 21 + BPJS (2102)
    if ($Total_Utang_Pajak_BPJS > 0) {
        $sql_kredit_utang = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                           VALUES ('$tgl_jurnal', '$no_bukti_transaksi', 'Utang PPh 21 dan BPJS', $AKUN_UTANG_PPH21, 'K', $Total_Utang_Pajak_BPJS)";
        if (!$conn->query($sql_kredit_utang)) throw new Exception("Gagal insert Kredit Utang PPh 21/BPJS: " . $conn->error);
        $Total_Kredit += $Total_Utang_Pajak_BPJS;
    }
    
    // Kredit 3: Pendapatan Lain-lain (Sisa Bonus)
    if ($sisa_bonus > 0) {
        $sql_kredit_sisa = "INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                           VALUES ('$tgl_jurnal', '$no_bukti_transaksi', 'Sisa Alokasi Bonus', $AKUN_PENDAPATAN_LAIN, 'K', $sisa_bonus)";
        if (!$conn->query($sql_kredit_sisa)) throw new Exception("Gagal insert Kredit Sisa Bonus: " . $conn->error);
        $Total_Kredit += $sisa_bonus;
    }

    // --- FINAL CHECK: DEBIT HARUS SAMA DENGAN KREDIT ---
    $Total_Beban_Debit_Final = $Total_Beban_Gaji_5101 + $Total_Beban_Bonus_5102;
    if ($Total_Beban_Debit_Final != $Total_Kredit) {
        throw new Exception("ERROR TOTAL JURNAL: Total Debit (".($Total_Beban_Debit_Final).") tidak sama dengan Total Kredit (".$Total_Kredit."). Jurnal dibatalkan.");
    }

    // Jika semua berhasil, COMMIT
    $conn->commit();
    $_SESSION['success_message'] = "✅ Payroll periode $periode berhasil diproses dan dijurnalkan dengan Nomor Bukti $no_bukti_transaksi.";
    header("Location: dashboard_owner.php");

} catch (Exception $e) {
    // Jika ada error, ROLLBACK
    $conn->rollback();
    $_SESSION['error_message'] = "❌ Gagal memproses Payroll (Rollback): " . $e->getMessage();
    header("Location: dashboard_owner.php");
}

exit();
?>