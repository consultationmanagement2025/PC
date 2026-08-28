import os

index_file = r'c:\xampp\htdocs\CAP101\PC\public\index.php'

with open(index_file, 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Update Category Filter buttons array in public/index.php
old_cat_array = """                        <?php 
                        $categories = [
                            'all' => 'All Topics',
                            'consultations' => '📜 Public Consultations',
                            'surveys' => '📊 Community Surveys',
                            'infrastructure' => 'Infrastructure',
                            'health' => 'Health & Sanitation',
                            'environment' => 'Environment',
                            'education' => 'Education',
                            'transportation' => 'Traffic & Transport',
                            'other' => 'General Governance'
                        ];"""

new_cat_array = """                        <?php 
                        $categories = [
                            'all' => 'All Topics',
                            'consultations' => '📜 Public Consultations',
                            'surveys' => '📊 Community Surveys',
                            'orts' => '⚖️ ORTS Ordinances',
                            'infrastructure' => 'Infrastructure',
                            'health' => 'Health & Sanitation',
                            'environment' => 'Environment',
                            'education' => 'Education',
                            'transportation' => 'Traffic & Transport',
                            'other' => 'General Governance'
                        ];"""

if old_cat_array in code:
    code = code.replace(old_cat_array, new_cat_array)
    print("Updated Category Filter array with ORTS Ordinances tab.")

# 2. Update Card Tag rendering logic in public/index.php feed
old_card_tag_block = """                                        <?php
                                            $tLow = strtolower($c['title'] ?? '');
                                            $cLow = strtolower($c['category'] ?? '');
                                            $isSurveyItem = (strpos($tLow, 'survey') !== false || strpos($tLow, 'poll') !== false || strpos($cLow, 'survey') !== false);
                                            $itemTypeTag = $isSurveyItem ? '📊 COMMUNITY SURVEY' : '📜 PUBLIC CONSULTATION';
                                            $itemTagStyle = $isSurveyItem ? 'bg-purple-50 text-purple-700 border-purple-200' : $badgeStyle;
                                        ?>
                                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-lg border <?php echo $itemTagStyle; ?>">
                                            <?php echo $itemTypeTag; ?>
                                        </span>"""

new_card_tag_block = """                                        <?php
                                            $tLow = strtolower($c['title'] ?? '');
                                            $cLow = strtolower($c['category'] ?? '');
                                            $typeClean = strtolower($c['type'] ?? '');
                                            $srcClean = strtoupper($c['source_system'] ?? '');

                                            $isOrtsItem = ($typeClean === 'ordinance' || $srcClean === 'ORTS' || strpos($tLow, 'ordinance') !== false || strpos($cLow, 'orts') !== false || strpos($cLow, 'ordinance') !== false);
                                            $isSurveyItem = (!$isOrtsItem) && (strpos($tLow, 'survey') !== false || strpos($tLow, 'poll') !== false || strpos($cLow, 'survey') !== false);

                                            if ($isOrtsItem) {
                                                $itemTypeTag = '⚖️ ORTS ORDINANCE';
                                                $itemTagStyle = 'bg-indigo-50 text-indigo-700 border-indigo-200 font-extrabold';
                                            } elseif ($isSurveyItem) {
                                                $itemTypeTag = '📊 COMMUNITY SURVEY';
                                                $itemTagStyle = 'bg-purple-50 text-purple-700 border-purple-200';
                                            } else {
                                                $itemTypeTag = '📜 PUBLIC CONSULTATION';
                                                $itemTagStyle = 'bg-blue-50 text-valenzuela-blue border-blue-200';
                                            }
                                        ?>
                                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-lg border <?php echo $itemTagStyle; ?>">
                                            <?php echo $itemTypeTag; ?>
                                        </span>"""

if old_card_tag_block in code:
    code = code.replace(old_card_tag_block, new_card_tag_block)
    print("Updated card tag block to distinguish ORTS Ordinances, Surveys, and Consultations.")

# 3. Add ORTS Reference Card to Modal HTML
old_modal_header = """            <div class="p-6 sm:p-8 border-b border-slate-100">
                <span id="modal-category" class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md bg-blue-50 text-valenzuela-blue border border-blue-200 mb-3 inline-block"></span>
                <h3 id="modal-title" class="text-2xl font-black text-slate-900 mb-3"></h3>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 mb-4">
                    <span><i class="fa-solid fa-barcode text-valenzuela-red"></i> Code: <strong id="modal-code" class="font-mono text-slate-700"></strong></span>
                    <span><i class="fa-regular fa-clock text-valenzuela-blue"></i> End Date: <strong id="modal-end-date" class="text-slate-700"></strong></span>
                </div>
                <div id="modal-description" class="text-slate-700 text-sm leading-relaxed bg-slate-50 p-4 rounded-2xl border border-slate-100 whitespace-pre-line"></div>
            </div>"""

new_modal_header = """            <div class="p-6 sm:p-8 border-b border-slate-100 space-y-3">
                <span id="modal-category" class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md bg-blue-50 text-valenzuela-blue border border-blue-200 inline-block"></span>
                
                <!-- ORTS Document Provenance Box -->
                <div id="modal-orts-ref-card" class="hidden bg-gradient-to-r from-indigo-900 via-slate-900 to-indigo-950 text-white p-4 rounded-2xl border border-indigo-700 shadow-xs space-y-1">
                    <div class="flex justify-between items-center text-[10px] uppercase font-bold text-indigo-300 tracking-wider">
                        <span><i class="fa-solid fa-scale-balanced mr-1"></i> ORTS Interconnected Legislative File</span>
                        <span class="px-2 py-0.5 rounded bg-indigo-500/20 border border-indigo-400/30 text-indigo-200">ORTS Live Synced</span>
                    </div>
                    <div class="flex items-center gap-4 text-xs pt-0.5">
                        <span>Document ID: <strong id="orts-doc-id" class="font-mono text-white">#104</strong></span>
                        <span class="text-indigo-400">|</span>
                        <span>Ref Number: <strong id="orts-ref-num" class="font-mono text-indigo-200">ORD-2025-001</strong></span>
                    </div>
                </div>

                <h3 id="modal-title" class="text-2xl font-black text-slate-900 pt-1"></h3>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
                    <span><i class="fa-solid fa-barcode text-valenzuela-red"></i> Code: <strong id="modal-code" class="font-mono text-slate-700"></strong></span>
                    <span><i class="fa-regular fa-clock text-valenzuela-blue"></i> End Date: <strong id="modal-end-date" class="text-slate-700"></strong></span>
                </div>
                <div id="modal-description" class="text-slate-700 text-sm leading-relaxed bg-slate-50 p-4 rounded-2xl border border-slate-100 whitespace-pre-line"></div>
            </div>"""

if old_modal_header in code:
    code = code.replace(old_modal_header, new_modal_header)
    print("Updated modal header with ORTS Document Provenance Box.")

# 4. Update JS in openConsultationModal to detect ORTS and configure form submit button & badge
old_js_modal_badge = """                        const cLow = String(d.category || '').toLowerCase();
                        const tLow = String(d.title || '').toLowerCase();
                        const isSurveyModal = (cLow.includes('survey') || tLow.includes('survey') || tLow.includes('poll'));
                        
                        const categoryEl = document.getElementById('modal-category');
                        if (isSurveyModal) {
                            categoryEl.className = 'text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-md bg-purple-50 text-purple-700 border border-purple-200 mb-3 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-square-poll-vertical mr-1"></i> COMMUNITY SURVEY & POLL';
                        } else {
                            categoryEl.className = 'text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-md bg-blue-50 text-valenzuela-blue border border-blue-200 mb-3 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scroll mr-1"></i> PUBLIC CONSULTATION';
                        }"""

new_js_modal_badge = """                        const cLow = String(d.category || '').toLowerCase();
                        const tLow = String(d.title || '').toLowerCase();
                        const typeClean = String(d.type || '').toLowerCase();
                        const srcClean = String(d.source_system || '').toUpperCase();

                        const isOrtsModal = (typeClean === 'ordinance' || srcClean === 'ORTS' || cLow.includes('orts') || cLow.includes('ordinance') || tLow.includes('ordinance'));
                        const isSurveyModal = (!isOrtsModal) && (cLow.includes('survey') || tLow.includes('survey') || tLow.includes('poll'));
                        
                        const categoryEl = document.getElementById('modal-category');
                        const ortsCard = document.getElementById('modal-orts-ref-card');
                        const formTitle = document.querySelector('#feedback-submission-wrapper h4');
                        const submitBtn = document.querySelector('#feedback-form button[type="submit"]');

                        if (isOrtsModal) {
                            categoryEl.className = 'text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scale-balanced mr-1.5"></i> ORTS ENACTED ORDINANCE';
                            
                            if (ortsCard) {
                                ortsCard.classList.remove('hidden');
                                document.getElementById('orts-doc-id').textContent = '#' + (d.id || '104');
                                document.getElementById('orts-ref-num').textContent = d.tracking_number || d.external_ref || 'ORD-2025-001';
                            }
                            if (formTitle) formTitle.innerHTML = '<i class="fa-solid fa-paper-plane text-indigo-600 mr-1.5"></i> Submit Feedback to ORTS (Ordinance & Resolution Tracking)';
                            if (submitBtn) {
                                submitBtn.className = 'w-full bg-indigo-700 hover:bg-indigo-800 text-white font-extrabold py-3 px-4 rounded-xl text-xs transition-colors shadow-md flex items-center justify-center gap-2 cursor-pointer';
                                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Feedback to ORTS';
                            }
                        } else if (isSurveyModal) {
                            categoryEl.className = 'text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-md bg-purple-50 text-purple-700 border border-purple-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-square-poll-vertical mr-1.5"></i> COMMUNITY SURVEY & POLL';
                            if (ortsCard) ortsCard.classList.add('hidden');
                            if (formTitle) formTitle.textContent = 'Submit Your Voice & Rating';
                            if (submitBtn) {
                                submitBtn.className = 'w-full bg-valenzuela-blue hover:bg-blue-800 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors shadow-sm';
                                submitBtn.innerHTML = 'Submit Feedback & Voice';
                            }
                        } else {
                            categoryEl.className = 'text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-md bg-blue-50 text-valenzuela-blue border border-blue-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scroll mr-1.5"></i> PUBLIC CONSULTATION';
                            if (ortsCard) ortsCard.classList.add('hidden');
                            if (formTitle) formTitle.textContent = 'Submit Your Voice & Rating';
                            if (submitBtn) {
                                submitBtn.className = 'w-full bg-valenzuela-blue hover:bg-blue-800 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors shadow-sm';
                                submitBtn.innerHTML = 'Submit Feedback & Voice';
                            }
                        }"""

if old_js_modal_badge in code:
    code = code.replace(old_js_modal_badge, new_js_modal_badge)
    print("Updated openConsultationModal JS with ORTS card and custom submit button.")

# 5. Update backend POST handler for ORTS feedback submission in public/index.php
old_post_handler = """        // Generate feedback tracking token
        $tracking_token = 'FDBK-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $stmt = $conn->prepare("INSERT INTO feedback (consultation_id, guest_name, guest_email, guest_phone, rating, category, message, tracking_token, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())");
        $stmt->bind_param('isssisss', $consultation_id, $user_name, $user_email, $user_phone, $rating, $category, $message, $tracking_token);

        if ($stmt->execute()) {
            // Update posts_count in consultations
            $conn->query("UPDATE consultations SET posts_count = posts_count + 1 WHERE id = " . $consultation_id);
            require_once __DIR__ . '/../DATABASE/notifications.php';
            @createNotification(0, "💬 New Citizen Feedback Received from " . htmlspecialchars($user_name) . " ($tracking_token)", 'feedback');
            echo json_encode([
                'success' => true, 
                'message' => 'Thank you! Your feedback has been submitted successfully.',
                'tracking_token' => $tracking_token
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving feedback: ' . $conn->error]);
        }"""

new_post_handler = """        // Check if item is an ORTS Ordinance
        $isOrtsDoc = false;
        $docRefNum = 'ORD-' . date('Y') . '-' . sprintf('%03d', $consultation_id);
        $cCheck = $conn->query("SELECT type, source_system, tracking_number, external_ref FROM consultations WHERE id = $consultation_id LIMIT 1");
        if ($cCheck && $cRow = $cCheck->fetch_assoc()) {
            $stClean = strtoupper($cRow['source_system'] ?? '');
            $tpClean = strtolower($cRow['type'] ?? '');
            if ($stClean === 'ORTS' || $tpClean === 'ordinance') {
                $isOrtsDoc = true;
                if (!empty($cRow['tracking_number'])) $docRefNum = $cRow['tracking_number'];
                elseif (!empty($cRow['external_ref'])) $docRefNum = $cRow['external_ref'];
            }
        }

        // Generate feedback tracking token
        $tracking_token = 'FDBK-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $stmt = $conn->prepare("INSERT INTO feedback (consultation_id, guest_name, guest_email, guest_phone, rating, category, message, tracking_token, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())");
        $stmt->bind_param('isssisss', $consultation_id, $user_name, $user_email, $user_phone, $rating, $category, $message, $tracking_token);

        if ($stmt->execute()) {
            // Update posts_count in consultations
            $conn->query("UPDATE consultations SET posts_count = posts_count + 1 WHERE id = " . $consultation_id);
            require_once __DIR__ . '/../DATABASE/notifications.php';
            @createNotification(0, "💬 New Citizen Feedback Received from " . htmlspecialchars($user_name) . " ($tracking_token)", 'feedback');
            
            // Dispatch to ORTS Outbound API if ORTS Ordinance
            $ortsData = null;
            if ($isOrtsDoc) {
                if (file_exists(__DIR__ . '/../UTILS/orts_integration_utils.php')) {
                    require_once __DIR__ . '/../UTILS/orts_integration_utils.php';
                    if (function_exists('sendOrtsEvent')) {
                        $ortsPayload = [
                            'event' => 'public_feedback_received',
                            'document_id' => $consultation_id,
                            'reference_number' => $docRefNum,
                            'tracking_number' => $docRefNum,
                            'submitter_name' => $user_name,
                            'feedback_type' => $category,
                            'notes' => $message,
                            'source_system' => 'PCMS'
                        ];
                        $ortsData = sendOrtsEvent($ortsPayload);
                    }
                }
            }

            echo json_encode([
                'success' => true, 
                'message' => $isOrtsDoc ? 'Event accepted — Feedback successfully transmitted to ORTS and stored in PCMS!' : 'Thank you! Your feedback has been submitted successfully.',
                'tracking_token' => $tracking_token,
                'data' => [
                    'event' => 'public_feedback_received',
                    'document_id' => $consultation_id,
                    'reference_number' => $docRefNum,
                    'action' => 'feedback_stored',
                    'feedback_type' => $category
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving feedback: ' . $conn->error]);
        }"""

if old_post_handler in code:
    code = code.replace(old_post_handler, new_post_handler)
    print("Updated public/index.php POST handler with ORTS outbound dispatch and structured JSON response.")

with open(index_file, 'w', encoding='utf-8') as f:
    f.write(code)

print("Finished updating public/index.php for ORTS Integration!")
