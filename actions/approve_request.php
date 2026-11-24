<?php
// actions/approve_request.php - 1-Click ACC and Rejection Handler
require_once __DIR__ . '/../config/auth.php';

$user = requireAuth(['atasan', 'hrd']);
$db = getDb();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

$subId  = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? 'approve'; // 'approve' or 'reject'
$notes  = trim($_POST['notes'] ?? $_GET['notes'] ?? '');

// Handle quick link from WhatsApp simulation (?id=X&quick=1)
if (isset($_GET['quick']) && $_GET['quick'] == 1) {
    $action = 'approve';
    if (empty($notes)) {
        $notes = 'Disetujui via 1-Click ACC Mobile.';
    }
}

if ($subId <= 0) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'ID pengajuan tidak valid.']);
        exit;
    }
    header("Location: ../dashboard.php?error=invalid_id");
    exit;
}

// Fetch submission & applicant data
$stmt = $db->prepare("SELECT s.*, u.name as employee_name, u.leave_quota, u.division FROM submissions s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->execute([$subId]);
$submission = $stmt->fetch();

if (!$submission) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Pengajuan tidak ditemukan.']);
        exit;
    }
    header("Location: ../dashboard.php?error=not_found");
    exit;
}

if ($action === 'approve') {
    $newStatus = 'Disetujui (ACC)';
    if (empty($notes)) {
        $notes = 'Disetujui oleh atasan.';
    }

    $db->beginTransaction();
    try {
        // Update submission
        $stmtUp = $db->prepare("UPDATE submissions SET status = ?, supervisor_notes = ?, approved_by = ?, approved_at = datetime('now') WHERE id = ?");
        $stmtUp->execute([$newStatus, $notes, $user['id'], $subId]);

        // If cuti, deduct leave quota from employee
        if ($submission['category'] === 'cuti' && $submission['days_count'] > 0) {
            $daysToDeduct = (int)$submission['days_count'];
            $stmtQuota = $db->prepare("UPDATE users SET leave_quota = MAX(0, leave_quota - ?) WHERE id = ?");
            $stmtQuota->execute([$daysToDeduct, $submission['user_id']]);

            $stmtLogQuota = $db->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)");
            $stmtLogQuota->execute([$subId, $user['id'], 'Quota Deducted', "Kuota cuti karyawan dikurangi {$daysToDeduct} hari."]);
        }

        // Log approval
        $stmtLog = $db->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)");
        $stmtLog->execute([$subId, $user['id'], 'Approved', "Pengajuan disetujui: {$notes}"]);

        $db->commit();

        if ($isAjax) {
            echo json_encode([
                'success' => true, 
                'message' => "Pengajuan #{$subId} ({$submission['employee_name']}) berhasil disetujui (ACC)!",
                'new_status' => $newStatus,
                'sub_id' => $subId
            ]);
            exit;
        }

        header("Location: ../dashboard.php?success=acc_approved&sub_id={$subId}");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'Gagal memproses persetujuan: ' . $e->getMessage()]);
            exit;
        }
        header("Location: ../dashboard.php?error=db_failed");
        exit;
    }

} else {
    // Reject
    $newStatus = 'Ditolak';
    if (empty($notes)) {
        $notes = 'Mohon maaf, pengajuan saat ini belum dapat disetujui.';
    }

    $stmtUp = $db->prepare("UPDATE submissions SET status = ?, supervisor_notes = ?, approved_by = ?, approved_at = datetime('now') WHERE id = ?");
    $stmtUp->execute([$newStatus, $notes, $user['id'], $subId]);

    $stmtLog = $db->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)");
    $stmtLog->execute([$subId, $user['id'], 'Rejected', "Pengajuan ditolak: {$notes}"]);

    if ($isAjax) {
        echo json_encode([
            'success' => true, 
            'message' => "Pengajuan #{$subId} ({$submission['employee_name']}) telah ditolak.",
            'new_status' => $newStatus,
            'sub_id' => $subId
        ]);
        exit;
    }

    header("Location: ../dashboard.php?success=acc_rejected&sub_id={$subId}");
    exit;
}
