<?php
// payroll_proses_eksekutor.php (revisi full — dinamis tanpa hardcode 5101/5103)
// Proses: menerima data preview (single atau massal) lalu mencatat tr_gaji, tr_gaji_detail, dan tr_jurnal_umum
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
$periode = $conn->real_escape_string($_POST['periode'] ?? '');
$periode_db = $periode . '-01';
$tgl_jurnal = date('Y-m-d');

// Cek jenis input (single vs massal)
if (isset($_POST['data_payroll_single'])) {
    $data_payroll_json = $_POST['data_payroll_single'];
    $sisa_bonus = 0;
} elseif (isset($_POST['data_payroll_massal'])) {
    $data_payroll_json = $_POST['data_payroll_massal'];
    $sisa_bonus = (int) ($_POST['sisa_bonus'] ?? 0);
} else {
    $_SESSION['error_message'] = "❌ Gagal: Data payroll tidak ditemukan.";
    header("Location: dashboard_owner.php");
    exit();
}

// decode JSON (toleran terhadap htmlspecialchars)
$data_payroll_raw = json_decode(htmlspecialchars_decode($data_payroll_json), true);
if (!is_array($data_payroll_raw)) {
    $_SESSION['error_message'] = "❌ Gagal: format data payroll tidak valid.";
    header("Location: dashboard_owner.php");
    exit();
}

$data_payroll = array_filter(
    $data_payroll_raw,
    fn($p) => !($p['is_processed'] ?? false)
);

// =======================================================
// 3. KONSTANTA AKUN (target posting jurnal)
// =======================================================
$AKUN_KAS = 1101;
$AKUN_BEBAN_GAJI_TOTAL = 5100;   // Semua beban gaji di-debit ke sini
$AKUN_UTANG_PPH21 = 2102;        // Semua kewajiban (is_liability = 1) -> kredit 2102
$AKUN_PENDAPATAN_LAIN = 4200;    // Semua potongan non-utang (is_liability = 0) -> kredit 4200

$NAMA_BEBAN_BPJS_PERUSAHAAN = 'Beban BPJS Ditanggung Perusahaan';
$NAMA_POTONGAN_BPJS = 'Potongan BPJS';

// =======================================================
// 4. AMBIL MAPPING KOMPOSISI GAJI (DINAMIS TANPA 5101/5103)
// =======================================================
$komponen_map = [];               // key: nama_komponen => meta
$komponen_gaji_pokok_map = [];    // key: role => row

