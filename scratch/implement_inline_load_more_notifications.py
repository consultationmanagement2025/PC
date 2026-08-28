import os

# 1. Update system-template-full.php files
system_template_files = [
    r'c:\xampp\htdocs\CAP101\PC\system-template-full.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\system-template-full.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\system-template-full.php'
]

old_dropdown_block = """<div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 md:w-[380px] bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 flex flex-col overflow-hidden transition-all duration-200" style="z-index: 9999 !important; max-height: 480px;">
                                     <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                                         <h3 class="font-extrabold text-gray-900 text-sm md:text-base">Notifications</h3>
                                         <button type="button" onclick="pfpMarkAllNotificationsRead()" class="text-xs font-bold text-red-600 hover:text-red-700 transition cursor-pointer">Mark all read</button>
                                     </div>
                                     <div id="notifications-list" class="overflow-y-auto flex-1 min-h-0 divide-y divide-gray-100 max-h-[330px]">"""

new_dropdown_block = """<div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 md:w-[380px] bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 flex flex-col overflow-hidden transition-all duration-200" style="z-index: 9999 !important; max-height: 480px;">
                                     <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white shrink-0">
                                         <h3 class="font-extrabold text-gray-900 text-sm md:text-base">Notifications</h3>
                                         <button type="button" onclick="pfpMarkAllNotificationsRead()" class="text-xs font-bold text-red-600 hover:text-red-700 transition cursor-pointer">Mark all read</button>
                                     </div>
                                     <div id="notifications-list" onscroll="pfpCheckNotificationScrollPosition(this)" class="overflow-y-auto flex-1 min-h-0 divide-y divide-gray-100 max-h-[350px]">"""

old_footer_block = """                                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-center shrink-0">
                                        <button type="button" onclick="pfpOpenViewPreviousNotificationsModal()" class="w-full py-2 px-3 bg-white hover:bg-gray-100 text-red-600 border border-gray-200 rounded-xl text-xs font-extrabold transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                            <i class="bi bi-clock-history text-sm"></i> View Previous Notifications
                                        </button>
                                    </div>"""

new_footer_block = """                                    <div id="notifications-load-more-container" class="hidden px-4 py-3 bg-gray-50 border-t border-gray-100 text-center shrink-0 transition-all duration-200">
                                        <button id="btn-load-previous-notifs" type="button" onclick="pfpLoadPreviousNotifications()" class="w-full py-2 px-3 bg-white hover:bg-gray-100 text-red-600 border border-gray-200 rounded-xl text-xs font-extrabold transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                            <i class="bi bi-clock-history text-sm"></i> View Previous Notifications
                                        </button>
                                    </div>"""

print("=== UPDATING SYSTEM-TEMPLATE-FULL.PHP FILES ===")
for fpath in system_template_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    code = code.replace(old_dropdown_block, new_dropdown_block)
    code = code.replace(old_footer_block, new_footer_block)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated dropdown HTML in:", fpath)

print("Finished updating system-template-full.php!")
