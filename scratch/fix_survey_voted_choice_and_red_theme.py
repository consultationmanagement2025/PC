import os

index_file = r'c:\xampp\htdocs\CAP101\PC\public\index.php'

with open(index_file, 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Update PHP template for status pill in public/index.php
old_php_status_pill = """                                    <?php if ($is_logged_in && !empty($s['user_vote'])): ?>
                                        <div class="w-full bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold py-1.5 px-3 rounded-xl text-xs flex items-center justify-between shadow-xs">
                                            <span class="flex items-center gap-1.5">
                                                <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                                <span>You voted: <strong class="uppercase font-extrabold text-emerald-950"><?php echo htmlspecialchars($s['user_vote']); ?></strong></span>
                                            </span>
                                            <span class="text-[9px] text-emerald-700 font-semibold bg-emerald-100 px-1.5 py-0.5 rounded-full">Change</span>
                                        </div>
                                    <?php endif; ?>"""

new_php_status_pill = """                                    <?php 
                                        $userV = $is_logged_in ? strtolower(trim($s['user_vote'] ?? '')) : '';
                                        $isA = ($is_logged_in && $userV !== '' && ($userV === strtolower(trim($optA)) || $userV === 'agree'));
                                        $isB = ($is_logged_in && $userV !== '' && ($userV === strtolower(trim($optB)) || $userV === 'disagree'));

                                        $displayVoteText = $s['user_vote'] ?? '';
                                        if (strtolower($displayVoteText) === 'agree') {
                                            $displayVoteText = $optA;
                                        } elseif (strtolower($displayVoteText) === 'disagree') {
                                            $displayVoteText = $optB;
                                        }
                                    ?>
                                    <?php if ($is_logged_in && !empty($s['user_vote'])): ?>
                                        <?php if ($isB): ?>
                                            <div class="w-full bg-rose-50 text-rose-800 border border-rose-300 font-semibold py-1.5 px-3 rounded-xl text-xs flex items-center justify-between shadow-2xs">
                                                <span class="flex items-center gap-1.5 truncate pr-2">
                                                    <i class="fa-solid fa-circle-check text-rose-600 shrink-0"></i>
                                                    <span class="truncate">You voted: <strong class="font-extrabold text-rose-950"><?php echo htmlspecialchars($displayVoteText); ?></strong></span>
                                                </span>
                                                <span class="text-[9px] text-rose-700 font-semibold bg-rose-100 px-1.5 py-0.5 rounded-full shrink-0">Change</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold py-1.5 px-3 rounded-xl text-xs flex items-center justify-between shadow-2xs">
                                                <span class="flex items-center gap-1.5 truncate pr-2">
                                                    <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i>
                                                    <span class="truncate">You voted: <strong class="font-extrabold text-emerald-950"><?php echo htmlspecialchars($displayVoteText); ?></strong></span>
                                                </span>
                                                <span class="text-[9px] text-emerald-700 font-semibold bg-emerald-100 px-1.5 py-0.5 rounded-full shrink-0">Change</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>"""

if old_php_status_pill in code:
    code = code.replace(old_php_status_pill, new_php_status_pill)
    print("Updated PHP template status pill with dynamic red/green theme and subject choice text.")

# 2. Update updateSurveyButtonsUI JS function in public/index.php
old_js_update_func = """        function updateSurveyButtonsUI(surveyId, optionChosen) {
            const container = document.getElementById('survey-action-buttons-' + surveyId);
            if (!container) return;

            const optA = container.getAttribute('data-opta') || 'Agree';
            const optB = container.getAttribute('data-optb') || 'Disagree';

            const isA = (optionChosen.toLowerCase() === optA.toLowerCase());
            const isB = (optionChosen.toLowerCase() === optB.toLowerCase());

            const btnAClass = isA ? 'bg-emerald-600 text-white border-emerald-700 font-extrabold shadow' : 'bg-blue-50 hover:bg-valenzuela-blue hover:text-white text-valenzuela-blue border-blue-200 font-bold';
            const btnBClass = isB ? 'bg-red-600 text-white border-red-700 font-extrabold shadow' : 'bg-red-50 hover:bg-valenzuela-red hover:text-white text-valenzuela-red border-red-200 font-bold';

            const iconA = isA ? 'fa-check-circle' : 'fa-thumbs-up';
            const iconB = isB ? 'fa-check-circle' : 'fa-thumbs-down';

            const textA = isA ? ('Voted ' + escapeHtml(optA)) : ('Vote ' + escapeHtml(optA));
            const textB = isB ? ('Voted ' + escapeHtml(optB)) : ('Vote ' + escapeHtml(optB));

            container.innerHTML = `
                <div class="w-full bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold py-2 px-3.5 rounded-xl text-xs flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        <span>You voted: <strong class="uppercase font-extrabold text-emerald-950">${escapeHtml(optionChosen)}</strong></span>
                    </span>
                    <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-100 px-2 py-0.5 rounded-full">(Click other button to change)</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="castSurveyVote(${surveyId}, '${optA.replace(/'/g, "\\'")}')" class="w-full ${btnAClass} border py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid ${iconA}"></i> ${textA}
                    </button>
                    <button onclick="castSurveyVote(${surveyId}, '${optB.replace(/'/g, "\\'")}')" class="w-full ${btnBClass} border py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid ${iconB}"></i> ${textB}
                    </button>
                </div>
            `;
        }"""

new_js_update_func = """        function updateSurveyButtonsUI(surveyId, optionChosen) {
            const container = document.getElementById('survey-action-buttons-' + surveyId);
            if (!container) return;

            const optA = container.getAttribute('data-opta') || 'Agree';
            const optB = container.getAttribute('data-optb') || 'Disagree';

            const isA = (optionChosen.toLowerCase() === optA.toLowerCase() || optionChosen.toLowerCase() === 'agree');
            const isB = (optionChosen.toLowerCase() === optB.toLowerCase() || optionChosen.toLowerCase() === 'disagree');

            let displayVote = optionChosen;
            if (optionChosen.toLowerCase() === 'agree') displayVote = optA;
            if (optionChosen.toLowerCase() === 'disagree') displayVote = optB;

            const pillBgClass = isB ? 'bg-rose-50 text-rose-800 border-rose-300' : 'bg-emerald-50 text-emerald-800 border-emerald-300';
            const pillIconClass = isB ? 'text-rose-600' : 'text-emerald-600';
            const pillTextClass = isB ? 'text-rose-950' : 'text-emerald-950';
            const pillBadgeClass = isB ? 'text-rose-700 bg-rose-100' : 'text-emerald-700 bg-emerald-100';

            const btnAClass = isA ? 'bg-emerald-600 text-white border-emerald-700 font-extrabold shadow-sm' : 'bg-emerald-50/90 hover:bg-emerald-600 hover:text-white text-emerald-800 border-emerald-200/80 font-bold';
            const btnBClass = isB ? 'bg-red-600 text-white border-red-700 font-extrabold shadow-sm' : 'bg-rose-50/90 hover:bg-red-600 hover:text-white text-rose-800 border-rose-200/80 font-bold';

            const iconA = isA ? 'fa-check-circle' : 'fa-thumbs-up';
            const iconB = isB ? 'fa-check-circle' : 'fa-thumbs-down';

            container.innerHTML = `
                <div class="w-full ${pillBgClass} border font-semibold py-1.5 px-3 rounded-xl text-xs flex items-center justify-between shadow-2xs">
                    <span class="flex items-center gap-1.5 truncate pr-2">
                        <i class="fa-solid fa-circle-check ${pillIconClass} shrink-0"></i>
                        <span class="truncate">You voted: <strong class="font-extrabold ${pillTextClass}">${escapeHtml(displayVote)}</strong></span>
                    </span>
                    <span class="text-[9px] ${pillBadgeClass} font-semibold px-1.5 py-0.5 rounded-full shrink-0">Change</span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="castSurveyVote(${surveyId}, '${optA.replace(/'/g, "\\'")}')" class="w-full ${btnAClass} border py-2 px-2.5 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5 min-w-0" title="${escapeHtml(optA)}">
                        <i class="fa-solid ${iconA} text-xs shrink-0"></i>
                        <span class="truncate">${escapeHtml(optA)}</span>
                    </button>
                    <button onclick="castSurveyVote(${surveyId}, '${optB.replace(/'/g, "\\'")}')" class="w-full ${btnBClass} border py-2 px-2.5 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5 min-w-0" title="${escapeHtml(optB)}">
                        <i class="fa-solid ${iconB} text-xs shrink-0"></i>
                        <span class="truncate">${escapeHtml(optB)}</span>
                    </button>
                </div>
            `;
        }"""

if old_js_update_func in code:
    code = code.replace(old_js_update_func, new_js_update_func)
    print("Updated JS updateSurveyButtonsUI with dynamic red/green theme and subject choice text.")

with open(index_file, 'w', encoding='utf-8') as f:
    f.write(code)

print("Finished updating survey voted choice display and red theme!")