$q = $conn->query("SELECT id_komponen, nama_komponen, id_akun_beban, tipe, is_liability 
                   FROM ms_gaji_komponen");

if ($q === false) {
    $_SESSION['error_message'] = "❌ Gagal mengambil master komponen: " . $conn->error;
    header("Location: dashboard_owner.php");
    exit();
}

while ($row = $q->fetch_assoc()) {
    $komponen_map[$row['nama_komponen']] = [
        'id_komponen'   => (int) $row['id_komponen'],
        'nama'          => $row['nama_komponen'],
        'tipe'          => $row['tipe'],
        'id_akun_beban' => (int) $row['id_akun_beban'],
        'is_liability'  => (int) $row['is_liability']
    ];

    // Mapping gaji pokok berdasarkan nama komponen "Gaji Pokok - <Role>"
    if (stripos($row['nama_komponen'], 'Gaji Pokok -') === 0) {
        $parts = explode('-', $row['nama_komponen'], 2);
        $role_name = trim($parts[1] ?? '');
        if ($role_name !== '') {
            $komponen_gaji_pokok_map[$role_name] = [
                'id_komponen'   => (int) $row['id_komponen'],
                'nama'          => $row['nama_komponen'],
                'id_akun_beban' => (int) $row['id_akun_beban'],
                'is_liability'  => (int) $row['is_liability'],
                'tipe'          => $row['tipe']
            ];
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
// 5. MULAI TRANSAKSI
// =======================================================
$conn->begin_transaction();

try {

    // Akumulator jurnal
    $Total_Beban_Gaji_Debit = 0;
    $Total_Kas_Keluar = 0;
    $Total_Utang_Pajak_PPh21 = 0;
    $Total_Potongan_4200_Credit = 0;
    $Total_Beban_BPJS_Perusahaan = 0;

    $id_login = $_SESSION['id_pengguna'] ?? 0;
    $username_login = $_SESSION['username'] ?? 'SYSTEM_USER';

    $no_bukti_transaksi = "PAYROLL-$periode-" . time();
    $memo_jurnal = "Pencatatan Gaji Karyawan periode $periode";

    // ===================================================
    // 6. LOOP PEGAWAI
    // ===================================================
    foreach ($data_payroll as $pgw) {

        $id_pengguna = (int) ($pgw['id_pengguna'] ?? 0);
        $gaji_bersih = (int) ($pgw['gaji_bersih'] ?? 0);
        $total_penambah = (int) ($pgw['total_penambah'] ?? 0);
        $total_pengurang = (int) ($pgw['total_pengurang'] ?? 0);
        $role = trim($pgw['role'] ?? '');

        // Cek duplikasi slip
        $dup_sql = "
            SELECT id_slip_gaji FROM tr_gaji
            WHERE id_cleaner = {$id_pengguna}
            AND DATE_FORMAT(periode_gaji, '%Y-%m') = '$periode'
            LIMIT 1
        ";
        $dup = $conn->query($dup_sql);
        if ($dup === false) throw new Exception("Gagal cek duplikasi slip: " . $conn->error);
        if ($dup->num_rows > 0) continue;

        // Ambil gaji pokok dari mapping berdasarkan role
        $gaji_pokok = (int) (($pgw['rinci_penambah']['Gaji Pokok'] ?? 0));

        // Jika preview form sudah memastikan gaji pokok tersedia, kita lanjut.
        // Namun kita juga coba fallback ke komponen_gaji_pokok_map jika nilai 0
        if ($gaji_pokok <= 0) {
            $komp_pokok = $komponen_gaji_pokok_map[$role] ?? null;
            if ($komp_pokok !== null) {
                // jika ada mapping role di master, gunakan nilai default dari ms_gaji_komponen
                // ambil nilai_default dari ms_gaji_komponen untuk id_komponen tersebut
                $rowK = $conn->query("SELECT nilai_default FROM ms_gaji_komponen WHERE id_komponen = {$komp_pokok['id_komponen']} LIMIT 1");
                if ($rowK && $r = $rowK->fetch_assoc()) {
                    $gaji_pokok = (int) $r['nilai_default'];
                }
            }
        }

        // Kalau masih 0, set 0 (boleh saja) — slip tetap dibuat (atau batalkan? kita buat slip namun nilai 0)
        // Insert slip
        $sql = "
            INSERT INTO tr_gaji
            (id_cleaner, periode_gaji, tgl_proses, gaji_pokok, gaji_bersih, total_penambah, total_pengurang, id_jurnal_umum, status_jurnal)
            VALUES
            ({$id_pengguna}, '{$periode_db}', '{$tgl_jurnal}', {$gaji_pokok}, {$gaji_bersih}, {$total_penambah}, {$total_pengurang}, NULL, 1)
        ";

        if (!$conn->query($sql)) {
            throw new Exception("Gagal insert tr_gaji: " . $conn->error);
        }

        $id_slip_gaji = $conn->insert_id;

        // ----------------------------------------
        // A. PENAMBAH
        // ----------------------------------------
        foreach ($pgw['rinci_penambah'] as $nama => $nilai_raw) {
            $nilai = (int) $nilai_raw;

            // Resolve komponen: jika nama berisi 'Gaji Pokok' gunakan mapping role, else gunakan nama langsung
            if (stripos($nama, 'Gaji Pokok') !== false) {
                // cari mapping komponen berdasarkan role
                $komp = $komponen_gaji_pokok_map[$role] ?? null;
                if ($komp === null) {
                    // fallback: cari di komponen_map dengan nama persis 'Gaji Pokok' atau 'Gaji Pokok - ...'
                    $komp = $komponen_map[$nama] ?? null;
                }
            } else {
                $komp = $komponen_map[$nama] ?? null;
            }

            if (!$komp) {
                throw new Exception("Komponen Penambah '$nama' tidak ditemukan pada master komponen.");
            }

            // Insert detail slip
            $ins_detail = "
                INSERT INTO tr_gaji_detail (id_slip_gaji, id_komponen, nilai_dihitung)
                VALUES ({$id_slip_gaji}, {$komp['id_komponen']}, {$nilai})
            ";
            if (!$conn->query($ins_detail)) {
                throw new Exception("Gagal insert tr_gaji_detail (penambah): " . $conn->error);
            }

            // Semua akun beban (semula 5xxx) dipost ke 5100 (AKUN_BEBAN_GAJI_TOTAL)
            // Kita menambahkan nilai ke total beban jika komponen tipe 'Penambah' atau id_akun_beban adalah 5xxx
            if ($komp['tipe'] === 'Penambah' || ($komp['id_akun_beban'] >= 5000 && $komp['id_akun_beban'] < 6000)) {
                $Total_Beban_Gaji_Debit += $nilai;
            }

            // Jika komponen adalah beban BPJS Perusahaan, akumulasikan untuk penyesuaian utang nanti
            if ($komp['nama'] === $NAMA_BEBAN_BPJS_PERUSAHAAN) {
                $Total_Beban_BPJS_Perusahaan += $nilai;
            }
        }

        // ----------------------------------------
        // B. PENGURANG
        // ----------------------------------------
        foreach ($pgw['rinci_pengurang'] as $nama => $nilai_raw) {
            $nilai = (int) $nilai_raw;
            $komp = $komponen_map[$nama] ?? null;

            if (!$komp) {
                throw new Exception("Komponen Pengurang '$nama' tidak ditemukan pada master komponen.");
            }

            // Insert detail slip
            $ins_detail = "
                INSERT INTO tr_gaji_detail (id_slip_gaji, id_komponen, nilai_dihitung)
                VALUES ({$id_slip_gaji}, {$komp['id_komponen']}, {$nilai})
            ";
            if (!$conn->query($ins_detail)) {
                throw new Exception("Gagal insert tr_gaji_detail (pengurang): " . $conn->error);
            }

            // Jika komponen ditandai sebagai kewajiban (is_liability == 1) => kredit UTANG (2102)
            if ((int)$komp['is_liability'] === 1) {
                $Total_Utang_Pajak_PPh21 += $nilai;
            } else {
                // Non-utang => dianggap potongan non-utang => kredit PENDAPATAN_LAIN (4200)
                $Total_Potongan_4200_Credit += $nilai;
            }
        }

        // Total kas keluar
        $Total_Kas_Keluar += $gaji_bersih;
    }

    // ======================================================
    // 7. SYNC UTANG 2102 (gabungkan utang pajak dan beban bpjs jika aturan mengharuskan)
    // ======================================================
    // Beberapa sistem menempatkan sebagian beban BPJS perusahaan sebagai kewajiban sementara
    // Di desain ini: kita tambahkan Total_Beban_BPJS_Perusahaan ke Utang jika diperlukan
$Total_Utang_Pajak_BPJS_PPh21 = $Total_Utang_Pajak_PPh21; 

    // ======================================================
    // 8. SUSUN FINAL JURNAL
    // ======================================================
    $jurnal_debit = [];
    $jurnal_kredit = [];

    if ($Total_Beban_Gaji_Debit > 0) {
        $jurnal_debit[$AKUN_BEBAN_GAJI_TOTAL] = $Total_Beban_Gaji_Debit;
    }

    if ($Total_Kas_Keluar > 0) {
        $jurnal_kredit[$AKUN_KAS] = $Total_Kas_Keluar;
    }

    if ($Total_Utang_Pajak_BPJS_PPh21 > 0) {
        $jurnal_kredit[$AKUN_UTANG_PPH21] = $Total_Utang_Pajak_BPJS_PPh21;
    }

    $Total_Kredit_4200_Final = $sisa_bonus + $Total_Potongan_4200_Credit;

    if ($Total_Kredit_4200_Final > 0) {
        $jurnal_kredit[$AKUN_PENDAPATAN_LAIN] = $Total_Kredit_4200_Final;
    }

    // ======================================================
    // 9. VALIDASI DEBIT = KREDIT
    // ======================================================
    $Total_Debit = array_sum($jurnal_debit);
    $Total_Kredit = array_sum($jurnal_kredit);

    if ($Total_Debit !== $Total_Kredit) {
        throw new Exception("ERROR JURNAL: Debit ($Total_Debit) ≠ Kredit ($Total_Kredit)");
    }

    // ======================================================
    // 10. INSERT JURNAL UMUM
    // ======================================================
    foreach ($jurnal_debit as $akun => $nilai) {
        $sql_ins = "
            INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
            VALUES ('{$tgl_jurnal}', '{$no_bukti_transaksi}', '{$memo_jurnal}', {$akun}, 'D', {$nilai})
        ";
        if (!$conn->query($sql_ins)) {
            throw new Exception("Gagal insert jurnal debit: " . $conn->error);
        }
    }

    foreach ($jurnal_kredit as $akun => $nilai) {
        $deskripsi = $memo_jurnal;
        if ($akun == $AKUN_KAS) $deskripsi = 'Pembayaran Gaji Bersih';
        if ($akun == $AKUN_UTANG_PPH21) $deskripsi = 'Utang Pajak & BPJS';
        if ($akun == $AKUN_PENDAPATAN_LAIN) $deskripsi = 'Sisa Bonus / Potongan Non-Utang';

        $sql_ins = "
            INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
            VALUES ('{$tgl_jurnal}', '{$no_bukti_transaksi}', '{$deskripsi}', {$akun}, 'K', {$nilai})
        ";
        if (!$conn->query($sql_ins)) {
            throw new Exception("Gagal insert jurnal kredit: " . $conn->error);
        }
    }

    // ======================================================
    // 11. COMMIT
    // ======================================================
    $conn->commit();

    $_SESSION['success_message'] = "✔️ Payroll berhasil diproses untuk periode $periode.";
    header("Location: dashboard_owner.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "❌ Gagal memproses payroll: " . $e->getMessage();
    header("Location: dashboard_owner.php");
    exit();
}
?>
