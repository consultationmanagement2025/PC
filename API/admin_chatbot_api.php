<?php
/**
 * Admin AI Assistant API
 * Helps LGU officials navigate the admin system, understand features,
 * and get guidance on managing consultations, feedback, documents, and users.
 */
header('Content-Type: application/json');
session_start();

// Require admin login
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin' && $current_role !== 'administrator' && $current_role !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');

if ($userMessage === '') {
    echo json_encode(['success' => false, 'message' => 'No message provided']);
    exit;
}

$msgLower = mb_strtolower($userMessage, 'UTF-8');
$msgNorm = preg_replace('/[^\w\s]/u', ' ', $msgLower);
$msgNorm = preg_replace('/\s+/', ' ', trim($msgNorm));
$msgWords = explode(' ', $msgNorm);

function adminStem($word) {
    $word = mb_strtolower($word, 'UTF-8');
    $suffixes = ['tion', 'sion', 'ment', 'ness', 'ing', 'ied', 'ies', 'ed', 'er', 'ly', 's'];
    foreach ($suffixes as $s) {
        if (mb_strlen($word) > mb_strlen($s) + 3 && mb_substr($word, -mb_strlen($s)) === $s) {
            return mb_substr($word, 0, -mb_strlen($s));
        }
    }
    return $word;
}

$msgStems = array_map('adminStem', $msgWords);

function adminKeywordMatch($keyword, $msgWords, $msgStems, $msgNorm) {
    $kwLower = mb_strtolower($keyword, 'UTF-8');
    if (mb_strpos($msgNorm, $kwLower) !== false) return 2.0;
    $kwParts = explode(' ', $kwLower);
    if (count($kwParts) > 1) {
        $allFound = true;
        foreach ($kwParts as $p) {
            if (!in_array($p, $msgWords) && !in_array(adminStem($p), $msgStems)) {
                $allFound = false;
                break;
            }
        }
        return $allFound ? 1.5 : 0;
    }
    if (in_array($kwLower, $msgWords)) return 1.0;
    if (in_array(adminStem($kwLower), $msgStems)) return 0.7;
    if (mb_strlen($kwLower) >= 4) {
        foreach ($msgWords as $w) {
            if (mb_strlen($w) >= 4 && (mb_strpos($w, $kwLower) !== false || mb_strpos($kwLower, $w) !== false)) {
                return 0.5;
            }
        }
    }
    return 0;
}

