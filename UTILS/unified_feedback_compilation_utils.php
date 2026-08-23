<?php
if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
}
if (file_exists(__DIR__ . '/../DATABASE/unified_feedback.php')) {
    require_once __DIR__ . '/../DATABASE/unified_feedback.php';
}
if (file_exists(__DIR__ . '/../DATABASE/audit-log.php')) {
    require_once __DIR__ . '/../DATABASE/audit-log.php';
}

function resolveUnifiedCategory($title, $rawCat, $message) {
    $text = strtolower(trim($title . ' ' . $rawCat . ' ' . $message));
    
    if (preg_match('/(flood|drainage|pumping|river|dike|culvert|overflow|waterway|road|pothole|digging|bridge)/i', $text)) {
        return 'Infrastructure, Drainage & Flood Control';
    }
    if (preg_match('/(waste|garbage|plastic|segregation|recycling|sanitation|mrf|clean|litter|dump|environment|eco)/i', $text)) {
        return 'Environment, Waste & Sanitation';
    }
    if (preg_match('/(traffic|bike|transport|jeepney|commuter|bollard|vehicle|lane|highway)/i', $text)) {
        return 'Traffic & Transport';
    }
    if (preg_match('/(health|clinic|medicine|hospital|doctor|sanitary|wellness|medical|dengue)/i', $text)) {
        return 'Health & Social Services';
    }
    if (preg_match('/(park|playground|recreation|greenery|tree|plaza|open space|lighting|lamp|court)/i', $text)) {
        return 'Public Parks & Recreation';
    }
    if (preg_match('/(legal|notary|lawyer|court|dispute|paralegal|rights|assistance|ordinance|counseling)/i', $text)) {
        return 'Justice & Human Rights';
    }
    
    return 'General Governance';
}

function normalizeFeedbackText($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    
    // Normalize spacing
    $text = preg_replace('/\s+/', ' ', $text);
    // Capitalize first letter
    $text = ucfirst($text);
    // Ensure ending punctuation
    if (!preg_match('/[.!?]$/', $text)) {
        $text .= '.';
    }
    return $text;
}

