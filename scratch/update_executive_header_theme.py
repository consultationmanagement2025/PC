import os

files_to_update = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

old_target = """            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-red-950 to-slate-900 text-white p-7 rounded-2xl shadow-xl border border-red-950/40 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="px-3 py-1 rounded-full bg-white/15 text-red-100 text-[11px] font-extrabold uppercase tracking-wider backdrop-blur-xs border border-white/10">
                        <i class="bi bi-file-earmark-bar-graph-fill mr-1 text-red-300"></i> Policy Intelligence & Transmittal Hub
                    </span>
                    <h1 class="text-2xl font-black text-white mt-2 flex items-center gap-2">
                        Executive Policy Reports
                    </h1>
                    <p class="text-xs text-red-100/90 mt-1 max-w-2xl font-medium leading-relaxed">
                        High-level AI synthesis reports, legislative transmittals for city council (LRS/ORTS), and public sentiment intelligence summaries.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="openCustomReportExportModal('pdf')" class="px-4 py-2.5 bg-white text-red-950 hover:bg-red-50 text-xs font-black rounded-xl transition shadow-sm flex items-center gap-1.5 cursor-pointer border border-white/20">
                        <i class="bi bi-file-earmark-pdf-fill text-red-600 text-sm"></i> Export Official PDF Report
                    </button>
                    <button onclick="openCustomReportExportModal('word')" class="px-4 py-2.5 bg-gradient-to-r from-blue-700 to-indigo-800 hover:from-blue-800 hover:to-indigo-900 text-white text-xs font-black rounded-xl transition shadow-sm flex items-center gap-1.5 cursor-pointer border border-blue-500/30">
                        <i class="bi bi-file-earmark-word-fill text-blue-200 text-sm"></i> Export MS Word (.doc)
                    </button>
                    <button onclick="renderSystemReportsSection()" class="px-3 py-2.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl transition border border-white/20 flex items-center gap-1.5 cursor-pointer">
                        <i class="bi bi-arrow-repeat"></i> Refresh Data
                    </button>
                </div>
            </div>"""

new_replacement = """            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-red-700 via-red-800 to-red-950 text-white p-7 rounded-2xl shadow-xl border border-red-800/40 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="px-3 py-1 rounded-full bg-white/20 text-white text-[11px] font-extrabold uppercase tracking-wider backdrop-blur-md border border-white/25 shadow-xs">
                        <i class="bi bi-file-earmark-bar-graph-fill mr-1.5 text-red-100"></i> Policy Intelligence & Transmittal Hub
                    </span>
                    <h1 class="text-2xl font-black text-white mt-2.5 flex items-center gap-2 tracking-tight">
                        Executive Policy Reports
                    </h1>
                    <p class="text-xs text-red-100/90 mt-1 max-w-2xl font-medium leading-relaxed">
                        High-level AI synthesis reports, legislative transmittals for city council (LRS/ORTS), and public sentiment intelligence summaries.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <button onclick="openCustomReportExportModal('pdf')" class="px-4 py-2.5 bg-white text-red-700 hover:bg-red-50 text-xs font-bold rounded-xl transition shadow-md hover:shadow-lg flex items-center gap-2 cursor-pointer border border-white">
                        <i class="bi bi-file-earmark-pdf-fill text-red-600 text-sm"></i> Export Official PDF Report
                    </button>
                    <button onclick="openCustomReportExportModal('word')" class="px-4 py-2.5 bg-red-900/80 hover:bg-red-900 text-white text-xs font-bold rounded-xl transition shadow-md flex items-center gap-2 cursor-pointer border border-white/30 backdrop-blur-sm">
                        <i class="bi bi-file-earmark-word-fill text-red-200 text-sm"></i> Export MS Word (.doc)
                    </button>
                    <button onclick="renderSystemReportsSection()" class="px-3.5 py-2.5 bg-white/15 hover:bg-white/25 text-white text-xs font-bold rounded-xl transition border border-white/25 flex items-center gap-1.5 cursor-pointer backdrop-blur-sm">
                        <i class="bi bi-arrow-repeat"></i> Refresh Data
                    </button>
                </div>
            </div>"""

for filepath in files_to_update:
    if os.path.exists(filepath):
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        if old_target in content:
            content = content.replace(old_target, new_replacement)
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Updated header banner theme in {filepath}")
        else:
            print(f"Target block not found in {filepath}")
    else:
        print(f"File not found: {filepath}")
