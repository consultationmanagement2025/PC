from pathlib import Path
p = Path(r'c:\xampp\htdocs\CAP101\PC\public-portal.php')
text = p.read_text(encoding='utf-8')
start = text.index('<script>')
end = text.index('</script>', start) + len('</script>')
new_script = '''<script>
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

    function closeAppModal() {
        var el = document.getElementById('app-modal');
        if (el) el.style.display = 'none';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAppModal();
    });

    (function(){
        var initial = <?php echo json_encode(($section === 'detail') ? 'consultations' : $section); ?>;
        if (initial && document.getElementById('section-' + initial) && document.getElementById('nav-' + initial)) {
            switchSection(initial);
        }
    })();

    (function(){
        var openPrivacy = document.getElementById('openPrivacy');
        if (openPrivacy) {
            openPrivacy.addEventListener('click', function(e){
                e.preventDefault();
                var pm = document.getElementById('policyModal');
                if (pm) pm.style.display = 'flex';
            });
        }

        var openTerms = document.getElementById('openTerms');
        if (openTerms) {
            openTerms.addEventListener('click', function(e){
                e.preventDefault();
                var tm = document.getElementById('termsModal');
                if (tm) tm.style.display = 'flex';
            });
        }

        window.closePolicyModal = function(){ var pm = document.getElementById('policyModal'); if (pm) pm.style.display = 'none'; };
        window.closeTermsModal = function(){ var tm = document.getElementById('termsModal'); if (tm) tm.style.display = 'none'; };
    })();
</script>'''
updated = text[:start] + new_script + text[end:]
p.write_text(updated, encoding='utf-8')
print('updated', p)
