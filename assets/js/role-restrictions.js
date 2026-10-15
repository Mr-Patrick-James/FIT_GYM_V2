/**
 * role-restrictions.js
 * Restricts manager UI access to trainer management and admin-only settings.
 * Loaded on every admin page after other scripts.
 */
(function () {
    // Only applies to managers — admins keep full access
    if (!document.body.classList.contains('role-manager')) return;

    // ── CSS rule injected at runtime ───────────────────────────────────────────
    // Catches statically-rendered buttons immediately (before any JS runs).
    const style = document.createElement('style');
    style.textContent = `
        /* Trainer management and admin-only account/system controls */
        body.role-manager [onclick*="editTrainer("],
        body.role-manager [onclick*="deleteTrainer("],
        body.role-manager [onclick*="confirmDeleteTrainer("],
        body.role-manager [onclick*="openAddTrainerModal("],
        body.role-manager [onclick*="removeAdmin("],
        body.role-manager [onclick*="removeManager("],
        body.role-manager [onclick*="exportDatabase("],
        body.role-manager .settings-danger-zone,
        body.role-manager .sidebar a[href="trainers.php"],
        body.role-manager .sidebar a[href="settings.php"],
        body.role-manager .sidebar-nav a[href="trainers.php"],
        body.role-manager .sidebar-nav a[href="settings.php"],
        body.role-manager .control-tab[data-tab="trainers"] {
            display: none !important;
        }
    `;
    document.head.appendChild(style);

    // ── MutationObserver: catches buttons injected by JS templates ─────────────
    // Runs every time new DOM nodes are added (e.g. when loadAllTrainers() renders cards).
    const BLOCKED_PATTERNS = [
        /\beditTrainer\s*\(/i,
        /\bdeleteTrainer\s*\(/i,
        /\bconfirmDeleteTrainer\s*\(/i,
        /\bopenAddTrainerModal\s*\(/i,
        /\bremoveAdmin\s*\(/i,
        /\bremoveManager\s*\(/i,
        /\bexportDatabase\s*\(/i,
    ];

    function hideManagerButtons(root) {
        const buttons = root.querySelectorAll
            ? root.querySelectorAll('[onclick]')
            : [];
        buttons.forEach(el => {
            const fn = el.getAttribute('onclick') || '';
            if (BLOCKED_PATTERNS.some(re => re.test(fn))) {
                el.style.setProperty('display', 'none', 'important');
            }
        });
    }

    // Run once on current DOM
    hideManagerButtons(document);

    // Watch for future DOM mutations
    const observer = new MutationObserver(mutations => {
        mutations.forEach(m => {
            m.addedNodes.forEach(node => {
                if (node.nodeType === 1) { // Element node
                    hideManagerButtons(node);
                    // Also check the node itself
                    const fn = node.getAttribute && node.getAttribute('onclick');
                    if (fn && BLOCKED_PATTERNS.some(re => re.test(fn))) {
                        node.style.setProperty('display', 'none', 'important');
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();
