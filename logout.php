<?php
// logout.php

// WAJIB: session_start() harus dipanggil paling awal untuk mengakses sesi
session_start();

// 1. Hapus semua variabel sesi yang disimpan
session_unset();

// 2. Hancurkan sesi (menghapus session ID)
session_destroy();

// 3. Redirect ke halaman login
// Pastikan tidak ada spasi, baris kosong, atau karakter lain sebelum tag <?php
header("Location: login.html");
exit(); // Penting untuk menghentikan eksekusi skrip

?>