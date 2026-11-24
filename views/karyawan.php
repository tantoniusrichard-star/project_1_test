<?php
// views/karyawan.php - Employee Portal View
defined('APP_RUN') or die('Direct access not permitted');

// Get supervisor info
$stmtSup = $db->prepare("SELECT name, job_title, email FROM users WHERE id = ?");
$stmtSup->execute([$user['direct_supervisor_id'] ?? 1]);
$supervisor = $stmtSup->fetch() ?: ['name' => 'Budi Santoso', 'job_title' => 'Manajer Operasional'];

// Get employee statistics
$stmtStats = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Menunggu ACC' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Disetujui (ACC)' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected
    FROM submissions WHERE user_id = ?
");
$stmtStats->execute([$user['id']]);
$stats = $stmtStats->fetch();

// Get submissions with AI analysis
$stmtList = $db->prepare("
    SELECT s.*, 
           a.recommendation as ai_rec, 
           a.confidence_score as ai_score, 
           a.summary as ai_summary,
           a.doc_status, a.doc_notes,
           a.quota_status, a.quota_notes,
           a.conflict_status, a.conflict_notes,
           a.flags as ai_flags,
           a.wa_preview,
           sup.name as supervisor_name
    FROM submissions s
    LEFT JOIN ai_analyses a ON s.id = a.submission_id
    LEFT JOIN users sup ON s.approved_by = sup.id
    WHERE s.user_id = ?
    ORDER BY s.id DESC
");
$stmtList->execute([$user['id']]);
$mySubmissions = $stmtList->fetchAll();

// Check if just submitted
$newSubId = isset($_GET['sub_id']) ? (int)$_GET['sub_id'] : null;
?>

<div class="space-y-6">

    <!-- Profile & KPI Summary Header -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-stretch">
        <!-- Profile Card -->
        <div class="md:col-span-7 bg-slate-800/80 backdrop-blur-md border border-slate-700/70 rounded-2xl p-5 shadow-lg flex flex-col justify-between">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-white text-xl font-bold shadow-md shadow-blue-500/20 shrink-0">
                        <?= strtoupper(substr($user['name'], 0, 2)) ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($user['name']) ?></h2>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-400 border border-blue-500/30 font-medium">
                                <?= htmlspecialchars($user['nik']) ?>
                            </span>
                        </div>
                        <p class="text-xs text-slate-300 font-medium mt-0.5"><?= htmlspecialchars($user['job_title']) ?> • <span class="text-blue-400">Divisi <?= htmlspecialchars($user['division']) ?></span></p>
                        <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Atasan Langsung: <strong class="text-slate-200"><?= htmlspecialchars($supervisor['name']) ?></strong> (<?= htmlspecialchars($supervisor['job_title']) ?>)
                        </p>
                    </div>
                </div>

                <button onclick="document.getElementById('formSection').scrollIntoView({behavior: 'smooth'})"
                        class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-md shadow-blue-600/30 transition-all shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Buat Pengajuan</span>
                </button>
            </div>

            <!-- Mobile Quick Button -->
            <div class="mt-4 sm:hidden">
                <button onclick="document.getElementById('formSection').scrollIntoView({behavior: 'smooth'})"
                        class="w-full py-2.5 px-3 rounded-xl bg-blue-600 text-white text-xs font-semibold flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Buat Pengajuan Baru</span>
                </button>
            </div>
        </div>

        <!-- Metric Badges: Quota & Status Cards -->
        <div class="md:col-span-5 grid grid-cols-2 gap-3">
            <!-- Sisa Cuti Card -->
            <div class="bg-gradient-to-br from-blue-900/40 via-slate-800/60 to-slate-800/80 border border-blue-500/30 rounded-2xl p-4 flex flex-col justify-between shadow-lg">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-xs font-medium">Sisa Kuota Cuti</span>
                    <span class="text-sm">🏖️</span>
                </div>
                <div class="my-2">
                    <span class="text-3xl font-extrabold text-white tracking-tight"><?= $user['leave_quota'] ?></span>
                    <span class="text-xs text-blue-300 ml-1">Hari Kerja</span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Tahun Berjalan <?= date('Y') ?>
                </div>
            </div>

            <!-- Menunggu ACC Card -->
            <div class="bg-slate-800/80 border border-amber-500/30 rounded-2xl p-4 flex flex-col justify-between shadow-lg">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-xs font-medium">Menunggu ACC</span>
                    <span class="text-sm">⏳</span>
                </div>
                <div class="my-2">
                    <span class="text-3xl font-extrabold text-amber-400 tracking-tight"><?= (int)$stats['pending'] ?></span>
                    <span class="text-xs text-slate-400 ml-1">Pengajuan</span>
                </div>
                <div class="text-[11px] text-amber-300/80 flex items-center gap-1">
                    Menunggu review atasan
                </div>
            </div>

            <!-- Disetujui Card -->
            <div class="bg-slate-800/80 border border-emerald-500/30 rounded-2xl p-3.5 flex items-center justify-between shadow-lg">
                <div>
                    <span class="text-[11px] text-slate-400 font-medium block">Disetujui (ACC)</span>
                    <span class="text-xl font-bold text-emerald-400 mt-0.5 block"><?= (int)$stats['approved'] ?></span>
                </div>
                <div class="w-8 h-8 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
            </div>

            <!-- Ditolak Card -->
            <div class="bg-slate-800/80 border border-red-500/30 rounded-2xl p-3.5 flex items-center justify-between shadow-lg">
                <div>
                    <span class="text-[11px] text-slate-400 font-medium block">Ditolak</span>
                    <span class="text-xl font-bold text-red-400 mt-0.5 block"><?= (int)$stats['rejected'] ?></span>
                </div>
                <div class="w-8 h-8 rounded-full bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Success Notification if redirected from submit -->
    <?php if (isset($_GET['success']) && $_GET['success'] === 'submitted'): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-xs sm:text-sm flex items-start justify-between gap-3 shadow-lg">
            <div class="flex items-start gap-3">
                <div class="w-6 h-6 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-400 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <h4 class="font-bold text-emerald-300">Pengajuan Berhasil Terkirim & Terpindai AI!</h4>
                    <p class="text-slate-300 text-xs mt-0.5">SmartApprove AI Engine telah memvalidasi dokumen Anda dan meneruskan ringkasan 1-Click ACC ke atasan.</p>
                </div>
            </div>
            <a href="dashboard.php" class="text-slate-400 hover:text-white text-xs font-semibold">&times; Tutup</a>
        </div>
    <?php endif; ?>

    <!-- Formulir Pengajuan Terpadu (Unified Submission Form) -->
    <div id="formSection" class="bg-slate-800/80 backdrop-blur-md border border-slate-700/80 rounded-2xl p-5 sm:p-7 shadow-xl">
        <div class="border-b border-slate-700/60 pb-4 mb-6">
            <div class="flex items-center gap-2 text-blue-400 text-xs font-bold uppercase tracking-wider">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Formulir Pengajuan Terpadu
            </div>
            <h3 class="text-xl font-extrabold text-white mt-1">Buat Permohonan Izin / Cuti / Pekerjaan</h3>
            <p class="text-xs text-slate-400 mt-0.5">Pilih kategori pengajuan, sistem AI akan otomatis memvalidasi kelengkapan dokumen sebelum diserahkan ke atasan.</p>
        </div>

        <form action="actions/submit_request.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            
            <!-- Category Tabs Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-2 uppercase tracking-wide">Kategori Pengajuan *</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                    
                    <label class="cursor-pointer">
                        <input type="radio" name="category" value="izin_keluar" checked onchange="switchCategory('izin_keluar')" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-slate-700 bg-slate-900/60 peer-checked:bg-blue-600/20 peer-checked:border-blue-500 peer-checked:text-blue-300 transition-all text-center h-full flex flex-col justify-between">
                            <div>
                                <span class="text-xl block mb-1">🏢</span>
                                <span class="text-xs font-bold block">Izin Keluar Kantor</span>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-1">Meeting / Klien</span>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="category" value="izin_sakit" onchange="switchCategory('izin_sakit')" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-slate-700 bg-slate-900/60 peer-checked:bg-blue-600/20 peer-checked:border-blue-500 peer-checked:text-blue-300 transition-all text-center h-full flex flex-col justify-between">
                            <div>
                                <span class="text-xl block mb-1">🩺</span>
                                <span class="text-xs font-bold block">Izin Sakit</span>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-1">Surat Dokter</span>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="category" value="cuti" onchange="switchCategory('cuti')" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-slate-700 bg-slate-900/60 peer-checked:bg-blue-600/20 peer-checked:border-blue-500 peer-checked:text-blue-300 transition-all text-center h-full flex flex-col justify-between">
                            <div>
                                <span class="text-xl block mb-1">🌴</span>
                                <span class="text-xs font-bold block">Cuti / Libur</span>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-1">Potong Kuota</span>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="category" value="pekerjaan_penting" onchange="switchCategory('pekerjaan_penting')" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-slate-700 bg-slate-900/60 peer-checked:bg-blue-600/20 peer-checked:border-blue-500 peer-checked:text-blue-300 transition-all text-center h-full flex flex-col justify-between">
                            <div>
                                <span class="text-xl block mb-1">⚡</span>
                                <span class="text-xs font-bold block">Pekerjaan Penting</span>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-1">Otorisasi / Urgent</span>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="category" value="pembelian_alat" onchange="switchCategory('pembelian_alat')" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-slate-700 bg-slate-900/60 peer-checked:bg-blue-600/20 peer-checked:border-blue-500 peer-checked:text-blue-300 transition-all text-center h-full flex flex-col justify-between">
                            <div>
                                <span class="text-xl block mb-1">🛒</span>
                                <span class="text-xs font-bold block">Beli Alat Kantor</span>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-1">ATK / IT / Furnitur</span>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="category" value="dokumen_legal" onchange="switchCategory('dokumen_legal')" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-slate-700 bg-slate-900/60 peer-checked:bg-blue-600/20 peer-checked:border-blue-500 peer-checked:text-blue-300 transition-all text-center h-full flex flex-col justify-between">
                            <div>
                                <span class="text-xl block mb-1">📄</span>
                                <span class="text-xs font-bold block">Dokumen Legal</span>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-1">Quotation / PKS / MoU</span>
                        </div>
                    </label>

                </div>
            </div>

            <!-- Title & Urgency Row -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                <div class="sm:col-span-8">
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Judul Pengajuan *</label>
                    <input type="text" name="title" required placeholder="Contoh: Meeting Klien PT Maju Bersama & Presentasi Kontrak" 
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700 text-white placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-blue-500">
                </div>
                <div class="sm:col-span-4">
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Tingkat Urgensi</label>
                    <select name="urgency" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500">
                        <option value="Sedang">Sedang (Standar)</option>
                        <option value="Rendah">Rendah (Fleksibel)</option>
                        <option value="Tinggi">Tinggi (Perlu Segera)</option>
                        <option value="Darurat">🚨 Darurat (Dalam 1-2 Jam)</option>
                    </select>
                </div>
            </div>

            <!-- Dynamic Field 1: Izin Keluar Kantor -->
            <div id="fieldIzinKeluar" class="p-4 rounded-xl bg-slate-900/70 border border-slate-700/60 space-y-4">
                <div class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
                    <span>🏢</span> Spesifikasi Lokasi & Jam Izin Keluar
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-6">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Tujuan / Nama Klien / Lokasi *</label>
                        <input type="text" id="destinationInput" name="destination" placeholder="Contoh: Kantor Klien PT ABC, SCBD Jakarta"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Jam Keluar *</label>
                        <input type="time" name="departure_time" value="13:00"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Estimasi Kembali *</label>
                        <input type="time" name="return_time" value="17:00"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:border-blue-500">
                    </div>
                </div>
            </div>

            <!-- Dynamic Field 2 & 3: Date Range (Izin Sakit & Cuti) -->
            <div id="fieldDateRange" class="hidden p-4 rounded-xl bg-slate-900/70 border border-slate-700/60 space-y-3">
                <div class="flex items-center justify-between">
                    <span id="dateRangeTitle" class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>📅</span> Periode Tanggal Pengajuan
                    </span>
                    <span id="quotaCalcBadge" class="hidden text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30">
                        Durasi: 0 Hari
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Tanggal Mulai *</label>
                        <input type="date" id="startDate" name="start_date" onchange="calculateDuration()"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Tanggal Berakhir *</label>
                        <input type="date" id="endDate" name="end_date" onchange="calculateDuration()"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div id="quotaWarning" class="hidden p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span id="quotaWarningText">Peringatan: Jumlah hari cuti melebihi sisa kuota yang tersedia!</span>
                </div>
            </div>

            <!-- Dynamic Field 5: Persetujuan Pembelian Alat Kantor -->
            <div id="fieldPembelianAlat" class="hidden p-4 rounded-xl bg-slate-900/70 border border-slate-700/60 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🛒</span> Rincian Pengadaan Alat & Perlengkapan Kantor
                    </span>
                    <span class="text-[11px] text-slate-400">Pagu Standar: <strong>Rp 2.500.000</strong></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-8">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Nama Barang / Perangkat *</label>
                        <input type="text" id="purchaseItemName" name="item_name" placeholder="Contoh: Printer Epson L3210, 2 Unit SSD NVMe 1TB, Kursi Kerja"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Kategori Barang *</label>
                        <select name="item_category" class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:border-blue-500">
                            <option value="Perangkat IT & Elektronik">Perangkat IT & Elektronik</option>
                            <option value="ATK & Habis Pakai">ATK & Habis Pakai</option>
                            <option value="Furnitur & Perlengkapan Kantor">Furnitur & Perlengkapan Kantor</option>
                        </select>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Jumlah (Qty) *</label>
                        <input type="number" id="purchaseQty" name="item_qty" min="1" value="1" oninput="calcPurchaseTotal()"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs font-bold focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Estimasi Harga Satuan (Rp) *</label>
                        <input type="number" id="purchasePrice" name="item_price" min="0" step="1000" placeholder="Contoh: 1050000" oninput="calcPurchaseTotal()"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs font-mono focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Nama Vendor / Toko Referensi *</label>
                        <input type="text" name="vendor_name" placeholder="Contoh: Enterkomputer Official Store, Tokopedia"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <!-- Real-time Calculated Total & Budget Indicator -->
                <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="text-[11px] text-slate-400 block">Total Estimasi Biaya Pengadaan:</span>
                        <div class="flex items-baseline gap-2">
                            <span id="purchaseTotalDisplay" class="text-lg sm:text-xl font-extrabold text-emerald-400">Rp 0</span>
                            <span class="text-xs text-slate-400">(Otomatis dikalkulasi)</span>
                        </div>
                        <input type="hidden" name="item_total" id="purchaseTotalInput" value="0">
                    </div>

                    <div id="purchaseBudgetAlert" class="text-xs py-1 px-3 rounded-lg bg-slate-900 border border-slate-700 text-slate-300">
                        Isi jumlah dan harga satuan di atas
                    </div>
                </div>
            </div>

            <!-- Dynamic Field 6: Persetujuan Pengadaan Dokumen Legal -->
            <div id="fieldDokumenLegal" class="hidden p-4 rounded-xl bg-slate-900/70 border border-slate-700/60 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>📄</span> Rincian Pengesahan Dokumen Legal & Komersial
                    </span>
                    <span class="text-[11px] text-slate-400">Batas Otorisasi Standar: <strong>Rp 50.000.000</strong></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Jenis Dokumen *</label>
                        <select name="doc_type" id="legalDocType" class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:border-blue-500">
                            <option value="Quotation / Penawaran Harga">Quotation / Penawaran Harga</option>
                            <option value="Kontrak / Perjanjian Kerja Sama (PKS)">Kontrak / Perjanjian Kerja Sama (PKS)</option>
                            <option value="Memorandum of Understanding (MoU)">Memorandum of Understanding (MoU)</option>
                            <option value="Purchase Order (PO)">Purchase Order (PO)</option>
                            <option value="Surat Keterangan / Legal Lainnya">Surat Keterangan / Legal Lainnya</option>
                        </select>
                    </div>

                    <div class="sm:col-span-7">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Nama Klien / Mitra / Pihak Terkait *</label>
                        <input type="text" id="legalClientName" name="client_name" placeholder="Contoh: PT Mitra Global Nusantara"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Nilai Transaksi / Kontrak (Rp)</label>
                        <input type="number" id="legalContractValue" name="contract_value" min="0" step="1000" placeholder="Contoh: 35000000 (Isi 0 jika non-moneter)" oninput="checkLegalValue()"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs font-mono focus:outline-none focus:border-blue-500">
                        <span class="text-[10px] text-slate-500 block mt-0.5">*Opsional/0 jika perjanjian non-moneter (MoU / NDA)</span>
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-xs font-medium text-slate-300 mb-1">Batas Waktu / Masa Berlaku Dokumen *</label>
                        <input type="date" id="legalValidityDate" name="validity_date"
                               class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div id="legalRiskAlert" class="hidden p-3 rounded-xl text-xs flex items-center gap-2">
                </div>
            </div>

            <!-- Description Textarea -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Keterangan / Alasan Lengkap *</label>
                <textarea name="description" rows="3" required placeholder="Jelaskan kebutuhan pengajuan secara rinci agar AI Assistant dapat menyusun ringkasan eksekutif yang akurat untuk atasan..."
                          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700 text-white placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <!-- Attachment File Upload -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label id="attachmentLabel" class="text-xs font-semibold text-slate-300">
                        Unggah Dokumen Lampiran <span class="text-slate-500 font-normal">(Opsional)</span>
                    </label>
                    <span id="attachmentHint" class="text-[11px] text-slate-400">PDF, JPG, PNG (Maks 5MB)</span>
                </div>
                <div class="relative">
                    <input type="file" id="attachmentInput" name="attachment" 
                           class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-700 file:text-slate-200 hover:file:bg-slate-600 cursor-pointer bg-slate-900/80 border border-slate-700 rounded-xl p-1.5">
                </div>
                <p id="attachmentReqNote" class="text-[11px] text-slate-500 mt-1">
                    *Untuk <em>Izin Sakit</em>, lampiran surat dokter wajib disertakan agar Rekomendasi AI berstatus Layak ACC.
                </p>
            </div>

            <!-- Submit Button & AI Scanner Notice -->
            <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-700/60">
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-ping"></span>
                    <span>SmartApprove AI Engine akan otomatis menganalisis pengajuan ini</span>
                </div>

                <button type="submit" 
                        class="w-full sm:w-auto px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs sm:text-sm shadow-xl shadow-blue-600/30 transition-all transform active:scale-95 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Kirim Pengajuan & Scan AI</span>
                </button>
            </div>

        </form>
    </div>

    <!-- Riwayat Pengajuan & Real-time Status Tracking -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    Riwayat Pengajuan & Status Real-Time
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                        <?= count($mySubmissions) ?> Pengajuan
                    </span>
                </h3>
                <p class="text-xs text-slate-400">Pantau verifikasi AI dan keputusan ACC atasan Anda secara langsung.</p>
            </div>
        </div>

        <?php if (empty($mySubmissions)): ?>
            <div class="p-8 text-center bg-slate-800/40 border border-slate-700/60 rounded-2xl text-slate-400 text-xs">
                Belum ada riwayat pengajuan. Gunakan formulir di atas untuk membuat pengajuan baru.
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($mySubmissions as $sub): 
                    $catIcons = [
                        'izin_keluar' => '🏢 Izin Keluar',
                        'izin_sakit' => '🩺 Izin Sakit',
                        'cuti' => '🌴 Cuti Tahunan',
                        'pekerjaan_penting' => '⚡ Pekerjaan Penting',
                        'pembelian_alat' => '🛒 Beli Alat Kantor',
                        'dokumen_legal' => '📄 Dokumen Legal'
                    ];

                    $statusBadge = 'bg-amber-500/20 text-amber-300 border-amber-500/30';
                    $statusIcon = '⏳';
                    if ($sub['status'] === 'Disetujui (ACC)') {
                        $statusBadge = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
                        $statusIcon = '✅';
                    } elseif ($sub['status'] === 'Ditolak') {
                        $statusBadge = 'bg-red-500/20 text-red-300 border-red-500/30';
                        $statusIcon = '❌';
                    }

                    $recBadge = ($sub['ai_rec'] === 'Layak ACC') 
                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' 
                        : 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                ?>
                <div class="bg-slate-800/70 border border-slate-700/70 hover:border-slate-600 rounded-2xl p-4 sm:p-5 shadow-lg space-y-3 transition-all">
                    
                    <!-- Header Bar -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-700/50 pb-3">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-700 text-slate-300">
                                <?= $catIcons[$sub['category']] ?? $sub['category'] ?>
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-full border <?= $statusBadge ?> font-bold flex items-center gap-1">
                                <span><?= $statusIcon ?></span>
                                <span><?= htmlspecialchars($sub['status']) ?></span>
                            </span>
                            <?php if ($sub['urgency'] === 'Darurat' || $sub['urgency'] === 'Tinggi'): ?>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-red-500/20 text-red-300 border border-red-500/30 font-bold animate-pulse">
                                    🚨 <?= $sub['urgency'] ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="text-[11px] text-slate-400">
                            Diajukan: <?= date('d M Y, H:i', strtotime($sub['created_at'])) ?> WIB
                        </div>
                    </div>

                    <!-- Title & Description -->
                    <div>
                        <h4 class="text-sm sm:text-base font-bold text-white"><?= htmlspecialchars($sub['title']) ?></h4>
                        <p class="text-xs text-slate-300 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($sub['description'])) ?></p>

                        <!-- Additional Details -->
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400">
                            <?php if ($sub['category'] === 'izin_keluar'): ?>
                                <span>📍 <strong>Tujuan:</strong> <?= htmlspecialchars($sub['destination']) ?> (<?= htmlspecialchars($sub['departure_time']) ?> - <?= htmlspecialchars($sub['return_time']) ?>)</span>
                            <?php elseif ($sub['category'] === 'cuti' || $sub['category'] === 'izin_sakit'): ?>
                                <span>📅 <strong>Periode:</strong> <?= $sub['start_date'] ?> s/d <?= $sub['end_date'] ?> (<?= $sub['days_count'] ?> Hari)</span>
                            <?php elseif ($sub['category'] === 'pembelian_alat'): ?>
                                <span>🛒 <strong>Barang:</strong> <?= htmlspecialchars($sub['item_name']) ?> (<?= htmlspecialchars($sub['item_category']) ?>, Qty: <?= $sub['item_qty'] ?>)</span>
                                <span>💰 <strong>Total:</strong> <strong class="text-emerald-400">Rp <?= number_format($sub['item_total'], 0, ',', '.') ?></strong> (Vendor: <?= htmlspecialchars($sub['vendor_name'] ?? '-') ?>)</span>
                            <?php elseif ($sub['category'] === 'dokumen_legal'): ?>
                                <span>📄 <strong>Jenis:</strong> <?= htmlspecialchars($sub['doc_type']) ?></span>
                                <span>🏢 <strong>Mitra:</strong> <?= htmlspecialchars($sub['client_name']) ?></span>
                                <span>💵 <strong>Nilai:</strong> <strong class="text-blue-400"><?= $sub['contract_value'] > 0 ? 'Rp ' . number_format($sub['contract_value'], 0, ',', '.') : 'Non-Moneter' ?></strong></span>
                                <?php if (!empty($sub['validity_date'])): ?>
                                    <span>📅 <strong>Berlaku s/d:</strong> <?= date('d M Y', strtotime($sub['validity_date'])) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (!empty($sub['attachment_name'])): ?>
                                <a href="uploads/<?= urlencode($sub['attachment_path'] ?? $sub['attachment_name']) ?>" target="_blank" 
                                   class="text-blue-400 hover:text-blue-300 underline flex items-center gap-1">
                                    📎 Lampiran: <?= htmlspecialchars($sub['attachment_name']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- AI Insight Box (SmartApprove AI Engine) -->
                    <?php if (!empty($sub['ai_rec'])): ?>
                    <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-700/70 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-sm">🤖</span>
                                <span class="text-xs font-bold text-white">SmartApprove AI Engine</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full border <?= $recBadge ?> font-bold">
                                    Rekomendasi: <?= htmlspecialchars($sub['ai_rec']) ?> (<?= $sub['ai_score'] ?>%)
                                </span>
                            </div>

                            <!-- WhatsApp Preview Trigger -->
                            <?php if (!empty($sub['wa_preview'])): ?>
                            <button onclick="showWaModal(<?= htmlspecialchars(json_encode($sub['wa_preview'])) ?>)"
                                    class="text-[11px] text-emerald-400 hover:text-emerald-300 flex items-center gap-1 font-medium">
                                <span>💬</span>
                                <span class="hidden sm:inline">Preview WhatsApp Atasan</span>
                            </button>
                            <?php endif; ?>
                        </div>

                        <p class="text-xs text-slate-300 italic leading-relaxed">
                            "<?= htmlspecialchars($sub['ai_summary']) ?>"
                        </p>

                        <!-- AI Checklist Pills -->
                        <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px]">
                            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
                                Dokumen: <strong><?= htmlspecialchars($sub['doc_status'] ?? 'Valid') ?></strong>
                            </span>
                            <?php if ($sub['category'] === 'cuti'): ?>
                            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
                                Kuota: <strong><?= htmlspecialchars($sub['quota_status'] ?? 'N/A') ?></strong>
                            </span>
                            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
                                Jadwal Tim: <strong><?= htmlspecialchars($sub['conflict_status'] ?? 'Aman') ?></strong>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Supervisor Decision & Notes (if already reviewed) -->
                    <?php if ($sub['status'] !== 'Menunggu ACC'): ?>
                    <div class="p-3 rounded-xl <?= $sub['status'] === 'Disetujui (ACC)' ? 'bg-emerald-950/30 border border-emerald-800/40 text-emerald-200' : 'bg-red-950/30 border border-red-800/40 text-red-200' ?> text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <span><?= $sub['status'] === 'Disetujui (ACC)' ? '✅ ACC oleh' : '❌ Ditolak oleh' ?>: <?= htmlspecialchars($sub['supervisor_name'] ?? 'Atasan Operasional') ?></span>
                            <?php if (!empty($sub['approved_at'])): ?>
                                <span class="font-normal text-[11px] opacity-75">(<?= date('d M Y, H:i', strtotime($sub['approved_at'])) ?> WIB)</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($sub['supervisor_notes'])): ?>
                            <p class="italic text-slate-300">Catatan Atasan: "<?= htmlspecialchars($sub['supervisor_notes']) ?>"</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- WhatsApp Notification Preview Modal -->