$intents = [
    // ─── DASHBOARD ───
    [
        'keywords' => ['dashboard' => 3, 'overview' => 2.5, 'home' => 2, 'main page' => 3, 'statistics' => 2, 'stats' => 2, 'summary' => 2],
        'negative' => [],
        'response' => "The **Dashboard** is your main overview page. It shows:\n\n• **Total Consultations** — all submitted consultations\n• **Active Consultations** — currently open for public input\n• **Total Feedback** — all feedback received\n• **Recent Activity** — latest submissions and actions\n• **Quick Actions** — shortcuts to common tasks\n\nClick **Dashboard** in the sidebar to return to it anytime.",
        'priority' => 5
    ],
    // ─── MANAGE CONSULTATIONS ───
    [
        'keywords' => ['consultation' => 2, 'manage consultation' => 3, 'view consultation' => 3, 'edit consultation' => 3, 'approve' => 2.5, 'reject' => 2, 'review consultation' => 3, 'consultation list' => 3, 'public consultation' => 2.5],
        'negative' => ['submit', 'feedback', 'document'],
        'response' => "To manage consultations:\n\n1. Go to **Public Consultation** in the sidebar\n2. You'll see all submitted consultations in a table\n3. Click on any consultation to view full details\n4. Use the **status dropdown** to change status (Draft → Active → Closed)\n5. You can **edit** consultation details or **delete** if needed\n\n**Statuses:**\n• **Draft** — Not yet visible to the public\n• **Active** — Open for public viewing and feedback\n• **Closed** — No longer accepting feedback\n\nCitizens submit consultations through the public portal, and they appear here for your review.",
        'priority' => 7
    ],
    // ─── MANAGE FEEDBACK ───
    [
        'keywords' => ['feedback' => 2.5, 'manage feedback' => 3, 'view feedback' => 3, 'feedback list' => 3, 'citizen feedback' => 3, 'review feedback' => 3, 'respond feedback' => 3],
        'negative' => ['submit', 'consultation', 'document'],
        'response' => "To manage feedback:\n\n1. Go to **Feedback Collection** in the sidebar\n2. View all feedback in the table with filters for status, type, and date\n3. Click any feedback to see full details including attachments\n4. Update the **status** (New → Under Review → Responded → Resolved)\n5. Use **Sentiment Analysis** to automatically tag feedback as Positive/Negative/Neutral\n\n**Sentiment Analysis:**\n• Click the brain icon on individual feedback to analyze it\n• Use **Run Batch Analysis** to analyze all untagged feedback at once\n• Sentiment tags help you prioritize responses",
        'priority' => 7
    ],
    // ─── SENTIMENT ANALYSIS ───
    [
        'keywords' => ['sentiment' => 3, 'sentiment analysis' => 3, 'analyze' => 2, 'positive' => 1.5, 'negative' => 1.5, 'neutral' => 1.5, 'emotion' => 2, 'tone' => 2],
        'negative' => [],
        'response' => "**Sentiment Analysis** automatically classifies feedback as Positive, Negative, or Neutral.\n\n**How to use it:**\n1. Go to **Feedback Collection**\n2. For individual feedback: click the **brain icon** (🧠) in the Actions column\n3. For bulk analysis: click **Run Batch Analysis** button at the top\n\n**How it works:**\n• The system analyzes keywords, phrases, and context in the feedback message\n• It assigns a score from -1 (very negative) to +1 (very positive)\n• Results are saved and shown as colored badges in the table\n\nThis helps you quickly identify urgent concerns vs. positive feedback.",
        'priority' => 8
    ],
    // ─── DOCUMENTS ───
    [
        'keywords' => ['document' => 2.5, 'upload' => 2.5, 'file' => 2, 'document management' => 3, 'upload document' => 3, 'download' => 2, 'attachment' => 2],
        'negative' => ['feedback attachment'],
        'response' => "**Document Management** lets you store and organize files:\n\n**To upload a document:**\n1. Go to **Document Management** in the sidebar\n2. Click **Upload Document**\n3. Select the file, add a title and category\n4. Click Upload\n\n**Features:**\n• Search documents by name or category\n• Filter by document type\n• Download or delete documents\n• Supported formats: PDF, Word, Excel, images, and more\n\nYou can also upload via the **Quick Actions** on the Dashboard.",
        'priority' => 6
    ],
    // ─── USER MANAGEMENT ───
    [
        'keywords' => ['user' => 2, 'user management' => 3, 'citizen' => 2, 'submitter' => 2.5, 'staff account' => 3, 'manage user' => 3, 'add user' => 3, 'create account' => 3],
        'negative' => [],
        'response' => "**User Management** has two tabs:\n\n**1. Citizen Submitters Tab:**\n• Shows all citizens who have submitted consultations or feedback\n• Displays their name, email, submission counts, and last activity\n• Data is automatically collected from consultation and feedback submissions\n• Search and sort by name, email, or activity\n\n**2. Staff Accounts Tab:**\n• Manage LGU official login accounts\n• Add new staff accounts with roles (Admin, Staff, Viewer)\n• Edit, activate/deactivate, or delete accounts\n• Reset passwords\n\nGo to **Administration → User Management** in the sidebar.",
        'priority' => 7
    ],
    // ─── AUDIT LOG ───
    [
        'keywords' => ['audit' => 3, 'audit log' => 3, 'activity log' => 3, 'log' => 2, 'history' => 2, 'track' => 2, 'who did' => 2.5],
        'negative' => [],
        'response' => "The **Audit Log** tracks all system activities:\n\n• **Who** performed the action\n• **What** action was taken (create, edit, delete, login, etc.)\n• **When** it happened\n• **Details** of the change\n\nGo to **Administration → Audit Log** in the sidebar.\n\nThis helps maintain accountability and track changes made by staff members.",
        'priority' => 5
    ],
    // ─── ANALYTICS / REPORTS ───
    [
        'keywords' => ['analytics' => 3, 'report' => 2.5, 'chart' => 2, 'graph' => 2, 'statistics' => 2, 'data' => 1.5, 'trend' => 2, 'insight' => 2],
        'negative' => [],
        'response' => "**Analytics & Reports** provides visual insights:\n\n• **Consultation trends** — submissions over time\n• **Feedback statistics** — breakdown by type, sentiment, and status\n• **Category distribution** — which topics get the most consultations\n• **Response times** — how quickly feedback is addressed\n\nGo to **Analytics** in the sidebar to view charts and reports.\n\nYou can also see quick stats on the **Dashboard**.",
        'priority' => 6
    ],
    // ─── DARK MODE ───
    [
        'keywords' => ['dark mode' => 3, 'dark theme' => 3, 'light mode' => 3, 'theme' => 2, 'night mode' => 3],
        'negative' => [],
        'response' => "To toggle **dark mode**:\n\n• Click the **moon/sun icon** (🌙) in the top-right corner of the admin panel\n• Your preference is saved automatically\n• All pages will use the dark theme\n\nDark mode reduces eye strain during extended use.",
        'priority' => 4
    ],
    // ─── NOTIFICATIONS ───
    [
        'keywords' => ['notification' => 3, 'alert' => 2.5, 'bell' => 2, 'new submission' => 3, 'notify' => 2],
        'negative' => [],
        'response' => "**Notifications** alert you about new activity:\n\n• **New consultations** submitted by citizens\n• **New feedback** received\n• **System alerts** and updates\n\nClick the **bell icon** 🔔 in the top-right corner to view notifications.\n\nNotifications are generated automatically when citizens submit consultations or feedback through the public portal.",
        'priority' => 5
    ],
    // ─── HOW TO RESPOND TO CONSULTATION ───
    [
        'keywords' => ['respond' => 2.5, 'reply' => 2.5, 'answer' => 2, 'respond to consultation' => 3, 'reply to citizen' => 3, 'how to respond' => 3],
        'negative' => [],
        'response' => "To respond to a consultation:\n\n1. Go to **Public Consultation** in the sidebar\n2. Find the consultation and click to view details\n3. Change the status to **Active** to make it visible to the public\n4. If the citizen provided an email and opted in, they'll receive updates\n5. You can add notes or update the consultation details\n\nFor feedback responses:\n1. Go to **Feedback Collection**\n2. Click on the feedback item\n3. Update the status to **Responded** or **Resolved**\n4. The citizen will be notified if they opted into email updates.",
        'priority' => 8
    ],
    // ─── SEARCH ───
    [
        'keywords' => ['search' => 2.5, 'find' => 2, 'look for' => 2.5, 'filter' => 2, 'advanced search' => 3],
        'negative' => [],
        'response' => "**Search & Filter** options are available throughout the system:\n\n• **Dashboard** — Quick Actions → Advanced Search\n• **Consultations** — Search by topic, name, status, date\n• **Feedback** — Filter by type, status, sentiment, date\n• **Documents** — Search by name, category\n• **Users** — Search by name, email; filter by role/status\n\nMost tables have a search bar at the top and filter dropdowns for quick filtering.",
        'priority' => 5
    ],
    // ─── PUBLIC PORTAL ───
    [
        'keywords' => ['public portal' => 3, 'citizen portal' => 3, 'public side' => 3, 'citizen side' => 3, 'portal' => 2],
        'negative' => ['admin'],
        'response' => "The **Public Portal** is the citizen-facing side of the system:\n\n• Citizens can **view active consultations**\n• **Submit new consultation requests**\n• **Submit feedback** on consultations\n• **Edit their submissions** within 7 days using a secure link\n• Use the **AI chatbot** for help\n• Switch between **English and Tagalog**\n• Toggle **dark/light mode**\n\nAccess it at: **public-portal.php**\n\nEverything citizens submit appears in your admin panel for review.",
        'priority' => 6
    ],
    // ─── HELP / WHAT CAN YOU DO ───
    [
        'keywords' => ['help' => 2, 'what can you do' => 3, 'assist' => 2, 'guide' => 2, 'menu' => 2, 'options' => 2],
        'negative' => [],
        'response' => "I'm your **Admin AI Assistant**! I can help you with:\n\n• **Dashboard** — Understanding your overview stats\n• **Consultations** — Managing citizen consultation requests\n• **Feedback** — Reviewing and responding to feedback\n• **Sentiment Analysis** — Analyzing feedback tone\n• **Documents** — Uploading and managing files\n• **User Management** — Viewing citizens and managing staff\n• **Audit Log** — Tracking system activities\n• **Analytics** — Viewing reports and trends\n• **Notifications** — Understanding alerts\n• **Dark Mode** — Toggling themes\n• **Public Portal** — How the citizen side works\n• **Security** — Best practices\n\nJust ask me anything about the system!",
        'priority' => 3
    ],
    // ─── GREETINGS ───
    [
        'keywords' => ['hello' => 2, 'hi' => 2, 'hey' => 2, 'good morning' => 3, 'good afternoon' => 3, 'kumusta' => 3],
        'negative' => [],
        'response' => "Hello! 👋 I'm your Admin AI Assistant for the Public Consultation Management Portal.\n\nI can help you navigate the system, manage consultations and feedback, understand features, and more.\n\nWhat would you like help with? Type **\"help\"** to see all topics!",
        'priority' => 2
    ],
    // ─── THANK YOU ───
    [
        'keywords' => ['thank' => 2.5, 'thanks' => 2.5, 'salamat' => 3, 'appreciate' => 2.5],
        'negative' => [],
        'response' => "You're welcome! Happy to help. If you have more questions about the system, just ask anytime. 😊",
        'priority' => 2
    ],
    // ─── SECURITY / BEST PRACTICES ───
    [
        'keywords' => ['security' => 3, 'password' => 2.5, 'secure' => 2, 'best practice' => 3, 'safety' => 2, 'protect' => 2],
        'negative' => [],
        'response' => "**Security Best Practices:**\n\n• Use **strong passwords** (12+ characters with mixed case, numbers, symbols)\n• **Never share** your login credentials\n• **Log out** when leaving your workstation\n• Review the **Audit Log** regularly for suspicious activity\n• Only grant **Admin** role to trusted officials\n• Use **Staff** or **Viewer** roles for limited access\n• Keep your browser updated\n\nThe system uses CSRF protection, password hashing, and session management for security.",
        'priority' => 5
    ],
    // ─── EXPORT / PRINT ───
    [
        'keywords' => ['export' => 3, 'print' => 2.5, 'download data' => 3, 'csv' => 2, 'excel' => 2, 'pdf' => 1.5],
        'negative' => [],
        'response' => "**Exporting Data:**\n\n• **Consultations** — You can view and print individual consultation details\n• **Feedback** — View details and print individual feedback items\n• **Documents** — Download uploaded documents directly\n• **Citizens** — The citizen submitters table shows all recorded emails and names\n\nFor bulk data export, you can access the database directly or use the consultation/feedback detail views for individual records.",
        'priority' => 5
    ],
    // ─── ROLES / PERMISSIONS ───
    [
        'keywords' => ['role' => 2.5, 'permission' => 2.5, 'access' => 2, 'admin role' => 3, 'staff role' => 3, 'viewer role' => 3, 'who can' => 2],
        'negative' => [],
        'response' => "**User Roles & Permissions:**\n\n• **Admin** — Full access to all features, can manage staff accounts, view audit logs, and configure the system\n• **Staff** — Can manage consultations, feedback, and documents, but cannot manage other accounts\n• **Viewer** — Read-only access to view data without making changes\n\nTo manage roles:\n1. Go to **User Management → Staff Accounts** tab\n2. Click **Edit** on any account\n3. Change the role from the dropdown\n4. Save changes",
        'priority' => 6
    ],
];

