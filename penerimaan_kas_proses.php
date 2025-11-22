<?php
// penerimaan_kas_proses.php
session_start(); 
include 'koneksi.php'; // Memuat koneksi + logAktivitas()

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: penerimaan_kas_form.php");
    exit();
}

// =======================================================
// 1. INISIALISASI DATA POST
// =======================================================
$id_pelanggan = $_POST['id_pelanggan'] ?? '';
$id_karyawan = $_POST['id_karyawan'] ?? '';
$total_penjualan_netto = (int)($_POST['total_penjualan'] ?? 0);
$metode_bayar_raw = $_POST['metode_bayar'] ?? ''; 
$items_json = $_POST['items_json'] ?? '[]';
$disc_persen_global = (float)($_POST['disc_persen_global'] ?? 0);

$tgl_transaksi = date('Y-m-d'); 

// Mapping metode bayar
$metode_bayar_db = $metode_bayar_raw;
if ($metode_bayar_raw === 'Kredit_Termin') $metode_bayar_db = 'Termin';
elseif ($metode_bayar_raw === 'Kredit_Cicilan') $metode_bayar_db = 'Cicilan';

$is_lunas = ($metode_bayar_db === 'Kas') ? 1 : 0;
$tgl_jatuh_tempo_raw = $_POST['tgl_jatuh_tempo'] ?? null;
$jml_bulan_cicilan = ($is_lunas === 0) ? (int)($_POST['jml_bulan_cicilan'] ?? 1) : 0;

$tgl_jatuh_tempo = (!empty($tgl_jatuh_tempo_raw) && $is_lunas === 0) ? $tgl_jatuh_tempo_raw : null;

// Akun
$AKUN_KAS = 1101;
$AKUN_PIUTANG = 1102;
$AKUN_DISKON_PENJUALAN = 4102;

$items = json_decode($items_json, true);

if (empty($id_pelanggan) || empty($items) || $total_penjualan_netto <= 0) {
    $_SESSION['error_message'] = "Data transaksi tidak valid.";
    header("Location: penerimaan_kas_form.php");
    exit();
}

// =======================================================
// 2. HITUNG TOTAL BRUTO & PENDAPATAN
// =======================================================
$total_bruto = 0;
$revenue_group = [];
$layanan_map = [];

$item_ids = array_map(fn($item) => $item['id_layanan'], $items);
$item_ids_str = implode(',', $item_ids);

$layanan_res = $conn->query("SELECT id_layanan, id_akun_pendapatan FROM ms_layanan WHERE id_layanan IN ($item_ids_str)");
while ($row = $layanan_res->fetch_assoc()) {
    $layanan_map[$row['id_layanan']] = $row['id_akun_pendapatan'];
}

foreach ($items as $item) {
    $subtotal = $item['harga_satuan'] * $item['qty'];
    $total_bruto += $subtotal;

    $id_akun_pendapatan = $layanan_map[$item['id_layanan']] ?? null;
    if ($id_akun_pendapatan) {
        if (!isset($revenue_group[$id_akun_pendapatan])) {
            $revenue_group[$id_akun_pendapatan] = 0;
        }
        $revenue_group[$id_akun_pendapatan] += $subtotal;
    }
}

$total_diskon_rp = $total_bruto - $total_penjualan_netto;


// =======================================================
// 3. START TRANSACTION
// =======================================================
$conn->begin_transaction();
$success = true;
$id_transaksi_baru = null;