<div id="waModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2 text-emerald-400 font-bold text-sm">
                <span class="text-lg">💬</span>
                <span>Preview Format Pesan WhatsApp ke Atasan</span>
            </div>
            <button onclick="closeWaModal()" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
        </div>

        <p class="text-xs text-slate-400 leading-relaxed">
            Ini adalah contoh ringkasan otomatis yang dikirimkan SmartApprove AI ke nomor WhatsApp atasan Anda sehingga atasan dapat melakukan <strong>1-Click ACC</strong> langsung dari smartphone:
        </p>

        <!-- Mock WhatsApp Bubble -->
        <div class="bg-[#0b141a] border border-[#202c33] rounded-xl p-4 text-slate-200 font-mono text-xs whitespace-pre-wrap leading-relaxed shadow-inner" id="waContent">
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
            <button onclick="copyWaText()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                <span id="copyBtnText">Salin Teks WhatsApp</span>
            </button>
            <button onclick="closeWaModal()" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-colors">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
const currentLeaveQuota = <?= (int)$user['leave_quota'] ?>;

function switchCategory(cat) {
    const fIzinKeluar = document.getElementById('fieldIzinKeluar');
    const fDateRange  = document.getElementById('fieldDateRange');
    const fPembelianAlat = document.getElementById('fieldPembelianAlat');
    const fDokumenLegal = document.getElementById('fieldDokumenLegal');
    const dateTitle   = document.getElementById('dateRangeTitle');
    const attLabel    = document.getElementById('attachmentLabel');
    const attReqNote  = document.getElementById('attachmentReqNote');
    const quotaBadge  = document.getElementById('quotaCalcBadge');

    // Hide all dynamic fields first
    fIzinKeluar.classList.add('hidden');
    fDateRange.classList.add('hidden');
    fPembelianAlat.classList.add('hidden');
    fDokumenLegal.classList.add('hidden');

    if (cat === 'izin_keluar') {
        fIzinKeluar.classList.remove('hidden');
        attLabel.innerHTML = 'Unggah Dokumen Lampiran <span class="text-slate-500 font-normal">(Opsional)</span>';
        attReqNote.textContent = '*Lampirkan surat undangan meeting/kunjungan klien jika ada.';
    } else if (cat === 'izin_sakit') {
        fDateRange.classList.remove('hidden');
        dateTitle.innerHTML = '<span>🩺</span> Tanggal Istirahat Sakit';
        quotaBadge.classList.add('hidden');
        attLabel.innerHTML = 'Unggah Surat Keterangan Dokter <span class="text-red-400 font-bold">*Wajib</span>';
        attReqNote.textContent = '*Wajib melampirkan foto/PDF surat keterangan sakit dari dokter/klinik.';
    } else if (cat === 'cuti') {
        fDateRange.classList.remove('hidden');
        dateTitle.innerHTML = '<span>🌴</span> Periode Tanggal Cuti Tahunan';
        quotaBadge.classList.remove('hidden');
        attLabel.innerHTML = 'Unggah Dokumen Lampiran <span class="text-slate-500 font-normal">(Opsional)</span>';
        attReqNote.textContent = '*Pemotongan kuota cuti tahunan dihitung otomatis berdasarkan rentang tanggal kerja.';
        calculateDuration();
    } else if (cat === 'pekerjaan_penting') {
        attLabel.innerHTML = 'Unggah Berkas Pendukung / Invoice <span class="text-blue-400 font-semibold">(Sangat Disarankan)</span>';
        attReqNote.textContent = '*Contoh: Faktur tagihan vendor, dokumen kontrak klien, atau disposisi darurat.';
    } else if (cat === 'pembelian_alat') {
        fPembelianAlat.classList.remove('hidden');
        attLabel.innerHTML = 'Lampiran Bukti Harga / Katalog <span class="text-amber-400 font-bold">*Disarankan</span>';
        attReqNote.textContent = '*Screenshot keranjang belanja, invoice proforma, atau penawaran toko resmi (Maks 5MB).';
        calcPurchaseTotal();
    } else if (cat === 'dokumen_legal') {
        fDokumenLegal.classList.remove('hidden');
        attLabel.innerHTML = 'Upload Draft Dokumen Legal <span class="text-red-400 font-bold">*Wajib</span>';
        attReqNote.textContent = '*Wajib melampirkan berkas draft Quotation, Kontrak (PKS), MoU, atau Purchase Order (PDF/DOCX).';
        checkLegalValue();
    }
}

