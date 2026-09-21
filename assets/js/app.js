document.addEventListener('DOMContentLoaded', () => {
    // Mobile navigation toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // Dismissible alerts
    const alertDismissBtns = document.querySelectorAll('.alert-dismiss');
    alertDismissBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const alert = e.target.closest('.alert');
            if (alert) {
                alert.style.display = 'none';
            }
        });
    });
});

function initFlashMessages() {
    const flashes = document.querySelectorAll('.flash-message[data-auto-dismiss="true"]');
    
    flashes.forEach((flash) => {
        if (flash.dataset.dismissBound === 'true') {
            return;
        }
        
        flash.dataset.dismissBound = 'true';
        
        setTimeout(() => {
            flash.classList.add('flash-hide');
            
            setTimeout(() => {
                flash.remove();
            }, 300);
        }, 4000);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlashMessages);
} else {
    initFlashMessages();
}
