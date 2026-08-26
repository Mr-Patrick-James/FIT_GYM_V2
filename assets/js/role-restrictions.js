/**
 * role-restrictions.js
 * Hides edit and delete controls for manager-role users across all admin pages.
 * Loaded on every admin page after other scripts.
 */
(function () {
    // Only applies to managers — admins keep full access
    if (!document.body.classList.contains('role-manager')) return;

    // ── CSS rule injected at runtime ───────────────────────────────────────────
    // Catches statically-rendered buttons immediately (before any JS runs).
    const style = document.createElement('style');
    style.textContent = `
        /* Hide any button whose onclick touches edit/delete/remove actions */
        body.role-manager [onclick*="editTrainer"],
        body.role-manager [onclick*="deleteTrainer"],
        body.role-manager [onclick*="editPackage"],
        body.role-manager [onclick*="deletePackage"],
        body.role-manager [onclick*="editExercise"],
        body.role-manager [onclick*="deleteExercise"],
        body.role-manager [onclick*="editEquipment"],
        body.role-manager [onclick*="deleteEquipment"],
        body.role-manager [onclick*="removeExerciseFromPackage"],
        body.role-manager [onclick*="removeAdmin"],
        body.role-manager [onclick*="removeManager"],
        body.role-manager [onclick*="confirmDelete"],
        body.role-manager [onclick*="confirmDeleteTrainer"],
        body.role-manager [onclick*="confirmDeleteExercise"],
        body.role-manager .btn-danger,
        body.role-manager .icon-btn.danger,
        body.role-manager .settings-danger-zone {
            display: none !important;
        }

        /* Add new trainer / package / exercise buttons — managers cannot create */
        body.role-manager [onclick*="openAddTrainerModal"],
        body.role-manager [onclick*="openAddExerciseModal"],
        body.role-manager [onclick*="openCreatePackageModal"],
        body.role-manager [onclick*="openAddEquipmentModal"] {
            display: none !important;
        }

        /* Bookings page — reject button hidden for managers */
        body.role-manager #rejectPaymentBtn {
            display: none !important;
        }

        /* Settings page — danger zone */
        body.role-manager .settings-danger-zone,
        body.role-manager [onclick*="exportDatabase"] {
            display: none !important;
        }
    `;
    document.head.appendChild(style);

    // ── MutationObserver: catches buttons injected by JS templates ─────────────
    // Runs every time new DOM nodes are added (e.g. when loadAllTrainers() renders cards).
    const BLOCKED_PATTERNS = [
        /editTrainer/i, /deleteTrainer/i,
        /editPackage/i, /deletePackage/i,
        /editExercise/i, /deleteExercise/i,
        /editEquipment/i, /deleteEquipment/i,
        /removeExerciseFromPackage/i,
        /removeAdmin/i, /removeManager/i,
        /confirmDelete/i,
        /openAddTrainerModal/i,
        /openAddExerciseModal/i,
        /openCreatePackageModal/i,
        /openAddEquipmentModal/i,
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
