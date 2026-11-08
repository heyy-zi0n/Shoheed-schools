document.addEventListener('DOMContentLoaded', () => {
    // Show/Hide Password visibility toggle
    document.querySelectorAll('button[data-password-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-password-toggle');
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) return;

            const isMasked = input.type === 'password';
            input.type = isMasked ? 'text' : 'password';
            btn.setAttribute('aria-pressed', isMasked ? 'true' : 'false');
            btn.setAttribute('aria-label', isMasked ? 'Hide password' : 'Show password');

            const eyeOpen = btn.querySelector('[data-eye-open]');
            const eyeClosed = btn.querySelector('[data-eye-closed]');
            if (eyeOpen && eyeClosed) {
                eyeOpen.classList.toggle('hidden', isMasked);
                eyeClosed.classList.toggle('hidden', !isMasked);
            }
        });
    });

    // Sync hidden action_type input when a submit button with data-action-type is clicked
    document.querySelectorAll('button[type="submit"][data-action-type]').forEach(btn => {
        btn.addEventListener('click', () => {
            const form = btn.closest('form');
            if (!form) return;
            const hiddenAction = form.querySelector('input[name="action_type"]');
            if (hiddenAction) {
                hiddenAction.value = btn.getAttribute('data-action-type');
            }
        });
    });

    // Form or submit-button confirmation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            const submitterConfirm = e.submitter ? e.submitter.getAttribute('data-confirm') : null;
            const formConfirm = form.getAttribute('data-confirm');
            const message = submitterConfirm || formConfirm;
            if (message && !confirm(message)) {
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
            const activeSubmitter = e.submitter;
            const submitBtns = form.querySelectorAll('button[type="submit"]');
            submitBtns.forEach(btn => {
                setTimeout(() => {
                    btn.disabled = true;
                    if (btn === activeSubmitter || submitBtns.length === 1) {
                        if (!btn.getAttribute('data-original-text')) {
                            btn.setAttribute('data-original-text', btn.innerHTML);
                        }
                        btn.innerHTML = 'Processing...';
                    }
                }, 50);
            });
        });
    });
});

// Restore disabled submit buttons when navigating back via browser bfcache
window.addEventListener('pageshow', () => {
    document.querySelectorAll('button[type="submit"]').forEach(btn => {
        btn.disabled = false;
        if (btn.getAttribute('data-original-text')) {
            btn.innerHTML = btn.getAttribute('data-original-text');
        }
    });
});
