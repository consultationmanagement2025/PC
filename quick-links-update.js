// Quick links helper: expose the HTML snippet as a safe JS string.
// Use `window.QUICK_LINKS_HTML` to insert or replace the Quick Links section in `app-features.js`.
(function(){
    const QUICK_LINKS_HTML = `
<div class="space-y-3">
    <!-- Account Settings -->
    <button onclick="openEditProfileModal()" class="w-full flex items-center gap-3 p-3 text-left hover:bg-red-50 rounded-lg transition group">
        <i class="bi bi-gear text-gray-600 group-hover:text-red-600"></i>
        <div class="flex-1">
            <span class="text-sm text-gray-700 group-hover:text-red-600">Account Settings</span>
            <p class="text-xs text-gray-500">Manage profile & preferences</p>
        </div>
        <i class="bi bi-chevron-right text-gray-400"></i>
    </button>

    <!-- Activity Report -->
    <button onclick="openActivityReportModal()" class="w-full flex items-center gap-3 p-3 text-left hover:bg-indigo-50 rounded-lg transition group">
        <i class="bi bi-bar-chart text-gray-600 group-hover:text-indigo-600"></i>
        <div class="flex-1">
            <span class="text-sm text-gray-700 group-hover:text-indigo-600">Activity Report</span>
            <p class="text-xs text-gray-500">Your activities</p>
        </div>
        <i class="bi bi-chevron-right text-gray-400"></i>
    </button>

    <!-- Session Settings -->
    <button onclick="openSessionSettingsModal()" class="w-full flex items-center gap-3 p-3 text-left hover:bg-cyan-50 rounded-lg transition group">
        <i class="bi bi-wifi text-gray-600 group-hover:text-cyan-600"></i>
        <div class="flex-1">
            <span class="text-sm text-gray-700 group-hover:text-cyan-600">Session Settings</span>
            <p class="text-xs text-gray-500">Manage sessions</p>
        </div>
        <i class="bi bi-chevron-right text-gray-400"></i>
    </button>
</div>
`;

    // Quick links removed; expose empty string
    window.QUICK_LINKS_HTML = '';
})();
