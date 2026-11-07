document.addEventListener('DOMContentLoaded', () => {
    // Form submission confirmation
    const confirmForms = document.querySelectorAll('form[data-confirm]');
    confirmForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const message = form.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Disable buttons on POST submit to prevent double submission (ignore GET filter forms)
    const forms = document.querySelectorAll('form:not([data-no-disable])');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) {
                return;
            }
            const method = (form.getAttribute('method') || form.method || 'GET').toUpperCase();
            if (method !== 'POST') {
                return;
            }
            const submitBtns = form.querySelectorAll('button[type="submit"]');
            submitBtns.forEach(btn => {
                setTimeout(() => {
                    btn.disabled = true;
                    if (!btn.getAttribute('data-original-text')) {
                        btn.setAttribute('data-original-text', btn.innerHTML);
                    }
                    btn.innerHTML = 'Processing...';
                }, 50);
            });
        });
    });
});

// Restore disabled submit buttons when navigating back via browser bfcache
window.addEventListener('pageshow', () => {
    document.querySelectorAll('button[type="submit"][data-original-text]').forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = btn.getAttribute('data-original-text');
    });
});

