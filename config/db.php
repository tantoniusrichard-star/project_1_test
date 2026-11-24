<?php
// config/db.php - Database connection & initialization (SQLite)

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbFile = __DIR__ . '/../database.sqlite';
    $needInit = !file_exists($dbFile);

    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($needInit) {
            initDatabase($pdo);
        } else {
            migrateSubmissionsTable($pdo);
            seedNewCategoriesIfMissing($pdo);
        }
        return $pdo;
    } catch (PDOException $e) {
        die("Koneksi Database Gagal: " . $e->getMessage());
    }
}

function migrateSubmissionsTable(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(submissions)")->fetchAll();
    $existing = array_column($cols, 'name');

    $newCols = [
        'item_name'      => 'VARCHAR(255) NULL',
        'item_category'  => 'VARCHAR(100) NULL',
        'item_qty'       => 'INTEGER DEFAULT 0',
        'item_price'     => 'REAL DEFAULT 0',
        'item_total'     => 'REAL DEFAULT 0',
        'vendor_name'    => 'VARCHAR(255) NULL',
        'doc_type'       => 'VARCHAR(100) NULL',
        'client_name'    => 'VARCHAR(255) NULL',
        'contract_value' => 'REAL DEFAULT 0',
        'validity_date'  => 'DATE NULL',
    ];

    foreach ($newCols as $colName => $colType) {
        if (!in_array($colName, $existing, true)) {
            $pdo->exec("ALTER TABLE submissions ADD COLUMN {$colName} {$colType}");
        }
    }
}