function calcPurchaseTotal() {
    const qty = parseInt(document.getElementById('purchaseQty').value) || 0;
    const price = parseFloat(document.getElementById('purchasePrice').value) || 0;
    const total = qty * price;
    
    document.getElementById('purchaseTotalInput').value = total;
    document.getElementById('purchaseTotalDisplay').textContent = 'Rp ' + total.toLocaleString('id-ID');
    
    const alertBox = document.getElementById('purchaseBudgetAlert');
    if (total <= 0) {
        alertBox.className = 'text-xs py-1 px-3 rounded-lg bg-slate-900 border border-slate-700 text-slate-400';
        alertBox.innerHTML = 'Isi jumlah dan harga satuan di atas';
    } else if (total <= 2500000) {
        alertBox.className = 'text-xs py-1 px-3 rounded-lg bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-semibold';
        alertBox.innerHTML = '✓ Sesuai Pagu Standar (<= Rp 2.500.000) — AI: Layak ACC';
    } else {
        alertBox.className = 'text-xs py-1 px-3 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-300 font-semibold';
        alertBox.innerHTML = '⚠️ Melebihi Pagu Standar (Rp 2.500.000) — AI: Perlu Tinjauan Anggaran';
    }
}

function checkLegalValue() {
    const val = parseFloat(document.getElementById('legalContractValue').value) || 0;
    const alertBox = document.getElementById('legalRiskAlert');
    if (!alertBox) return;
    alertBox.classList.remove('hidden');
    
    if (val > 50000000) {
        alertBox.className = 'p-3 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2';
        alertBox.innerHTML = '⚠️ <strong>Kontrak Bernilai Tinggi (> Rp 50.000.000)!</strong> SmartApprove AI Engine akan menandai untuk peninjauan menyeluruh legal & otorisasi manajemen senior.';
    } else if (val > 0) {
        alertBox.className = 'p-3 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2';
        alertBox.innerHTML = '✓ Nilai transaksi berada dalam batas otorisasi standar (<= Rp 50.000.000) — AI: Layak ACC.';
    } else {
        alertBox.className = 'p-3 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 text-xs flex items-center gap-2';
        alertBox.innerHTML = 'ℹ️ Dokumen Non-Moneter (MoU / NDA / Surat Legal) — Penilaian AI difokuskan pada kelengkapan draf naskah.';
    }
}

