<?php
// pengeluaran_kas_form.php
session_start();
include 'koneksi.php';

// --- 1. CEK AUTENTIKASI ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Karyawan' && $_SESSION['role'] !== 'Owner')) {
    header("Location: login.html");
    exit();
}

// --- INISIALISASI VARIABEL LOGIN ---
$id_user_pencatat = $_SESSION['id_pengguna'];
$role_saat_ini   = $_SESSION['role'];

$back_link = ($role_saat_ini === 'Owner') ? 'dashboard_owner.php' : 'dashboard_karyawan.php';

// Variabel untuk footer
$id_login   = htmlspecialchars($id_user_pencatat);
$role_login = htmlspecialchars($role_saat_ini);
$nama_login = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Pengguna');
$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);


// --------------------------------------------------
// --- LOGIKA QUERY MASTER AKUN ---

$AKUN_PRIVE   = 3201;
$AKUN_KAS     = 1101;
$AKUN_PIUTANG = 1102;

if ($role_saat_ini === 'Owner') {

    $sql_akun_debit = "
        SELECT id_akun, nama_akun, tipe_akun
        FROM ms_akun
        WHERE id_akun = {$AKUN_PRIVE}
    ";

} else {

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


// --------------------------------------------------
// --- 3. LOGIKA NOTIFIKASI ---
$error_message   = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';

unset($_SESSION['error_message']);
unset($_SESSION['success_message']);

?>

<?php include '_header.php'; ?>

<div class="container mt-5">

    <p>
        <a href="<?= $back_link ?>" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard</a>
    </p>

    <h1 class="mb-4 display-6 text-danger">2. Pencatatan Pengeluaran Kas</h1>
    <hr>

    <?php if ($error_message): ?>
        <div class='alert alert-danger'><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class='alert alert-success'><?= $success_message ?></div>
    <?php endif; ?>


    <div class="card shadow-lg p-4 border-danger">
        <h3 class="card-title h5 text-danger">Form Pengeluaran</h3>

        <form action="pengeluaran_kas_proses.php" method="POST">

            <div class="row">

                <!-- KOLOM KIRI -->
                <div class="col-md-6">

                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi Pengeluaran:</label>
                        <input type="text" class="form-control" id="deskripsi" name="deskripsi" required
                               placeholder="<?= ($role_saat_ini === 'Owner' ? 'Contoh: Penarikan Prive' : 'Contoh: Beli Perlengkapan, Bayar Sewa') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="akun_debit" class="form-label">Akun Debit (Tujuan Pengeluaran):</label>
                        <select id="akun_debit" name="id_akun_debit" class="form-select" required>
                            <option value="">-- Pilih Akun Debit --</option>
                            <?php while ($row = $akun_debit_query->fetch_assoc()): ?>
                                <option value="<?= $row['id_akun'] ?>">
                                    <?= htmlspecialchars($row['id_akun'] . ' - ' . $row['nama_akun']) ?> (<?= $row['tipe_akun'] ?>)
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

                    <input type="hidden" name="id_karyawan" value="<?= $id_user_pencatat ?>">
                </div>


                <!-- KOLOM KANAN -->
                <div class="col-md-6">

                    <div class="mb-3">
                        <label for="metode" class="form-label fw-bold">Metode Pembayaran:</label>
                        <select id="metode" name="metode_bayar" class="form-select" required onchange="togglePengeluaranKredit(this.value)">
                            <option value="">-- Pilih Metode --</option>
                            <option value="Tunai">Tunai (Mengurangi Kas 1101)</option>

                            <?php if ($role_saat_ini !== 'Owner'): ?>
                                <option value="Kredit_Termin">Kredit (Termin Lunas) - Utang Usaha 2101</option>
                                <option value="Kredit_Cicilan">Kredit (Cicilan) - Utang Usaha 2101</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div id="kredit_options_div"
                         style="display:none; <?= ($role_saat_ini === 'Owner') ? 'visibility:hidden;height:0;' : '' ?>"
                         class="p-3 border rounded mb-3 bg-light">

                        <h5 class="mt-3 text-warning">Detail Utang:</h5>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Jatuh Tempo LUNAS:</label>
                            <input type="date" class="form-control text-end" id="jatuh_tempo" name="tgl_jatuh_tempo">
                        </div>

                        <div id="cicilan_div" style="display:none;">
                            <label class="form-label">Jumlah Bulan Cicilan:</label>
                            <input type="number" class="form-control text-end" id="jml_cicilan"
                                   name="jml_bulan_cicilan" min="2" placeholder="Minimum 2 bulan">
                        </div>

                    </div>

                    <button type="submit" class="btn btn-danger w-100 mt-4">Catat Pengeluaran</button>

                </div>

            </div>
        </form>

    </div>

    <!-- HIDDEN INPUT UNTUK FOOTER -->
    <input type="hidden" id="session-role" value="<?= $role_login ?>">
    <input type="hidden" id="session-nama" value="<?= $nama_login ?>">
    <input type="hidden" id="session-id"   value="<?= $id_login ?>">

</div>


<!-- ============================== -->
<!-- JAVASCRIPT UTAMA -->
<!-- ============================== -->

<script>
document.addEventListener('DOMContentLoaded', () => {

    const inputCicilan = document.getElementById('jml_cicilan');
    const metodeSelect = document.getElementById('metode');
    const akunDebit    = document.getElementById('akun_debit');

    if (inputCicilan) inputCicilan.addEventListener('change', calculateDueDate);

    if (metodeSelect) {
        metodeSelect.addEventListener('change', () => {
            togglePengeluaranKredit(metodeSelect.value);

            if (metodeSelect.value === 'Kredit_Cicilan' && inputCicilan && inputCicilan.value >= 1) {
                calculateDueDate();
            }
        });
    }

    // AUTO SELECT KHUSUS OWNER
    if ('<?= $role_saat_ini ?>' === 'Owner') {
        document.getElementById('metode').value = 'Tunai';

        if (akunDebit.options.length > 1) {
            for (let i = 0; i < akunDebit.options.length; i++) {
                if (akunDebit.options[i].value == '3201') {
                    akunDebit.selectedIndex = i;
                    break;
                }
            }
        }
    }

    togglePengeluaranKredit(metodeSelect.value);
});



// ================================
// FUNGSI TOGGLE MODE KREDIT
// ================================
function togglePengeluaranKredit(metode) {

    const kreditDiv      = document.getElementById('kredit_options_div');
    const cicilanDiv     = document.getElementById('cicilan_div');
    const inputTempo     = document.getElementById('jatuh_tempo');
    const inputCicilan   = document.getElementById('jml_cicilan');

    const role = '<?= $role_saat_ini ?>';

    if (role === 'Owner') return;

    inputTempo.removeAttribute('required');
    inputCicilan.removeAttribute('required');
    inputTempo.value = '';

    kreditDiv.style.display  = 'none';
    cicilanDiv.style.display = 'none';

    if (metode === 'Kredit_Termin') {
        kreditDiv.style.display = 'block';
        inputTempo.setAttribute('required', 'required');
    }

    if (metode === 'Kredit_Cicilan') {
        kreditDiv.style.display = 'block';
        cicilanDiv.style.display = 'block';
        inputTempo.setAttribute('required', 'required');
        inputCicilan.setAttribute('required', 'required');
    }
}


// ================================
// AUTO HITUNG JATUH TEMPO CICILAN
// ================================
function calculateDueDate() {

    const months = parseInt(document.getElementById('jml_cicilan').value);
    const inputTempo = document.getElementById('jatuh_tempo');

    if (months >= 1) {

        const today = new Date();
        let dueDate = new Date();

        dueDate.setMonth(today.getMonth() + (months - 1));

        if (dueDate.getDate() !== today.getDate() && months > 1) {
            dueDate.setDate(0);
            dueDate.setMonth(today.getMonth() + (months - 1));
            dueDate.setDate(today.getDate());
        }

        const y = dueDate.getFullYear();
        const m = String(dueDate.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');

        inputTempo.value = `${y}-${m}-${d}`;
    } else {
        inputTempo.value = '';
    }
}
</script>

<?php include '_footer.php'; ?>
