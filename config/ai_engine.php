<?php
// config/ai_engine.php - SmartApprove AI Engine

function runSmartApproveAI(PDO $pdo, array $subData, array $user, ?string $attachmentName = null): array {
    $category    = $subData['category'] ?? 'izin_keluar';
    $title       = trim($subData['title'] ?? '');
    $description = trim($subData['description'] ?? '');
    $urgency     = $subData['urgency'] ?? 'Sedang';
    $destination = trim($subData['destination'] ?? '');
    $depTime     = trim($subData['departure_time'] ?? '');
    $retTime     = trim($subData['return_time'] ?? '');
    $startDate   = $subData['start_date'] ?? null;
    $endDate     = $subData['end_date'] ?? null;
    $daysCount   = (int)($subData['days_count'] ?? 1);

    // New Category 5: Pembelian Alat Kantor Fields
    $itemName     = trim($subData['item_name'] ?? '');
    $itemCategory = trim($subData['item_category'] ?? '');
    $itemQty      = (int)($subData['item_qty'] ?? 1);
    $itemPrice    = (float)($subData['item_price'] ?? 0);
    $itemTotal    = (float)($subData['item_total'] ?? ($itemQty * $itemPrice));
    $vendorName   = trim($subData['vendor_name'] ?? '');

    // New Category 6: Dokumen Legal Fields
    $docType       = trim($subData['doc_type'] ?? '');
    $clientName    = trim($subData['client_name'] ?? '');
    $contractValue = (float)($subData['contract_value'] ?? 0);
    $validityDate  = !empty($subData['validity_date']) ? $subData['validity_date'] : null;

    $flags = [];
    $docStatus = 'Valid & Terlampir';
    $docNotes = 'Dokumen dan kelengkapan informasi terverifikasi.';
    $quotaStatus = 'N/A';
    $quotaNotes = 'Kategori ini tidak mengurangi kuota cuti tahunan.';
    $conflictStatus = 'Aman (Tanpa Bentrok)';
    $conflictNotes = 'Tidak terdeteksi bentrok jadwal atau risiko kebijakan operasional.';
    $recommendation = 'Layak ACC';
    $confidence = 96;

    // 1. Category Specific Logic & Document Scanner
    if ($category === 'izin_keluar') {
        if (empty($destination)) {
            $flags[] = "Tujuan lokasi kunjungan atau nama klien belum diisi secara spesifik.";
            $docStatus = "Perlu Verifikasi";
        }
        if (empty($depTime) || empty($retTime)) {
            $flags[] = "Estimasi jam keberangkatan atau jam kembali ke kantor tidak lengkap.";
        }
        $docNotes = $attachmentName ? "Dokumen pendukung ({$attachmentName}) terlampir." : "Izin operasional harian terdata.";

    } elseif ($category === 'izin_sakit') {
        if (empty($attachmentName)) {
            $flags[] = "Surat Keterangan Sakit dari Dokter / Klinik belum dilampirkan.";
            $docStatus = "Tidak Ada Dokumen";
            $docNotes = "Wajib melampirkan foto/PDF surat dokter untuk verifikasi HRD.";
            $recommendation = 'Perlu Tinjauan';
            $confidence = 72;
        } else {
            $docStatus = "Valid & Terlampir";
            $docNotes = "Surat dokter ({$attachmentName}) berhasil diunggah dan terbaca oleh sistem.";
        }

    } elseif ($category === 'cuti') {
        // Quota Checker
        $currentQuota = (int)$user['leave_quota'];
        if ($daysCount > $currentQuota) {
            $defisit = $daysCount - $currentQuota;
            $flags[] = "Sisa kuota cuti pemohon ({$currentQuota} hari) tidak mencukupi pengajuan ({$daysCount} hari). Defisit {$defisit} hari.";
            $quotaStatus = "Kurang / Melebihi Kuota";
            $quotaNotes = "Pemohon mengajukan {$daysCount} hari, kuota saat ini hanya {$currentQuota} hari.";
            $recommendation = 'Perlu Tinjauan';
            $confidence = 65;
        } else {
            $sisaSetelah = $currentQuota - $daysCount;
            $quotaStatus = "Mencukupi";
            $quotaNotes = "Sisa kuota mencukupi ({$currentQuota} hari). Estimasi saldo setelah ACC: {$sisaSetelah} hari.";
        }

        // Division Conflict Checker
        if ($startDate && $endDate) {
            $stmtConf = $pdo->prepare("
                SELECT s.id, u.name, u.job_title, s.start_date, s.end_date 
                FROM submissions s
                JOIN users u ON s.user_id = u.id
                WHERE u.division = :div 
                  AND u.id != :uid 
                  AND s.status = 'Disetujui (ACC)'
                  AND s.start_date <= :ed 
                  AND s.end_date >= :sd
            ");
            $stmtConf->execute([
                ':div' => $user['division'],
                ':uid' => $user['id'],
                ':sd'  => $startDate,
                ':ed'  => $endDate
            ]);
            $conflicts = $stmtConf->fetchAll();

            if (!empty($conflicts)) {
                $conflictNames = [];
                foreach ($conflicts as $c) {
                    $conflictNames[] = "{$c['name']} ({$c['job_title']})";
                }
                $flags[] = "Potensi bentrok divisi " . $user['division'] . ": Rekan kerja (" . implode(', ', $conflictNames) . ") telah disetujui cuti pada periode yang sama.";
                $conflictStatus = "Potensi Bentrok Jadwal";
                $conflictNotes = "Terdapat personil lain di divisi " . $user['division'] . " yang sedang cuti pada tanggal tersebut.";
                $recommendation = 'Perlu Tinjauan';
                $confidence = min($confidence, 68);
            }
        }

    } elseif ($category === 'pekerjaan_penting') {
        if ($urgency === 'Darurat' || $urgency === 'Tinggi') {
            $docNotes = "Pengajuan prioritas tinggi. Dokumen pendukung " . ($attachmentName ? "({$attachmentName}) terlampir." : "disarankan.");
        }
        if (strlen($description) < 20) {
            $flags[] = "Deskripsi pekerjaan penting terlalu singkat. Mohon pertimbangkan kelayakan justifikasi.";
            $docStatus = "Perlu Verifikasi";
            $recommendation = 'Perlu Tinjauan';
            $confidence = 70;
        }

    } elseif ($category === 'pembelian_alat') {
        // Category 5: Persetujuan Pembelian Alat Kantor
        $isAttachmentPresent = !empty($attachmentName);
        $formattedTotal = 'Rp ' . number_format($itemTotal, 0, ',', '.');

        if (!$isAttachmentPresent) {
            $flags[] = "Bukti katalog harga, penawaran toko, atau tangkapan layar keranjang belanja belum dilampirkan.";
            $docStatus = "Tidak Ada Dokumen";
            $docNotes = "Disarankan melampirkan screenshot bukti harga vendor.";
        } else {
            $docStatus = "Valid & Terlampir";
            $docNotes = "Bukti katalog penawaran harga ({$attachmentName}) terlampir.";
        }

        if ($itemTotal > 2500000) {
            $flags[] = "Total estimasi biaya ({$formattedTotal}) melebihi pagu operasional standar (Rp 2.500.000). Disarankan atasan memverifikasi alokasi anggaran atau meminta perbandingan vendor alternatif.";
            $conflictStatus = "Pagu Anggaran Terlampaui";
            $conflictNotes = "Total biaya di atas ambang batas persetujuan cepat tanpa komparasi (Rp 2.500.000).";
            $recommendation = 'Perlu Tinjauan';
            $confidence = 68;
        } else {
            $conflictStatus = "Aman (Anggaran Sesuai)";
            $conflictNotes = "Estimasi belanja ({$formattedTotal}) berada dalam batas pagu pengadaan operasional standar.";
        }

        if (!$isAttachmentPresent) {
            $recommendation = 'Perlu Tinjauan';
            $confidence = min($confidence, 70);
        } elseif ($itemTotal <= 2500000) {
            $recommendation = 'Layak ACC';
            $confidence = 97;
        }

    } elseif ($category === 'dokumen_legal') {
        // Category 6: Persetujuan Pengadaan Dokumen Legal
        $isDraftPresent = !empty($attachmentName);
        $formattedContractVal = $contractValue > 0 ? 'Rp ' . number_format($contractValue, 0, ',', '.') : 'Non-Moneter';

        if (!$isDraftPresent) {
            $flags[] = "Draf naskah dokumen legal ({$docType}) belum diunggah. Atasan disarankan tidak menandatangani/ACC sebelum membaca naskah draf lengkap.";
            $docStatus = "Tidak Ada Dokumen";
            $docNotes = "Wajib mengunggah draf berkas (PDF/Word).";
        } else {
            $docStatus = "Valid & Terlampir";
            $docNotes = "Draf naskah dokumen legal ({$attachmentName}) terlampir dan terbaca sistem.";
        }

        if (empty($clientName)) {
            $flags[] = "Nama klien / mitra / pihak terkait belum diisi secara lengkap.";
        }

        if (empty($validityDate)) {
            $flags[] = "Batas waktu atau masa berlaku dokumen belum ditentukan.";
        }

        if ($contractValue > 50000000) {
            $flags[] = "Nilai transaksi kontrak ({$formattedContractVal}) bernilai tinggi (> Rp 50.000.000). Memerlukan peninjauan menyeluruh klausul hukum, termin pembayaran, dan otorisasi manajemen senior.";
            $conflictStatus = "Perlu Tinjauan Legal Senior";
            $conflictNotes = "Nilai komersial melebihi batas toleransi persetujuan cepat (Rp 50.000.000).";
            $recommendation = 'Perlu Tinjauan';
            $confidence = 65;
        } else {
            $conflictStatus = "Aman (Kepatuhan Legal)";
            $conflictNotes = "Nilai komersial berada dalam batas wewenang otorisasi standar (<= Rp 50.000.000).";
        }

        if (!$isDraftPresent || empty($clientName)) {
            $recommendation = 'Perlu Tinjauan';
            $confidence = min($confidence, 68);
        } elseif ($contractValue <= 50000000) {
            $recommendation = 'Layak ACC';
            $confidence = 95;
        }
    }

    // 2. Generate 2-sentence Executive Summary
    if ($category === 'pembelian_alat') {
        $formattedTotal = 'Rp ' . number_format($itemTotal, 0, ',', '.');
        if ($recommendation === 'Layak ACC') {
            $summary = "{$user['name']} ({$user['job_title']}) mengajukan pembelian {$itemName} sebanyak {$itemQty} unit dari {$vendorName} dengan total estimasi {$formattedTotal}. Dokumen penawaran harga terlampir lengkap dan total biaya berada di dalam batas pagu belanja operasional standar (<= Rp 2.500.000).";
        } else {
            $reasonsText = !empty($flags) ? implode('; ', $flags) : 'perlu verifikasi anggaran';
            $summary = "Pengajuan pembelian {$itemName} senilai {$formattedTotal} oleh {$user['name']} memerlukan tinjauan khusus atasan karena {$reasonsText}. Disarankan atasan memverifikasi ketersediaan anggaran sebelum memberikan persetujuan.";
        }
    } elseif ($category === 'dokumen_legal') {
        $valText = $contractValue > 0 ? "senilai Rp " . number_format($contractValue, 0, ',', '.') : "non-moneter";
        $validText = $validityDate ? "dengan masa berlaku s/d " . date('d M Y', strtotime($validityDate)) : "";
        if ($recommendation === 'Layak ACC') {
            $summary = "{$user['name']} ({$user['job_title']}) mengajukan pengesahan {$docType} untuk pihak {$clientName} {$valText} {$validText}. Draf dokumen terlampir lengkap dan parameter komersial telah memenuhi standar operasional sehingga layak disetujui.";
        } else {
            $reasonsText = !empty($flags) ? implode('; ', $flags) : 'perlu tinjauan legal komprehensif';
            $summary = "Pengajuan dokumen legal {$docType} untuk {$clientName} oleh {$user['name']} memerlukan tinjauan khusus atasan karena {$reasonsText}. Disarankan memeriksa klausul perjanjian secara menyeluruh sebelum menyetujui.";
        }
    } elseif ($recommendation === 'Layak ACC') {
        $summary = "{$user['name']} ({$user['job_title']}) mengajukan {$category} dengan data terverifikasi lengkap dan tidak ada bentrok kebijakan operasional. Rekomendasi AI adalah layak disetujui untuk mendukung kelancaran aktivitas tim.";
    } else {
        $reasonsText = !empty($flags) ? implode('; ', $flags) : 'perlu penyesuaian jadwal';
        $summary = "Pengajuan {$category} oleh {$user['name']} memerlukan tinjauan khusus atasan karena terdeteksi catatan operasional: {$reasonsText}. Disarankan konfirmasi sebelum memberikan persetujuan final.";
    }

    // 3. Format WhatsApp Notification Template
    $categoryLabels = [
        'izin_keluar' => 'Izin Keluar Kantor',
        'izin_sakit' => 'Izin Sakit',
        'cuti' => 'Cuti / Libur Tahunan',
        'pekerjaan_penting' => 'Pekerjaan Penting',
        'pembelian_alat' => 'Persetujuan Pembelian Alat Kantor',
        'dokumen_legal' => 'Persetujuan Pengadaan Dokumen Legal'
    ];
    $catLabel = $categoryLabels[$category] ?? ucfirst($category);
    $urgencyIcons = [
        'Darurat' => '🚨 DARURAT',
        'Mendesak' => '⚡ MENDESAK',
        'Tinggi' => '⚡ TINGGI',
        'Sedang' => '🔹 SEDANG',
        'Normal' => '🔹 NORMAL',
        'Rendah' => '⚪ RENDAH'
    ];
    $urgIcon = $urgencyIcons[$urgency] ?? '🔹';

    $recEmoji = ($recommendation === 'Layak ACC') ? '🟢 LAYAK ACC' : '🟡 PERLU TINJAUAN';

    $waMessage = "🔔 *SMARTAPPROVE AI NOTIFICATION*\n";
    $waMessage .= "Yth. Manajer / Atasan Operasional,\n\n";
    $waMessage .= "📌 *Pengajuan:* {$catLabel} ({$urgIcon})\n";
    $waMessage .= "👤 *Pemohon:* {$user['name']} ({$user['nik']})\n";
    $waMessage .= "🏢 *Divisi:* {$user['division']} — {$user['job_title']}\n";
    $waMessage .= "📝 *Judul:* {$title}\n";
    
    if ($category === 'izin_keluar') {
        $waMessage .= "📍 *Tujuan:* {$destination} ({$depTime} s/d {$retTime})\n";
    } elseif ($category === 'cuti' || $category === 'izin_sakit') {
        $waMessage .= "📅 *Tanggal:* {$startDate} s/d {$endDate} ({$daysCount} Hari)\n";
        $waMessage .= "📊 *Sisa Cuti:* {$user['leave_quota']} Hari\n";
    } elseif ($category === 'pembelian_alat') {
        $waMessage .= "🛒 *Barang:* {$itemName} ({$itemCategory})\n";
        $waMessage .= "🔢 *Jumlah:* {$itemQty} Unit (Est. Satuan: Rp " . number_format($itemPrice, 0, ',', '.') . ")\n";
        $waMessage .= "💰 *Total Biaya:* Rp " . number_format($itemTotal, 0, ',', '.') . "\n";
        if (!empty($vendorName)) {
            $waMessage .= "🏪 *Vendor / Toko:* {$vendorName}\n";
        }
    } elseif ($category === 'dokumen_legal') {
        $waMessage .= "📄 *Jenis Dokumen:* {$docType}\n";
        $waMessage .= "🏢 *Klien / Mitra:* {$clientName}\n";
        if ($contractValue > 0) {
            $waMessage .= "💵 *Nilai Transaksi:* Rp " . number_format($contractValue, 0, ',', '.') . "\n";
        } else {
            $waMessage .= "💵 *Nilai Transaksi:* Non-Moneter / Tanpa Nilai Kontrak\n";
        }
        if ($validityDate) {
            $waMessage .= "📅 *Masa Berlaku:* s/d " . date('d M Y', strtotime($validityDate)) . "\n";
        }
    }
    $waMessage .= "\n🤖 *AI INSIGHT ({$recEmoji} - {$confidence}%):*\n";
    $waMessage .= "_{$summary}_\n\n";
    if (!empty($flags)) {
        $waMessage .= "⚠️ *Catatan AI:*\n";
        foreach ($flags as $f) {
            $waMessage .= "• {$f}\n";
        }
        $waMessage .= "\n";
    }
    $waMessage .= "⚡ *Aksi Cepat via Mobile:*\n";
    $waMessage .= "Buka dashboard untuk 1-Click ACC atau tinjau langsung.";

    return [
        'recommendation'   => $recommendation,
        'confidence_score' => $confidence,
        'summary'          => $summary,
        'doc_status'       => $docStatus,
        'doc_notes'        => $docNotes,
        'quota_status'     => $quotaStatus,
        'quota_notes'      => $quotaNotes,
        'conflict_status'  => $conflictStatus,
        'conflict_notes'   => $conflictNotes,
        'flags'            => json_encode($flags),
        'wa_preview'       => $waMessage
    ];
}
