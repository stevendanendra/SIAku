<?php
// payroll_komponen_edit.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) { 
    header("Location: login.html"); 
    exit(); 
}

$id_komponen = $conn->real_escape_string($_GET['id']);
$error_message = '';
$success_message = '';

// --- INISIALISASI DATA LOGIN ---
$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);
// --------------------------------------------------

// --- DEFINISI MAPPING AKUN KHUSUS PAYROLL ---
function mapPayrollAccount($id_akun, $is_liability) {
    if ($is_liability == 1) {
        return ['Kategori' => 'Kewajiban', 'SubAkun' => 'Utang (2102)'];
    }
    switch ($id_akun) {
        case 5101:
            return ['Kategori' => 'Beban', 'SubAkun' => 'Gaji Pokok (5101)'];
        case 5102:
            return ['Kategori' => 'Beban', 'SubAkun' => 'Komponen Plus (5102)'];
        case 5103:
            return ['Kategori' => 'Beban', 'SubAkun' => 'Komponen Minus (5103)'];
        case 2102:
            return ['Kategori' => 'Kewajiban', 'SubAkun' => 'Utang PPh 21 (2102)'];
        default:
            return ['Kategori' => 'Tidak Dikenal', 'SubAkun' => $id_akun];
    }
}

// --- LOGIKA UPDATE DATA (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    
    $nama_komponen = $conn->real_escape_string($_POST['nama_komponen']);
    $tipe = $conn->real_escape_string($_POST['tipe']);
    $nilai_default = $conn->real_escape_string($_POST['nilai_default']);
    
    // Mengambil nilai is_persentase langsung dari dropdown (0 atau 1)
    $is_persentase = $conn->real_escape_string($_POST['is_persentase']); 
    
    // >> START: LOGIKA OTOMATISASI AKUN BARU (5103)
    $is_liability = isset($_POST['is_liability']) ? 1 : 0;
    $id_akun_beban = 0; 

    $is_gaji_pokok_edited = isset($_POST['id_akun_beban_fixed']) && $_POST['id_akun_beban_fixed'] == 5101;
    
    if ($is_gaji_pokok_edited) {
        $id_akun_beban = 5101; // Gaji Pokok tidak boleh diubah
        $is_liability = 0;
    } elseif ($is_liability == 1) {
        $id_akun_beban = 2102; // Utang (Kredit)
    } elseif ($tipe == 'Pengurang') {
        $id_akun_beban = 5103; // Beban Komponen Minus
    } else { // Tipe Penambah
        $id_akun_beban = 5102; // Beban Komponen Plus
    }
    // << END: LOGIKA OTOMATISASI AKUN BARU

    // Validasi dasar
    if (empty($nama_komponen) || empty($tipe) || !is_numeric($nilai_default)) {
        $error_message = "Semua field wajib diisi dan Nilai Default harus numerik.";
    } else {
        $sql = "UPDATE ms_gaji_komponen SET 
                nama_komponen = '$nama_komponen', 
                tipe = '$tipe', 
                nilai_default = '$nilai_default', 
                is_persentase = '$is_persentase',     
                id_akun_beban = '$id_akun_beban', 
                is_liability = '$is_liability'   
                WHERE id_komponen = '$id_komponen'";
        
        if ($conn->query($sql) === TRUE) {
            $_SESSION['success_message'] = "Komponen #$id_komponen berhasil diperbarui. Akun terkait disetel ke $id_akun_beban.";
            header("Location: payroll_komponen.php?success=update"); 
            exit();
        } else {
            $error_message = "Error saat memperbarui komponen: " . $conn->error;
        }
    }
}

// --- LOGIKA AMBIL DATA LAMA (READ FOR UPDATE) ---
$sql_data = "SELECT id_komponen, nama_komponen, tipe, nilai_default, is_persentase, id_akun_beban, is_liability 
             FROM ms_gaji_komponen WHERE id_komponen = '$id_komponen'";
$result = $conn->query($sql_data);

if ($result->num_rows == 0) {
    die("Komponen gaji tidak ditemukan.");
}

$data = $result->fetch_assoc();
$akun_info_current = mapPayrollAccount($data['id_akun_beban'], $data['is_liability']);
?>