try {
    // A. INSERT ke MASTER
    $stmt_penjualan = $conn->prepare("
        INSERT INTO tr_penjualan (
            tgl_transaksi, id_pelanggan, id_karyawan, metode_bayar,
            total_penjualan, total_diskon_rp, is_lunas, tgl_jatuh_tempo, jml_bulan_cicilan
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $bind_types = ($tgl_jatuh_tempo === null) ? "sissiisii" : "sissisisi";
    $jt_bind = ($tgl_jatuh_tempo === null) ? NULL : $tgl_jatuh_tempo;

    $stmt_penjualan->bind_param(
        $bind_types, 
        $tgl_transaksi, $id_pelanggan, $id_karyawan, $metode_bayar_db,
        $total_penjualan_netto, $total_diskon_rp, $is_lunas,
        $jt_bind, $jml_bulan_cicilan
    );

    $stmt_penjualan->execute();
    $id_transaksi_baru = $conn->insert_id;
    $stmt_penjualan->close();

    if (!$id_transaksi_baru) throw new Exception("Gagal menyimpan transaksi master.");


    // B. INSERT DETAIL
    $stmt_detail = $conn->prepare("
        INSERT INTO tr_penjualan_detail
        (id_penjualan, id_layanan, jumlah_layanan, harga_satuan, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $harga_satuan = $item['harga_satuan'];
        $subtotal = $item['qty'] * $harga_satuan;

        $stmt_detail->bind_param(
            "iiiii",
            $id_transaksi_baru, $item['id_layanan'], $item['qty'], $harga_satuan, $subtotal
        );

        if (!$stmt_detail->execute()) {
            throw new Exception("Gagal menyimpan detail transaksi.");
        }
    }
    $stmt_detail->close();


    // C. JURNAL UMUM
    $no_bukti = "PJL-" . $id_transaksi_baru;

    // 1. Debet (Kas / Piutang)
    $id_akun_debet = ($metode_bayar_db === 'Kas') ? $AKUN_KAS : $AKUN_PIUTANG;
    $stmt_jurnal_d = $conn->prepare("
        INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
        VALUES (?, ?, ?, ?, 'D', ?)
    ");
    $ket_debet = "Pencatatan Penjualan Netto";
    $stmt_jurnal_d->bind_param("ssisi",
        $tgl_transaksi, $no_bukti, $ket_debet, $id_akun_debet, $total_penjualan_netto
    );
    if (!$stmt_jurnal_d->execute()) {
        throw new Exception("Gagal mencatat jurnal debet.");
    }
    $stmt_jurnal_d->close();


    // 2. Kredit per akun pendapatan
    $stmt_jurnal_k = $conn->prepare("
        INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
        VALUES (?, ?, ?, ?, 'K', ?)
    ");
    $ket_kredit = "Pencatatan Pendapatan Bruto";
    foreach ($revenue_group as $id_akun => $nominal) {
        $stmt_jurnal_k->bind_param("ssisi",
            $tgl_transaksi, $no_bukti, $ket_kredit, $id_akun, $nominal
        );
        if (!$stmt_jurnal_k->execute()) {
            throw new Exception("Gagal mencatat jurnal kredit.");
        }
    }
    $stmt_jurnal_k->close();


    // 3. Diskon (jika ada)
    if ($total_diskon_rp > 0) {
        $stmt_jurnal_disk = $conn->prepare("
            INSERT INTO tr_jurnal_umum (tgl_jurnal, no_bukti, deskripsi, id_akun, posisi, nilai)
            VALUES (?, ?, ?, ?, 'D', ?)
        ");
        $ket_disk = "Diskon Penjualan ID #$id_transaksi_baru";
        $stmt_jurnal_disk->bind_param("ssisi",
            $tgl_transaksi, $no_bukti, $ket_disk, $AKUN_DISKON_PENJUALAN, $total_diskon_rp
        );
        if (!$stmt_jurnal_disk->execute()) {
            throw new Exception("Gagal mencatat jurnal diskon.");
        }
        $stmt_jurnal_disk->close();
    }


    // ============================
    // COMMIT
    // ============================
    $conn->commit();
    $_SESSION['success_message'] =
        "Transaksi Penjualan #$id_transaksi_baru berhasil disimpan.";

    // ============================
    // LOG SUCCESS
    // ============================
    if (function_exists('logAktivitas')) {
        $user_id  = $_SESSION['id_pengguna'] ?? 0;
        $username = $_SESSION['username'] ?? 'SYSTEM_USER';

        logAktivitas(
            $user_id,
            $username,
            "Mencatat transaksi penjualan ID: $id_transaksi_baru (Berhasil)",
            "Penerimaan Kas"
        );
    }

} catch (Exception $e) {

    // ============================
    // ROLLBACK
    // ============================
    $conn->rollback();
    $_SESSION['error_message'] = 
        "Transaksi GAGAL! Error: " . $e->getMessage();
    $success = false;

    // ============================
    // LOG FAILURE
    // ============================
    if (function_exists('logAktivitas')) {
        $user_id  = $_SESSION['id_pengguna'] ?? 0;
        $username = $_SESSION['username'] ?? 'SYSTEM_USER';

        logAktivitas(
            $user_id,
            $username,
            "Gagal mencatat transaksi penjualan. Error: " . $e->getMessage(),
            "Penerimaan Kas"
        );
    }
}


// =======================================================
// 4. REDIRECT
// =======================================================
header("Location: penerimaan_kas_form.php");
exit();

?>
