<?php
/**
 * Create Sample Consultations
 * This script creates sample consultation data for demonstration
 */

require_once 'db.php';
require_once 'DATABASE/consultations.php';

// Initialize the consultations table
initializeConsultationsTable();

// Sample consultation data
$samples = [
    [
        'title' => 'Traffic Management Ordinance Proposal',
        'description' => 'We are proposing a new traffic management ordinance to address congestion in major areas of Valenzuela City. This proposal includes new traffic patterns, designated bus lanes, and revised parking regulations. Your input is crucial in shaping a better transportation system for our city.',
        'category' => 'Transportation',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
        'status' => 'active'
    ],
    [
        'title' => 'Environmental Protection and Waste Management',
        'description' => 'Valenzuela City is committed to environmental protection. We are seeking public consultation on our new waste management policies, including segregation requirements, recycling programs, and environmental compliance standards. Share your thoughts on how we can make our city greener.',
        'category' => 'Environment',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+35 days')),
        'status' => 'active'
    ],
    [
        'title' => 'Public Market Development and Modernization',
        'description' => 'We are planning to modernize the public markets in Valenzuela City to improve vendor spaces, customer experience, and overall infrastructure. This project aims to support local businesses while providing better services to residents. We welcome your suggestions and feedback on this important project.',
        'category' => 'Infrastructure',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+25 days')),
        'status' => 'active'
    ],
    [
        'title' => 'Youth Development and Educational Programs',
        'description' => 'Valenzuela City is investing in youth development through educational programs, skills training, and mentorship opportunities. We are seeking input from youth, parents, educators, and community members on what programs would be most beneficial for our young people.',
        'category' => 'Social',
        'start_date' => date('Y-m-d H:i:s', strtotime('-10 days')),
        'end_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'status' => 'closed'
    ],
    [
        'title' => 'Anti-Illegal Dumping Ordinance',
        'description' => 'To combat illegal dumping in our city, we are implementing stricter regulations and monitoring systems. This ordinance includes penalties and community clean-up programs. Your support in this environmental initiative is essential.',
        'category' => 'Environment',
        'start_date' => date('Y-m-d H:i:s', strtotime('-20 days')),
        'end_date' => date('Y-m-d H:i:s', strtotime('-15 days')),
        'status' => 'closed'
    ],
    [
        'title' => 'Street Lighting Enhancement Program',
        'description' => 'We are upgrading street lighting in residential areas to improve safety and visibility. This program includes LED installations, better maintenance schedules, and community-reported issue responses. Tell us which areas need the most attention.',
        'category' => 'Infrastructure',
        'start_date' => date('Y-m-d H:i:s', strtotime('-12 days')),
        'end_date' => date('Y-m-d H:i:s', strtotime('-8 days')),
        'status' => 'closed'
    ]
];

// Insert samples
$inserted = 0;
$errors = [];

foreach ($samples as $sample) {
    $stmt = $conn->prepare("
        INSERT INTO consultations 
        (title, description, category, start_date, end_date, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    if (!$stmt) {
        $errors[] = "Prepare failed: " . $conn->error;
        continue;
    }
    
    $stmt->bind_param(
        "ssssss",
        $sample['title'],
        $sample['description'],
        $sample['category'],
        $sample['start_date'],
        $sample['end_date'],
        $sample['status']
    );
    
    if ($stmt->execute()) {
        $inserted++;
    } else {
        $errors[] = "Insert failed for '{$sample['title']}': " . $stmt->error;
    }
    
    $stmt->close();
}

$conn->close();

// Output result
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sample Data Creation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        a { color: #007bff; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Sample Data Creation</h1>
    
    <?php if ($inserted > 0): ?>
        <div class="success">
            <strong>✓ Success!</strong> Created <?php echo $inserted; ?> sample consultations.
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="error">
            <strong>⚠ Errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <p>
        <a href="public-portal.php">← Back to Public Portal</a> | 
        <a href="index.php">← Back to Home</a>
    </p>
</body>
</html>
