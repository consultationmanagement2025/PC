<?php

function normalizeAiRoutingText($value) {
    return mb_strtolower(trim((string)$value), 'UTF-8');
}

function classifyConsultationRequest($title, $description, $category = '', $userEmail = '') {
    $combinedText = trim(implode(' ', array_filter([
        $title,
        $description,
        $category,
    ], static function ($value) {
        return $value !== null && $value !== '';
    })));

    $normalized = normalizeAiRoutingText($combinedText);

    $serviceKeywords = [
        'repair', 'repairing', 'broken', 'damaged', 'complaint', 'complain', 'leak', 'drainage',
        'streetlight', 'road', 'pothole', 'garbage', 'trash', 'water', 'sewer', 'noise', 'hazard',
        'safety', 'illegal', 'flood', 'power', 'electric', 'waste', 'construction', 'eviction',
        'rent', 'relocation', 'housing', 'house', 'home', 'lot'
    ];

    $healthKeywords = [
        'health', 'clinic', 'hospital', 'medicine', 'doctor', 'vaccination', 'sanitation',
        'dengue', 'covid', 'medical', 'ambulance', 'pregnancy'
    ];

    $housingKeywords = [
        'housing', 'house', 'home', 'lot', 'eviction', 'relocation', 'rent', 'apartment', 'tenement', 'squatter'
    ];

    $legalKeywords = [
        'legal', 'law', 'rights', 'permit', 'contract', 'court', 'police', 'case', 'lawyer', 'ordinance',
        'violation', 'counsel', 'complaint'
    ];

    $consultationKeywords = [
        'consultation', 'feedback', 'opinion', 'suggestion', 'proposal', 'policy', 'program',
        'project', 'ordinance', 'advisory', 'community', 'plan', 'public input', 'concern', 'issue',
        'thought', 'recommendation', 'input', 'review', 'voice'
    ];

    $matchedService = array_values(array_filter($serviceKeywords, static function ($keyword) use ($normalized) {
        return strpos($normalized, $keyword) !== false;
    }));

    $matchedHealth = array_values(array_filter($healthKeywords, static function ($keyword) use ($normalized) {
        return strpos($normalized, $keyword) !== false;
    }));

    $matchedHousing = array_values(array_filter($housingKeywords, static function ($keyword) use ($normalized) {
        return strpos($normalized, $keyword) !== false;
    }));

    $matchedLegal = array_values(array_filter($legalKeywords, static function ($keyword) use ($normalized) {
        return strpos($normalized, $keyword) !== false;
    }));

    $matchedConsultation = array_values(array_filter($consultationKeywords, static function ($keyword) use ($normalized) {
        return strpos($normalized, $keyword) !== false;
    }));

    $serviceScore = count($matchedService);
    $consultationScore = count($matchedConsultation);
    $healthScore = count($matchedHealth);
    $housingScore = count($matchedHousing);
    $legalScore = count($matchedLegal);

    $classification = 'consultation';
    $department = 'General';
    $confidence = 0.45;
    $reason = 'No strong routing signal detected. Defaulted to general consultation review.';

    if ($serviceScore > 0 && $consultationScore === 0) {
        $classification = 'service_request';
        $department = 'Public Services';
        $confidence = min(0.95, 0.7 + ($serviceScore * 0.05));
        $reason = 'Matched service request keywords: ' . implode(', ', array_slice($matchedService, 0, 5));
    } elseif ($healthScore > 0) {
        $classification = 'consultation';
        $department = 'Health';
        $confidence = min(0.95, 0.65 + ($healthScore * 0.05));
        $reason = 'Matched health-related keywords: ' . implode(', ', array_slice($matchedHealth, 0, 5));
    } elseif ($housingScore > 0) {
        $classification = 'consultation';
        $department = 'Housing';
        $confidence = min(0.95, 0.65 + ($housingScore * 0.05));
        $reason = 'Matched housing-related keywords: ' . implode(', ', array_slice($matchedHousing, 0, 5));
    } elseif ($legalScore > 0) {
        $classification = 'consultation';
        $department = 'Legal';
        $confidence = min(0.95, 0.65 + ($legalScore * 0.05));
        $reason = 'Matched legal-related keywords: ' . implode(', ', array_slice($matchedLegal, 0, 5));
    } elseif ($consultationScore > 0) {
        $classification = 'consultation';
        $department = 'General';
        $confidence = min(0.95, 0.6 + ($consultationScore * 0.05));
        $reason = 'Matched consultation-style keywords: ' . implode(', ', array_slice($matchedConsultation, 0, 5));
    }

    return [
        'classification' => $classification,
        'department' => $department,
        'confidence' => round($confidence, 2),
        'reason' => $reason,
        'is_consultation' => $classification === 'consultation',
        'user_email' => trim((string)$userEmail),
    ];
}

function buildNonConsultationEmailBody($title, $description, $department, $reason) {
    $body = "Hello,\n\n";
    $body .= "Thank you for submitting your request through the Valenzuela Public Consultation Management System.\n";
    $body .= "Our AI review indicates that this submission was not classified as a public consultation request.\n\n";
    $body .= "Submitted topic: " . ($title ?: 'Not provided') . "\n";
    $body .= "Suggested routing: " . ($department ?: 'General Review') . "\n";
    $body .= "Reason: " . ($reason ?: 'No strong consultation pattern detected') . "\n\n";
    $body .= "Please note that this submission may need to be redirected to the appropriate office or service channel.\n";
    $body .= "Thank you,\n";
    $body .= "Valenzuela City Government";

    return $body;
}

function sendAiRoutingNotification($toEmail, $title, $description, $department, $reason) {
    $email = trim((string)$toEmail);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = 'Submission Review Notice - Valenzuela PCMS';
    $body = buildNonConsultationEmailBody($title, $description, $department, $reason);
    $headers = "From: noreply@valenzuelacity.gov\r\nContent-Type: text/plain; charset=UTF-8";

    return @mail($email, $subject, $body, $headers);
}