// ── Scoring Engine ──
$results = [];
foreach ($intents as $idx => $intent) {
    $score = 0;
    $matchedKeywords = 0;
    $totalWeight = 0;

    foreach ($intent['keywords'] as $kw => $weight) {
        $totalWeight += $weight;
        $matchVal = adminKeywordMatch($kw, $msgWords, $msgStems, $msgNorm);
        if ($matchVal > 0) {
            $score += $weight * $matchVal;
            $matchedKeywords++;
        }
    }

    foreach ($intent['negative'] as $neg) {
        if (mb_strpos($msgNorm, mb_strtolower($neg, 'UTF-8')) !== false) {
            $score *= 0.3;
        }
    }

    if ($totalWeight > 0) {
        $normalizedScore = $score / $totalWeight;
    } else {
        $normalizedScore = 0;
    }

    if ($matchedKeywords > 0 && $normalizedScore > 0) {
        if ($matchedKeywords >= 3) $normalizedScore += 0.15;
        elseif ($matchedKeywords >= 2) $normalizedScore += 0.08;
        $normalizedScore += ($intent['priority'] ?? 0) * 0.005;

        $results[] = [
            'index' => $idx,
            'score' => $normalizedScore,
            'matched' => $matchedKeywords,
            'response' => $intent['response']
        ];
    }
}

usort($results, function($a, $b) { return $b['score'] <=> $a['score']; });

$threshold = 0.15;

if (!empty($results) && $results[0]['score'] >= $threshold) {
    echo json_encode([
        'success' => true,
        'reply' => $results[0]['response'],
        'confidence' => round(min($results[0]['score'], 1.0), 2)
    ]);
} else {
    echo json_encode([
        'success' => true,
        'reply' => "I'm not sure about that specific topic. As your Admin AI Assistant, I can help with:\n\n• **Managing consultations** and feedback\n• **Sentiment analysis** on feedback\n• **Document management**\n• **User management** (citizens & staff)\n• **Audit logs** and analytics\n• **System navigation** and features\n• **Security** best practices\n\nTry rephrasing your question, or type **\"help\"** to see everything I can assist with.",
        'confidence' => 0
    ]);
}