<?php include '_header.php'; // Header Bootstrap ?>
<div class="container mt-5">
    
    <h1 class="mb-4">Ubah Komponen Gaji (Payroll Setting)</h1>
    
    <p><a href="payroll_komponen.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Komponen</a></p>
    <hr>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if (isset($_SESSION['success_message'])) {
        echo "<div class='alert alert-success'>" . $_SESSION['success_message'] . "</div>";
        unset($_SESSION['success_message']);
    } ?>

    <div class="row">
        
        <div class="col-md-6">
            <div class="card shadow-sm p-4 mb-4">
                <h3 class="card-title h5">Edit Komponen: #<?php echo $id_komponen . ' - ' . htmlspecialchars($data['nama_komponen']); ?></h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="mb-3">
                        <label class="form-label">ID Komponen:</label>
                        <input type="text" class="form-control" value="<?php echo $id_komponen; ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="nama_komponen" class="form-label">Nama Komponen:</label>
                        <input type="text" class="form-control" name="nama_komponen" value="<?php echo htmlspecialchars($data['nama_komponen']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipe" class="form-label">Tipe:</label>
                        <select class="form-select" name="tipe" required>
                            <option value="Penambah" <?php echo ($data['tipe'] == 'Penambah') ? 'selected' : ''; ?>>Penambah (Akun 5102)</option>
                            <option value="Pengurang" <?php echo ($data['tipe'] == 'Pengurang') ? 'selected' : ''; ?>>Pengurang (Akun 5103 atau 2102)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nilai_default" class="form-label">Nilai Default:</label> 
                        <input type="number" class="form-control" name="nilai_default" value="<?php echo $data['nilai_default']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="is_persentase" class="form-label">Jenis Nilai:</label>
                        <select class="form-select" name="is_persentase" required>
                            <option value="0" <?php echo ($data['is_persentase'] == 0) ? 'selected' : ''; ?>>Nominal (Rp)</option>
                            <option value="1" <?php echo ($data['is_persentase'] == 1) ? 'selected' : ''; ?>>Persentase (%)</option>
                        </select>
                    </div>
                    
                    <?php 
                    $is_gaji_pokok = ($data['id_akun_beban'] == 5101);
                    $disabled_attr = $is_gaji_pokok ? 'disabled' : '';
                    $checked_attr = ($data['is_liability'] == 1) ? 'checked' : '';
                    ?>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_liability" name="is_liability" value="1" <?php echo $checked_attr; ?> <?php echo $disabled_attr; ?>>
                        <label class="form-check-label" for="is_liability">Apakah ini **Potongan Kewajiban** (Utang/Pajak)?</label>
                        <small class="form-text text-muted d-block">
                            <?php if ($is_gaji_pokok): ?>
                                *Tidak dapat diubah. Gaji Pokok selalu dipetakan ke **5101 (Beban Gaji)**.
                            <?php elseif ($data['tipe'] == 'Pengurang'): ?>
                                *Jika dicentang: Akun otomatis **2102 (Utang)**.<br>
                                *Jika tidak dicentang: Akun otomatis **5103 (Beban Komponen Minus)**.
                            <?php else: ?>
                                *Akun otomatis **5102 (Beban Komponen Plus)**.
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <?php if ($is_gaji_pokok): ?>
                         <input type="hidden" name="is_liability" value="0">
                         <input type="hidden" name="id_akun_beban_fixed" value="5101">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100">Simpan Perubahan Komponen</button>
                </form>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow-sm p-4 mb-4 bg-light">
                <h3 class="card-title h5 text-primary">Informasi Akun Saat Ini</h3>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item">**Akun Tercatat:** <span class="badge bg-secondary"><?php echo $data['id_akun_beban']; ?></span></li>
                    <li class="list-group-item">**Sub-Akun Payroll:** <span class="badge bg-secondary"><?php echo $akun_info_current['SubAkun']; ?></span></li>
                    <li class="list-group-item mt-2">**CATATAN KEBIJAKAN BARU:**
                        <ul>
                            <li>5101: Gaji Pokok.</li>
                            <li>5102: Komponen Penambah (Tunjangan/Bonus).</li>
                            <li>5103: Komponen Pengurang Non-Utang.</li>
                            <li>2102: Potongan Kewajiban (Utang).</li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    const role = '<?php echo $role_login; ?>';
    const nama = '<?php echo $nama_login; ?>';
    const id = '<?php echo $id_login; ?>';
    document.getElementById('access-info').innerHTML = `Akses: **${role}** (${nama}, ID ${id})`;
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>