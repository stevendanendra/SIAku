<?php
// pengeluaran_kas_form.php
session_start(); 
include 'koneksi.php'; 

// --- 1. CEK AUTENTIKASI ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Karyawan' && $_SESSION['role'] !== 'Owner')) { 
    header("Location: login.html"); 
    exit(); 
}

$id_karyawan = $_SESSION['id_pengguna'];
$nama_login = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? '');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);

$id_user_pencatat = $_SESSION['id_pengguna']; 
$role_saat_ini = $_SESSION['role'];
$back_link = ($role_saat_ini === 'Owner') ? 'dashboard_owner.php' : 'dashboard_karyawan.php';

// --- LOGIKA QUERY MASTER AKUN ---
$AKUN_PRIVE   = 3201; 
$AKUN_KAS     = 1101; 
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

// --- LOGIKA QUERY MASTER SUPPLIER ---
$supplier_query = $conn->query("SELECT id_supplier, nama_supplier FROM ms_supplier ORDER BY nama_supplier ASC");

// --- 3. LOGIKA PESAN NOTIFIKASI ---
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']); 
unset($_SESSION['success_message']);
?>

<?php include '_header.php'; ?>

<div class="container mt-5">
    
    <p><a href='<?php echo $back_link; ?>' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard</a></p>
    <h1 class="mb-4 display-6 text-danger">Pencatatan Pengeluaran Kas</h1>
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
                        <label for="id_supplier" class="form-label fw-bold">Pemasok (Supplier):</label>
                        <div class="input-group">
                            <select id="id_supplier" name="id_supplier" class="form-select" required> 
                                <option value="">-- Pilih Pemasok --</option>
                                <?php while ($row = $supplier_query->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id_supplier']; ?>">
                                        <?php echo htmlspecialchars($row['nama_supplier']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                +
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="metode" class="form-label fw-bold">Metode Pembayaran:</label>
                        <select id="metode" name="metode_bayar" class="form-select" required onchange="togglePengeluaranKredit(this.value)">
                            <option value="">-- Pilih Metode --</option>
                            <option value="Tunai">Tunai (Mengurangi Kas 1101)</option>
                            
                            <?php if ($role_saat_ini !== 'Owner'): ?>
                            <option value="Kredit_Termin">Kredit (Termin Lunas)</option>
                            <option value="Kredit_Cicilan">Kredit (Cicilan)</option> 
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
                            <input type="number" class="form-control text-end" id="jml_cicilan" name="jml_bulan_cicilan" min="2" placeholder="Minimum 2 bulan">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-danger w-100 mt-4">Catat Pengeluaran</button>
                </div>
            </div>
            
        </form>
    </div>
    
<!-- ========== MODAL TAMBAH SUPPLIER ========= -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="addSupplierModalLabel">Tambah Pemasok Baru</h5>
      </div>
      <div class="modal-body">
        <form id="newSupplierForm" action="crud_master_supplier_proses.php" method="POST">

            <input type="hidden" name="action" value="add">
            <input type="hidden" name="from_pengeluaran" value="1">

            <div class="mb-3">
                <label for="new_supplier_nama" class="form-label">Nama Pemasok:</label>
                <input type="text" class="form-control" id="new_supplier_nama" name="nama_supplier" required>
            </div>
            <div class="mb-3">
                <label for="new_supplier_telp" class="form-label">No. Telepon:</label>
                <input type="text" class="form-control" id="new_supplier_telp" name="no_telepon">
            </div>
            <div class="mb-3">
                <label for="new_supplier_alamat" class="form-label">Alamat Lengkap:</label>
                <input type="text" class="form-control" id="new_supplier_alamat" name="alamat_lengkap">
            </div>
            <div class="mb-3">
                <label for="new_supplier_email" class="form-label">Email:</label>
                <input type="email" class="form-control" id="new_supplier_email" name="email">
            </div>
            
            <button type="submit" name="submit" form="newSupplierForm" class="btn btn-success w-100">Simpan Pemasok</button>

        </form>
      </div>
    </div>
  </div>
</div>

<script>
    const namaLogin = '<?php echo $nama_login; ?>'; 
    
    document.addEventListener('DOMContentLoaded', () => {
        const inputCicilan = document.getElementById('jml_cicilan');
        const metodeSelect = document.getElementById('metode');
        const akunDebit = document.getElementById('akun_debit');
        
        if (inputCicilan) inputCicilan.addEventListener('change', calculateDueDate);
        if (metodeSelect) metodeSelect.addEventListener('change', () => {
             togglePengeluaranKredit(metodeSelect.value);
             if (metodeSelect.value === 'Kredit_Cicilan' && inputCicilan && inputCicilan.value && inputCicilan.value >= 1) {
                 calculateDueDate();
             }
        });

        if ('<?php echo $role_saat_ini; ?>' === 'Owner') {
            document.getElementById('metode').value = 'Tunai'; 
            if (akunDebit.options.length > 1) {
                 for(let i=0; i < akunDebit.options.length; i++) {
                     if (akunDebit.options[i].value == '3201') {
                          akunDebit.selectedIndex = i;
                          break;
                     }
                 }
            }
        }
        
        togglePengeluaranKredit(metodeSelect.value);
    });

    function togglePengeluaranKredit(metode) {
        const kreditDiv = document.getElementById('kredit_options_div');
        const cicilanDiv = document.getElementById('cicilan_div');
        const inputJatuhTempo = document.getElementById('jatuh_tempo');
        const inputCicilan = document.getElementById('jml_cicilan');

        const role = '<?php echo $role_saat_ini; ?>'; 
        if (role === 'Owner') return; 

        if (inputJatuhTempo) inputJatuhTempo.removeAttribute('required');
        if (inputCicilan) inputCicilan.removeAttribute('required');
        if (inputJatuhTempo) inputJatuhTempo.value = ''; 
        
        kreditDiv.style.display = 'none';
        cicilanDiv.style.display = 'none';

        if (metode === 'Kredit_Termin') {
             kreditDiv.style.display = 'block';
             inputJatuhTempo.setAttribute('required', 'required');
        } 
        else if (metode === 'Kredit_Cicilan') {
            kreditDiv.style.display = 'block';
            cicilanDiv.style.display = 'block';
            inputJatuhTempo.setAttribute('required', 'required');
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
            
            if (dueDate.getDate() !== today.getDate() && months > 1) {
                 dueDate.setDate(0); 
                 dueDate.setMonth(today.getMonth() + (months - 1));
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

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_login; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<?php include '_footer.php'; ?>