function calculateDuration() {
    const sDate = document.getElementById('startDate').value;
    const eDate = document.getElementById('endDate').value;
    const badge = document.getElementById('quotaCalcBadge');
    const warning = document.getElementById('quotaWarning');
    const warningText = document.getElementById('quotaWarningText');

    if (!sDate || !eDate) return;

    const d1 = new Date(sDate);
    const d2 = new Date(eDate);

    if (d2 >= d1) {
        const diffTime = Math.abs(d2 - d1);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        badge.textContent = `Durasi: ${diffDays} Hari Kerja`;
        badge.classList.remove('hidden');

        // Check if cuti category
        const cat = document.querySelector('input[name="category"]:checked').value;
        if (cat === 'cuti') {
            if (diffDays > currentLeaveQuota) {
                warning.classList.remove('hidden');
                warningText.innerHTML = `⚠️ <strong>Peringatan Kuota Kurang!</strong> Anda mengajukan <strong>${diffDays} hari</strong>, tetapi sisa kuota Anda hanya <strong>${currentLeaveQuota} hari</strong>. AI akan menandai pengajuan ini sebagai <em>Perlu Tinjauan</em>.`;
            } else {
                warning.classList.add('hidden');
            }
        }
    }
}

let activeWaText = '';

function showWaModal(text) {
    activeWaText = text;
    document.getElementById('waContent').textContent = text;
    document.getElementById('waModal').classList.remove('hidden');
    document.getElementById('copyBtnText').textContent = 'Salin Teks WhatsApp';
}

function closeWaModal() {
    document.getElementById('waModal').classList.add('hidden');
}

function copyWaText() {
    navigator.clipboard.writeText(activeWaText).then(() => {
        document.getElementById('copyBtnText').textContent = 'Tersalin ke Clipboard!';
        setTimeout(() => {
            document.getElementById('copyBtnText').textContent = 'Salin Teks WhatsApp';
        }, 2000);
    });
}
</script>
