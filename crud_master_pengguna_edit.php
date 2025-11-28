<?php
// crud_master_pengguna_edit.php (revisi)
// - Hash password jika diganti
// - Validasi unik username/email
// - Prepared statements
session_start();
include 'koneksi.php'; // Memuat fungsi logAktivitas() jika ada

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner' || !isset($_GET['id'])) {
    header("Location: login.html");
    exit();
}

$id_pengguna = (int)$conn->real_escape_string($_GET['id']);
$error_message = '';
$success_message = '';

// --- INISIALISASI DATA LOGIN ---
$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna'] ?? '0');
$username_login = htmlspecialchars($_SESSION['username'] ?? 'SYSTEM_USER');
// --------------------------------------------------

// Ambil data lama
$sql_data = "SELECT * FROM ms_pengguna WHERE id_pengguna = ?";
$stmt = $conn->prepare($sql_data);
$stmt->bind_param("i", $id_pengguna);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    die("Pengguna tidak ditemukan.");
}
$data = $res->fetch_assoc();
$stmt->close();

// --- LOGIKA UPDATE DATA (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_password_raw = $_POST['password'] ?? ''; // kalau kosong berarti tidak diganti
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $is_menikah = isset($_POST['is_menikah']) ? 1 : 0;
    $jumlah_anak = is_numeric($_POST['jumlah_anak'] ?? '') ? (int)$_POST['jumlah_anak'] : 0;
    $is_aktif = isset($_POST['is_aktif']) ? 1 : 0;

    // Validasi dasar
    if (empty($username) || empty($email) || empty($nama_lengkap) || empty($role)) {
        $error_message = "Username, Email, Nama Lengkap, dan Role wajib diisi.";
    } else {
        // cek unik username (kecuali milik user ini)
        $chk = $conn->prepare("SELECT id_pengguna FROM ms_pengguna WHERE username = ? AND id_pengguna != ?");
        $chk->bind_param("si", $username, $id_pengguna);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error_message = "Username sudah digunakan oleh pengguna lain.";
            $chk->close();
        } else {
            $chk->close();
            // cek unik email
            $chk2 = $conn->prepare("SELECT id_pengguna FROM ms_pengguna WHERE email = ? AND id_pengguna != ?");
            $chk2->bind_param("si", $email, $id_pengguna);
            $chk2->execute();
            $chk2->store_result();
            if ($chk2->num_rows > 0) {
                $error_message = "Email sudah terdaftar untuk pengguna lain.";
                $chk2->close();
            } else {
                $chk2->close();

                // Siapkan update (dynamic)
                $params = [];
                $types = '';
                $sql_set_parts = "username = ?, email = ?, nama_lengkap = ?, role = ?, is_menikah = ?, jumlah_anak = ?, is_aktif = ?";
                $types .= "ssssiis"; // placeholder, will rebuild below (we'll do bind dynamically)
                // We'll use prepared statement with explicit binding:
                if (!empty($new_password_raw)) {
                    // Hash password sebelum disimpan
                    $hashed = password_hash($new_password_raw, PASSWORD_DEFAULT);
                    $sql = "UPDATE ms_pengguna SET username = ?, email = ?, nama_lengkap = ?, role = ?, is_menikah = ?, jumlah_anak = ?, is_aktif = ?, password = ? WHERE id_pengguna = ?";
                    $stmt_upd = $conn->prepare($sql);
                    if (!$stmt_upd) {
                        $error_message = "Error internal (prepare): " . $conn->error;
                    } else {
                        $stmt_upd->bind_param(
                            "ssssiissi",
                            $username,
                            $email,
                            $nama_lengkap,
                            $role,
                            $is_menikah,
                            $jumlah_anak,
                            $is_aktif,
                            $hashed,
                            $id_pengguna
                        );
                        if ($stmt_upd->execute()) {
                            $_SESSION['success_message_edit'] = "Data pengguna #$id_pengguna berhasil diperbarui (termasuk password).";
                            // Log aktivitas
                            if (function_exists('logAktivitas')) {
                                logAktivitas($id_login, $username_login, "Mengubah detail pengguna ID $id_pengguna (termasuk password)", "Master Pengguna");
                            }
                            header("Location: crud_master_pengguna.php");
                            exit();
                        } else {
                            $error_message = "Error saat memperbarui pengguna: " . $stmt_upd->error;
                        }
                        $stmt_upd->close();
                    }
                } else {
                    // Tanpa ubah password
                    $sql = "UPDATE ms_pengguna SET username = ?, email = ?, nama_lengkap = ?, role = ?, is_menikah = ?, jumlah_anak = ?, is_aktif = ? WHERE id_pengguna = ?";
                    $stmt_upd = $conn->prepare($sql);
                    if (!$stmt_upd) {
                        $error_message = "Error internal (prepare): " . $conn->error;
                    } else {
                        $stmt_upd->bind_param(
                            "ssssiiii",
                            $username,
                            $email,
                            $nama_lengkap,
                            $role,
                            $is_menikah,
                            $jumlah_anak,
                            $is_aktif,
                            $id_pengguna
                        );
                        if ($stmt_upd->execute()) {
                            $_SESSION['success_message_edit'] = "Data pengguna #$id_pengguna berhasil diperbarui.";
                            if (function_exists('logAktivitas')) {
                                logAktivitas($id_login, $username_login, "Mengubah detail pengguna ID $id_pengguna", "Master Pengguna");
                            }
                            header("Location: crud_master_pengguna.php");
                            exit();
                        } else {
                            $error_message = "Error saat memperbarui pengguna: " . $stmt_upd->error;
                        }
                        $stmt_upd->close();
                    }
                }
            }
        }
    }
}