function compileUnifiedFeedback($conn, $userId = null, $userName = 'System Admin') {
    ensureUnifiedFeedbackTables($conn);

    // 1. Fetch unprocessed PCMS feedback
    $pcmsStmt = $conn->query("
        SELECT f.id, f.consultation_id, f.guest_name, f.guest_email, f.rating, f.category as raw_category, 
               f.message, f.sentiment_tag, f.created_at, c.title as consultation_title
        FROM feedback f
        LEFT JOIN consultations c ON f.consultation_id = c.id
        WHERE f.is_processed = 0 OR f.is_processed IS NULL
        ORDER BY f.created_at ASC
    ");

    $pcmsItems = [];
    if ($pcmsStmt) {
        while ($r = $pcmsStmt->fetch_assoc()) {
            $pcmsItems[] = [
                'id' => (int)$r['id'],
                'source' => 'PCMS',
                'consultation_id' => (int)$r['consultation_id'],
                'title' => $r['consultation_title'] ?: ('Consultation #' . $r['consultation_id']),
                'author' => $r['guest_name'] ?: 'Citizen',
                'rating' => (float)($r['rating'] ?: 4.0),
                'raw_category' => $r['raw_category'] ?: 'General',
                'message' => normalizeFeedbackText($r['message']),
                'sentiment' => strtolower(trim((string)($r['sentiment_tag'] ?: 'neutral'))),
                'created_at' => $r['created_at'] ?: date('Y-m-d H:i:s')
            ];
        }
    }

    // 2. Fetch unprocessed PHMS hearing queue feedback
    $phmsItems = [];
    $hqCheck = $conn->query("SHOW TABLES LIKE 'hearing_queue'");
    if ($hqCheck && $hqCheck->num_rows > 0) {
        $phmsStmt = $conn->query("
            SELECT hq.id, hq.consultation_id, hq.full_name, hq.email, hq.payload_json, hq.created_at
            FROM hearing_queue hq
            WHERE hq.is_processed = 0 OR hq.is_processed IS NULL
            ORDER BY hq.created_at ASC
        ");

        if ($phmsStmt) {
            while ($r = $phmsStmt->fetch_assoc()) {
                $payload = json_decode($r['payload_json'] ?? '[]', true);
                $hTitle = $payload['hearing_title'] ?? $r['full_name'] ?? 'PHMS Public Hearing';
                $responses = $payload['citizen_responses'] ?? $payload['citizen_feedback'] ?? [];

                if (is_array($responses) && !empty($responses)) {
                    foreach ($responses as $resp) {
                        $msg = normalizeFeedbackText($resp['testimony'] ?? $resp['statement'] ?? $resp['message'] ?? '');
                        if ($msg !== '') {
                            $phmsItems[] = [
                                'id' => (int)$r['id'],
                                'source' => 'PHMS',
                                'consultation_id' => (int)($r['consultation_id'] ?: 0),
                                'title' => $hTitle,
                                'author' => $resp['citizen_name'] ?? $resp['name'] ?? 'PHMS Participant',
                                'rating' => (float)($resp['rating'] ?? 4.0),
                                'raw_category' => 'Public Hearing',
                                'message' => $msg,
                                'sentiment' => strtolower(trim((string)($resp['tone'] ?? $resp['sentiment'] ?? 'neutral'))),
                                'created_at' => $resp['submitted_at'] ?? $r['created_at'] ?: date('Y-m-d H:i:s')
                            ];
                        }
                    }
                }
            }
        }
    }

    $allItems = array_merge($pcmsItems, $phmsItems);
    $totalCount = count($allItems);

    if ($totalCount === 0) {
        return [
            'success' => false,
            'message' => 'No new or unprocessed feedback entries found. All citizen responses are already locked and compiled.',
            'total_processed_count' => 0
        ];
    }

    // 3. Deduplicate and group by Category -> Consultation/Hearing Title
    $categoriesMap = [];
    $seenHashes = [];
    $dedupedCount = 0;

    foreach ($allItems as $item) {
        $hash = md5(strtolower(trim($item['title'] . '|' . $item['message'])));
        if (isset($seenHashes[$hash])) {
            continue; // Skip exact duplicate feedback text
        }
        $seenHashes[$hash] = true;
        $dedupedCount++;

        $catName = resolveUnifiedCategory($item['title'], $item['raw_category'], $item['message']);

        if (!isset($categoriesMap[$catName])) {
            $categoriesMap[$catName] = [
                'category' => $catName,
                'total_entries' => 0,
                'consultations' => []
            ];
        }

        $categoriesMap[$catName]['total_entries']++;

        $titleKey = $item['title'];
        if (!isset($categoriesMap[$catName]['consultations'][$titleKey])) {
            $categoriesMap[$catName]['consultations'][$titleKey] = [
                'title' => $titleKey,
                'source' => $item['source'],
                'date' => date('Y-m-d', strtotime($item['created_at'])),
                'entries_count' => 0,
                'rating_sum' => 0.0,
                'sentiments' => ['positive' => 0, 'neutral' => 0, 'negative' => 0],
                'messages' => []
            ];
        }

        $categoriesMap[$catName]['consultations'][$titleKey]['entries_count']++;
        $categoriesMap[$catName]['consultations'][$titleKey]['rating_sum'] += $item['rating'];
        
        $sTag = in_array($item['sentiment'], ['positive', 'negative', 'neutral']) ? $item['sentiment'] : 'neutral';
        $categoriesMap[$catName]['consultations'][$titleKey]['sentiments'][$sTag]++;
        
        if (count($categoriesMap[$catName]['consultations'][$titleKey]['messages']) < 6) {
            $categoriesMap[$catName]['consultations'][$titleKey]['messages'][] = $item['message'];
        }
    }

    // 4. Finalize category insights & rating averages
    $formattedCategories = [];
    foreach ($categoriesMap as $catName => $catData) {
        $cList = [];
        foreach ($catData['consultations'] as $tKey => $cData) {
            $avgRating = $cData['entries_count'] > 0 ? round($cData['rating_sum'] / $cData['entries_count'], 1) : 4.0;
            
            // Determine dominant sentiment
            $s = $cData['sentiments'];
            $dominant = 'neutral';
            if ($s['negative'] > $s['positive'] && $s['negative'] >= $s['neutral']) $dominant = 'negative';
            elseif ($s['positive'] > $s['negative'] && $s['positive'] >= $s['neutral']) $dominant = 'positive';

            $cList[] = [
                'title' => $cData['title'],
                'source' => $cData['source'],
                'date' => $cData['date'],
                'entries_count' => $cData['entries_count'],
                'avg_rating' => $avgRating,
                'dominant_sentiment' => $dominant,
                'summarized_insights' => array_values(array_unique($cData['messages']))
            ];
        }

        $formattedCategories[] = [
            'category_name' => $catName,
            'total_entries' => $catData['total_entries'],
            'consultations' => $cList
        ];
    }

    // 5. Generate Unique Merge ID and Timestamps
    $mergeId = 'UNIFIED-MERGE-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    $now = date('Y-m-d H:i:s');

    // 6. Generate PDF Document
    require_once __DIR__ . '/generate_unified_feedback_pdf.php';
    $pdfResult = generateUnifiedFeedbackPdfDoc($formattedCategories, $mergeId, $now, $userName);

    if (!$pdfResult['success']) {
        return [
            'success' => false,
            'message' => 'PDF generation failed: ' . $pdfResult['message']
        ];
    }

    // 7. Lock Entries in Database Transaction
    $conn->begin_transaction();
    try {
        // Tag PCMS feedback rows as processed
        $pcmsIds = array_column($pcmsItems, 'id');
        if (!empty($pcmsIds)) {
            $idListStr = implode(',', array_map('intval', $pcmsIds));
            $conn->query("UPDATE feedback SET is_processed = 1, merge_id = '$mergeId', processed_at = '$now' WHERE id IN ($idListStr)");
        }

        // Tag PHMS hearing_queue rows as processed
        $phmsIds = array_column($phmsItems, 'id');
        if (!empty($phmsIds)) {
            $idListHqStr = implode(',', array_map('intval', array_unique($phmsIds)));
            $conn->query("UPDATE hearing_queue SET is_processed = 1, merge_id = '$mergeId', processed_at = '$now' WHERE id IN ($idListHqStr)");
        }

        // Record compilation entry
        $catJson = json_encode($formattedCategories);
        $insStmt = $conn->prepare("
            INSERT INTO unified_feedback_compilations (merge_id, total_feedback_count, categories_summary_json, pdf_filename, pdf_path, compiled_by, compiled_by_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insStmt->bind_param('sississs', $mergeId, $totalCount, $catJson, $pdfResult['pdf_filename'], $pdfResult['pdf_path'], $userId, $userName, $now);
        $insStmt->execute();
        $insStmt->close();

        // Audit Log
        if (function_exists('logAuditEvent')) {
            logAuditEvent('UNIFIED_FEEDBACK_LOCKED', "Compiled and locked $totalCount citizen feedback entries into unified PDF summary ($mergeId).", $userId, $userName);
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Database data lock failed: ' . $e->getMessage()
        ];
    }

    return [
        'success' => true,
        'merge_id' => $mergeId,
        'total_processed_count' => $totalCount,
        'deduped_count' => $dedupedCount,
        'categories_count' => count($formattedCategories),
        'categories' => $formattedCategories,
        'pdf_filename' => $pdfResult['pdf_filename'],
        'pdf_url' => $pdfResult['pdf_url'],
        'pdf_path' => $pdfResult['pdf_path'],
        'created_at' => $now
    ];
}