function seedNewCategoriesIfMissing(PDO $pdo): void {
    // Check if new categories already seeded
    $count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE category IN ('pembelian_alat', 'dokumen_legal')")->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmtSub = $pdo->prepare("
        INSERT INTO submissions (
            user_id, category, title, description, urgency,
            item_name, item_category, item_qty, item_price, item_total, vendor_name,
            doc_type, client_name, contract_value, validity_date,
            attachment_path, attachment_name, status, created_at
        ) VALUES (
            :user_id, :category, :title, :description, :urgency,
            :item_name, :item_category, :item_qty, :item_price, :item_total, :vendor_name,
            :doc_type, :client_name, :contract_value, :validity_date,
            :attachment_path, :attachment_name, 'Menunggu ACC', datetime('now', '-45 minutes')
        )
    ");

    // 1. Siti Rahma - Pembelian Alat Kantor
    $stmtSub->execute([
        ':user_id'         => 3, // Siti Rahma
        ':category'        => 'pembelian_alat',
        ':title'           => 'Pengadaan 2 Unit SSD NVMe 1TB & Kabel LAN untuk Workstation Operasional',
        ':description'     => 'Upgrade komponen penyimpanan SSD NVMe dan peremajaan kabel LAN Cat6 untuk 2 workstation staf operasional guna mengatasi kendala loading sistem yang lambat.',
        ':urgency'         => 'Normal',
        ':item_name'       => '2 Unit SSD NVMe 1TB & Roll Kabel LAN Cat6',
        ':item_category'   => 'Perangkat IT & Elektronik',
        ':item_qty'        => 2,
        ':item_price'      => 1050000,
        ':item_total'      => 2100000,
        ':vendor_name'     => 'Enterkomputer Official Store',
        ':doc_type'        => null,
        ':client_name'     => null,
        ':contract_value'  => 0,
        ':validity_date'   => null,
        ':attachment_path' => 'sample_katalog_ssd.pdf',
        ':attachment_name' => 'Katalog_Penawaran_SSD_Enterkomputer.pdf'
    ]);
    $sub5Id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO ai_analyses (submission_id, recommendation, confidence_score, summary, doc_status, doc_notes, quota_status, quota_notes, conflict_status, conflict_notes, flags, wa_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
        $sub5Id,
        'Layak ACC',
        97,
        'Siti Rahma mengajukan pembelian 2 unit SSD NVMe dan kabel LAN senilai total Rp 2.100.000 untuk workstation operasional. Bukti katalog harga terlampir lengkap dan nominal belanja berada di dalam batas pagu operasional standar (<= Rp 2.500.000).',
        'Valid & Terlampir',
        'Bukti katalog penawaran harga resmi terverifikasi.',
        'N/A',
        'Alokasi anggaran belanja operasional memadai.',
        'Aman (Anggaran Sesuai)',
        'Total biaya Rp 2.100.000 memenuhi standar pagu internal tanpa perlu eskalasi direksi.',
        json_encode([]),
        "🔔 *NOTIFIKASI SMARTAPPROVE AI*\n*Kepada:* Budi Santoso (Manajer Operasional)\n\n📌 *Pengajuan Baru:* Pembelian Alat Kantor (Normal)\n👤 *Pemohon:* Siti Rahma (Admin Keuangan / EMP-022)\n🛒 *Barang:* 2 Unit SSD NVMe 1TB & Roll Kabel LAN Cat6 (Qty: 2)\n💰 *Total Biaya:* Rp 2.100.000\n🏪 *Vendor:* Enterkomputer Official Store\n\n🤖 *Rekomendasi AI: LAYAK ACC (97%)*\n_Estimasi biaya <= Rp 2.500.000 dan bukti katalog harga terlampir lengkap._\n\n👉 *1-Click ACC:* http://project_1_test.test/actions/approve_request.php?id={$sub5Id}&quick=1"
    ]);

    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)")->execute([
        $sub5Id, 3, 'Submitted', 'Pengajuan pembelian alat kantor diajukan.'
    ]);
    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, 1, 'AI Analyzed', 'SmartApprove AI Engine memverifikasi anggaran <= Rp 2.500.000: Layak ACC.')")->execute([$sub5Id]);

    // 2. Andi Pratama - Dokumen Legal (Quotation)
    $stmtSub->execute([
        ':user_id'         => 2, // Andi Pratama
        ':category'        => 'dokumen_legal',
        ':title'           => 'Persetujuan Quotation Pengadaan Layanan IT & Jaringan PT Mitra Global Nusantara',
        ':description'     => 'Pengajuan persetujuan draf Quotation resmi penawaran solusi infrastruktur IT dan instalasi jaringan kantor cabang PT Mitra Global Nusantara senilai Rp 35.000.000.',
        ':urgency'         => 'Tinggi',
        ':item_name'       => null,
        ':item_category'   => null,
        ':item_qty'        => 0,
        ':item_price'      => 0,
        ':item_total'      => 0,
        ':vendor_name'     => null,
        ':doc_type'        => 'Quotation / Penawaran Harga',
        ':client_name'     => 'PT Mitra Global Nusantara',
        ':contract_value'  => 35000000,
        ':validity_date'   => '2026-12-31',
        ':attachment_path' => 'sample_quotation_mitra_global.pdf',
        ':attachment_name' => 'Quotation_PT_Mitra_Global_Nusantara_Q4.pdf'
    ]);
    $sub6Id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO ai_analyses (submission_id, recommendation, confidence_score, summary, doc_status, doc_notes, quota_status, quota_notes, conflict_status, conflict_notes, flags, wa_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
        $sub6Id,
        'Layak ACC',
        95,
        'Andi Pratama mengajukan persetujuan Quotation penawaran harga untuk klien PT Mitra Global Nusantara senilai Rp 35.000.000 dengan masa berlaku hingga 31 Desember 2026. Berkas draf penawaran resmi terlampir lengkap dan siap untuk diterbitkan/ditandatangani.',
        'Valid & Terlampir',
        'Draf penawaran harga (Quotation) PDF terlampir.',
        'N/A',
        'Penetapan komersial sesuai tarif layanan.',
        'Aman (Risiko Legal Terkendali)',
        'Nilai transaksi Rp 35.000.000 berada di bawah batas peninjauan khusus direksi (<= Rp 50.000.000).',
        json_encode([]),
        "🔔 *NOTIFIKASI SMARTAPPROVE AI*\n*Kepada:* Budi Santoso (Manajer Operasional)\n\n📌 *Pengajuan Baru:* Pengadaan Dokumen Legal (TINGGI)\n👤 *Pemohon:* Andi Pratama (Staff Sales / EMP-011)\n📄 *Jenis:* Quotation / Penawaran Harga\n🏢 *Mitra / Klien:* PT Mitra Global Nusantara\n💵 *Nilai Transaksi:* Rp 35.000.000\n📅 *Masa Berlaku:* s/d 31 Desember 2026\n\n🤖 *Rekomendasi AI: LAYAK ACC (95%)*\n_Draf dokumen lengkap dan nilai transaksi berada di bawah ambang batas Rp 50.000.000._\n\n👉 *1-Click ACC:* http://project_1_test.test/actions/approve_request.php?id={$sub6Id}&quick=1"
    ]);

    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)")->execute([
        $sub6Id, 2, 'Submitted', 'Pengajuan dokumen legal (Quotation) diajukan.'
    ]);
    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, 1, 'AI Analyzed', 'SmartApprove AI Engine memindai draf dokumen dan nilai kontrak <= 50jt: Layak ACC.')")->execute([$sub6Id]);
}

