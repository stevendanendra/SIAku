<?php
// pengeluaran_kas_form.php
session_start(); 
include 'koneksi.php'; 

// --- 1. CEK AUTENTIKASI ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Karyawan' && $_SESSION['role'] !== 'Owner')) { 
    header("Location: login.html"); 
    exit(); 
}

$id_user_pencatat = $_SESSION['id_pengguna']; 
$role_saat_ini = $_SESSION['role'];
$back_link = ($role_saat_ini === 'Owner') ? 'dashboard_owner.php' : 'dashboard_karyawan.php';

// --- LOGIKA QUERY MASTER AKUN (FIX KRITIS) ---
if ($role_saat_ini === 'Owner') {
    // Jika OWNER: Hanya boleh Prive (3201)
    $sql_akun_debit = "
        SELECT id_akun, nama_akun 
        FROM ms_akun 
        WHERE id_akun = 3201
    ";
} else {
    // Jika KARYAWAN: Boleh semua Beban/Aset, kecuali Prive (3201)
    $sql_akun_debit = "
        SELECT id_akun, nama_akun 
        FROM ms_akun 
        WHERE saldo_normal = 'D' 
        AND id_akun NOT IN (1101, 1102, 3201) 
        ORDER BY id_akun
    ";
}

$akun_debit_query = $conn->query($sql_akun_debit);

// Cek jika Query Akun Gagal (Penting untuk debugging)
if (!$akun_debit_query) {
    die("Error Database Query Akun: " . $conn->error);
}

// --- 3. LOGIKA PESAN NOTIFIKASI ---
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']); 
unset($_SESSION['success_message']);
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">

    <h1 class="mb-4">2. Pencatatan Pengeluaran Kas</h1>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>
    
    <div class="card shadow-sm p-4">
        <h3 class="card-title h5 text-primary">Form Pengeluaran</h3>
        <form action="pengeluaran_kas_proses.php" method="POST">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi Pengeluaran:</label>
                        <input type="text" class="form-control" id="deskripsi" name="deskripsi" required 
                               placeholder="<?php echo $role_saat_ini === 'Owner' ? 'Contoh: Penarikan Prive' : 'Contoh: Beli Perlengkapan 5301'; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="akun_debit" class="form-label">Akun Debit (Tujuan Pengeluaran):</label>
                        <select id="akun_debit" name="id_akun_debit" class="form-select" required>
                            <option value="">-- Pilih Akun Debit --</option>
                            <?php while ($row = $akun_debit_query->fetch_assoc()): ?>
                                <option value="<?php echo $row['id_akun']; ?>">
                                    <?php echo htmlspecialchars($row['id_akun'] . ' - ' . $row['nama_akun']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jumlah" class="form-label">Jumlah (BIGINT):</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="jumlah" name="jumlah" required min="1000">
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="metode" class="form-label">Metode Pembayaran:</label>
                        <select id="metode" name="metode_bayar" class="form-select" required onchange="togglePengeluaranKredit(this.value)">
                            <option value="">-- Pilih Metode --</option>
                            <option value="Tunai">Tunai (Mengurangi Kas 1101)</option>
                            
                            <?php if ($role_saat_ini !== 'Owner'): ?>
                            <option value="Kredit_Termin">Kredit (Termin Lunas) - Menambah Utang Usaha 2101</option>
                            <option value="Kredit_Cicilan">Kredit (Cicilan) - Menambah Utang Usaha 2101</option> 
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div id="kredit_options_div" 
                         style="display:none; <?php echo $role_saat_ini === 'Owner' ? 'visibility:hidden; height:0;' : ''; ?>">
                        <h5 class="mt-3">Detail Utang:</h5>
                        
                        <div class="mb-3">
                            <label for="jatuh_tempo" class="form-label">Tanggal Jatuh Tempo LUNAS:</label>
                            <input type="date" class="form-control" id="jatuh_tempo" name="tgl_jatuh_tempo">
                        </div>
                        
                        <div id="cicilan_div" style="display:none;">
                            <label for="jml_cicilan" class="form-label">Jumlah Bulan Cicilan:</label>
                            <input type="number" class="form-control" id="jml_cicilan" name="jml_bulan_cicilan" min="2">
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success w-100 mt-3">Catat Pengeluaran</button>
        </form>
    </div>
    
    <hr class="my-4">
    <p class="text-muted">Akses: <?php echo $role_saat_ini; ?> (ID <?php echo $id_user_pencatat; ?>)</p> 
    <p><a href='<?php echo $back_link; ?>' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard</a></p>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const inputCicilan = document.getElementById('jml_cicilan');
        
        inputCicilan.addEventListener('change', calculateDueDate);
        
        document.getElementById('metode').addEventListener('change', () => {
            const metode = document.getElementById('metode').value;
            togglePengeluaranKredit(metode);
            
            // Re-calculate date if switching back to installment mode
            if (metode === 'Kredit_Cicilan' && inputCicilan.value && inputCicilan.value >= 1) {
                calculateDueDate();
            }
        });

        // AUTO SELECT: Jika Owner login, defaultkan metode ke Tunai dan akun ke Prive
        if ('<?php echo $role_saat_ini; ?>' === 'Owner') {
            const akunDebit = document.getElementById('akun_debit');
            document.getElementById('metode').value = 'Tunai'; 
            if (akunDebit.options.length > 1) {
                akunDebit.selectedIndex = 1; // Prive should be the second option
            }
        }
    });

    function togglePengeluaranKredit(metode) {
        const kreditDiv = document.getElementById('kredit_options_div');
        const cicilanDiv = document.getElementById('cicilan_div');
        const inputJatuhTempo = document.getElementById('jatuh_tempo');
        const inputCicilan = document.getElementById('jml_cicilan');
        const role = '<?php echo $role_saat_ini; ?>'; 

        // Jika Owner, tidak perlu logic toggle (sudah disembunyikan via CSS/visibility)
        if (role === 'Owner') return; 

        // Reset
        inputJatuhTempo.removeAttribute('required');
        inputCicilan.removeAttribute('required');
        inputJatuhTempo.value = ''; 
        kreditDiv.style.display = 'none';
        cicilanDiv.style.display = 'none';

        if (metode === 'Kredit_Termin') {
            kreditDiv.style.display = 'block';
            inputJatuhTempo.setAttribute('required', 'required');
        } else if (metode === 'Kredit_Cicilan') {
            kreditDiv.style.display = 'block';
            inputJatuhTempo.setAttribute('required', 'required');
            cicilanDiv.style.display = 'block';
            inputCicilan.setAttribute('required', 'required');
        }
    }

    function calculateDueDate() {
        const months = parseInt(document.getElementById('jml_cicilan').value);
        const inputJatuhTempo = document.getElementById('jatuh_tempo');
        
        if (months >= 1) {
            const today = new Date();
            let dueDate = new Date();
            
            dueDate.setMonth(today.getMonth() + (months - 1)); 
            
            const year = dueDate.getFullYear();
            const month = String(dueDate.getMonth() + 1).padStart(2, '0'); 
            const day = String(today.getDate()).padStart(2, '0');

            inputJatuhTempo.value = `${year}-${month}-${day}`;
        } else {
             inputJatuhTempo.value = '';
        }
    }
</script>

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_saat_ini; ?> (<?php echo $nama_login; ?>, ID <?php echo $id_user_pencatat; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>