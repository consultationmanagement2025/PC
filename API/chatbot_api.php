<?php
/**
 * PCMP AI Chatbot API — Smart Intent Matching Engine
 * Uses weighted keywords, negative keywords, word stemming, and contextual scoring
 * to understand citizen questions and provide accurate answers.
 */
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');

if ($userMessage === '') {
    echo json_encode(['success' => false, 'message' => 'No message provided']);
    exit;
}

$msgLower = mb_strtolower($userMessage, 'UTF-8');
// Normalize: remove punctuation for matching
$msgNorm = preg_replace('/[^\w\s]/u', ' ', $msgLower);
$msgNorm = preg_replace('/\s+/', ' ', trim($msgNorm));
$msgWords = explode(' ', $msgNorm);

// ── Simple stemmer: strip common suffixes for fuzzy matching ──
function stem($word) {
    $word = mb_strtolower($word, 'UTF-8');
    // Remove common English suffixes
    $suffixes = ['tion', 'sion', 'ment', 'ness', 'ing', 'ied', 'ies', 'ed', 'er', 'ly', 's'];
    foreach ($suffixes as $s) {
        if (mb_strlen($word) > mb_strlen($s) + 3 && mb_substr($word, -mb_strlen($s)) === $s) {
            return mb_substr($word, 0, -mb_strlen($s));
        }
    }
    return $word;
}

$msgStems = array_map('stem', $msgWords);

// ── Check if a keyword (or its stem) appears in the user message ──
function keywordMatch($keyword, $msgWords, $msgStems, $msgNorm) {
    $kwLower = mb_strtolower($keyword, 'UTF-8');
    // Exact phrase match in normalized message
    if (mb_strpos($msgNorm, $kwLower) !== false) return 2.0;
    // Multi-word keyword: check if all words present
    $kwParts = explode(' ', $kwLower);
    if (count($kwParts) > 1) {
        $allFound = true;
        foreach ($kwParts as $p) {
            if (!in_array($p, $msgWords) && !in_array(stem($p), $msgStems)) {
                $allFound = false;
                break;
            }
        }
        if ($allFound) return 1.5;
        return 0;
    }
    // Single word: exact
    if (in_array($kwLower, $msgWords)) return 1.0;
    // Single word: stem match
    $kwStem = stem($kwLower);
    if (in_array($kwStem, $msgStems)) return 0.7;
    // Partial match (word contains keyword or vice versa, min 4 chars)
    if (mb_strlen($kwLower) >= 4) {
        foreach ($msgWords as $w) {
            if (mb_strlen($w) >= 4 && (mb_strpos($w, $kwLower) !== false || mb_strpos($kwLower, $w) !== false)) {
                return 0.5;
            }
        }
    }
    return 0;
}

// ── Knowledge Base ──
// Each intent has:
//   keywords: words that SHOULD be present (higher weight = more important)
//   negative: words that should NOT be present (helps distinguish similar intents)
//   response: the answer
//   priority: tiebreaker (higher = preferred when scores are close)

