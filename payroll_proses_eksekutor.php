<?php
// payroll_proses_eksekutor.php
session_start();
include 'koneksi.php';

// =======================================================
// 1. VALIDASI AKSES & METODE REQUEST
// =======================================================
if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Owner' ||
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {
    header("Location: login.html");
    exit();
}

// =======================================================
// 2. INISIALISASI VARIABEL & DATA PERIODE
// =======================================================
$periode = $conn->real_escape_string($_POST['periode']);
$periode_db = $periode . '-01';
$tgl_jurnal = date('Y-m-d');

// Cek jenis input (single vs massal)
if (isset($_POST['data_payroll_single'])) {
    $data_payroll_json = $_POST['data_payroll_single'];
    $sisa_bonus = 0;
} elseif (isset($_POST['data_payroll_massal'])) {
    $data_payroll_json = $_POST['data_payroll_massal'];
    $sisa_bonus = (int) $_POST['sisa_bonus'];
} else {
    $_SESSION['error_message'] = "❌ Gagal: Data payroll tidak ditemukan.";
    header("Location: dashboard_owner.php");
    exit();
}

$data_payroll_raw = json_decode(htmlspecialchars_decode($data_payroll_json), true);
$data_payroll = array_filter(
    $data_payroll_raw,
    fn($p) => !($p['is_processed'] ?? false)
);

// =======================================================
// 3. KONSTANTA AKUN
// =======================================================
$AKUN_KAS = 1101;
$AKUN_BEBAN_GAJI_POKOK = 5101;
$AKUN_BEBAN_GAJI_PLUS = 5102;
$AKUN_BEBAN_GAJI_MINUS = 5103;
$AKUN_UTANG_PPH21 = 2102;
$AKUN_PENDAPATAN_LAIN = 4200;

// Nama komponen khusus
$NAMA_BEBAN_BPJS_PERUSAHAAN = 'Beban BPJS Ditanggung Perusahaan';
$NAMA_POTONGAN_BPJS = 'Potongan BPJS';

// =======================================================
// 4. AMBIL MAPPING KOMPOSISI GAJI
// =======================================================
$komponen_map = [];
$komponen_gaji_pokok_map = [];

$q = $conn->query("SELECT id_komponen, nama_komponen, id_akun_beban, tipe FROM ms_gaji_komponen");

while ($row = $q->fetch_assoc()) {
    $komponen_map[$row['nama_komponen']] = [
        'id_komponen' => $row['id_komponen'],
        'id_akun_beban' => (int) $row['id_akun_beban'],
        'tipe' => $row['tipe']
    ];

    // Mapping khusus Gaji Pokok berdasarkan role
    if ($row['id_akun_beban'] == $AKUN_BEBAN_GAJI_POKOK) {
        if (preg_match('/Gaji Pokok - (.+)/i', $row['nama_komponen'], $matches)) {
            $identifier = trim($matches[1]);
            $role_name = strpos($identifier, '(') !== false
                ? trim(strstr($identifier, '(', true))
                : $identifier;
            $komponen_gaji_pokok_map[$role_name] = $row;
        }
    }
}

// Jika semua pegawai sudah processed
if (empty($data_payroll)) {
    $_SESSION['error_message'] = "⚠️ Tidak ada pegawai pending untuk diproses.";
    header("Location: dashboard_owner.php");
    exit();
}

// =======================================================
// 5. MULAI TRANSAKSI DATABASE
// =======================================================
$conn->begin_transaction();

