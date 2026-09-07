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

    // Disable buttons on submit to prevent double submission
    const forms = document.querySelectorAll('form:not([data-no-disable])');
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            const submitBtns = form.querySelectorAll('button[type="submit"]');
            submitBtns.forEach(btn => {
                // Short timeout to allow form submission to proceed before disabling
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
