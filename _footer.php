<?php
// _footer.php
// Digunakan untuk menutup HTML body, menambahkan scripts, dan menampilkan info akses.
?>
    </div> 
    
    <div class="container footer-info mt-5">
        <p class="text-muted text-center" id="access-info">
            Akses: SYSTEM_INFO
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // Ambil data dari input hidden yang disisipkan di file utama (misalnya crud_master_pengguna.php)
            const roleInput = document.getElementById('session-role');
            const namaInput = document.getElementById('session-nama');
            const idInput = document.getElementById('session-id');
            const infoElement = document.getElementById('access-info');

            // Cek apakah data input hidden tersedia
            if (roleInput && namaInput && idInput && infoElement) {
                const role = roleInput.value;
                const nama = namaInput.value;
                const id = idInput.value;
                
                infoElement.innerHTML = `Akses: <strong>${role}</strong> (${nama}, ID ${id})`;
            }
        });
    </script>
</body>
</html>