$intents = [
    // ─── EDIT / CHANGE SUBMISSION ───
    [
        'keywords' => ['edit' => 3, 'change' => 3, 'modify' => 3, 'update my' => 3, 'revise' => 3, 'correct' => 2, 'fix' => 2, 'amend' => 2, 'i-edit' => 3, 'baguhin' => 3, 'palitan' => 3, 'submitted' => 1.5, 'submission' => 1.5, 'already submitted' => 2, 'my consultation' => 1.5, 'my feedback' => 1.5, 'change my' => 3, 'edit my' => 3, 'wrong' => 1.5, 'mistake' => 2, 'typo' => 2],
        'negative' => ['how to submit', 'new', 'create', 'file'],
        'response' => "Yes, you can edit your submission! Here's how:\n\n• When you submit a consultation or feedback, you receive a **unique edit link**\n• This link is shown on-screen after submission and also sent to your email\n• Click the edit link to open your submission and make changes\n• **Edit links are valid for 7 days** after submission\n\n⚠️ **Important:**\n• Save your edit link — it's the only way to access your submission for editing\n• Once your submission has been reviewed or responded to, it can no longer be edited\n• If you lost your edit link, check your email for the confirmation message\n\nIf you need further help, you can contact the city government directly.",
        'priority' => 10
    ],
    // ─── DELETE / WITHDRAW SUBMISSION ───
    [
        'keywords' => ['delete' => 3, 'remove' => 3, 'withdraw' => 3, 'cancel' => 2.5, 'take back' => 3, 'undo' => 2.5, 'burahin' => 3, 'tanggalin' => 3, 'iurong' => 3, 'cancel my' => 3],
        'negative' => [],
        'response' => "Currently, you cannot delete or withdraw a submission directly through the portal. However, you have these options:\n\n• **Edit your submission** using the edit link (valid for 7 days) to change its content\n• **Contact the city government** directly to request withdrawal\n• Submissions that are not yet reviewed can be modified via the edit link\n\nFor urgent withdrawal requests, please visit or contact the Valenzuela City Hall.",
        'priority' => 9
    ],
    // ─── SUBMIT CONSULTATION ───
    [
        'keywords' => ['submit' => 2, 'consultation' => 2, 'file' => 1.5, 'create' => 1.5, 'new consultation' => 3, 'how to submit' => 3, 'submit consultation' => 3, 'mag-submit' => 2, 'paano mag-submit' => 3, 'isumite' => 2, 'magsumite' => 2, 'consultation form' => 2.5, 'fill out' => 1.5, 'request' => 1],
        'negative' => ['edit', 'change', 'modify', 'update my', 'delete', 'feedback', 'status', 'track'],
        'response' => "To submit a consultation:\n\n1. Click **\"Submit Consultation\"** in the navigation bar above\n2. Fill in your personal details (name, age, gender, address, barangay)\n3. Enter your consultation topic and describe your concern in detail\n4. Provide your email address for updates\n5. Click **Submit**\n\nAfter submitting, you'll receive:\n• A **confirmation message** on screen\n• A **confirmation email** with your submission details\n• An **edit link** (valid for 7 days) in case you need to make changes",
        'priority' => 5
    ],
    // ─── SUBMIT FEEDBACK ───
    [
        'keywords' => ['feedback' => 2.5, 'submit feedback' => 3, 'give feedback' => 3, 'provide feedback' => 3, 'comment' => 1.5, 'rate' => 1.5, 'rating' => 1.5, 'review' => 1, 'opinion' => 1.5, 'mag-feedback' => 2, 'puna' => 2, 'feedback form' => 2.5],
        'negative' => ['edit', 'change', 'modify', 'delete', 'status', 'track', 'view'],
        'response' => "To submit feedback:\n\n1. Click **\"Submit Feedback\"** in the navigation bar\n2. First, verify your email address\n3. Fill in your name and select the feedback type\n4. Write your feedback message\n5. Optionally attach a file (images, PDF, documents)\n6. Click **Submit Feedback**\n\nAfter submitting, you'll receive a confirmation email with an **edit link** (valid for 7 days) in case you want to make changes.",
        'priority' => 5
    ],
    // ─── VIEW / BROWSE CONSULTATIONS ───
    [
        'keywords' => ['view' => 2, 'browse' => 2, 'see' => 1.5, 'look' => 1.5, 'active' => 2, 'ongoing' => 2, 'current' => 1.5, 'available' => 1.5, 'list' => 1.5, 'open' => 1, 'tingnan' => 2, 'makita' => 2, 'consultations' => 1.5],
        'negative' => ['submit', 'edit', 'feedback', 'delete', 'status', 'my'],
        'response' => "To view active consultations:\n\n1. Click **\"Active Consultations\"** in the navigation bar (it's the default page)\n2. Browse through the list of ongoing consultations\n3. Use the **search bar** to find specific topics\n4. Use **date filters** to narrow results\n5. Click on any consultation card to see full details\n\nEach consultation shows its topic, description, category, dates, and current status.",
        'priority' => 5
    ],
    // ─── STATUS / TRACKING ───
    [
        'keywords' => ['status' => 3, 'track' => 3, 'check' => 1.5, 'follow up' => 3, 'my submission' => 3, 'my consultation' => 2, 'where is' => 2, 'what happened' => 2, 'any update' => 2.5, 'how long' => 2, 'when will' => 2, 'waiting' => 2, 'replied' => 2, 'response' => 1.5, 'nasaan' => 2, 'ano na' => 2, 'update' => 1.5, 'progress' => 2],
        'negative' => ['edit', 'submit', 'new', 'create', 'how to', 'email update'],
        'response' => "To check on your submission:\n\n• **Check your email** — you should have received a confirmation email with your submission details\n• **Use your edit link** — the link sent to your email also lets you view your submission's current state\n• **Processing times** vary depending on the topic and complexity\n\nIf you haven't heard back:\n• Allow a few business days for review\n• For urgent concerns, contact the Valenzuela City Hall directly\n• You can also submit a follow-up through the portal",
        'priority' => 7
    ],
    // ─── EDIT LINK / LOST LINK ───
    [
        'keywords' => ['edit link' => 3, 'lost link' => 3, 'lost my link' => 3, 'where is my link' => 3, 'cant find link' => 3, 'link expired' => 3, 'expired' => 2, 'no link' => 2.5, 'didnt get link' => 3, 'nawala' => 2, 'link' => 1.5, 'how to edit' => 2.5],
        'negative' => ['submit', 'new', 'create'],
        'response' => "About edit links:\n\n• Your edit link was sent to your **email** when you submitted your consultation or feedback\n• It's also shown on-screen right after submission\n• Edit links are **valid for 7 days**\n\n**If you lost your edit link:**\n• Check your email inbox (and spam folder) for the confirmation email from the portal\n• The email subject contains \"Consultation Request Received\" or \"Feedback Received\"\n\n**If your link expired:**\n• Unfortunately, expired links cannot be renewed\n• You may contact the Valenzuela City Hall for assistance with changes",
        'priority' => 8
    ],
    // ─── EMAIL / VERIFICATION ───
    [
        'keywords' => ['email' => 1.5, 'verify' => 2.5, 'verification' => 2.5, 'verify email' => 3, 'email verification' => 3, 'otp' => 2, 'code' => 1.5, 'confirmation email' => 3, 'didnt receive email' => 3, 'no email' => 2.5, 'walang email' => 2.5, 'hindi dumating' => 2],
        'negative' => ['edit', 'submit consultation', 'contact', 'address'],
        'response' => "About email verification:\n\n• Before submitting feedback, you need to **verify your email address**\n• Click **\"Submit Feedback\"**, enter your email, and click **Send Email**\n• Check your inbox for a verification link and click it\n• Once verified, you can fill out and submit the feedback form\n\n**Didn't receive the email?**\n• Check your spam/junk folder\n• Make sure you entered the correct email address\n• Try again after a few minutes\n• Some email providers may delay delivery",
        'priority' => 6
    ],
    // ─── PRIVACY / DATA ───
    [
        'keywords' => ['privacy' => 3, 'data' => 2, 'personal data' => 3, 'data protection' => 3, 'safe' => 1.5, 'secure' => 2, 'security' => 2, 'confidential' => 2.5, 'privacy policy' => 3, 'data privacy' => 3, 'information safe' => 3, 'pribado' => 2, 'ligtas' => 2],
        'negative' => [],
        'response' => "Your privacy is important to us:\n\n• All data is handled under the **Data Privacy Act of 2012 (RA 10173)**\n• Personal information is used **only** for processing your consultation\n• Your email is only used for verification and sending updates you opted into\n• The system uses security measures to protect your data\n• You can review the full **Privacy Policy** at the bottom of the portal\n\nWe do not share your personal information with third parties.",
        'priority' => 5
    ],
    // ─── CONTACT / CITY HALL ───
    [
        'keywords' => ['contact' => 2.5, 'phone' => 2, 'city hall' => 3, 'office' => 2, 'address' => 2, 'visit' => 2, 'hotline' => 2.5, 'reach' => 2, 'talk to someone' => 3, 'human' => 2, 'agent' => 2, 'staff' => 1.5, 'person' => 1.5, 'tao' => 2, 'pumunta' => 2, 'saan' => 1],
        'negative' => ['email verification', 'verify'],
        'response' => "You can reach the Valenzuela City Government through:\n\n• **City Hall:** MacArthur Highway, Karuhatan, Valenzuela City\n• **Website:** www.valenzuela.gov.ph\n• **Facebook:** Valenzuela City Government (official page)\n\nFor consultation-related concerns, you can also use the **Submit Consultation** section of this portal for official processing.",
        'priority' => 5
    ],
    // ─── TOPICS / CATEGORIES ───
    [
        'keywords' => ['topic' => 2.5, 'category' => 2.5, 'what can i consult' => 3, 'types' => 2, 'what topics' => 3, 'consult about' => 3, 'issues' => 1.5, 'concerns' => 1.5, 'ano pwede' => 2, 'anong paksa' => 3],
        'negative' => [],
        'response' => "You can submit consultations on various topics:\n\n• **Infrastructure** — roads, buildings, public facilities\n• **Health** — public health, sanitation, medical services\n• **Education** — schools, educational programs\n• **Environment** — waste management, pollution, green spaces\n• **Social Services** — community programs, financial assistance\n• **Public Safety** — security, disaster preparedness\n• **Transportation** — traffic, public transit\n• **Housing** — housing concerns, land use\n• **Others** — any concern relevant to city governance\n\nChoose the most appropriate topic when filling out the form.",
        'priority' => 5
    ],
    // ─── BARANGAY ───
    [
        'keywords' => ['barangay' => 3, 'brgy' => 3, 'which barangay' => 3, 'barangay list' => 3, 'my barangay' => 2.5, 'what barangay' => 3],
        'negative' => [],
        'response' => "Valenzuela City has 33 barangays. When submitting a consultation, you can select your barangay from the dropdown list.\n\nSome barangays include: Bignay, Bagbaguin, Balangkas, Canumay, Caruhatan, Dalandanan, Gen. T. de Leon, Karuhatan, Malinta, Maysan, Marulas, Mapulang Lupa, Palasan, Parada, Poblacion, Veinte Reales, Wawang Pulo, and more.\n\nIf you're unsure which barangay you belong to, check your local address or contact the city hall.",
        'priority' => 4
    ],
    // ─── REQUIREMENTS ───
    [
        'keywords' => ['requirement' => 3, 'what do i need' => 3, 'need to bring' => 3, 'documents' => 2, 'prepare' => 2, 'kailangan' => 2, 'ano kailangan' => 3, 'what is needed' => 3, 'prerequisites' => 2],
        'negative' => [],
        'response' => "To submit a consultation, you'll need:\n\n• Your **full name**\n• **Age** and **gender** (optional)\n• **Address** and **barangay**\n• A clear **description** of your concern or topic\n• **Email address** — for receiving confirmation and edit link\n\n**No physical documents** are required for online submissions. Everything is done through the form on this portal!",
        'priority' => 5
    ],
    // ─── WHO CAN USE ───
    [
        'keywords' => ['who can' => 2.5, 'eligible' => 2.5, 'citizen' => 2, 'resident' => 2, 'can anyone' => 3, 'sino pwede' => 3, 'open to' => 2, 'allowed' => 2],
        'negative' => [],
        'response' => "The Public Consultation Portal is open to:\n\n• **All citizens and residents** of Valenzuela City\n• Anyone with a concern or feedback about city governance\n• No account or registration is required\n\nSimply visit the portal, fill out the form, and submit. It's free and open to everyone!",
        'priority' => 4
    ],
    // ─── ACCOUNT / LOGIN ───
    [
        'keywords' => ['login' => 2.5, 'sign up' => 2.5, 'register' => 2.5, 'account' => 2.5, 'create account' => 3, 'log in' => 3, 'password' => 2, 'sign in' => 3, 'mag-register' => 2],
        'negative' => [],
        'response' => "**No account or login is needed!** This portal is designed for easy, open access:\n\n• Submit consultations and feedback without creating an account\n• Just fill out the form with your name and email\n• Your email is only used for verification and sending you updates\n\nIt's that simple — no registration required!",
        'priority' => 5
    ],
    // ─── WHAT IS THIS / ABOUT ───
    [
        'keywords' => ['what is this' => 3, 'what is pcmp' => 3, 'what is the portal' => 3, 'about this' => 2.5, 'purpose' => 2.5, 'ano ito' => 3, 'para saan' => 3, 'what does this do' => 3, 'what is public consultation' => 3, 'explain' => 1.5],
        'negative' => [],
        'response' => "The **Public Consultation Management Portal (PCMP)** is an online platform by the Valenzuela City Government that allows citizens to:\n\n• **View** active public consultations on city policies and projects\n• **Submit** their own consultation requests on any city concern\n• **Provide feedback** on ongoing consultations\n• **Edit** their submissions within 7 days using a secure link\n\nIt helps the local government gather citizen input for better decision-making. No account needed — just visit and participate!",
        'priority' => 4
    ],
    // ─── ATTACHMENT / FILE UPLOAD ───
    [
        'keywords' => ['attach' => 3, 'upload' => 3, 'file' => 1.5, 'image' => 2, 'photo' => 2, 'document' => 2, 'pdf' => 2, 'picture' => 2, 'mag-upload' => 2, 'larawan' => 2],
        'negative' => ['file consultation', 'file a'],
        'response' => "You can attach files when submitting feedback:\n\n• **Allowed formats:** Images (JPG, PNG, GIF), PDF, Word documents, Excel files\n• **Maximum size:** 5MB per file\n• Attachments are optional — you can submit without one\n\nTo attach a file, look for the **\"Attach Image or Document\"** field in the feedback form and click to browse your files.",
        'priority' => 6
    ],
    // ─── LANGUAGE / TAGALOG ───
    [
        'keywords' => ['tagalog' => 3, 'filipino' => 3, 'language' => 2.5, 'english' => 2, 'translate' => 2.5, 'wika' => 3, 'salita' => 2, 'switch language' => 3],
        'negative' => [],
        'response' => "The portal supports both **English** and **Tagalog**!\n\nTo switch languages:\n• Look for the **\"EN\"** or **\"TL\"** button in the top-right corner of the page\n• Click it to toggle between English and Tagalog\n• Your language preference is saved automatically\n\nAll navigation, form labels, and headings will switch to your chosen language.",
        'priority' => 6
    ],
    // ─── DARK MODE ───
    [
        'keywords' => ['dark mode' => 3, 'dark theme' => 3, 'light mode' => 3, 'night mode' => 3, 'theme' => 2, 'brightness' => 2, 'dark' => 1.5, 'madilim' => 2],
        'negative' => [],
        'response' => "The portal has a **dark mode** option!\n\nTo toggle dark/light mode:\n• Look for the **moon/sun icon** (🌙/☀️) button in the top-right corner\n• Click it to switch between light and dark themes\n• Your preference is saved automatically\n\nDark mode is easier on the eyes, especially at night.",
        'priority' => 6
    ],
    // ─── HELP / MENU ───
    [
        'keywords' => ['help' => 2, 'what can you do' => 3, 'how can you help' => 3, 'menu' => 2, 'options' => 2, 'commands' => 2, 'assist' => 2, 'tulong' => 2, 'guide' => 2],
        'negative' => [],
        'response' => "Here's what I can help you with:\n\n• **Submit a Consultation** — How to file a consultation request\n• **Submit Feedback** — How to give feedback on consultations\n• **Edit Submission** — How to edit after submitting\n• **View Consultations** — Browse active consultations\n• **Track Status** — Check on your submission\n• **Topics** — What you can consult about\n• **Privacy & Security** — How your data is protected\n• **Requirements** — What you need to submit\n• **Email Verification** — How email verification works\n• **Attachments** — How to upload files\n• **Language** — Switch between English and Tagalog\n• **Dark Mode** — Toggle light/dark theme\n• **Contact** — Reach the Valenzuela City Government\n\nJust type your question naturally and I'll do my best to help!",
        'priority' => 3
    ],
    // ─── GREETINGS ───
    [
        'keywords' => ['hello' => 2, 'hi' => 2, 'hey' => 2, 'good morning' => 3, 'good afternoon' => 3, 'good evening' => 3, 'kumusta' => 3, 'magandang' => 2, 'musta' => 2],
        'negative' => [],
        'response' => "Hello! Welcome to the Valenzuela City Public Consultation Portal. 😊\n\nI can help you with submitting consultations, giving feedback, editing your submissions, navigating the portal, and more.\n\nHow can I assist you today? (Type **\"help\"** to see everything I can do!)",
        'priority' => 2
    ],
    // ─── THANK YOU ───
    [
        'keywords' => ['thank' => 2.5, 'thanks' => 2.5, 'salamat' => 3, 'appreciate' => 2.5, 'helpful' => 2, 'maraming salamat' => 3],
        'negative' => [],
        'response' => "You're welcome! I'm glad I could help. If you have any more questions about the portal, feel free to ask anytime. Have a great day! 😊",
        'priority' => 2
    ],
    // ─── GOODBYE ───
    [
        'keywords' => ['bye' => 2.5, 'goodbye' => 2.5, 'see you' => 3, 'paalam' => 3, 'sige' => 2, 'take care' => 2.5],
        'negative' => [],
        'response' => "Goodbye! Thank you for using the Valenzuela City Public Consultation Portal. Feel free to come back anytime. Have a wonderful day! 👋",
        'priority' => 2
    ],
    // ─── HOW DOES IT WORK / PROCESS ───
    [
        'keywords' => ['how does it work' => 3, 'process' => 2, 'how it works' => 3, 'step by step' => 3, 'procedure' => 2.5, 'paano gumagana' => 3, 'proseso' => 2, 'what happens' => 2.5, 'after submit' => 2.5, 'what happens after' => 3],
        'negative' => [],
        'response' => "Here's how the consultation process works:\n\n**1. Submit** — Fill out the consultation form with your details and concern\n**2. Confirmation** — You receive a confirmation email with an edit link\n**3. Review** — City officials review your submission\n**4. Processing** — Your concern is routed to the appropriate department\n**5. Response** — You may receive updates via email if you opted in\n\nYou can edit your submission within 7 days using the edit link provided. The entire process is online — no need to visit City Hall!",
        'priority' => 6
    ],
    // ─── COST / FREE ───
    [
        'keywords' => ['cost' => 3, 'fee' => 3, 'free' => 2.5, 'pay' => 2.5, 'charge' => 2.5, 'price' => 2.5, 'bayad' => 3, 'libre' => 3, 'magkano' => 3],
        'negative' => [],
        'response' => "The Public Consultation Portal is **completely free** to use!\n\n• No fees for submitting consultations\n• No fees for submitting feedback\n• No hidden charges\n\nIt's a free public service provided by the Valenzuela City Government to encourage citizen participation.",
        'priority' => 5
    ],
    // ─── DEADLINE / TIME LIMIT ───
    [
        'keywords' => ['deadline' => 3, 'time limit' => 3, 'how long' => 2, 'expir' => 2.5, 'when does' => 2, 'last day' => 2.5, 'hanggang kailan' => 3, 'until when' => 3],
        'negative' => ['how long processing', 'how long wait'],
        'response' => "Important time limits to know:\n\n• **Edit links** expire after **7 days** from submission\n• **Active consultations** have their own start and end dates (shown on each card)\n• **Feedback** can be submitted anytime while a consultation is active\n• There is **no deadline** for submitting new consultation requests — the portal is always open\n\nCheck each consultation's dates for specific deadlines.",
        'priority' => 5
    ],
    // ─── ERROR / PROBLEM / BUG ───
    [
        'keywords' => ['error' => 3, 'problem' => 2.5, 'bug' => 2.5, 'not working' => 3, 'broken' => 2.5, 'cant submit' => 3, 'failed' => 2.5, 'issue' => 2, 'hindi gumagana' => 3, 'may problema' => 3, 'stuck' => 2, 'loading' => 2, 'blank' => 2],
        'negative' => [],
        'response' => "If you're experiencing issues with the portal:\n\n• **Refresh the page** and try again\n• **Check your internet connection**\n• Make sure all **required fields** (marked with *) are filled in\n• Try using a different browser (Chrome, Firefox, Edge)\n• **Clear your browser cache** if the page looks broken\n\nIf the problem persists, you can:\n• Contact the Valenzuela City Government directly\n• Try again later — the system may be undergoing maintenance\n\nWe apologize for any inconvenience!",
        'priority' => 6
    ],
];

