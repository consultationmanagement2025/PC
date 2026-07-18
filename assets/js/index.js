document.addEventListener('DOMContentLoaded', () => {
    // Sticky Header on Scroll
    const mainNav = document.getElementById('main-nav');
    let lastScrollTop = 0;

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

        if (currentScroll > 50) {
            mainNav.classList.add('scrolled');
            mainNav.style.boxShadow = '0 10px 30px 0 rgba(0, 51, 160, 0.1)';
            mainNav.style.backdropFilter = 'blur(15px)';
        } else {
            mainNav.classList.remove('scrolled');
            mainNav.style.boxShadow = '';
            mainNav.style.backdropFilter = 'blur(10px)';
        }

        lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
    });

    // Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });

    // Close mobile menu on link click
    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.add('hidden');
        });
    });

    // File Upload Display Name
    const fileInput = document.getElementById('file-upload');
    const fileNameDisplay = document.getElementById('file-name-display');

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            const fileNames = Array.from(this.files).map(f => f.name).join(', ');
            fileNameDisplay.textContent = 'Selected: ' + fileNames;
            fileNameDisplay.classList.remove('hidden');
        } else {
            fileNameDisplay.classList.add('hidden');
        }
    });

    // Handle Form Submission (Simulation)
    const form = document.getElementById('consultation-form');
    const successMessage = document.getElementById('form-success-message');

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnContent = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Submitting...';
        submitBtn.disabled = true;

        setTimeout(() => {
            successMessage.classList.remove('hidden');
            form.reset();
            fileNameDisplay.classList.add('hidden');
            submitBtn.innerHTML = originalBtnContent;
            submitBtn.disabled = false;

            setTimeout(() => {
                successMessage.classList.add('hidden');
            }, 5000);
        }, 1500);
    });
});

// Add smooth page transition on navigation
document.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (link && link.href && !link.href.includes('#') && !link.target && link.origin === window.location.origin) {
        e.preventDefault();
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.3s ease-out';
        setTimeout(() => {
            window.location.href = link.href;
        }, 300);
    }
});

// Fade in on page load
window.addEventListener('pageshow', () => {
    document.body.style.opacity = '1';
});

document.body.style.opacity = '1';
