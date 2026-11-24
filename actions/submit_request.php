<?php
// actions/submit_request.php - Handler for unified submission form
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/ai_engine.php';

$user = requireAuth();
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php");
    exit;
}

$category    = $_POST['category'] ?? 'izin_keluar';
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$urgency     = $_POST['urgency'] ?? 'Sedang';

$destination = trim($_POST['destination'] ?? null);
$depTime     = trim($_POST['departure_time'] ?? null);
$retTime     = trim($_POST['return_time'] ?? null);

$startDate   = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$endDate     = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

// Category 5: Pembelian Alat Kantor Fields
$itemName     = trim($_POST['item_name'] ?? null);
$itemCategory = trim($_POST['item_category'] ?? null);
$itemQty      = (int)($_POST['item_qty'] ?? 0);
$itemPrice    = (float)($_POST['item_price'] ?? 0);
$itemTotal    = (float)($_POST['item_total'] ?? 0);
if ($itemTotal <= 0 && $itemQty > 0 && $itemPrice > 0) {
    $itemTotal = $itemQty * $itemPrice;
}
$vendorName   = trim($_POST['vendor_name'] ?? null);

// Category 6: Dokumen Legal Fields
$docType       = trim($_POST['doc_type'] ?? null);
$clientName    = trim($_POST['client_name'] ?? null);
$contractValue = (float)($_POST['contract_value'] ?? 0);
$validityDate  = !empty($_POST['validity_date']) ? $_POST['validity_date'] : null;

// Calculate days count
$daysCount = 0;
if ($startDate && $endDate) {
    $d1 = new DateTime($startDate);
    $d2 = new DateTime($endDate);
    if ($d2 >= $d1) {
        $interval = $d1->diff($d2);
        $daysCount = $interval->days + 1;
    }
}

// Handle File Upload
$attachmentPath = null;
$attachmentName = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['attachment']['tmp_name'];
    $origName = basename($_FILES['attachment']['name']);
    $fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
    if (in_array($fileExt, $allowed, true)) {
        $newFilename = 'doc_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
        $destPath    = __DIR__ . '/../uploads/' . $newFilename;
        if (move_uploaded_file($fileTmp, $destPath)) {
            $attachmentPath = $newFilename;
            $attachmentName = $origName;
        }
    }
}

// Basic fallback title if empty
if (empty($title)) {
    $catNames = [
        'izin_keluar' => 'Izin Keluar Kantor',
        'izin_sakit' => 'Izin Sakit',
        'cuti' => 'Cuti Tahunan',
        'pekerjaan_penting' => 'Pekerjaan Penting',
        'pembelian_alat' => 'Pembelian Alat Kantor',
        'dokumen_legal' => 'Pengadaan Dokumen Legal'
    ];
    $title = "Pengajuan " . ($catNames[$category] ?? 'Izin') . " - " . date('d M Y');
}

// 1. Insert into Submissions
$stmt = $db->prepare("
    INSERT INTO submissions (
        user_id, category, title, description, destination, departure_time, return_time,
        start_date, end_date, days_count, urgency,
        item_name, item_category, item_qty, item_price, item_total, vendor_name,
        doc_type, client_name, contract_value, validity_date,
        attachment_path, attachment_name, status, created_at
    ) VALUES (
        :user_id, :category, :title, :description, :destination, :departure_time, :return_time,
        :start_date, :end_date, :days_count, :urgency,
        :item_name, :item_category, :item_qty, :item_price, :item_total, :vendor_name,
        :doc_type, :client_name, :contract_value, :validity_date,
        :attachment_path, :attachment_name, 'Menunggu ACC', datetime('now')
    )
");

$stmt->execute([
    ':user_id'         => $user['id'],
    ':category'        => $category,
    ':title'           => $title,
    ':description'     => $description,
    ':destination'     => $destination,
    ':departure_time'  => $depTime,
    ':return_time'     => $retTime,
    ':start_date'      => $startDate,
    ':end_date'        => $endDate,
    ':days_count'      => $daysCount,
    ':urgency'         => $urgency,
    ':item_name'       => $itemName,
    ':item_category'   => $itemCategory,
    ':item_qty'        => $itemQty,
    ':item_price'      => $itemPrice,
    ':item_total'      => $itemTotal,
    ':vendor_name'     => $vendorName,
    ':doc_type'        => $docType,
    ':client_name'     => $clientName,
    ':contract_value'  => $contractValue,
    ':validity_date'   => $validityDate,
    ':attachment_path' => $attachmentPath,
    ':attachment_name' => $attachmentName
]);

$submissionId = $db->lastInsertId();

// 2. Run SmartApprove AI Engine Scanner
$subData = [
    'category'       => $category,
    'title'          => $title,
    'description'    => $description,
    'urgency'        => $urgency,
    'destination'    => $destination,
    'departure_time' => $depTime,
    'return_time'    => $retTime,
    'start_date'     => $startDate,
    'end_date'       => $endDate,
    'days_count'     => $daysCount,
    'item_name'      => $itemName,
    'item_category'  => $itemCategory,
    'item_qty'       => $itemQty,
    'item_price'     => $itemPrice,
    'item_total'     => $itemTotal,
    'vendor_name'    => $vendorName,
    'doc_type'       => $docType,
    'client_name'    => $clientName,
    'contract_value' => $contractValue,
    'validity_date'  => $validityDate,
];

$aiResult = runSmartApproveAI($db, $subData, $user, $attachmentName);

// 3. Save AI Analysis
$stmtAi = $db->prepare("
    INSERT INTO ai_analyses (
        submission_id, recommendation, confidence_score, summary, doc_status, doc_notes,
        quota_status, quota_notes, conflict_status, conflict_notes, flags, wa_preview, created_at
    ) VALUES (
        :submission_id, :recommendation, :confidence_score, :summary, :doc_status, :doc_notes,
        :quota_status, :quota_notes, :conflict_status, :conflict_notes, :flags, :wa_preview, datetime('now')
    )
");

$stmtAi->execute([
    ':submission_id'   => $submissionId,
    ':recommendation'  => $aiResult['recommendation'],
    ':confidence_score'=> $aiResult['confidence_score'],
    ':summary'         => $aiResult['summary'],
    ':doc_status'      => $aiResult['doc_status'],
    ':doc_notes'       => $aiResult['doc_notes'],
    ':quota_status'    => $aiResult['quota_status'],
    ':quota_notes'     => $aiResult['quota_notes'],
    ':conflict_status' => $aiResult['conflict_status'],
    ':conflict_notes'  => $aiResult['conflict_notes'],
    ':flags'           => $aiResult['flags'],
    ':wa_preview'      => $aiResult['wa_preview']
]);

// 4. Log to Approval Logs
$stmtLog = $db->prepare("INSERT INTO approval_logs (submission_id, actor_id, action, notes) VALUES (?, ?, ?, ?)");
$stmtLog->execute([$submissionId, $user['id'], 'Submitted', 'Pengajuan berhasil dikirimkan oleh pemohon.']);
$stmtLog->execute([$submissionId, $user['id'], 'AI Analyzed', "SmartApprove AI Engine selesai memindai: Rekomendasi {$aiResult['recommendation']} ({$aiResult['confidence_score']}%)."]);

header("Location: ../dashboard.php?success=submitted&sub_id=" . $submissionId);
exit;
