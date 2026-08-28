import os

index_file = r'c:\xampp\htdocs\CAP101\PC\public\index.php'

with open(index_file, 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Add Type Filter buttons to Category Filters section
old_filters = """                        <?php 
                        $categories = [
                            'all' => 'All Topics',
                            'infrastructure' => 'Infrastructure',
                            'health' => 'Health & Sanitation',
                            'environment' => 'Environment',
                            'education' => 'Education',
                            'transportation' => 'Traffic & Transport',
                            'other' => 'General Governance'
                        ];"""

new_filters = """                        <?php 
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

if old_filters in code:
    code = code.replace(old_filters, new_filters)
    print("Updated Category Filters array in public/index.php.")

# 2. Update Card Tag rendering
old_card_tag = """                                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-lg border <?php echo $badgeStyle; ?>">
                                            <?php echo htmlspecialchars($c['category'] ?? 'General Governance'); ?>
                                        </span>"""

new_card_tag = """                                        <?php
                                            $tLow = strtolower($c['title'] ?? '');
                                            $cLow = strtolower($c['category'] ?? '');
                                            $isSurveyItem = (strpos($tLow, 'survey') !== false || strpos($tLow, 'poll') !== false || strpos($cLow, 'survey') !== false);
                                            $itemTypeTag = $isSurveyItem ? '📊 COMMUNITY SURVEY' : '📜 PUBLIC CONSULTATION';
                                            $itemTagStyle = $isSurveyItem ? 'bg-purple-50 text-purple-700 border-purple-200' : $badgeStyle;
                                        ?>
                                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-lg border <?php echo $itemTagStyle; ?>">
                                            <?php echo $itemTypeTag; ?>
                                        </span>"""

if old_card_tag in code:
    code = code.replace(old_card_tag, new_card_tag)
    print("Updated card tag rendering to distinguish Public Consultation vs Community Survey.")

# 3. Update Modal Header Badge JS
old_modal_badge_js = """                        document.getElementById('modal-category').textContent = (d.category || 'General Governance').toUpperCase();"""

new_modal_badge_js = """                        const cLow = String(d.category || '').toLowerCase();
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

if old_modal_badge_js in code:
    code = code.replace(old_modal_badge_js, new_modal_badge_js)
    print("Updated openConsultationModal JS to distinguish Survey vs Consultation badge.")

with open(index_file, 'w', encoding='utf-8') as f:
    f.write(code)

print("Successfully updated public/index.php with separated Survey and Consultation types!")
