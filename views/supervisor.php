<?php
// views/supervisor.php - Mobile-First Supervisor Dashboard (1-Click ACC)
defined('APP_RUN') or die('Direct access not permitted');

// Get counts
$stmtCounts = $db->query("
    SELECT 
        SUM(CASE WHEN status = 'Menunggu ACC' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'Disetujui (ACC)' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM submissions
");
$counts = $stmtCounts->fetch();

// Fetch Pending Submissions first
$stmtPending = $db->prepare("
    SELECT s.*, 
           u.name as emp_name, u.nik as emp_nik, u.division as emp_division, 
           u.job_title as emp_job_title, u.leave_quota as emp_quota, u.avatar as emp_avatar,
           a.recommendation as ai_rec, a.confidence_score as ai_score, a.summary as ai_summary,
           a.doc_status, a.doc_notes, a.quota_status, a.quota_notes, a.conflict_status, a.conflict_notes,
           a.flags as ai_flags, a.wa_preview
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN ai_analyses a ON s.id = a.submission_id
    WHERE s.status = 'Menunggu ACC'
    ORDER BY 
        CASE WHEN s.urgency = 'Darurat' THEN 1 
             WHEN s.urgency = 'Tinggi' THEN 2 
             ELSE 3 END,
        s.id DESC
");
$stmtPending->execute();
$pendingList = $stmtPending->fetchAll();

// Fetch History Submissions
$stmtHistory = $db->prepare("
    SELECT s.*, 
           u.name as emp_name, u.nik as emp_nik, u.division as emp_division, 
           u.job_title as emp_job_title, u.leave_quota as emp_quota,
           a.recommendation as ai_rec, a.summary as ai_summary,
           sup.name as approver_name
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN ai_analyses a ON s.id = a.submission_id
    LEFT JOIN users sup ON s.approved_by = sup.id
    WHERE s.status != 'Menunggu ACC'
    ORDER BY s.approved_at DESC, s.id DESC
    LIMIT 20
");
$stmtHistory->execute();
$historyList = $stmtHistory->fetchAll();
?>

<div class="space-y-6">

    <!-- Supervisor Header & Mobile Priority Alert -->
    <div class="bg-slate-800/80 backdrop-blur-md border border-slate-700/80 rounded-2xl p-4 sm:p-6 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-600 flex items-center justify-center text-white text-xl font-bold shadow-lg shadow-amber-500/20 shrink-0">
                    <?= strtoupper(substr($user['name'], 0, 2)) ?>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($user['name']) ?></h2>
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 font-semibold">
                            <?= htmlspecialchars($user['job_title']) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Divisi <?= htmlspecialchars($user['division']) ?> • Mode: <span class="text-blue-400 font-semibold">Mobile 1-Click ACC</span>
                    </p>
                </div>
            </div>

            <!-- Quick Filter Badges -->
            <div class="flex items-center gap-2">
                <div class="px-4 py-2 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-300 flex items-center gap-2 text-xs font-bold">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                    <span><?= (int)$counts['pending_count'] ?> Butuh ACC</span>
                </div>
                <div class="px-4 py-2 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-semibold">
                    <?= (int)$counts['approved_count'] ?> Telah di-ACC
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Toast Container (AJAX) -->
    <div id="ajaxToast" class="hidden fixed bottom-6 right-6 z-50 p-4 rounded-2xl shadow-2xl text-xs font-semibold flex items-center gap-2.5 transition-all">
    </div>

    <!-- Navigation Tabs (Pending vs History) -->
    <div class="flex items-center gap-2 border-b border-slate-700/60 pb-1">
        <button onclick="switchTab('pending')" id="tabPendingBtn" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-blue-600 text-white shadow-md shadow-blue-600/30 flex items-center gap-2">
            <span>Perlu Tindakan (ACC)</span>
            <span class="px-2 py-0.5 rounded-full bg-white/20 text-[10px]"><?= count($pendingList) ?></span>
        </button>
        <button onclick="switchTab('history')" id="tabHistoryBtn" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-400 hover:text-white hover:bg-slate-800 flex items-center gap-2">
            <span>Riwayat Selesai</span>
            <span class="px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700 text-[10px]"><?= count($historyList) ?></span>
        </button>
    </div>

    <!-- TAB 1: PENDING 1-CLICK ACC SECTION -->
    <div id="tabPending" class="space-y-4">
        
        <?php if (empty($pendingList)): ?>
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center space-y-3">
                <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto text-xl">
                    ✓
                </div>
                <h4 class="text-base font-bold text-white">Semua Pengajuan Telah Ditinjau!</h4>
                <p class="text-xs text-slate-400 max-w-md mx-auto">
                    Tidak ada pengajuan izin, cuti, atau permohonan operasional yang tertunda saat ini. Anda akan mendapatkan notifikasi begitu ada pengajuan baru.
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php foreach ($pendingList as $item): 
                    $catIcons = [
                        'izin_keluar' => '🏢 Izin Keluar Kantor',
                        'izin_sakit' => '🩺 Izin Sakit',
                        'cuti' => '🌴 Cuti Tahunan',
                        'pekerjaan_penting' => '⚡ Pekerjaan Penting',
                        'pembelian_alat' => '🛒 Beli Alat Kantor',
                        'dokumen_legal' => '📄 Dokumen Legal'
                    ];

                    $isLayakAcc = ($item['ai_rec'] === 'Layak ACC');
                    $cardBorder = $isLayakAcc ? 'border-emerald-500/40 hover:border-emerald-500/70' : 'border-amber-500/40 hover:border-amber-500/70';
                    $aiFlags = !empty($item['ai_flags']) ? json_decode($item['ai_flags'], true) : [];
                ?>
                
                <!-- Actionable Approval Card (Optimized for Mobile) -->
                <div id="card-<?= $item['id'] ?>" class="bg-slate-800/85 backdrop-blur-md border <?= $cardBorder ?> rounded-2xl p-4 sm:p-5 shadow-xl space-y-4 transition-all">
                    
                    <!-- 1. Employee Identity Header -->
                    <div class="flex items-start justify-between gap-3 border-b border-slate-700/50 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-md shadow-blue-500/20 shrink-0">
                                <?= strtoupper(substr($item['emp_name'], 0, 2)) ?>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold text-white"><?= htmlspecialchars($item['emp_name']) ?></h3>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-700 text-slate-300 font-mono">
                                        <?= htmlspecialchars($item['emp_nik']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-slate-300"><?= htmlspecialchars($item['emp_job_title']) ?> • <span class="text-blue-400"><?= htmlspecialchars($item['emp_division']) ?></span></p>
                            </div>
                        </div>

                        <!-- Employee Quota Badge -->
                        <div class="text-right shrink-0">
                            <span class="text-[10px] text-slate-400 block font-medium">Sisa Cuti</span>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-lg <?= $item['emp_quota'] <= 2 ? 'bg-red-500/20 text-red-300 border border-red-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30' ?>">
                                <?= $item['emp_quota'] ?> Hari
                            </span>
                        </div>
                    </div>

                    <!-- 2. Submission Details & Type -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-slate-900 border border-slate-700 text-slate-300">
                                <?= $catIcons[$item['category']] ?? $item['category'] ?>
                            </span>

                            <?php if ($item['urgency'] === 'Darurat' || $item['urgency'] === 'Tinggi'): ?>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-red-500/20 text-red-300 border border-red-500/30 animate-pulse">
                                    🚨 <?= $item['urgency'] ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] text-slate-400">
                                    <?= date('d M Y, H:i', strtotime($item['created_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h4 class="text-sm font-bold text-white"><?= htmlspecialchars($item['title']) ?></h4>
                        <p class="text-xs text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($item['description'])) ?></p>

                        <!-- Specific Category Meta -->
                        <div class="pt-1 text-xs text-slate-400 space-y-1">
                            <?php if ($item['category'] === 'izin_keluar'): ?>
                                <div class="flex items-center gap-1.5 text-slate-300">
                                    <span>📍</span>
                                    <span><strong>Tujuan:</strong> <?= htmlspecialchars($item['destination']) ?> (<?= htmlspecialchars($item['departure_time']) ?> - <?= htmlspecialchars($item['return_time']) ?>)</span>
                                </div>
                            <?php elseif ($item['category'] === 'cuti' || $item['category'] === 'izin_sakit'): ?>
                                <div class="flex items-center gap-1.5 text-slate-300">
                                    <span>📅</span>
                                    <span><strong>Periode:</strong> <?= $item['start_date'] ?> s/d <?= $item['end_date'] ?> (<strong><?= $item['days_count'] ?> Hari Kerja</strong>)</span>
                                </div>
                            <?php elseif ($item['category'] === 'pembelian_alat'): ?>
                                <div class="flex items-center gap-1.5 text-slate-300">
                                    <span>🛒</span>
                                    <span><strong>Barang:</strong> <?= htmlspecialchars($item['item_name']) ?> (<?= htmlspecialchars($item['item_category']) ?>)</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-300 pt-0.5">
                                    <span>Qty: <strong><?= $item['item_qty'] ?> Unit</strong> (Est: Rp <?= number_format($item['item_price'], 0, ',', '.') ?>)</span>
                                    <span>Total: <strong class="text-emerald-400 font-bold">Rp <?= number_format($item['item_total'], 0, ',', '.') ?></strong></span>
                                </div>
                                <?php if (!empty($item['vendor_name'])): ?>
                                    <div class="text-[11px] text-slate-400">
                                        🏪 Vendor / Toko: <strong class="text-slate-200"><?= htmlspecialchars($item['vendor_name']) ?></strong>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($item['category'] === 'dokumen_legal'): ?>
                                <div class="flex items-center gap-2 text-slate-300">
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-blue-500/20 text-blue-300 border border-blue-500/30 font-bold">
                                        <?= htmlspecialchars($item['doc_type'] ?? 'DOKUMEN LEGAL') ?>
                                    </span>
                                    <span class="truncate">Mitra: <strong class="text-white"><?= htmlspecialchars($item['client_name']) ?></strong></span>
                                </div>
                                <div class="flex items-center justify-between text-slate-300 pt-0.5">
                                    <span>Nilai: <strong class="text-blue-400 font-bold"><?= $item['contract_value'] > 0 ? 'Rp ' . number_format($item['contract_value'], 0, ',', '.') : 'Non-Moneter' ?></strong></span>
                                    <?php if (!empty($item['validity_date'])): ?>
                                        <span class="text-[11px] text-slate-400">Berlaku s/d: <?= date('d M Y', strtotime($item['validity_date'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 3. SmartApprove AI Insight Box -->
                    <div class="p-3.5 rounded-xl bg-slate-900/90 border <?= $isLayakAcc ? 'border-emerald-500/30 bg-emerald-950/10' : 'border-amber-500/30 bg-amber-950/10' ?> space-y-2.5">
                        
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm">🤖</span>
                                <span class="text-xs font-bold text-white">AI Assistant Insight</span>
                            </div>

                            <!-- Recommendation Badge -->
                            <?php if ($isLayakAcc): ?>
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Rekomendasi AI: Layak ACC (<?= $item['ai_score'] ?>%)
                                </span>
                            <?php else: ?>
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                    Rekomendasi AI: Perlu Tinjauan (<?= $item['ai_score'] ?>%)
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- 2-Sentence AI Summary -->
                        <p class="text-xs text-slate-300 italic leading-relaxed">
                            "<?= htmlspecialchars($item['ai_summary']) ?>"
                        </p>

                        <!-- AI Checklist & Warnings -->
                        <div class="space-y-1.5 text-[11px] pt-1 border-t border-slate-800">
                            <div class="grid grid-cols-2 gap-2 text-slate-400">
                                <div>📁 Lampiran: <strong class="text-slate-200"><?= htmlspecialchars($item['doc_status'] ?? 'Valid') ?></strong></div>
                                <?php if ($item['category'] === 'cuti'): ?>
                                    <div>🏖️ Status Kuota: <strong class="<?= $item['quota_status'] === 'Mencukupi' ? 'text-emerald-400' : 'text-amber-400' ?>"><?= htmlspecialchars($item['quota_status']) ?></strong></div>
                                    <div class="col-span-2">👥 Bentrok Divisi: <strong class="<?= $item['conflict_status'] === 'Aman (Tanpa Bentrok)' ? 'text-emerald-400' : 'text-red-400' ?>"><?= htmlspecialchars($item['conflict_status']) ?></strong></div>
                                <?php elseif ($item['category'] === 'pembelian_alat'): ?>
                                    <div>💰 Status Pagu: <strong class="<?= $item['item_total'] <= 2500000 ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $item['item_total'] <= 2500000 ? 'Sesuai Pagu (<= 2.5Jt)' : 'Pagu Terlampaui (> 2.5Jt)' ?></strong></div>
                                    <div class="col-span-2">🏪 Evaluasi Vendor: <strong class="text-slate-200"><?= htmlspecialchars($item['vendor_name'] ?? 'Toko Referensi') ?></strong></div>
                                <?php elseif ($item['category'] === 'dokumen_legal'): ?>
                                    <div>⚖️ Legal Risk: <strong class="<?= $item['contract_value'] <= 50000000 ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $item['contract_value'] <= 50000000 ? 'Standar (<= 50Jt)' : 'Tinggi (> 50Jt)' ?></strong></div>
                                    <div class="col-span-2">📜 Status Naskah: <strong class="text-slate-200"><?= !empty($item['attachment_name']) ? 'Draf Siap Review' : 'Draf Belum Terlampir' ?></strong></div>
                                <?php endif; ?>
                            </div>

                            <!-- Flagged warnings if any -->
                            <?php if (!empty($aiFlags)): ?>
                                <div class="mt-2 p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 space-y-1">
                                    <span class="text-[10px] font-bold text-amber-300 uppercase tracking-wide block">Catatan Peringatan AI:</span>
                                    <?php foreach ($aiFlags as $fl): ?>
                                        <p class="text-[11px] text-amber-200 flex items-start gap-1">
                                            <span class="text-amber-400 mt-0.5">•</span>
                                            <span><?= htmlspecialchars($fl) ?></span>
                                        </p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- WhatsApp Notification Preview Trigger -->
                        <?php if (!empty($item['wa_preview'])): ?>
                            <div class="pt-1 flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Terkirim ke WhatsApp Atasan</span>
                                <button onclick="showWaModal(<?= htmlspecialchars(json_encode($item['wa_preview'])) ?>)"
                                        class="text-emerald-400 hover:text-emerald-300 font-semibold flex items-center gap-1">
                                    <span>💬</span>
                                    <span>Lihat Format WA</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Attachment Button (if exists) -->
                    <?php if (!empty($item['attachment_name'])): ?>
                        <div class="flex items-center justify-between text-xs p-2.5 rounded-xl bg-slate-900/60 border border-slate-700/60">
                            <span class="text-slate-300 truncate max-w-[200px]">📎 <?= htmlspecialchars($item['attachment_name']) ?></span>
                            <a href="uploads/<?= urlencode($item['attachment_path'] ?? $item['attachment_name']) ?>" target="_blank"
                               class="text-blue-400 hover:text-blue-300 font-bold underline shrink-0">
                                [Buka Lampiran]
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- 5. Optional Feedback Input -->
                    <div>
                        <input type="text" id="notes-<?= $item['id'] ?>" placeholder="Catatan opsional untuk karyawan (misal: 'Disetujui', 'Jangan lupa handover tugas')"
                               class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500">
                    </div>

                    <!-- 6. Mobile 1-Click Action Buttons -->
                    <div class="grid grid-cols-12 gap-2 pt-1">
                        <!-- Reject Button -->
                        <div class="col-span-4">
                            <button onclick="handleAccAction(<?= $item['id'] ?>, 'reject')"
                                    class="w-full py-2.5 px-3 rounded-xl bg-slate-800 hover:bg-red-600/30 text-red-400 hover:text-red-300 border border-red-500/40 text-xs font-bold transition-all flex items-center justify-center gap-1.5 active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span>Tolak</span>
                            </button>
                        </div>

                        <!-- 1-Click ACC Button -->
                        <div class="col-span-8">
                            <button onclick="handleAccAction(<?= $item['id'] ?>, 'approve')"
                                    class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-extrabold shadow-lg shadow-emerald-600/30 transition-all flex items-center justify-center gap-2 active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>ACC SEKARANG (1-Click)</span>
                            </button>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- TAB 2: HISTORY SECTION -->
    <div id="tabHistory" class="hidden space-y-3">
        <?php if (empty($historyList)): ?>
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-2xl p-8 text-center text-xs text-slate-400">
                Belum ada riwayat persetujuan.
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($historyList as $h): 
                    $isApproved = ($h['status'] === 'Disetujui (ACC)');
                ?>
                <div class="bg-slate-800/70 border border-slate-700/70 rounded-2xl p-4 shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $isApproved ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30' ?>">
                                <?= htmlspecialchars($h['status']) ?>
                            </span>
                            <h4 class="text-xs sm:text-sm font-bold text-white"><?= htmlspecialchars($h['title']) ?></h4>
                        </div>
                        <p class="text-xs text-slate-400">
                            Pemohon: <strong class="text-slate-200"><?= htmlspecialchars($h['emp_name']) ?></strong> (<?= htmlspecialchars($h['emp_division']) ?>) • 
                            Diputuskan: <?= date('d M Y, H:i', strtotime($h['approved_at'] ?? $h['created_at'])) ?> WIB
                        </p>
                        <?php if (!empty($h['supervisor_notes'])): ?>
                            <p class="text-[11px] text-slate-300 italic">"<?= htmlspecialchars($h['supervisor_notes']) ?>"</p>
                        <?php endif; ?>
                    </div>

                    <div class="text-right shrink-0">
                        <?php 
                            $histCats = [
                                'izin_keluar' => 'Izin Keluar',
                                'izin_sakit' => 'Izin Sakit',
                                'cuti' => 'Cuti Tahunan',
                                'pekerjaan_penting' => 'Pekerjaan Penting',
                                'pembelian_alat' => 'Beli Alat Kantor',
                                'dokumen_legal' => 'Dokumen Legal'
                            ];
                        ?>
                        <span class="text-[11px] text-slate-400 block font-medium">Kategori: <?= htmlspecialchars($histCats[$h['category']] ?? $h['category']) ?></span>
                    </div>
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
                <span>Notifikasi WhatsApp Atasan (Mobile Direct Deep-Link)</span>
            </div>
            <button onclick="closeWaModal()" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
        </div>

        <p class="text-xs text-slate-400 leading-relaxed">
            Format pesan ringkas otomatis yang dikirimkan SmartApprove AI ke smartphone atasan, dilengkapi dengan <strong>1-Click Magic Link</strong>:
        </p>

        <div class="bg-[#0b141a] border border-[#202c33] rounded-xl p-4 text-slate-200 font-mono text-xs whitespace-pre-wrap leading-relaxed shadow-inner" id="waContent">
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
            <button onclick="copyWaText()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold flex items-center gap-1.5 transition-colors">
                <span id="copyBtnText">Salin Teks WhatsApp</span>
            </button>
            <button onclick="closeWaModal()" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-colors">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    const tPending = document.getElementById('tabPending');
    const tHistory = document.getElementById('tabHistory');
    const bPending = document.getElementById('tabPendingBtn');
    const bHistory = document.getElementById('tabHistoryBtn');

    if (tab === 'pending') {
        tPending.classList.remove('hidden');
        tHistory.classList.add('hidden');
        bPending.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-blue-600 text-white shadow-md shadow-blue-600/30 flex items-center gap-2';
        bHistory.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-400 hover:text-white hover:bg-slate-800 flex items-center gap-2';
    } else {
        tPending.classList.add('hidden');
        tHistory.classList.remove('hidden');
        bHistory.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-blue-600 text-white shadow-md shadow-blue-600/30 flex items-center gap-2';
        bPending.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-400 hover:text-white hover:bg-slate-800 flex items-center gap-2';
    }
}

function handleAccAction(id, action) {
    const notesInput = document.getElementById('notes-' + id);
    const notes = notesInput ? notesInput.value : '';

    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', action);
    formData.append('notes', notes);
    formData.append('ajax', '1');

    fetch('actions/approve_request.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, action === 'approve' ? 'emerald' : 'red');
            // Animate card removal
            const card = document.getElementById('card-' + id);
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    card.remove();
                    // reload if no cards left
                    if (document.querySelectorAll('#tabPending [id^="card-"]').length === 0) {
                        window.location.reload();
                    }
                }, 300);
            }
        } else {
            showToast(data.message || 'Terjadi kesalahan.', 'red');
        }
    })
    .catch(err => {
        // Fallback to traditional form submit
        window.location.href = `actions/approve_request.php?id=${id}&action=${action}&notes=${encodeURIComponent(notes)}`;
    });
}

function showToast(message, color) {
    const toast = document.getElementById('ajaxToast');
    toast.textContent = message;
    toast.className = `fixed bottom-6 right-6 z-50 p-4 rounded-2xl shadow-2xl text-xs font-semibold flex items-center gap-2.5 transition-all text-white ${color === 'emerald' ? 'bg-emerald-600' : 'bg-red-600'}`;
    toast.classList.remove('hidden');

    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3500);
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
