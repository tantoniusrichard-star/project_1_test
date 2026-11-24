<?php
// actions/update_quota.php - Update employee leave quota by HRD
require_once __DIR__ . '/../config/auth.php';

$user = requireAuth(['hrd']);
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId = (int)($_POST['employee_id'] ?? 0);
    $newQuota = (int)($_POST['leave_quota'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Penyesuaian kuota oleh HRD');

    if ($empId > 0 && $newQuota >= 0) {
        $stmt = $db->prepare("UPDATE users SET leave_quota = ? WHERE id = ?");
        $stmt->execute([$newQuota, $empId]);

        // Audit log
        $stmtLog = $db->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (0, ?, 'Quota Adjusted', ?)");
        $stmtLog->execute([$user['id'], "Kuota cuti Karyawan ID #{$empId} diubah menjadi {$newQuota} hari. Alasan: {$reason}"]);

        header("Location: ../dashboard.php?success=quota_updated");
        exit;
    }
}

header("Location: ../dashboard.php?error=invalid_quota");
exit;
