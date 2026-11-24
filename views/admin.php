<?php
// views/admin.php - HR / Admin Portal View
defined('APP_RUN') or die('Direct access not permitted');

// Company-wide stats
$stmtStats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'karyawan') as total_employees,
        COUNT(*) as total_submissions,
        SUM(CASE WHEN status = 'Menunggu ACC' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'Disetujui (ACC)' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected_count
    FROM submissions
");
$stats = $stmtStats->fetch();

// Fetch all employees for quota management
$employees = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM submissions WHERE user_id = u.id) as sub_count
    FROM users u 
    WHERE u.role = 'karyawan'
    ORDER BY u.name ASC
")->fetchAll();

// Fetch all company submissions
$filterStatus = $_GET['status_filter'] ?? 'all';
$query = "
    SELECT s.*, 
           u.name as emp_name, u.nik as emp_nik, u.division, u.job_title,
           a.recommendation as ai_rec, a.confidence_score as ai_score, a.summary as ai_summary,
           sup.name as approver_name
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN ai_analyses a ON s.id = a.submission_id
    LEFT JOIN users sup ON s.approved_by = sup.id
";

if ($filterStatus !== 'all') {
    $query .= " WHERE s.status = :status ";
}
$query .= " ORDER BY s.id DESC LIMIT 50";

$stmtAll = $db->prepare($query);
if ($filterStatus !== 'all') {
    $stmtAll->execute([':status' => $filterStatus]);
} else {
    $stmtAll->execute();
}
$companySubmissions = $stmtAll->fetchAll();