function initDatabase(PDO $pdo): void {
    // 1. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        nik VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL, -- 'karyawan', 'atasan', 'hrd'
        division VARCHAR(50) NOT NULL,
        job_title VARCHAR(50) NOT NULL,
        direct_supervisor_id INTEGER NULL,
        leave_quota INTEGER NOT NULL DEFAULT 12,
        avatar VARCHAR(255) DEFAULT 'default.png',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Submissions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        category VARCHAR(50) NOT NULL, -- 'izin_keluar', 'izin_sakit', 'cuti', 'pekerjaan_penting'
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        destination VARCHAR(255) NULL,
        departure_time VARCHAR(50) NULL,
        return_time VARCHAR(50) NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        days_count INTEGER DEFAULT 0,
        urgency VARCHAR(20) DEFAULT 'Sedang', -- 'Rendah', 'Sedang', 'Tinggi', 'Darurat'
        item_name VARCHAR(255) NULL,
        item_category VARCHAR(100) NULL,
        item_qty INTEGER DEFAULT 0,
        item_price REAL DEFAULT 0,
        item_total REAL DEFAULT 0,
        vendor_name VARCHAR(255) NULL,
        doc_type VARCHAR(100) NULL,
        client_name VARCHAR(255) NULL,
        contract_value REAL DEFAULT 0,
        validity_date DATE NULL,
        attachment_path VARCHAR(255) NULL,
        attachment_name VARCHAR(255) NULL,
        status VARCHAR(30) DEFAULT 'Menunggu ACC', -- 'Menunggu ACC', 'Disetujui (ACC)', 'Ditolak'
        supervisor_notes TEXT NULL,
        approved_by INTEGER NULL,
        approved_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // 3. AI Analyses Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_analyses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        submission_id INTEGER NOT NULL,
        recommendation VARCHAR(50) NOT NULL, -- 'Layak ACC', 'Perlu Tinjauan'
        confidence_score INTEGER DEFAULT 95,
        summary TEXT NOT NULL,
        doc_status VARCHAR(50) NOT NULL, -- 'Valid & Terlampir', 'Tidak Ada Dokumen', 'Perlu Verifikasi'
        doc_notes TEXT NULL,
        quota_status VARCHAR(50) NOT NULL, -- 'Mencukupi', 'Kurang / Melebihi Kuota', 'N/A'
        quota_notes TEXT NULL,
        conflict_status VARCHAR(50) NOT NULL, -- 'Aman (Tanpa Bentrok)', 'Potensi Bentrok Jadwal', 'N/A'
        conflict_notes TEXT NULL,
        flags TEXT NULL, -- JSON string
        wa_preview TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
    )");

    // 4. Approval Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS approval_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        submission_id INTEGER NOT NULL,
        actor_id INTEGER NOT NULL,
        action VARCHAR(50) NOT NULL, -- 'Submitted', 'AI Analyzed', 'Approved', 'Rejected'
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
    )");

    seedDatabase($pdo);
}

