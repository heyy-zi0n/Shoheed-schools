document.addEventListener('DOMContentLoaded', () => {
    // Public mobile navigation toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', () => {
            const isHidden = mobileMenu.classList.toggle('hidden');
            mobileMenuBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        });
    }

    // Portal off-canvas sidebar drawer toggle (mobile/tablet)
    const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
    const sidebarCloseBtn = document.getElementById('sidebar-close-btn');
    const portalSidebar = document.getElementById('portal-sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    const closeSidebarDrawer = () => {
        if (!portalSidebar) return;
        portalSidebar.classList.add('-translate-x-full');
        if (sidebarBackdrop) sidebarBackdrop.classList.add('hidden');
        if (sidebarToggleBtn) sidebarToggleBtn.setAttribute('aria-expanded', 'false');
    };

    const openSidebarDrawer = () => {
        if (!portalSidebar) return;
        portalSidebar.classList.remove('-translate-x-full');
        if (sidebarBackdrop) sidebarBackdrop.classList.remove('hidden');
        if (sidebarToggleBtn) sidebarToggleBtn.setAttribute('aria-expanded', 'true');
    };

    if (sidebarToggleBtn && portalSidebar) {
        sidebarToggleBtn.addEventListener('click', () => {
            const isClosed = portalSidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                openSidebarDrawer();
            } else {
                closeSidebarDrawer();
            }
        });
    }
    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', closeSidebarDrawer);
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebarDrawer);
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

    // Profile dropdown menu
    const profileBtn = document.getElementById('profile-dropdown-btn');
    const profileMenu = document.getElementById('profile-dropdown-menu');
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = profileMenu.classList.toggle('hidden');
            profileBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        });
        document.addEventListener('click', (e) => {
            if (!profileMenu.contains(e.target) && !profileBtn.contains(e.target)) {
                profileMenu.classList.add('hidden');
                profileBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Global Escape key handler for dropdowns and mobile drawers
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (profileMenu && !profileMenu.classList.contains('hidden')) {
                profileMenu.classList.add('hidden');
                if (profileBtn) {
                    profileBtn.setAttribute('aria-expanded', 'false');
                    profileBtn.focus();
                }
            }
            if (portalSidebar && !portalSidebar.classList.contains('-translate-x-full')) {
                closeSidebarDrawer();
                if (sidebarToggleBtn) sidebarToggleBtn.focus();
            }
            if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
                if (mobileMenuBtn) {
                    mobileMenuBtn.setAttribute('aria-expanded', 'false');
                    mobileMenuBtn.focus();
                }
            }
        }
    });
});

function initFlashMessages() {
    const flashes = document.querySelectorAll('.flash-message[data-auto-dismiss="true"]');
    
    flashes.forEach((flash) => {
        if (flash.dataset.dismissBound === 'true') {
            return;
        }
        
        flash.dataset.dismissBound === 'true';
        flash.setAttribute('data-dismiss-bound', 'true');
        
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


