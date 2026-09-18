/**
 * mobile-menu.js
 * Shared mobile sidebar toggle — works on all admin and user pages.
 * Handles: open/close sidebar, overlay backdrop, icon swap, body scroll lock.
 */
(function () {
    var initialized = false;

    function initMobileMenu() {
        if (initialized) return;
        initialized = true;

        var btn = document.getElementById('mobileMenuToggle');
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        if (!btn || !sidebar) return;

        function setSidebarOpen(isOpen) {
            sidebar.classList.toggle('active', isOpen);
            if (overlay) {
                overlay.classList.toggle('active', isOpen);
            }
            document.body.style.overflow = isOpen ? 'hidden' : '';

            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-times', isOpen);
                icon.classList.toggle('fa-bars', !isOpen);
            }

            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        function openSidebar() {
            setSidebarOpen(true);
        }

        function closeSidebar() {
            setSidebarOpen(false);
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            setSidebarOpen(!sidebar.classList.contains('active'));
        }, true);

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
                setSidebarOpen(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu, { once: true });
    } else {
        initMobileMenu();
    }
})();
