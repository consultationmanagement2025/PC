import os

files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
]

old_block = """    const citizenTabActive = (_userMgmtTab === 'citizens');
    const adminTabActive = (_userMgmtTab === 'admins');
    const pendingTabActive = (_userMgmtTab === 'pending');
    const expertTabActive = (_userMgmtTab === 'experts');

    const html = `
        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">User Management & Citizen Registry</h1>
                        <p class="text-red-100 text-sm">Monitor, verify, and engage registered public citizen participants.</p>
                    </div>
                </div>

                <!-- KPI Metric Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Registered Citizens</div>
                        <div class="text-3xl font-bold">${totalCitizens}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Verified via Google / 2FA</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Proposals Submitted</div>
                        <div class="text-3xl font-bold">${totalConsultations}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Citizen initiative papers</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Survey Votes Cast</div>
                        <div class="text-3xl font-bold">${totalVotes}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Community poll participation</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Engagement</div>
                        <div class="text-3xl font-bold">${totalConsultations + totalFeedbacks + totalVotes}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Combined citizen actions</div>
                    </div>
                </div>
            </div>

            <!-- Role Tabs Navigation (Matching Document Management Group Tabs) -->
            <div class="flex flex-wrap gap-2 mt-6 border-b border-gray-200">
                <button onclick="_userMgmtTab='citizens'; renderUsers(true);" class="px-6 py-3 font-semibold text-sm border-b-2 transition flex items-center gap-2 ${citizenTabActive ? 'border-red-600 text-red-600 bg-red-50/40 font-bold' : 'border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600'}">
                    <i class="bi bi-people-fill"></i> Citizen Submitters <span class="ml-1 px-2 py-0.5 rounded-full text-xs ${citizenTabActive ? 'bg-red-100 text-red-800 font-bold' : 'bg-gray-200 text-gray-700'}">${totalCitizens}</span>
                </button>

                <button onclick="_userMgmtTab='admins'; renderUsers(true);" class="px-6 py-3 font-semibold text-sm border-b-2 transition flex items-center gap-2 ${adminTabActive ? 'border-red-600 text-red-600 bg-red-50/40 font-bold' : 'border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600'}">
                    <i class="bi bi-shield-lock-fill"></i> Admins & Staff <span class="ml-1 px-2 py-0.5 rounded-full text-xs ${adminTabActive ? 'bg-red-100 text-red-800 font-bold' : 'bg-gray-200 text-gray-700'}">4</span>
                </button>"""

new_block = """    const isSuperAdmin = currentUserIsSuperAdmin();
    if (!isSuperAdmin && _userMgmtTab === 'admins') {
        _userMgmtTab = 'citizens';
    }

    const citizenTabActive = (_userMgmtTab === 'citizens');
    const adminTabActive = (_userMgmtTab === 'admins');
    const pendingTabActive = (_userMgmtTab === 'pending');
    const expertTabActive = (_userMgmtTab === 'experts');

    const html = `
        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">User Management & Citizen Registry</h1>
                        <p class="text-red-100 text-sm">Monitor, verify, and engage registered public citizen participants.</p>
                    </div>
                </div>

                <!-- KPI Metric Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Registered Citizens</div>
                        <div class="text-3xl font-bold">${totalCitizens}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Verified via Google / 2FA</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Proposals Submitted</div>
                        <div class="text-3xl font-bold">${totalConsultations}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Citizen initiative papers</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Survey Votes Cast</div>
                        <div class="text-3xl font-bold">${totalVotes}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Community poll participation</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Engagement</div>
                        <div class="text-3xl font-bold">${totalConsultations + totalFeedbacks + totalVotes}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Combined citizen actions</div>
                    </div>
                </div>
            </div>

            <!-- Role Tabs Navigation (Matching Document Management Group Tabs) -->
            <div class="flex flex-wrap gap-2 mt-6 border-b border-gray-200">
                <button onclick="_userMgmtTab='citizens'; renderUsers(true);" class="px-6 py-3 font-semibold text-sm border-b-2 transition flex items-center gap-2 ${citizenTabActive ? 'border-red-600 text-red-600 bg-red-50/40 font-bold' : 'border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600'}">
                    <i class="bi bi-people-fill"></i> Citizen Submitters <span class="ml-1 px-2 py-0.5 rounded-full text-xs ${citizenTabActive ? 'bg-red-100 text-red-800 font-bold' : 'bg-gray-200 text-gray-700'}">${totalCitizens}</span>
                </button>

                ${isSuperAdmin ? `
                <button onclick="_userMgmtTab='admins'; renderUsers(true);" class="px-6 py-3 font-semibold text-sm border-b-2 transition flex items-center gap-2 ${adminTabActive ? 'border-red-600 text-red-600 bg-red-50/40 font-bold' : 'border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600'}">
                    <i class="bi bi-shield-lock-fill"></i> Admins & Staff <span class="ml-1 px-2 py-0.5 rounded-full text-xs ${adminTabActive ? 'bg-red-100 text-red-800 font-bold' : 'bg-gray-200 text-gray-700'}">4</span>
                </button>
                ` : ''}"""

for path in files:
    if not os.path.exists(path):
        print(f"Skipping missing path: {path}")
        continue
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()
    if old_block in content:
        content = content.replace(old_block, new_block)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Successfully updated: {path}")
    else:
        print(f"Pattern not found in: {path}")