// Reload data if updated? (we redirected on success). Otherwise keep $data for display.
?>

<?php include '_header.php'; // Header Bootstrap ?>

<div class="container mt-5">

```
<h1 class="mb-4">Ubah Data Master Pengguna & Karyawan</h1>

<div class="d-flex gap-3 mb-4 border-bottom pb-3">
    <a href="crud_master_akun.php" class="btn btn-outline-dark btn-sm">📊 Master Akun (COA)</a>
    <a href="crud_master_layanan.php" class="btn btn-outline-dark btn-sm">🧼 Master Layanan Jasa</a>
    <a href="crud_master_pengguna.php" class="btn btn-dark btn-sm active">👤 Master Pengguna & Karyawan</a>
    <a href="crud_master_pelanggan.php" class="btn btn-outline-dark btn-sm">👥 Master Pelanggan</a>
    <a href="payroll_komponen.php" class="btn btn-outline-dark btn-sm">💵 Pengaturan Payroll</a>
</div>

<p><a href="crud_master_pengguna.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Daftar Pengguna</a></p>
<hr>

<?php if ($error_message) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
<?php if ($success_message) echo "<div class='alert alert-success'>$success_message</div>"; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm p-4">
            <h3 class="card-title mb-3">Edit Pengguna: <strong><?php echo htmlspecialchars($data['nama_lengkap']); ?></strong></h3>

            <form method="POST">
                <input type="hidden" name="action" value="edit">

                <div class="mb-3">
                    <label class="form-label">ID Pengguna:</label>
                    <input type="text" class="form-control" value="<?php echo $id_pengguna; ?>" disabled>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username:</label>
                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($data['username']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($data['email']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-bold">Password Baru:</label>
                    <input type="text" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin diubah">
                    <div class="form-text small">Jika diisi, password akan diperbarui dan disimpan secara terenkripsi.</div>
                </div>

                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap:</label>
                    <input type="text" class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($data['nama_lengkap']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Role:</label>
                    <select class="form-select" name="role" required>
                        <?php
                        // Roles allowed di DB: Owner, Karyawan, Cleaner (DB had Owner,Karyawan,Cleaner)
                        // kalau kamu punya Admin, tambahkan di DB enum (sebelumnya ada Admin di UI) — pastikan konsisten
                        $role_options = ['Owner', 'Karyawan', 'Cleaner'];
                        foreach ($role_options as $r) {
                            $selected = ($r == $data['role']) ? 'selected' : '';
                            $display_name = $r === 'Karyawan' ? 'Karyawan (Kasir)' : $r;
                            echo "<option value='{$r}' {$selected}>{$display_name}</option>";
                        }
                        ?>
                    </select>
                </div>

                <hr class="mt-4 mb-4">

                <h5 class="mb-3">Data Payroll (PTKP)</h5>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_menikah" name="is_menikah" value="1" <?php echo $data['is_menikah'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_menikah">Status Menikah</label>
                </div>

                <div class="mb-3">
                    <label for="jumlah_anak" class="form-label">Jumlah Anak (Maks 2 yang Ditunjang):</label>
                    <input type="number" class="form-control" name="jumlah_anak" min="0" value="<?php echo htmlspecialchars($data['jumlah_anak']); ?>">
                </div>

                <hr class="mt-4 mb-4">

                <h5 class="mb-3">Status Sistem</h5>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_aktif" name="is_aktif" value="1" <?php echo $data['is_aktif'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_aktif">Status Aktif (Login/Payroll)</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-4">Simpan Perubahan Pengguna</button>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm p-4 bg-light">
            <h4 class="h5">Informasi Detail</h4>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item"><strong>Tanggal Daftar:</strong> <?php echo htmlspecialchars($data['tgl_daftar'] ?? 'N/A'); ?></li>
                <li class="list-group-item"><strong>Password Saat Ini:</strong> *Tersimpan terenkripsi*</li>
                <li class="list-group-item"><strong>Status Akun:</strong> <?php echo $data['is_aktif'] ? '<span class="badge bg-success">AKTIF</span>' : '<span class="badge bg-danger">NON-AKTIF</span>'; ?></li>
            </ul>
        </div>
    </div>
</div>
```

</div>

<input type="hidden" id="session-role" value="<?php echo $role_login; ?>">
<input type="hidden" id="session-nama" value="<?php echo $nama_owner; ?>">
<input type="hidden" id="session-id" value="<?php echo $id_login; ?>">

<script>
    document.getElementById('access-info').innerHTML = 'Akses: <?php echo $role_login; ?> (<?php echo $nama_owner; ?>, ID <?php echo $id_login; ?>)';
</script>

<?php include '_footer.php'; // Footer Bootstrap ?>
