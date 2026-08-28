import os

index_file = r'c:\xampp\htdocs\CAP101\PC\public\index.php'

with open(index_file, 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Simplify Category Filter Buttons Array (Remove ORTS Ordinances & Public Consultations text buttons)
old_filters_block = """                        <?php 
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

new_filters_block = """                        <?php 
                        $categories = [
                            'all' => 'All Topics',
                            'infrastructure' => 'Infrastructure',
                            'health' => 'Health & Sanitation',
                            'environment' => 'Environment',
                            'education' => 'Education',
                            'transportation' => 'Traffic & Transport',
                            'other' => 'General Governance'
                        ];"""

if old_filters_block in code:
    code = code.replace(old_filters_block, new_filters_block)
    print("Cleaned Category Filters array in public/index.php.")

# 2. Update Card Image & Corner Badges (Upper-Left Corner Ord / Draft / Survey badge)
old_card_header_image = """                            <!-- Top Banner Image if available -->
                            <?php if (!empty($c['image_path']) && file_exists(__DIR__ . '/../' . $c['image_path'])): ?>
                                <div class="h-44 w-full overflow-hidden bg-slate-100 relative">
                                    <img src="../<?php echo htmlspecialchars($c['image_path']); ?>" alt="Consultation Image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-md px-3 py-1 rounded-full text-[11px] font-extrabold text-slate-800 shadow-md">
                                        <i class="fa-regular fa-clock text-red-600 mr-1"></i> <?php echo $days_left; ?>d remaining
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="h-3 w-full bg-gradient-to-r from-red-700 via-slate-800 to-blue-900"></div>
                            <?php endif; ?>"""

new_card_header_image = """                            <?php
                                $tLow = strtolower($c['title'] ?? '');
                                $cLow = strtolower($c['category'] ?? '');
                                $typeClean = strtolower($c['type'] ?? '');
                                $srcClean = strtoupper($c['source_system'] ?? '');

                                $isOrtsItem = ($typeClean === 'ordinance' || $srcClean === 'ORTS' || strpos($tLow, 'ordinance') !== false || strpos($cLow, 'orts') !== false || strpos($cLow, 'ordinance') !== false);
                                $isSurveyItem = (!$isOrtsItem) && (strpos($tLow, 'survey') !== false || strpos($tLow, 'poll') !== false || strpos($cLow, 'survey') !== false);
                            ?>
                            <div class="h-44 w-full overflow-hidden bg-slate-900 relative">
                                <?php if (!empty($c['image_path']) && file_exists(__DIR__ . '/../' . $c['image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($c['image_path']); ?>" alt="Consultation Image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-r from-red-900 via-slate-900 to-indigo-950 flex items-center justify-center p-6 text-white/20">
                                        <i class="fa-solid <?php echo $isOrtsItem ? 'fa-scale-balanced text-6xl text-indigo-400/30' : ($isSurveyItem ? 'fa-square-poll-vertical text-6xl text-purple-400/30' : 'fa-scroll text-6xl text-amber-400/30'); ?>"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Upper Left Corner Badge (Ord vs Draft vs Survey) -->
                                <div class="absolute top-3 left-3 z-10">
                                    <?php if ($isOrtsItem): ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-indigo-900/90 text-indigo-100 border border-indigo-400/40 backdrop-blur-md shadow-md flex items-center gap-1.5" title="Ordinance from ORTS">
                                            <i class="fa-solid fa-scale-balanced text-indigo-300"></i> Ord
                                        </span>
                                    <?php elseif ($isSurveyItem): ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-purple-900/90 text-purple-100 border border-purple-400/40 backdrop-blur-md shadow-md flex items-center gap-1.5" title="Community Survey">
                                            <i class="fa-solid fa-square-poll-vertical text-purple-300"></i> Survey
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-amber-500/95 text-white border border-amber-300/50 backdrop-blur-md shadow-md flex items-center gap-1.5" title="Draft Ordinance Idea from Admin">
                                            <i class="fa-solid fa-scroll text-amber-100"></i> Draft
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Upper Right Corner Badge (Days Remaining) -->
                                <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-md px-3 py-1 rounded-full text-[11px] font-extrabold text-slate-800 shadow-md z-10">
                                    <i class="fa-regular fa-clock text-red-600 mr-1"></i> <?php echo $days_left; ?>d remaining
                                </div>
                            </div>"""

if old_card_header_image in code:
    code = code.replace(old_card_header_image, new_card_header_image)
    print("Updated Card Header with Upper Left Corner Badges (Ord / Draft / Survey).")

# 3. Remove mid-card text tag block from card body
old_mid_tag_block = """                                        <?php
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
                                        </span>
                                        <?php if (empty($c['image_path'])): ?>
                                            <span class="text-[11px] font-bold text-slate-400 flex items-center gap-1">
                                                <i class="fa-regular fa-clock"></i> Closes in <?php echo $days_left; ?>d
                                            </span>
                                        <?php endif; ?>"""

new_mid_tag_block = """                                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-lg border <?php echo $badgeStyle; ?>">
                                            <?php echo htmlspecialchars($c['category'] ?? 'General Governance'); ?>
                                        </span>"""

if old_mid_tag_block in code:
    code = code.replace(old_mid_tag_block, new_mid_tag_block)
    print("Removed duplicate mid-card text tag block from card body.")

# 4. Update Modal Header Badge JS in openConsultationModal
old_modal_badge_js = """                        if (isOrtsModal) {
                            categoryEl.className = 'text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scale-balanced mr-1.5"></i> ORTS ENACTED ORDINANCE';"""

new_modal_badge_js = """                        if (isOrtsModal) {
                            categoryEl.className = 'text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scale-balanced mr-1.5"></i> ORTS ORDINANCE (ORD)';"""

if old_modal_badge_js in code:
    code = code.replace(old_modal_badge_js, new_modal_badge_js)

old_draft_badge_js = """                        } else {
                            categoryEl.className = 'text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-md bg-blue-50 text-valenzuela-blue border border-blue-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scroll mr-1.5"></i> PUBLIC CONSULTATION';"""

new_draft_badge_js = """                        } else {
                            categoryEl.className = 'text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-md bg-amber-50 text-amber-900 border border-amber-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scroll mr-1.5"></i> DRAFT ORDINANCE CONSULTATION';"""

if old_draft_badge_js in code:
    code = code.replace(old_draft_badge_js, new_draft_badge_js)

with open(index_file, 'w', encoding='utf-8') as f:
    f.write(code)

print("Successfully updated public/index.php with Upper-Left Corner Badges (Ord vs Draft vs Survey)!")
