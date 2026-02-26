<?php

/**
 * Cek apakah data pegawai sudah lengkap
 * 
 * @param PDO $conn - Database connection
 * @param int $pegawai_id - ID pegawai yang akan dicek
 * @return array - ['is_complete' => boolean, 'missing_fields' => array, 'message' => string]
 */
function checkPegawaiCompletion($conn, $pegawai_id) {
    try {
        // Query data pegawai + status kepegawaian
        $stmt = $conn->prepare("
            SELECT 
                p.pegawai_id,
                p.nik,
                p.nip,
                p.nidn,
                p.prodi,
                p.nama_lengkap,
                p.jenis_pegawai,
                p.tempat_lahir,
                p.tanggal_lahir,
                p.jenis_kelamin,
                p.email,
                p.no_telepon,
                p.alamat_domisili,
                p.alamat_ktp,
                sk.jabatan,
                sk.jenis_kepegawaian,
                sk.status_aktif,
                sk.unit_kerja,
                sk.tanggal_mulai_kerja
            FROM pegawai p
            LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
            WHERE p.pegawai_id = ?
        ");
        $stmt->execute([$pegawai_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return [
                'is_complete' => false,
                'missing_fields' => ['Data pegawai tidak ditemukan'],
                'message' => 'Data pegawai tidak ditemukan di sistem.'
            ];
        }
        
        $missing_fields = [];
        
        // ===== CEK DATA PEGAWAI (WAJIB SEMUA) =====
        $required_pegawai_fields = [
            'nik' => 'NIK',
            'nip' => 'NIP',
            'nama_lengkap' => 'Nama Lengkap',
            'jenis_pegawai' => 'Jenis Pegawai',
            'tempat_lahir' => 'Tempat Lahir',
            'tanggal_lahir' => 'Tanggal Lahir',
            'jenis_kelamin' => 'Jenis Kelamin',
            'email' => 'Email',
            'no_telepon' => 'Nomor Telepon',
            'alamat_ktp' => 'Alamat KTP',
            'alamat_domisili' => 'Alamat Domisili'
        ];
        
        foreach ($required_pegawai_fields as $field => $label) {
            if (empty($data[$field])) {
                $missing_fields[] = $label;
            }
        }
        
        // ===== CEK FIELD KHUSUS DOSEN =====
        if ($data['jenis_pegawai'] === 'dosen') {
            if (empty($data['nidn'])) {
                $missing_fields[] = 'NIDN';
            }
            if (empty($data['prodi'])) {
                $missing_fields[] = 'Program Studi';
            }
        }
        
        // ===== CEK DATA STATUS KEPEGAWAIAN (WAJIB SEMUA) =====
        $required_status_fields = [
            'jabatan' => 'Jabatan',
            'jenis_kepegawaian' => 'Jenis Kepegawaian',
            'status_aktif' => 'Status Aktif',
            'unit_kerja' => 'Unit Kerja',
            'tanggal_mulai_kerja' => 'Tanggal Mulai Kerja'
        ];
        
        foreach ($required_status_fields as $field => $label) {
            if (empty($data[$field])) {
                $missing_fields[] = $label;
            }
        }
        
        // ===== HASIL VALIDASI =====
        $is_complete = empty($missing_fields);
        
        if ($is_complete) {
            return [
                'is_complete' => true,
                'missing_fields' => [],
                'message' => 'Data pegawai sudah lengkap.'
            ];
        } else {
            $message = 'Data Anda belum lengkap. Silakan lengkapi data berikut di halaman Administrasi Kepegawaian: <br><br>';
            $message .= '<ul class="text-start mb-0">';
            foreach ($missing_fields as $field) {
                $message .= '<li>' . htmlspecialchars($field) . '</li>';
            }
            $message .= '</ul>';
            
            return [
                'is_complete' => false,
                'missing_fields' => $missing_fields,
                'message' => $message
            ];
        }
        
    } catch (PDOException $e) {
        return [
            'is_complete' => false,
            'missing_fields' => ['Database Error'],
            'message' => 'Terjadi kesalahan saat mengecek kelengkapan data: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate JavaScript alert untuk data tidak lengkap
 * 
 * @param array $check_result - Hasil dari checkPegawaiCompletion()
 * @return string - JavaScript SweetAlert code
 */
function generateIncompleteAlert($check_result) {
    $message = addslashes($check_result['message']);
    
    return "
    <script>
    Swal.fire({
        icon: 'warning',
        title: 'Data Belum Lengkap!',
        html: '{$message}',
        showCancelButton: false,
        confirmButtonText: 'Lengkapi Data Sekarang',
        confirmButtonColor: '#F6C35A',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '" . BASE_URL . "users/pegawai/administrasi.php';
        }
    });
    </script>
    ";
}