try {

    // Akumulator Global Jurnal
    $jurnal_debit_akumulator = [];
    $Total_Kas_Keluar = 0;
    $Total_Utang_Pajak_PPh21 = 0;
    $Total_Potongan_5103_Credit = 0;
    $Total_Beban_BPJS_Perusahaan = 0;

    $no_bukti_transaksi = "PAYROLL-$periode-" . time();
    $memo_jurnal = "Pencatatan Gaji Karyawan periode $periode";

    // ===================================================
    // 6. LOOP PER PEGAWAI
    // ===================================================
    foreach ($data_payroll as $pgw) {

        $id_pengguna = (int) $pgw['id_pengguna'];
        $gaji_bersih = (int) $pgw['gaji_bersih'];
        $total_penambah = (int) $pgw['total_penambah'];
        $total_pengurang = (int) $pgw['total_pengurang'];
        $role = $pgw['role'];

        // Cek duplikasi slip gaji
        $dup = $conn->query("
            SELECT id_slip_gaji
            FROM tr_gaji
            WHERE id_cleaner = $id_pengguna
              AND DATE_FORMAT(periode_gaji, '%Y-%m') = '$periode'
            LIMIT 1
        ");

        if ($dup->num_rows > 0) {
            continue;
        }

        // Ambil Gaji Pokok
        $gaji_pokok = (int) ($pgw['rinci_penambah']['Gaji Pokok'] ?? 0);

        // Insert Slip Gaji
        $sql = "
            INSERT INTO tr_gaji
            (id_cleaner, periode_gaji, tgl_proses, gaji_pokok, gaji_bersih, total_penambah, total_pengurang, id_jurnal_umum, status_jurnal)
            VALUES
            ($id_pengguna, '$periode_db', '$tgl_jurnal', $gaji_pokok, $gaji_bersih, $total_penambah, $total_pengurang, NULL, 1)
        ";

        if (!$conn->query($sql)) {
            throw new Exception("Gagal insert tr_gaji: " . $conn->error);
        }

        $id_slip_gaji = $conn->insert_id;

        // ----------------------------
        // A. PENAMBAH (DEBIT)
        // ----------------------------
        foreach ($pgw['rinci_penambah'] as $nama => $nilai) {
            $nilai = (int) $nilai;

            // Tentukan mapping komponen
            if ($nama === 'Gaji Pokok') {
                $komp = $komponen_gaji_pokok_map[$role] ?? null;
            } elseif ($nama === 'Tunjangan Anak') {
                $komp = $komponen_map['Tunjangan Anak'] ?? null;
            } elseif ($nama === 'Bonus Laba Bersih') {
                $komp = $komponen_map['Bonus Laba Bersih Persentase'] ?? null;
            } elseif ($nama === $NAMA_BEBAN_BPJS_PERUSAHAAN) {
                $komp = $komponen_map[$NAMA_BEBAN_BPJS_PERUSAHAAN] ?? null;
                $Total_Beban_BPJS_Perusahaan += $nilai;
            } else {
                $komp = $komponen_map[$nama] ?? null;
            }

            if (!$komp) {
                throw new Exception("Komponen Penambah '$nama' tidak ditemukan.");
            }

            // Insert detail slip
            $conn->query("
                INSERT INTO tr_gaji_detail (id_slip_gaji, id_komponen, nilai_dihitung)
                VALUES ($id_slip_gaji, {$komp['id_komponen']}, $nilai)
            ");

            // Akumulasi jurnal debit
            $id_akun = $komp['id_akun_beban'];
            $jurnal_debit_akumulator[$id_akun] = ($jurnal_debit_akumulator[$id_akun] ?? 0) + $nilai;
        }

        // ----------------------------
        // B. PENGURANG (KREDIT)
        // ----------------------------
        foreach ($pgw['rinci_pengurang'] as $nama => $nilai) {
            $nilai = (int) $nilai;
            $komp = $komponen_map[$nama] ?? null;

            if (!$komp) {
                throw new Exception("Komponen Pengurang '$nama' tidak ditemukan.");
            }

            // Insert detail slip
            $conn->query("
                INSERT INTO tr_gaji_detail (id_slip_gaji, id_komponen, nilai_dihitung)
                VALUES ($id_slip_gaji, {$komp['id_komponen']}, $nilai)
            ");

            // Jika Utang (2102) → Akumulasi
            if ($komp['id_akun_beban'] == $AKUN_UTANG_PPH21) {

                if ($nama !== $NAMA_POTONGAN_BPJS) {
                    $Total_Utang_Pajak_PPh21 += $nilai;
                }

            }

            // Jika potongan non-utangnya masuk akun 5103 → Kredit 5103
            if ($komp['id_akun_beban'] == $AKUN_BEBAN_GAJI_MINUS) {
                $Total_Potongan_5103_Credit += $nilai;
            }
        }

        // Akumulasi kas keluar
        $Total_Kas_Keluar += $gaji_bersih;
    }

    // ======================================================
    // 7. SYNC UTANG 2102 (PPh21 + BPJS)
    // ======================================================
    $Total_Utang_Pajak_BPJS_PPh21 = $Total_Utang_Pajak_PPh21 + $Total_Beban_BPJS_Perusahaan;

    $komp_bpjs = $komponen_map[$NAMA_POTONGAN_BPJS] ?? null;
    $default_bpjs = (int) ($komp_bpjs['nilai_default'] ?? 0);

    if ($default_bpjs > 0 && $komp_bpjs['id_akun_beban'] == $AKUN_UTANG_PPH21) {
        $Total_Utang_Pajak_BPJS_PPh21 += $default_bpjs;
    }

    // ======================================================
    // 8. SUSUN FINAL JURNAL
    // ======================================================
    $jurnal_debit = $jurnal_debit_akumulator;

    $jurnal_kredit = [];

    if ($Total_Kas_Keluar > 0) {
        $jurnal_kredit[$AKUN_KAS] = $Total_Kas_Keluar;
    }
    if ($Total_Utang_Pajak_BPJS_PPh21 > 0) {
        $jurnal_kredit[$AKUN_UTANG_PPH21] = $Total_Utang_Pajak_BPJS_PPh21;
    }
    if ($Total_Potongan_5103_Credit > 0) {
        $jurnal_kredit[$AKUN_BEBAN_GAJI_MINUS] = $Total_Potongan_5103_Credit;
    }
    if ($sisa_bonus > 0) {
        $jurnal_kredit[$AKUN_PENDAPATAN_LAIN] = $sisa_bonus;
    }

    // ======================================================
    // 9. VALIDASI NERACA DEBIT = KREDIT
    // ======================================================
    $Total_Debit = array_sum($jurnal_debit);
    $Total_Kredit = array_sum($jurnal_kredit);

    if ($Total_Debit !== $Total_Kredit) {
        throw new Exception(
            "ERROR JURNAL: Debit (" . number_format($Total_Debit) .
            ") ≠ Kredit (" . number_format($Total_Kredit) . "). Selisih: " .
            number_format(abs($Total_Debit - $Total_Kredit))
        );
    }

    // ======================================================
    // 10. INSERT JURNAL UMUM
    // ======================================================
    foreach ($jurnal_debit as $akun => $nilai) {
        if ($nilai > 0) {
            $conn->query("
                INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
                VALUES ('$tgl_jurnal', '$no_bukti_transaksi', '$memo_jurnal', $akun, 'D', $nilai)
            ");
        }
    }

    foreach ($jurnal_kredit as $akun => $nilai) {
        $deskripsi = match ($akun) {
            $AKUN_KAS => 'Pembayaran Gaji Bersih',
            $AKUN_UTANG_PPH21 => 'Utang PPh 21 dan BPJS',
            $AKUN_PENDAPATAN_LAIN => 'Sisa Alokasi Bonus',
            $AKUN_BEBAN_GAJI_MINUS => 'Potongan Non-Utang/Keterlambatan',
            default => $memo_jurnal
        };

        $conn->query("
            INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
            VALUES ('$tgl_jurnal', '$no_bukti_transaksi', '$deskripsi', $akun, 'K', $nilai)
        ");
    }

    // ======================================================
    // 11. COMMIT & SELESAI
    // ======================================================
    $conn->commit();

    $_SESSION['success_message'] =
        "✅ Payroll periode $periode berhasil diproses. Nomor Bukti: $no_bukti_transaksi";

    header("Location: dashboard_owner.php");
    exit();

} catch (Exception $e) {

    $conn->rollback();
    $_SESSION['error_message'] =
        "❌ Gagal memproses Payroll (Rollback): " . $e->getMessage();

    header("Location: dashboard_owner.php");
    exit();
}

?>
