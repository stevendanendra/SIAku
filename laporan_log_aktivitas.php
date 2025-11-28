<?php
// laporan_log_aktivitas.php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.html");
    exit();
}

$nama_owner = htmlspecialchars($_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Owner');
$role_login = htmlspecialchars($_SESSION['role']);
$id_login = htmlspecialchars($_SESSION['id_pengguna']);

// ==========================
// FILTER INPUT
// ==========================
$filter_start = $_GET['start'] ?? '';
$filter_end   = $_GET['end'] ?? '';
$filter_user  = $_GET['user'] ?? '';

$where = " WHERE 1=1 ";

if ($filter_start !== '') {
    $s = $conn->real_escape_string($filter_start);
    $where .= " AND DATE(tgl_waktu) >= '{$s}' ";
}

if ($filter_end !== '') {
    $e = $conn->real_escape_string($filter_end);
    $where .= " AND DATE(tgl_waktu) <= '{$e}' ";
}

if ($filter_user !== '') {
    $u = $conn->real_escape_string($filter_user);
    $where .= " AND id_pengguna = '{$u}' ";
}

// ==========================
// LOAD LOG
// ==========================
$sql = "
    SELECT tgl_waktu, id_pengguna, username, deskripsi, modul, ip_address
    FROM tr_log_aktivitas
    $where
    ORDER BY tgl_waktu DESC
";

$log_query = $conn->query($sql);
if (!$log_query) {
    die("ERROR QUERY: " . $conn->error);
}

// Ambil user list untuk filter
$user_q = $conn->query("SELECT DISTINCT id_pengguna, username FROM tr_log_aktivitas ORDER BY username ASC");

include '_header.php';
?>

<div class="container mt-5">

    <h1 class="mb-3">Laporan Jejak Audit (Log Aktivitas)</h1>
    <p><a href="dashboard_owner.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Dashboard Owner</a></p>
    <hr>

    <div class="alert alert-info small">
        Laporan ini mencatat setiap aktivitas penting pada sistem: login, perubahan data master, transaksi jurnal, payroll, dan lainnya.
    </div>

    <!-- ==========================
         FILTER PANEL
    ========================== -->
    <form class="card p-3 shadow-sm mb-4" method="GET">
        <div class="row g-3">

            <div class="col-md-3">
                <label class="form-label">Dari Tanggal:</label>
                <input type="date" name="start" class="form-control" value="<?= $filter_start ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Sampai Tanggal:</label>
                <input type="date" name="end" class="form-control" value="<?= $filter_end ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">User</label>
                <select name="user" class="form-select">
                    <option value="">Semua User</option>
                    <?php while ($u = $user_q->fetch_assoc()): ?>
                        <option value="<?= $u['id_pengguna'] ?>"
                            <?= ($filter_user == $u['id_pengguna']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['username']) ?> (ID <?= $u['id_pengguna'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary w-100">Terapkan Filter</button>
            </div>

        </div>
    </form>

    <!-- ==========================
         DATA LOG
    ========================== -->
    <div class="table-responsive">
        <table class="table table-bordered table-sm table-hover align-middle" style="min-width: 900px;">
            <thead class="table-dark">
                <tr>
                    <th style="width: 16%;">Waktu</th>
                    <th style="width: 8%;">User ID</th>
                    <th style="width: 15%;">Username</th>
                    <th style="width: 15%;">Modul</th>
                    <th>Deskripsi Aktivitas</th>
                    <th style="width: 10%;">IP</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($log_query->num_rows > 0): ?>
                    <?php while ($row = $log_query->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['tgl_waktu']; ?></td>
                            <td><?= $row['id_pengguna']; ?></td>
                            <td><?= htmlspecialchars($row['username']); ?></td>
                            <td>
                                <span class="badge bg-primary">
                                    <?= htmlspecialchars($row['modul']); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['deskripsi']); ?></td>
                            <td><?= htmlspecialchars($row['ip_address']); ?></td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">
                            Tidak ada aktivitas pada rentang filter.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<input type="hidden" id="session-role" value="<?= $role_login ?>">
<input type="hidden" id="session-nama" value="<?= $nama_owner ?>">
<input type="hidden" id="session-id" value="<?= $id_login ?>">

<?php include '_footer.php'; ?>
