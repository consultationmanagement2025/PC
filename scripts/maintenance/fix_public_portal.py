from pathlib import Path
p = Path('c:/xampp/htdocs/CAP101/PC/public-portal.php')
text = p.read_text(encoding='utf-8')
start = text.index('    function switchSection(section) {')
end = text.index('    function closeAppModal() {')
new_block = '''    function switchSection(section) {
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

'''
updated = text[:start] + new_block + text[end:]
p.write_text(updated, encoding='utf-8')
print('updated', p)
