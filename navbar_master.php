<?php 
// Deteksi halaman aktif
$current = basename($_SERVER['PHP_SELF']);
?>

<div class="d-flex flex-wrap gap-2 mb-4 border-bottom pb-3"> 

    <?php
    // Daftar menu navbar master
    $menus = [
        "crud_master_akun.php"      => "📊 Master Akun (COA)",
        "crud_master_layanan.php"   => "🧼 Master Layanan Jasa",
        "crud_master_pengguna.php"  => "👤 Master Pengguna & Karyawan",
        "crud_master_pelanggan.php" => "👥 Master Pelanggan",
        "crud_master_supplier.php"  => "📦 Master Supplier",
        "payroll_komponen.php"      => "💵 Pengaturan Payroll"
    ];

    foreach ($menus as $file => $label) {
        $active = ($current === $file) ? 'active' : '';
        echo "
        <a href='$file' class='btn btn-outline-dark btn-sm $active'>
            $label
        </a>
        ";
    }
    ?>
</div>

<style>
    .btn-outline-dark.active {
        background-color: #e9ecef !important; /* abu-abu muda elegan */
        color: #000 !important;
        border-color: #ced4da !important;
        font-weight: 600;
    }
</style>
