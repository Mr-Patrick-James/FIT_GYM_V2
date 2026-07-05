/**
 * mobile-menu.js
 * Shared mobile sidebar toggle — works on all admin pages.
 * Handles: open/close sidebar, overlay backdrop, icon swap, body scroll lock.
 */
(function () {
    function initMobileMenu() {
        var btn     = document.getElementById('mobileMenuToggle');
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        if (!btn || !sidebar) return;

        function openSidebar() {
            sidebar.classList.add('active');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            var icon = btn.querySelector('i');
            if (icon) { icon.classList.remove('fa-bars'); icon.classList.add('fa-times'); }
        }

        function closeSidebar() {
            sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
            var icon = btn.querySelector('i');
            if (icon) { icon.classList.remove('fa-times'); icon.classList.add('fa-bars'); }
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebar.classList.contains('active') ? closeSidebar() : openSidebar();
        });

        // Close when clicking the overlay
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Close when clicking outside sidebar on mobile
        document.addEventListener('click', function (e) {
            if (
                sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                e.target !== btn &&
                !btn.contains(e.target)
            ) {
                closeSidebar();
            }
        });

        // Close sidebar when a nav link is clicked on mobile
        sidebar.querySelectorAll('.nav-links a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 1280) closeSidebar();
            });
        });

        // Re-open body scroll if window is resized above breakpoint
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1280) {
                document.body.style.overflow = '';
                if (overlay) overlay.classList.remove('active');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
})();
