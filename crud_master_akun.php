<?php
// crud_master_akun.php
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') { 
    header("Location: login.html"); 
    exit(); 
}

// --- INISIALISASI DATA LOGIN ---
$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);

// --------------------------------------------------

$error_message = '';
$success_message = '';

// --- LOGIKA PESAN DARI SESI (DELETE/EDIT) ---
if (isset($_SESSION['error_delete'])) {
    $error_message .= $_SESSION['error_delete'];
    unset($_SESSION['error_delete']);
}

if (isset($_SESSION['success_delete'])) {
    $success_message .= $_SESSION['success_delete'];
    unset($_SESSION['success_delete']);
}

if (isset($_SESSION['success_message_edit'])) {
    $success_message .= $_SESSION['success_message_edit'];
    unset($_SESSION['success_message_edit']);
}

// ===============================================================
// ========================== CREATE =============================
// ===============================================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {

    $id_akun = $conn->real_escape_string($_POST['id_akun']);
    $nama_akun = $conn->real_escape_string($_POST['nama_akun']);
    $tipe_akun = $conn->real_escape_string($_POST['tipe_akun']);
    $saldo_normal = $conn->real_escape_string($_POST['saldo_normal']);
    $saldo_awal = $conn->real_escape_string($_POST['saldo_awal']);

    // ---- VALIDASI DATA ----
    if (empty($id_akun) || empty($nama_akun) || empty($tipe_akun) || empty($saldo_normal)) {
        $error_message = "Semua field wajib diisi.";
    }
    elseif (!is_numeric($saldo_awal) || floor($saldo_awal) != $saldo_awal) {
        $error_message = "Saldo Awal harus bilangan bulat.";
    }
    else {

        // ---- CEK DUPLICATE ID AKUN ----
        $cek_q = $conn->query("SELECT id_akun FROM ms_akun WHERE id_akun='$id_akun'");
        if ($cek_q && $cek_q->num_rows > 0) {
            $error_message = "❌ Nomor akun $id_akun sudah terpakai. Gunakan nomor lain.";
        } else {

            // ---- INSERT JIKA AMAN ----
            $sql = "
                INSERT INTO ms_akun (id_akun, nama_akun, tipe_akun, saldo_normal, saldo_awal, saldo_saat_ini) 
                VALUES ('$id_akun', '$nama_akun', '$tipe_akun', '$saldo_normal', '$saldo_awal', '$saldo_awal')
            ";

            if ($conn->query($sql) === TRUE) {
                $success_message = "Akun '$nama_akun' berhasil ditambahkan.";
            } else {
                $error_message = "Error saat menyimpan data: " . $conn->error;
            }
        }
    }
}

// ===============================================================
// ========================== READ ===============================
// ===============================================================

$akun_query = $conn->query("SELECT * FROM ms_akun ORDER BY id_akun ASC");

?>

<?php include '_header.php'; ?>

<div class="container mt-5">
    <h1 class="mb-4">Kelola Master Data Perusahaan</h1>

    <?php include 'navbar_master.php'; ?>

    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>

    <h2>Daftar Akun (COA)</h2>

    <?php if ($error_message): ?>
        <div class='alert alert-danger'><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class='alert alert-success'><?= $success_message ?></div>
    <?php endif; ?>


    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-4 mb-4">
                <h3 class="card-title h5">Tambah Akun Baru</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label">No. Akun:</label>
                        <input type="number" class="form-control" name="id_akun" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Akun:</label>
                        <input type="text" class="form-control" name="nama_akun" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tipe Akun:</label>
                        <select class="form-select" name="tipe_akun" required>
                            <option value="">Pilih</option>
                            <option value="Aktiva">1xxx - Aktiva (Aset)</option>
                            <option value="Kewajiban">2xxx - Kewajiban</option>
                            <option value="Modal">3xxx - Modal</option>
                            <option value="Pendapatan">4xxx - Pendapatan</option>
                            <option value="Beban">5xxx - Beban</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Saldo Normal:</label>
                        <select class="form-select" name="saldo_normal" required>
                            <option value="D">Debit (D)</option>
                            <option value="K">Kredit (K)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Saldo Awal (Rp):</label>
                        <input type="number" class="form-control text-end" name="saldo_awal" value="0" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Simpan Akun</button>
                </form>
            </div>
        </div>


        <div class="col-md-8">
            <h3 class="mb-3 h5">Daftar Akun Aktif</h3>

            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>No. Akun</th>
                            <th>Nama Akun</th>
                            <th>Tipe</th>
                            <th>S.Normal</th>
                            <th class="text-end">Saldo Awal</th>
                            <th class="text-end">Saldo Saat Ini</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $akun_query->fetch_assoc()): ?>
                            <?php
                                $saldo_val = (int)$row['saldo_saat_ini'];
                                $warna = $saldo_val < 0 ? 'text-danger' : 'text-primary';
                            ?>
                            <tr>
                                <td><?= $row['id_akun'] ?></td>
                                <td><?= htmlspecialchars($row['nama_akun']) ?></td>
                                <td><?= $row['tipe_akun'] ?></td>
                                <td><?= $row['saldo_normal'] ?></td>
                                <td class="text-end">Rp <?= number_format($row['saldo_awal'],0,',','.') ?></td>
                                <td class="text-end fw-bold <?= $warna ?>">
                                    Rp <?= number_format(abs($saldo_val),0,',','.') ?>
                                </td>
                                <td>
                                    <a href="crud_master_akun_edit.php?id=<?= $row['id_akun'] ?>" 
                                       class="btn btn-sm btn-info text-white">Ubah</a>

                                    <a href="#" 
                                       class="btn btn-sm btn-danger"
                                       data-bs-toggle="modal"
                                       data-bs-target="#confirmDeleteAkunModal"
                                       data-id="<?= $row['id_akun'] ?>"
                                       data-nama="<?= htmlspecialchars($row['nama_akun']) ?>">
                                       Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>


<!-- ================= MODAL DELETE ====================== -->

<div class="modal fade" id="confirmDeleteAkunModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Konfirmasi Penghapusan Akun</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <p>Anda yakin ingin menghapus akun berikut?</p>

        <p>
            <strong>No. Akun:</strong> <span id="modal-delete-akun-id"></span><br>
            <strong>Nama Akun:</strong> <span id="modal-delete-akun-nama"></span>
        </p>

        <div class="alert alert-warning small">
            *Penghapusan gagal jika akun memiliki transaksi jurnal.*
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="delete-akun-link" class="btn btn-danger">Hapus</a>
      </div>
    </div>
  </div>
</div>

<script>
    // Modal Delete
    const deleteModal = document.getElementById('confirmDeleteAkunModal');

    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nama = button.getAttribute('data-nama');

        document.getElementById('modal-delete-akun-id').textContent = id;
        document.getElementById('modal-delete-akun-nama').textContent = nama;

        document.getElementById('delete-akun-link').href =
            'crud_master_akun_delete.php?id=' + id;
    });
</script>

<input type="hidden" id="session-role" value="<?= $role_login ?>">
<input type="hidden" id="session-nama" value="<?= $nama_owner ?>">
<input type="hidden" id="session-id" value="<?= $id_login ?>">

<?php include '_footer.php'; ?>