function seedDatabase(PDO $pdo): void {
    // Hash default password '123456'
    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);

    // 1. Seed Users
    $users = [
        [
            'name' => 'Budi Santoso',
            'email' => 'budi@perusahaan.id',
            'nik' => 'MGR-001',
            'password' => $defaultPassword,
            'role' => 'atasan',
            'division' => 'Operasional',
            'job_title' => 'Manajer Operasional',
            'direct_supervisor_id' => null,
            'leave_quota' => 12,
            'avatar' => 'avatar-budi.png'
        ],
        [
            'name' => 'Andi Pratama',
            'email' => 'andi@perusahaan.id',
            'nik' => 'EMP-011',
            'password' => $defaultPassword,
            'role' => 'karyawan',
            'division' => 'Marketing',
            'job_title' => 'Staff Sales',
            'direct_supervisor_id' => 1,
            'leave_quota' => 10,
            'avatar' => 'avatar-andi.png'
        ],
        [
            'name' => 'Siti Rahma',
            'email' => 'siti@perusahaan.id',
            'nik' => 'EMP-022',
            'password' => $defaultPassword,
            'role' => 'karyawan',
            'division' => 'Finance',
            'job_title' => 'Admin Keuangan',
            'direct_supervisor_id' => 1,
            'leave_quota' => 8,
            'avatar' => 'avatar-siti.png'
        ],
        [
            'name' => 'Dimas Saputra',
            'email' => 'dimas@perusahaan.id',
            'nik' => 'EMP-033',
            'password' => $defaultPassword,
            'role' => 'karyawan',
            'division' => 'Operasional',
            'job_title' => 'Teknisi Lapangan',
            'direct_supervisor_id' => 1,
            'leave_quota' => 2,
            'avatar' => 'avatar-dimas.png'
        ],
        [
            'name' => 'Rina Wijaya',
            'email' => 'hrd@perusahaan.id',
            'nik' => 'HRD-001',
            'password' => $defaultPassword,
            'role' => 'hrd',
            'division' => 'HRD',
            'job_title' => 'HR Operations Lead',
            'direct_supervisor_id' => null,
            'leave_quota' => 12,
            'avatar' => 'avatar-rina.png'
        ],
    ];

    $stmt = $pdo->prepare("INSERT INTO users (name, email, nik, password, role, division, job_title, direct_supervisor_id, leave_quota, avatar) VALUES (:name, :email, :nik, :password, :role, :division, :job_title, :direct_supervisor_id, :leave_quota, :avatar)");
    foreach ($users as $u) {
        $stmt->execute($u);
    }

    // 2. Seed Pre-loaded Submissions
    // Sub 1: Andi Pratama - Izin Keluar Kantor (Pending, Layak ACC)
    $stmtSub = $pdo->prepare("INSERT INTO submissions (user_id, category, title, description, destination, departure_time, return_time, start_date, end_date, days_count, urgency, attachment_path, attachment_name, status, supervisor_notes, approved_by, approved_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Sub 1
    $stmtSub->execute([
        2, // Andi
        'izin_keluar',
        'Meeting Klien PT Maju Bersama & Presentasi Kontrak Q4',
        'Menghadiri negosiasi final kesepakatan pengadaan software tahunan dengan Direktur Operasional PT Maju Bersama.',
        'Gedung Bursa Efek Indonesia, Tower 2 Lt. 14, Jakarta',
        '13:30 WIB',
        '17:00 WIB',
        null, null, 0,
        'Tinggi',
        'sample_undangan_klien.pdf',
        'Surat_Undangan_Meeting_PT_Maju.pdf',
        'Menunggu ACC',
        null, null, null,
        date('Y-m-d H:i:s', strtotime('-2 hours'))
    ]);
    $sub1Id = $pdo->lastInsertId();

    // AI Analysis Sub 1
    $pdo->prepare("INSERT INTO ai_analyses (submission_id, recommendation, confidence_score, summary, doc_status, doc_notes, quota_status, quota_notes, conflict_status, conflict_notes, flags, wa_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
        $sub1Id,
        'Layak ACC',
        98,
        'Andi Pratama mengajukan izin keluar 3.5 jam untuk negosiasi kontrak klien strategis. Dokumen undangan terlampir valid dan estimasi waktu kembali sebelum akhir jam kantor.',
        'Valid & Terlampir',
        'Surat undangan resmi meeting terdeteksi & terverifikasi.',
        'N/A',
        'Tidak memotong kuota cuti tahunan.',
        'Aman (Tanpa Bentrok)',
        'Jadwal tim sales lain tidak ada agenda keluar serentak.',
        json_encode([]),
        "🔔 *NOTIFIKASI SMARTAPPROVE AI*\n*Kepada:* Budi Santoso (Manajer Operasional)\n\n📌 *Pengajuan Baru:* Izin Keluar Kantor\n👤 *Pemohon:* Andi Pratama (Staff Sales / EMP-011)\n🏢 *Tujuan:* PT Maju Bersama (13:30 - 17:00 WIB)\n\n🤖 *Rekomendasi AI: LAYAK ACC (98%)*\n_Dokumen undangan valid & agenda penjualan strategis._\n\n👉 *1-Click ACC:* http://project_1_test.test/actions/approve_request.php?id={$sub1Id}&quick=1"
    ]);

    // Sub 2: Siti Rahma - Pekerjaan Penting (Pending, Layak ACC)
    $stmtSub->execute([
        3, // Siti
        'pekerjaan_penting',
        'Persetujuan Urgent Pembayaran Invoice Server AWS',
        'Otorisasi pembayaran invoice cloud hosting AWS sebelum jatuh tempo pukul 16:00 agar server production tidak terputus.',
        null, null, null, null, null, 0,
        'Darurat',
        'sample_invoice_aws.pdf',
        'Invoice_AWS_Enterprise_Nov2026.pdf',
        'Menunggu ACC',
        null, null, null,
        date('Y-m-d H:i:s', strtotime('-1 hours'))
    ]);
    $sub2Id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO ai_analyses (submission_id, recommendation, confidence_score, summary, doc_status, doc_notes, quota_status, quota_notes, conflict_status, conflict_notes, flags, wa_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
        $sub2Id,
        'Layak ACC',
        96,
        'Pengajuan pembayaran darurat tagihan infrastruktur cloud AWS oleh Siti Rahma dengan risiko downtime sistem jika terlambat diproses. Lampiran faktur resmi lengkap.',
        'Valid & Terlampir',
        'Faktur pajak & invoice resmi AWS terlampir jelas.',
        'N/A',
        'Pengajuan non-cuti operasional penting.',
        'Aman (Tanpa Bentrok)',
        'Tidak mengganggu alur otorisasi keuangan lain.',
        json_encode([]),
        "🔔 *NOTIFIKASI SMARTAPPROVE AI*\n*Kepada:* Budi Santoso (Manajer Operasional)\n\n📌 *Pengajuan Baru:* Pekerjaan Penting (DARURAT)\n👤 *Pemohon:* Siti Rahma (Admin Keuangan / EMP-022)\n📋 *Kebutuhan:* Pembayaran Server Cloud AWS\n\n🤖 *Rekomendasi AI: LAYAK ACC (96%)*\n_Urgensi darurat untuk mitigasi risiko downtime web._\n\n👉 *1-Click ACC:* http://project_1_test.test/actions/approve_request.php?id={$sub2Id}&quick=1"
    ]);

    // Sub 3: Andi Pratama - Cuti Tahunan (Disetujui - Riwayat)
    $stmtSub->execute([
        2, // Andi
        'cuti',
        'Cuti Tahunan Acara Keluarga Luar Kota',
        'Mengajukan cuti 2 hari untuk menghadiri hajatan keluarga besar di Yogyakarta. Tugas harian di-cover oleh rekan sales.',
        null, null, null,
        date('Y-m-d', strtotime('-10 days')),
        date('Y-m-d', strtotime('-9 days')),
        2,
        'Sedang',
        null, null,
        'Disetujui (ACC)',
        'Disetujui. Pastikan koordinasi klien dialihkan dengan baik selama cuti.',
        1, // Budi
        date('Y-m-d H:i:s', strtotime('-11 days')),
        date('Y-m-d H:i:s', strtotime('-12 days'))
    ]);
    $sub3Id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO ai_analyses (submission_id, recommendation, confidence_score, summary, doc_status, doc_notes, quota_status, quota_notes, conflict_status, conflict_notes, flags, wa_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
        $sub3Id,
        'Layak ACC',
        92,
        'Pengajuan cuti tahunan 2 hari dengan kuota sisa yang aman dan pelimpahan tugas tim telah diverifikasi.',
        'Valid & Terlampir',
        'Formulir terisi lengkap.',
        'Mencukupi',
        'Kuota tersisa 10 hari setelah pemotongan 2 hari.',
        'Aman (Tanpa Bentrok)',
        'Tidak ada jadwal promosi sales besar di tanggal tersebut.',
        json_encode([]),
        ""
    ]);

    // Sub 4: Dimas Saputra - Cuti Melebihi Kuota & Bentrok Jadwal (Flagged - Perlu Tinjauan)
    $stmtSub->execute([
        4, // Dimas
        'cuti',
        'Pengajuan Cuti Panjang Liburan Akhir Bulan',
        'Mengajukan libur selama 3 hari kerja untuk perjalanan keluarga.',
        null, null, null,
        date('Y-m-d', strtotime('+3 days')),
        date('Y-m-d', strtotime('+5 days')),
        3,
        'Rendah',
        null, null,
        'Menunggu ACC',
        null, null, null,
        date('Y-m-d H:i:s', strtotime('-30 minutes'))
    ]);
    $sub4Id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO ai_analyses (submission_id, recommendation, confidence_score, summary, doc_status, doc_notes, quota_status, quota_notes, conflict_status, conflict_notes, flags, wa_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
        $sub4Id,
        'Perlu Tinjauan',
        65,
        'Dimas Saputra mengajukan cuti 3 hari, padahal sisa kuota hanya 2 hari. Terdeteksi pula risiko kekurangan teknisi operasional karena ada jadwal pemeliharaan rutin pada tanggal yang sama.',
        'Perlu Verifikasi',
        'Pengajuan 3 hari tanpa surat keterangan khusus peminjaman cuti tahun depan.',
        'Kurang / Melebihi Kuota',
        'Karyawan butuh 3 hari, kuota cuti saat ini hanya tersisa 2 hari.',
        'Potensi Bentrok Jadwal',
        'Divisi Operasional memiliki jadwal maintenance infrastruktur penting pada tanggal tersebut.',
        json_encode([
            'Sisa kuota cuti pemohon (2 hari) lebih kecil dari durasi pengajuan (3 hari).',
            'Peringatan jadwal divisi: Pengurangan staf teknisi berpotensi menghambat SLA penanganan gangguan lapangan.'
        ]),
        "⚠️ *PERINGATAN SMARTAPPROVE AI*\n*Kepada:* Budi Santoso (Manajer Operasional)\n\n📌 *Pengajuan Cuti Butuh Tinjauan Khusus*\n👤 *Pemohon:* Dimas Saputra (Teknisi Lapangan / EMP-033)\n📅 *Durasi:* 3 Hari Kerja\n\n🤖 *Rekomendasi AI: PERLU TINJAUAN (Risiko)*\n1. Sisa kuota hanya 2 hari (Minus 1 hari).\n2. Potensi bentrok dengan jadwal pemeliharaan lapangan divisi Operasional.\n\n👉 *Review Detail:* http://project_1_test.test/actions/approve_request.php?id={$sub4Id}"
    ]);

    // Seed Approval Logs
    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)")->execute([
        $sub1Id, 2, 'Submitted', 'Pengajuan diajukan oleh karyawan.'
    ]);
    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, 1, 'AI Analyzed', 'SmartApprove AI Engine memverifikasi lampiran dan status kuota: Layak ACC.')")->execute([$sub1Id]);

    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)")->execute([
        $sub2Id, 3, 'Submitted', 'Pengajuan darurat pekerjaan diajukan.'
    ]);
    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, 1, 'AI Analyzed', 'SmartApprove AI Engine mendeteksi urgensi tinggi invoice server AWS.')")->execute([$sub2Id]);

    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)")->execute([
        $sub3Id, 2, 'Submitted', 'Pengajuan cuti diajukan.'
    ]);
    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, 1, 'Approved', 'Disetujui oleh Budi Santoso.')")->execute([$sub3Id]);

    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)")->execute([
        $sub4Id, 4, 'Submitted', 'Pengajuan cuti 3 hari diajukan.'
    ]);
    $pdo->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, 1, 'AI Analyzed', 'AI mendeteksi anomali kuota cuti kurang dan bentrok jadwal teknisi.')")->execute([$sub4Id]);
}