// Fetch recent audit logs
$auditLogs = $db->query("
    SELECT l.*, u.name as actor_name, u.role as actor_role
    FROM approval_logs l
    LEFT JOIN users u ON l.actor_id = u.id
    ORDER BY l.id DESC LIMIT 8
")->fetchAll();
?>

<div class="space-y-6">

    <!-- HR Header & Export Action -->
    <div class="bg-slate-800/80 backdrop-blur-md border border-slate-700/80 rounded-2xl p-4 sm:p-6 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white text-xl font-bold shadow-lg shadow-purple-500/20 shrink-0">
                    <?= strtoupper(substr($user['name'], 0, 2)) ?>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($user['name']) ?></h2>
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 font-semibold">
                            HR Operations Lead / Admin
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Portal Manajemen Izin & Cuti Perusahaan • Audit Log & Rekapitulasi
                    </p>
                </div>
            </div>

            <!-- Export Button -->
            <a href="actions/export_recap.php" 
               class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/20 transition-all flex items-center justify-center gap-2 shrink-0 active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Unduh Rekapitulasi (CSV/Excel)</span>
            </a>
        </div>
    </div>

    <!-- Alert Success Notification -->
    <?php if (isset($_GET['success']) && $_GET['success'] === 'quota_updated'): ?>
        <div class="p-3.5 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2">
            <span>✓</span> Kuota cuti karyawan berhasil diperbarui dan tercatat di Audit Log.
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-slate-800/70 border border-slate-700/70 rounded-2xl p-4 shadow-lg">
            <span class="text-xs text-slate-400 font-medium block">Total Karyawan Aktif</span>
            <span class="text-2xl font-extrabold text-white mt-1 block"><?= (int)$stats['total_employees'] ?></span>
            <span class="text-[11px] text-blue-400 mt-1 block">Terkoneksi ke sistem</span>
        </div>

        <div class="bg-slate-800/70 border border-slate-700/70 rounded-2xl p-4 shadow-lg">
            <span class="text-xs text-slate-400 font-medium block">Menunggu ACC Atasan</span>
            <span class="text-2xl font-extrabold text-amber-400 mt-1 block"><?= (int)$stats['pending_count'] ?></span>
            <span class="text-[11px] text-amber-300/80 mt-1 block">Perlu verifikasi</span>
        </div>

        <div class="bg-slate-800/70 border border-slate-700/70 rounded-2xl p-4 shadow-lg">
            <span class="text-xs text-slate-400 font-medium block">Total Pengajuan ACC</span>
            <span class="text-2xl font-extrabold text-emerald-400 mt-1 block"><?= (int)$stats['approved_count'] ?></span>
            <span class="text-[11px] text-emerald-300/80 mt-1 block">Telah disetujui</span>
        </div>

        <div class="bg-slate-800/70 border border-slate-700/70 rounded-2xl p-4 shadow-lg">
            <span class="text-xs text-slate-400 font-medium block">Total Seluruh Log</span>
            <span class="text-2xl font-extrabold text-purple-400 mt-1 block"><?= (int)$stats['total_submissions'] ?></span>
            <span class="text-[11px] text-purple-300/80 mt-1 block">Arsip perusahaan</span>
        </div>
    </div>

    <!-- Section 1: Employee Accounts & Leave Quota Management -->
    <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    👥 Manajemen Akun Karyawan & Sisa Kuota Cuti
                </h3>
                <p class="text-xs text-slate-400">Kelola jatah cuti tahunan dan pantau aktivitas pengajuan per karyawan.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="text-slate-400 uppercase tracking-wider bg-slate-900/60 border-b border-slate-700 text-[11px]">
                    <tr>
                        <th class="p-3">Nama Karyawan</th>
                        <th class="p-3">NIK</th>
                        <th class="p-3">Divisi & Jabatan</th>
                        <th class="p-3">Total Pengajuan</th>
                        <th class="p-3">Sisa Kuota Cuti</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50 text-slate-300">
                    <?php foreach ($employees as $emp): ?>
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="p-3 font-bold text-white flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-blue-600/30 text-blue-300 flex items-center justify-center text-xs font-bold">
                                <?= strtoupper(substr($emp['name'], 0, 2)) ?>
                            </div>
                            <span><?= htmlspecialchars($emp['name']) ?></span>
                        </td>
                        <td class="p-3 font-mono text-slate-400"><?= htmlspecialchars($emp['nik']) ?></td>
                        <td class="p-3"><?= htmlspecialchars($emp['job_title']) ?> • <span class="text-blue-400"><?= htmlspecialchars($emp['division']) ?></span></td>
                        <td class="p-3"><?= $emp['sub_count'] ?> Berkas</td>
                        <td class="p-3">
                            <span class="px-2.5 py-1 rounded-lg font-bold text-xs <?= $emp['leave_quota'] <= 2 ? 'bg-red-500/20 text-red-300 border border-red-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' ?>">
                                <?= $emp['leave_quota'] ?> Hari
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <button onclick="openQuotaModal(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name']) ?>', <?= $emp['leave_quota'] ?>)"
                                    class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-blue-600 text-white font-semibold text-xs transition-colors">
                                Sesuaikan Kuota
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Company-Wide Log Submissions -->
    <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-700/60 pb-3">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    📋 Log Seluruh Pengajuan Perusahaan (Real-Time)
                </h3>
                <p class="text-xs text-slate-400">Pantau seluruh alur persetujuan dan rekomendasi SmartApprove AI secara transparan.</p>
            </div>

            <!-- Filter Buttons -->
            <div class="flex items-center gap-1.5 text-xs">
                <a href="dashboard.php?status_filter=all" 
                   class="px-3 py-1 rounded-lg <?= $filterStatus === 'all' ? 'bg-blue-600 text-white font-bold' : 'bg-slate-900 text-slate-400 hover:text-white' ?>">Semua</a>
                <a href="dashboard.php?status_filter=Menunggu ACC" 
                   class="px-3 py-1 rounded-lg <?= $filterStatus === 'Menunggu ACC' ? 'bg-amber-600 text-white font-bold' : 'bg-slate-900 text-slate-400 hover:text-white' ?>">Menunggu ACC</a>
                <a href="dashboard.php?status_filter=Disetujui (ACC)" 
                   class="px-3 py-1 rounded-lg <?= $filterStatus === 'Disetujui (ACC)' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-900 text-slate-400 hover:text-white' ?>">Disetujui</a>
                <a href="dashboard.php?status_filter=Ditolak" 
                   class="px-3 py-1 rounded-lg <?= $filterStatus === 'Ditolak' ? 'bg-red-600 text-white font-bold' : 'bg-slate-900 text-slate-400 hover:text-white' ?>">Ditolak</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="text-slate-400 uppercase tracking-wider bg-slate-900/60 border-b border-slate-700 text-[11px]">
                    <tr>
                        <th class="p-3">ID & Karyawan</th>
                        <th class="p-3">Kategori & Judul</th>
                        <th class="p-3">Rekomendasi AI</th>
                        <th class="p-3">Status ACC</th>
                        <th class="p-3">Atasan / Approval</th>
                        <th class="p-3">Waktu Pengajuan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50 text-slate-300">
                    <?php foreach ($companySubmissions as $row): 
                        $statusBadge = 'bg-amber-500/20 text-amber-300 border-amber-500/30';
                        if ($row['status'] === 'Disetujui (ACC)') {
                            $statusBadge = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
                        } elseif ($row['status'] === 'Ditolak') {
                            $statusBadge = 'bg-red-500/20 text-red-300 border-red-500/30';
                        }

                        $isAiLayak = ($row['ai_rec'] === 'Layak ACC');
                    ?>
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="p-3">
                            <span class="text-slate-500 font-mono">#<?= $row['id'] ?></span>
                            <div class="font-bold text-white"><?= htmlspecialchars($row['emp_name']) ?></div>
                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($row['emp_nik']) ?> • <?= htmlspecialchars($row['division']) ?></div>
                        </td>
                        <td class="p-3">
                            <?php 
                                $adminCatMap = [
                                    'izin_keluar' => 'Izin Keluar',
                                    'izin_sakit' => 'Izin Sakit',
                                    'cuti' => 'Cuti Tahunan',
                                    'pekerjaan_penting' => 'Pekerjaan Penting',
                                    'pembelian_alat' => 'Beli Alat Kantor',
                                    'dokumen_legal' => 'Dokumen Legal'
                                ];
                            ?>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-slate-900 border border-slate-700 text-slate-300">
                                <?= htmlspecialchars($adminCatMap[$row['category']] ?? $row['category']) ?>
                            </span>
                            <div class="font-bold text-white mt-1"><?= htmlspecialchars($row['title']) ?></div>
                        </td>
                        <td class="p-3">
                            <?php if (!empty($row['ai_rec'])): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $isAiLayak ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border-amber-500/30' ?>">
                                    <?= htmlspecialchars($row['ai_rec']) ?> (<?= $row['ai_score'] ?>%)
                                </span>
                            <?php else: ?>
                                <span class="text-slate-500">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold border <?= $statusBadge ?>">
                                <?= htmlspecialchars($row['status']) ?>
                            </span>
                        </td>
                        <td class="p-3">
                            <?php if (!empty($row['approver_name'])): ?>
                                <div class="font-medium text-slate-200"><?= htmlspecialchars($row['approver_name']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= date('d M H:i', strtotime($row['approved_at'])) ?></div>
                            <?php else: ?>
                                <span class="text-slate-500 italic">Menunggu review</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-slate-400 font-mono text-[11px]">
                            <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 3: Audit Trail Log -->
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5 shadow-lg space-y-3">
        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
            <span>🛡️</span> AI & System Audit Trail (Activity Logs)
        </h4>
        <div class="space-y-2 text-xs">
            <?php foreach ($auditLogs as $log): ?>
            <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 border border-slate-700/50">
                <div class="flex items-center gap-2">
                    <span class="text-blue-400 font-mono text-[11px]">#<?= $log['submission_id'] ? $log['submission_id'] : 'SYS' ?></span>
                    <span class="px-1.5 py-0.5 rounded bg-slate-800 text-[10px] text-slate-300 font-bold"><?= htmlspecialchars($log['action']) ?></span>
                    <span class="text-slate-300"><?= htmlspecialchars($log['notes']) ?></span>
                </div>
                <div class="text-[10px] text-slate-500 shrink-0">
                    <?= date('d M, H:i:s', strtotime($log['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Adjust Quota Modal -->
<div id="quotaModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-sm font-bold text-white">Sesuaikan Kuota Cuti Karyawan</h3>
            <button onclick="closeQuotaModal()" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <form action="actions/update_quota.php" method="POST" class="space-y-4">
            <input type="hidden" name="employee_id" id="modalEmpId">
            
            <div>
                <label class="block text-xs text-slate-400 mb-1">Nama Karyawan</label>
                <input type="text" id="modalEmpName" readonly class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white text-xs">
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Sisa Kuota Cuti Baru (Hari)</label>
                <input type="number" name="leave_quota" id="modalEmpQuota" min="0" max="30" required
                       class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm font-bold focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Alasan Penyesuaian</label>
                <input type="text" name="reason" placeholder="Contoh: Penambahan cuti bersama / kompensasi lembur"
                       class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:border-blue-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeQuotaModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openQuotaModal(id, name, quota) {
    document.getElementById('modalEmpId').value = id;
    document.getElementById('modalEmpName').value = name;
    document.getElementById('modalEmpQuota').value = quota;
    document.getElementById('quotaModal').classList.remove('hidden');
}

function closeQuotaModal() {
    document.getElementById('quotaModal').classList.add('hidden');
}
</script>
