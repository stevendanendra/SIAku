<?php
// penerimaan_kas_form.php
session_start();
include 'koneksi.php'; 

// =======================================================
// LOGIC NOTIFIKASI DAN INISIALISASI
// =======================================================
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']); 
unset($_SESSION['success_message']);
// =======================================================


// Ambil data Master untuk dropdown
$pelanggan_query = $conn->query("SELECT id_pelanggan, nama_pelanggan FROM ms_pelanggan ORDER BY id_pelanggan DESC");
// Filter Layanan Aktif
$layanan_query = $conn->query("SELECT id_layanan, nama_layanan, harga_jual FROM ms_layanan WHERE is_aktif = TRUE");

// Pastikan Kasir sudah login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Karyawan') { 
    header("Location: login.html"); 
    exit(); 
}

$id_karyawan = $_SESSION['id_pengguna'];
$nama_karyawan = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Kasir');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);

// Ambil Akun Piutang
$AKUN_PIUTANG = 1102; 
?>

<?php include '_header.php'; // Header Bootstrap ?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<div class="container mt-5">

    <p><a href='dashboard_karyawan.php' class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Kasir</a></p>
    <h1 class="mb-3 text-success">Penerimaan Kas (POS)</h1> <hr>

    <?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

    <form action="penerimaan_kas_proses.php" method="POST" id="penjualanForm">
        
        <div class="row">
            
            <div class="col-md-6">
                
                <div class="card shadow-sm p-4 mb-4">
                    <h5 class="card-title text-dark">Detail Pelanggan</h5>
                    
                    <div class="mb-3">
                        <label for="id_pelanggan" class="form-label">Nama Pelanggan:</label>
                        <div class="input-group">
                            <select id="id_pelanggan" name="id_pelanggan" class="form-select" required>
                                <option value="">-- Pilih Pelanggan --</option>
                                <?php while ($row = $pelanggan_query->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id_pelanggan']; ?>">
                                        <?php echo htmlspecialchars($row['nama_pelanggan']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                +
                            </button>
                        </div>
                    </div>
                    
                    <h5 class="card-title text-dark mt-4">Input Item Jasa</h5>
                    
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-6">
                            <label for="layanan_select" class="form-label">Pilih Layanan Jasa:</label>
                            <select id="layanan_select" class="form-select">
                                <option value="" data-harga="0">-- Pilih Layanan --</option>
                                <?php if ($layanan_query && $layanan_query->num_rows > 0): ?>
                                    <?php while ($row = $layanan_query->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id_layanan']; ?>" 
                                                data-nama="<?php echo htmlspecialchars($row['nama_layanan']); ?>"
                                                data-harga="<?php echo $row['harga_jual']; ?>">
                                            <?php echo htmlspecialchars($row['nama_layanan']) . ' (Rp ' . number_format($row['harga_jual'], 0, ',', '.') . ')'; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <label for="input_qty" class="form-label">Qty:</label>
                            <input type="number" class="form-control text-end" id="input_qty" value="1" min="1">
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-success w-100 mt-4" onclick="addItem()">Tambah</button>
                        </div>
                    </div>

                    <h5 class="card-title text-dark mt-4">List Item Penjualan</h5>
                    <table class="table table-bordered table-sm mt-3" id="itemsTable">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th>Layanan</th>
                                <th class="text-center" style="width: 15%;">Qty</th>
                                <th class="text-end" style="width: 25%;">Subtotal</th>
                                <th style="width: 15%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-6">
                
                <div class="card shadow-sm p-4 mb-4 border-success"> <h5 class="card-title text-success">Ringkasan & Pembayaran</h5> <div class="mb-3">
                        <label class="form-label">Subtotal (Bruto):</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" id="subtotalBrutoDisplay" class="form-control text-end bg-light" readonly value="0">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="input_disc_global" class="form-label fw-bold text-danger">Diskon Total (Max 100%):</label>
                        <div class="input-group">
                             <input type="number" class="form-control text-end fw-bold text-danger" id="input_disc_global" name="disc_persen_global" value="0" min="0" max="100" oninput="updateSummary()">
                             <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">TOTAL HARGA AKHIR:</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" id="grandTotalDisplay" class="form-control text-end fw-bold bg-success-subtle" readonly value="0"> 
                        </div>
                        <input type="hidden" id="grandTotalInput" name="total_penjualan" value="0">
                        <input type="hidden" name="id_karyawan" value="<?php echo $id_karyawan; ?>">
                    </div>


                    <div class="mb-4">
                        <label class="form-label fw-bold">Metode Pembayaran:</label>
                        <select id="metode_bayar_select" name="metode_bayar" class="form-select" onchange="toggleKreditOptions(this.value)" required>
                            <option value="">-- Pilih Metode Pembayaran --</option>
                            <option value="Kas">1. Tunai (Kas 1101)</option>
                            <option value="Kredit_Termin">2. Kredit (Termin Lunas) - Piutang <?php echo $AKUN_PIUTANG; ?></option>
                            <option value="Kredit_Cicilan">3. Kredit (Cicilan) - Piutang <?php echo $AKUN_PIUTANG; ?></option>
                        </select>
                    </div>
                    
                    <div id="kreditOptionsDiv" style="display:none;" class="p-3 border rounded mb-3 bg-light">
                        <h6 class="text-danger">Detail Piutang:</h6>
                        
                        <div class="mb-3">
                            <label for="tgl_jatuh_tempo" class="form-label">Tanggal Jatuh Tempo:</label>
                            <input type="date" class="form-control text-end" id="tgl_jatuh_tempo" name="tgl_jatuh_tempo">
                        </div>
                        
                        <div class="mb-3" id="jangkaWaktuDiv">
                            <label for="jml_bulan_cicilan" class="form-label">Jangka Waktu (Bulan):</label>
                            <input type="number" class="form-control text-end" id="jml_bulan_cicilan" name="jml_bulan_cicilan" min="1" placeholder="1 = Termin Lunas; >1 = Cicilan">
                        </div>
                        
                        <div class="alert alert-warning small mt-3">
                            Jurnal akan mendebit Akun Piutang (<?php echo $AKUN_PIUTANG; ?>), bukan Kas.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 mt-3" id="submitButton" disabled>Catat Penerimaan</button>
                    
                    <input type="hidden" id="itemsInput" name="items_json"> 
                </div>
            </div>
            
        </div>
        
    </form>
    
</div>

<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="addCustomerModalLabel">Tambah Pelanggan Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="newCustomerForm" action="crud_master_pelanggan_proses.php" method="POST">
            <input type="hidden" name="action" value="add_from_pos">
            
            <div class="mb-3">
                <label for="new_nama" class="form-label">Nama Pelanggan:</label>
                <input type="text" class="form-control" id="new_nama" name="nama_pelanggan" required>
            </div>
            <div class="mb-3">
                <label for="new_telp" class="form-label">No. Telepon:</label>
                <input type="text" class="form-control" id="new_telp" name="no_telepon">
            </div>
            <div class="mb-3">
                <label for="new_alamat" class="form-label">Alamat Lengkap:</label>
                <input type="text" class="form-control" id="new_alamat" name="alamat_lengkap">
            </div>
             <div class="mb-3">
                <label for="new_email" class="form-label">Email:</label>
                <input type="email" class="form-control" id="new_email" name="email">
            </div>
            <button type="submit" class="btn btn-success w-100">Simpan Pelanggan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
let items = [];

function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number).replace('Rp', '').trim();
}

function updateSummary() {
    // 1. Hitung Subtotal Bruto
    let subtotalBruto = items.reduce((sum, item) => sum + item.subtotal_item, 0);

    // 2. Ambil Diskon Global
    let discPercent = parseFloat(document.getElementById('input_disc_global').value) || 0;
    if (discPercent > 100) discPercent = 100;

    // 3. Hitung Diskon dan Total Akhir
    let totalDiscount = Math.floor(subtotalBruto * (discPercent / 100));
    let totalAkhir = subtotalBruto - totalDiscount;

    // Update displays
    document.getElementById('subtotalBrutoDisplay').value = formatRupiah(subtotalBruto);
    document.getElementById('grandTotalDisplay').value = formatRupiah(totalAkhir);
    document.getElementById('grandTotalInput').value = totalAkhir;
    document.getElementById('itemsInput').value = JSON.stringify(items);
    
    // Aktifkan/Nonaktifkan tombol submit
    const isCustomerSelected = document.getElementById('id_pelanggan').value;
    document.getElementById('submitButton').disabled = totalAkhir <= 0 || !isCustomerSelected || items.length === 0;
}

function getNextItemId(id_layanan) {
    // Cek apakah item sudah ada di list
    return items.findIndex(item => item.id_layanan === id_layanan);
}

function addItem() {
    const select = document.getElementById('layanan_select');
    const selectedOption = select.options[select.selectedIndex];
    const qty = parseInt(document.getElementById('input_qty').value);
    
    if (selectedOption.value && qty > 0) {
        const id_layanan = selectedOption.value;
        const index = getNextItemId(id_layanan);
        const harga_satuan = parseInt(selectedOption.getAttribute('data-harga'));

        if (index !== -1) {
            // Tambahkan kuantitas ke item yang sudah ada
            items[index].qty += qty;
            items[index].subtotal_item = items[index].qty * items[index].harga_satuan;
        } else {
            // Tambahkan item baru
            const item = {
                id_layanan: id_layanan,
                nama: selectedOption.getAttribute('data-nama'),
                qty: qty,
                harga_satuan: harga_satuan, 
                subtotal_item: harga_satuan * qty
            };
            items.push(item);
        }
        
        renderItems();
        updateSummary();
        
        // Reset inputs
        select.selectedIndex = 0; 
        document.getElementById('input_qty').value = 1;
    } else {
        alert('Mohon pilih layanan dan masukkan kuantitas > 0.');
    }
}

function modifyItem(index, type) {
    if (index >= 0 && index < items.length) {
        if (type === 'plus') {
            items[index].qty += 1;
        } else if (type === 'minus' && items[index].qty > 1) {
            items[index].qty -= 1;
        } else if (type === 'minus' && items[index].qty === 1) {
            // Hapus item jika kuantitas dikurangi menjadi 0
            items.splice(index, 1);
            renderItems(); 
            updateSummary();
            return; 
        }
        items[index].subtotal_item = items[index].qty * items[index].harga_satuan;
        renderItems();
        updateSummary();
    }
}

function renderItems() {
    const tableBody = document.querySelector('#itemsTable tbody');
    tableBody.innerHTML = '';
    
    items.forEach((item, index) => {
        const row = tableBody.insertRow();
        row.innerHTML = `
            <td>${item.nama}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center align-items-center gap-1">
                    <button type="button" class="btn btn-danger btn-sm" onclick="modifyItem(${index}, 'minus')">-</button>
                    <span class="mx-1 fw-bold">${item.qty}</span>
                    <button type="button" class="btn btn-success btn-sm" onclick="modifyItem(${index}, 'plus')">+</button>
                </div>
            </td>
            <td class="text-end">${formatRupiah(item.subtotal_item)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(${index})">Hapus</button>
            </td>
        `;
    });
}

function toggleKreditOptions(metode) {
    const kreditDiv = document.getElementById('kreditOptionsDiv');
    const inputJatuhTempo = document.getElementById('tgl_jatuh_tempo');
    const inputJangkaWaktu = document.getElementById('jml_bulan_cicilan');
    const jangkaWaktuDiv = document.getElementById('jangkaWaktuDiv');


    // Reset requirement
    inputJatuhTempo.removeAttribute('required');
    inputJangkaWaktu.removeAttribute('required');

    if (metode.startsWith('Kredit')) {
        kreditDiv.style.display = 'block';
        inputJatuhTempo.setAttribute('required', 'required');
        
        if (metode === 'Kredit_Termin') {
            // Kredit Termin (Jangka waktu 1 bulan)
            inputJangkaWaktu.value = 1; 
            inputJangkaWaktu.setAttribute('readonly', true); // Lock input
            jangkaWaktuDiv.style.display = 'none'; // Sembunyikan Jangka Waktu
            calculateDueDate(); // Hitung jatuh tempo 1 bulan
        } else if (metode === 'Kredit_Cicilan') {
            // Kredit Cicilan (Min 2 bulan)
            inputJangkaWaktu.value = 2; // Default 2 bulan
            inputJangkaWaktu.setAttribute('required', 'required');
            inputJangkaWaktu.removeAttribute('readonly'); // Unlock input
            jangkaWaktuDiv.style.display = 'block'; // Tampilkan Jangka Waktu
            calculateDueDate(); // Hitung jatuh tempo default 2 bulan
        }
    } else {
        kreditDiv.style.display = 'none';
        inputJangkaWaktu.value = '';
        inputJatuhTempo.value = ''; 
    }
}

function calculateDueDate() {
    const months = parseInt(document.getElementById('jml_bulan_cicilan').value);
    const inputJatuhTempo = document.getElementById('tgl_jatuh_tempo');
    
    if (months >= 1) {
        const today = new Date();
        let dueDate = new Date(today); 
        
        // FIX KRITIS: Logika offset N-1 bulan
        // Jika N=2, offset = 1 bulan (jatuh tempo bulan depan)
        const monthsToAdd = (months >= 1) ? months - 1 : 0; 
        
        dueDate.setMonth(today.getMonth() + monthsToAdd); 
        
        // Tangani isu akhir bulan (misal 31 Jan + 1 bulan tidak menjadi 31 Feb)
        const targetMonth = today.getMonth() + monthsToAdd;
        if (dueDate.getMonth() !== (targetMonth % 12)) {
             dueDate.setDate(0); 
             dueDate.setMonth(targetMonth); 
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

// INISIALISASI SELECT2 DAN EVENT LISTENERS
document.addEventListener('DOMContentLoaded', () => {
    // INISIALISASI SELECT2 PADA PELANGGAN (membuat searchable)
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
        $('#id_pelanggan').select2({
            theme: "bootstrap", 
            placeholder: "-- Pilih Nama Pelanggan --" 
        });
        
        // Memastikan updateSummary dipanggil saat Select2 berubah nilainya
        $('#id_pelanggan').on('change', function() {
            updateSummary();
        });
    }
    
    updateSummary();
    
    // Event listeners for dynamic update
    document.getElementById('input_disc_global').addEventListener('input', updateSummary);
    
    // Event listener baru untuk memanggil toggle saat load
    const metodeSelect = document.getElementById('metode_bayar_select');
    if (metodeSelect) toggleKreditOptions(metodeSelect.value);
    
    // FIX KRITIS: Event listener baru untuk JANGKA WAKTU
    document.getElementById('jml_bulan_cicilan').addEventListener('change', calculateDueDate);
    document.getElementById('jml_bulan_cicilan').addEventListener('keyup', calculateDueDate);
});
</script>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_karyawan; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_karyawan; ?>">

<?php include '_footer.php'; // Footer Bootstrap ?>