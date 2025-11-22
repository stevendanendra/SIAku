<?php
// koneksi.php
$servername = "localhost";
$username = "root";
$password = ""; // Ganti dengan password MySQL Anda
$dbname = "shinyhome_sia"; // Nama database Anda

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

// Tambahkan fungsi global untuk logging aktivitas (Audit Trail)
function logAktivitas($id_pengguna, $username, $deskripsi, $modul) {
    global $conn; 
    
    if (!$conn) {
        error_log("Koneksi database tidak tersedia untuk logging aktivitas.");
        return false;
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $tgl_waktu = date('Y-m-d H:i:s');
    
    $sql = "
        INSERT INTO tr_log_aktivitas 
            (tgl_waktu, id_pengguna, username, deskripsi, modul, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt === FALSE) {
        error_log("Gagal menyiapkan log query: " . $conn->error);
        return false;
    }

    // Binding parameters: s(tgl_waktu), i(id_pengguna), s(username), s(deskripsi), s(modul), s(ip_address)
    // Walaupun id_pengguna berasal dari $_SESSION (string), binding ke 'i' seharusnya tetap aman.
    $stmt->bind_param("sissss", 
        $tgl_waktu, 
        $id_pengguna, 
        $username, 
        $deskripsi, 
        $modul, 
        $ip_address
    );
    
    $result = $stmt->execute();
    
    if ($result === FALSE) {
        // Log error ke file log PHP jika execute gagal
        error_log("Logging GAGAL, MySQL Error: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

?>