// ── Scoring Engine ──
$results = [];

foreach ($intents as $idx => $intent) {
    $score = 0;
    $matchedKeywords = 0;
    $totalWeight = 0;

    // Score positive keywords
    foreach ($intent['keywords'] as $kw => $weight) {
        $totalWeight += $weight;
        $matchVal = keywordMatch($kw, $msgWords, $msgStems, $msgNorm);
        if ($matchVal > 0) {
            $score += $weight * $matchVal;
            $matchedKeywords++;
        }
    }

    // Penalize if negative keywords are present
    foreach ($intent['negative'] as $neg) {
        $negLower = mb_strtolower($neg, 'UTF-8');
        if (mb_strpos($msgNorm, $negLower) !== false) {
            $score *= 0.3; // Heavy penalty
        }
    }

    // Normalize score
    if ($totalWeight > 0) {
        $normalizedScore = $score / $totalWeight;
    } else {
        $normalizedScore = 0;
    }

    // Require at least 1 keyword match
    if ($matchedKeywords > 0 && $normalizedScore > 0) {
        // Bonus for matching multiple keywords (shows understanding of context)
        if ($matchedKeywords >= 3) $normalizedScore += 0.15;
        elseif ($matchedKeywords >= 2) $normalizedScore += 0.08;

        // Add small priority bonus for tiebreaking
        $normalizedScore += ($intent['priority'] ?? 0) * 0.005;

        $results[] = [
            'index' => $idx,
            'score' => $normalizedScore,
            'matched' => $matchedKeywords,
            'response' => $intent['response']
        ];
    }
}

// Sort by score descending
usort($results, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

// ── Response ──
$threshold = 0.15;

if (!empty($results) && $results[0]['score'] >= $threshold) {
    $best = $results[0];
    echo json_encode([
        'success' => true,
        'reply' => $best['response'],
        'confidence' => round(min($best['score'], 1.0), 2)
    ]);
} else {
    // Off-topic — but try to suggest closest topic if we have a weak match
    $suggestion = '';
    if (!empty($results) && $results[0]['score'] > 0.05) {
        $suggestion = "\n\nDid you mean to ask about something related? Try typing **\"help\"** to see all topics I can assist with.";
    }

    echo json_encode([
        'success' => true,
        'reply' => "I'm sorry, I don't have information about that topic. I can only help with questions related to the **Valenzuela City Public Consultation Portal**, such as:\n\n• Submitting or editing consultations and feedback\n• Viewing active consultations\n• Tracking your submission status\n• Email verification and attachments\n• Privacy, requirements, and contact info\n• Portal features (dark mode, language)\n\nPlease try rephrasing your question, or type **\"help\"** to see everything I can assist with." . $suggestion,
        'confidence' => 0
    ]);
}
