$path = 'c:\xampp\htdocs\CAP101\PC\public-portal.php'
$text = Get-Content -Path $path -Raw
$start = $text.IndexOf("    function switchSection(section) {")
$end = $text.IndexOf("    function closeAppModal() {")
if ($start -lt 0 -or $end -lt 0 -or $end -le $start) {
    throw 'Could not locate script block markers.'
}
$newBlock = @"
    function switchSection(section) {
        document.querySelectorAll('[id^="section-"]').forEach(function(el) {
            el.classList.remove('section-active');
            el.classList.add('section-hidden');
        });

        var target = document.getElementById('section-' + section);
        if (target) {
            target.classList.remove('section-hidden');
            target.classList.add('section-active');
        }

        var navItem = document.getElementById('nav-' + section);
        if (navItem) {
            navItem.classList.add('active');
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function toggleConsultationDetail(consultationId) {
        var panel = document.getElementById('consultation-detail-' + consultationId);
        if (!panel) {
            window.location.href = '?section=detail&id=' + consultationId;
            return;
        }
        var isOpen = panel.style.display !== 'none' && panel.style.display !== '';
        if (isOpen) {
            panel.style.display = 'none';
        } else {
            document.querySelectorAll('.consultation-detail-panel').forEach(function(other) {
                other.style.display = 'none';
            });
            panel.style.display = 'block';
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function switchToConsultationsList() {
        window.location.href = '?section=consultations';
    }

    function viewConsultationDetail(consultationId) {
        window.location.href = '?section=detail&id=' + consultationId;
    }

"@
$updated = $text.Substring(0, $start) + $newBlock + $text.Substring($end)
Set-Content -Path $path -Value $updated -Encoding utf8
Write-Output 'Updated public-portal.php'
