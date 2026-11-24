<?php
// actions/export_recap.php - Export company submissions to CSV for HRD
require_once __DIR__ . '/../config/auth.php';

$user = requireAuth(['hrd', 'atasan']);
$db = getDb();

$stmt = $db->query("
    SELECT 
        s.id,
        u.name as employee_name,
        u.nik as employee_nik,
        u.division,
        u.job_title,
        s.category,
        s.title,
        s.description,
        s.urgency,
        s.destination,
        s.departure_time,
        s.return_time,
        s.start_date,
        s.end_date,
        s.days_count,
        s.item_name,
        s.item_category,
        s.item_qty,
        s.item_price,
        s.item_total,
        s.vendor_name,
        s.doc_type,
        s.client_name,
        s.contract_value,
        s.validity_date,
        s.status,
        a.recommendation as ai_recommendation,
        a.confidence_score as ai_confidence,
        a.summary as ai_summary,
        sup.name as approver_name,
        s.supervisor_notes,
        s.created_at,
        s.approved_at
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN ai_analyses a ON s.id = a.submission_id
    LEFT JOIN users sup ON s.approved_by = sup.id
    ORDER BY s.id DESC
");

$rows = $stmt->fetchAll();

$filename = 'Rekap_Pengajuan_SmartApprove_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Output UTF-8 BOM for Microsoft Excel compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header column names
fputcsv($output, [
    'No Pengajuan',
    'Nama Karyawan',
    'NIK',
    'Divisi',
    'Jabatan',
    'Kategori',
    'Judul Pengajuan',
    'Keterangan',
    'Urgensi',
    'Tujuan / Lokasi',
    'Jam Keluar',
    'Jam Kembali',
    'Tanggal Mulai',
    'Tanggal Selesai',
    'Durasi (Hari)',
    'Nama Barang',
    'Kategori Barang',
    'Qty',
    'Estimasi Harga Satuan (Rp)',
    'Total Estimasi Biaya (Rp)',
    'Vendor / Toko',
    'Jenis Dokumen Legal',
    'Klien / Mitra',
    'Nilai Transaksi (Rp)',
    'Masa Berlaku Dokumen',
    'Status Approval',
    'Rekomendasi AI',
    'Skor Keyakinan AI (%)',
    'Ringkasan AI',
    'Disetujui Oleh',
    'Catatan Atasan',
    'Tanggal Diajukan',
    'Tanggal Diputuskan'
]);

foreach ($rows as $r) {
    fputcsv($output, [
        '#' . $r['id'],
        $r['employee_name'],
        $r['employee_nik'],
        $r['division'],
        $r['job_title'],
        $r['category'],
        $r['title'],
        $r['description'],
        $r['urgency'],
        $r['destination'] ?? '-',
        $r['departure_time'] ?? '-',
        $r['return_time'] ?? '-',
        $r['start_date'] ?? '-',
        $r['end_date'] ?? '-',
        $r['days_count'],
        $r['item_name'] ?? '-',
        $r['item_category'] ?? '-',
        $r['item_qty'] ?: '-',
        $r['item_price'] ? number_format($r['item_price'], 0, ',', '.') : '-',
        $r['item_total'] ? number_format($r['item_total'], 0, ',', '.') : '-',
        $r['vendor_name'] ?? '-',
        $r['doc_type'] ?? '-',
        $r['client_name'] ?? '-',
        $r['contract_value'] ? number_format($r['contract_value'], 0, ',', '.') : '-',
        $r['validity_date'] ?? '-',
        $r['status'],
        $r['ai_recommendation'] ?? 'N/A',
        $r['ai_confidence'] ?? 'N/A',
        $r['ai_summary'] ?? 'N/A',
        $r['approver_name'] ?? '-',
        $r['supervisor_notes'] ?? '-',
        $r['created_at'],
        $r['approved_at'] ?? '-'
    ]);
}

fclose($output);
exit;
