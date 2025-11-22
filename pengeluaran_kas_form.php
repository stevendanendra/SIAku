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

// --- LOGIKA QUERY MASTER AKUN ---
$AKUN_PRIVE = 3201; // Akun Prive
$AKUN_KAS = 1101; 
$AKUN_PIUTANG = 1102; 

if ($role_saat_ini === 'Owner') {
    // Jika OWNER: Hanya boleh Prive (3201)
    $sql_akun_debit = "
        SELECT id_akun, nama_akun, tipe_akun 
        FROM ms_akun 
        WHERE id_akun = {$AKUN_PRIVE}
    ";
} else {
    // Jika KARYAWAN: Boleh semua Beban/Aset, kecuali Prive (3201), Kas, Piutang
    $sql_akun_debit = "
        SELECT id_akun, nama_akun, tipe_akun
        FROM ms_akun 
        WHERE saldo_normal = 'D' 
        AND id_akun NOT IN ({$AKUN_KAS}, {$AKUN_PIUTANG}, {$AKUN_PRIVE}) 
        ORDER BY tipe_akun ASC, id_akun
    ";
}

$akun_debit_query = $conn->query($sql_akun_debit);

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
    
    <p><a href='<?php echo $back_link; ?>' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard</a></p>
    <h1 class="mb-4 display-6 text-danger">2. Pencatatan Pengeluaran Kas</h1>
    <hr>
    
    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>
    
    <div class="card shadow-lg p-4 border-danger">
        <h3 class="card-title h5 text-danger">Form Pengeluaran</h3>
        
        <form action="pengeluaran_kas_proses.php" method="POST">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi Pengeluaran:</label>
                        <input type="text" class="form-control" id="deskripsi" name="deskripsi" required 
                               placeholder="<?php echo $role_saat_ini === 'Owner' ? 'Contoh: Penarikan Prive' : 'Contoh: Beli Perlengkapan, Bayar Sewa'; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="akun_debit" class="form-label">Akun Debit (Tujuan Pengeluaran):</label>
                        <select id="akun_debit" name="id_akun_debit" class="form-select" required>
                            <option value="">-- Pilih Akun Debit --</option>
                            <?php while ($row = $akun_debit_query->fetch_assoc()): ?>
                                <option value="<?php echo $row['id_akun']; ?>">
                                    <?php echo htmlspecialchars($row['id_akun'] . ' - ' . $row['nama_akun']) . ' (' . $row['tipe_akun'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jumlah" class="form-label fw-bold">Jumlah (Rp):</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control text-end" id="jumlah" name="jumlah" required min="1000">
                        </div>
                    </div>
                    
                    <input type="hidden" name="id_karyawan" value="<?php echo $id_user_pencatat; ?>">
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="metode" class="form-label fw-bold">Metode Pembayaran:</label>
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
                          style="display:none; <?php echo $role_saat_ini === 'Owner' ? 'visibility:hidden; height:0;' : ''; ?>"
                          class="p-3 border rounded mb-3 bg-light">
                        <h5 class="mt-3 text-warning">Detail Utang:</h5>
                        
                        <div class="mb-3">
                            <label for="jatuh_tempo" class="form-label">Tanggal Jatuh Tempo LUNAS:</label>
                            <input type="date" class="form-control text-end" id="jatuh_tempo" name="tgl_jatuh_tempo">
                        </div>
                        
                        <div id="cicilan_div" style="display:none;">
                            <label for="jml_cicilan" class="form-label">Jumlah Bulan Cicilan:</label>
                            <input type="number" class="form-control text-end" id="jml_cicilan" name="jml_bulan_cicilan" min="2" placeholder="Minimun 2 bulan">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-danger w-100 mt-4">Catat Pengeluaran</button>
                </div>
            </div>
            
        </form>
    </div>
    
    <hr class="my-4">
    <p class="text-muted small">Akses: <?php echo $role_saat_ini; ?> (ID <?php echo $id_user_pencatat; ?>)</p> 
    <p><a href='<?php echo $back_link; ?>' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard</a></p>

<script>
    const namaLogin = '<?php echo $nama_login ?? "Pengguna"; ?>'; 
    
    document.addEventListener('DOMContentLoaded', () => {
        const inputCicilan = document.getElementById('jml_cicilan');
        const metodeSelect = document.getElementById('metode');
        const akunDebit = document.getElementById('akun_debit');
        
        // --- Event Listeners ---
        if (inputCicilan) inputCicilan.addEventListener('change', calculateDueDate);
        if (metodeSelect) metodeSelect.addEventListener('change', () => {
             togglePengeluaranKredit(metodeSelect.value);
             if (metodeSelect.value === 'Kredit_Cicilan' && inputCicilan && inputCicilan.value && inputCicilan.value >= 1) {
                 calculateDueDate();
             }
        });

        // --- Auto Select Logic (Owner) ---
        if ('<?php echo $role_saat_ini; ?>' === 'Owner') {
            document.getElementById('metode').value = 'Tunai'; 
            // Owner hanya boleh Prive (3201)
            if (akunDebit.options.length > 1) {
                 // Cari dan pilih akun 3201 (Prive) secara otomatis
                 for(let i=0; i < akunDebit.options.length; i++) {
                     if (akunDebit.options[i].value == '3201') {
                          akunDebit.selectedIndex = i;
                          break;
                     }
                 }
            }
        }
        
        // Panggil toggle sekali saat load untuk setting awal visibility
        togglePengeluaranKredit(metodeSelect.value);
    });

    function togglePengeluaranKredit(metode) {
        const kreditDiv = document.getElementById('kredit_options_div');
        const cicilanDiv = document.getElementById('cicilan_div');
        const inputJatuhTempo = document.getElementById('jatuh_tempo');
        const inputCicilan = document.getElementById('jml_cicilan');
        const role = '<?php echo $role_saat_ini; ?>'; 

        // Jika Owner, tidak perlu logic toggle (Div sudah di-hidden via CSS)
        if (role === 'Owner') return; 

        // Reset inputs and visibility
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
            
            // Jatuh tempo harus di bulan yang merupakan (months) setelah bulan ini
            dueDate.setMonth(today.getMonth() + (months - 1)); 
            
            // Handle overflow (contoh: 31 Jan + 1 bulan = 3 Mar)
            if (dueDate.getDate() !== today.getDate() && months > 1) {
                 dueDate.setDate(0); 
                 dueDate.setDate(today.getDate()); 
            }
            
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
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_saat_ini; ?> (<?php echo $nama_login ?? "Pengguna"; ?>, ID <?php echo $id_user_pencatat; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>