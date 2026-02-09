// Theme Management - Shared across all pages
function loadTheme() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    
    // Apply theme instantly
    if (savedTheme === 'light') {
        document.documentElement.classList.add('light-mode');
        document.body.classList.add('light-mode');
    } else {
        document.documentElement.classList.remove('light-mode');
        document.body.classList.remove('light-mode');
    }
    
    // Update theme selector if on settings page
    if (document.querySelectorAll('.theme-option').length > 0) {
        document.querySelectorAll('.theme-option').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.theme === savedTheme) {
                btn.classList.add('active');
            }
        });
    }
}

function setTheme(theme) {
    // Apply theme instantly - no delays
    if (theme === 'light') {
        document.documentElement.classList.add('light-mode');
        document.body.classList.add('light-mode');
    } else {
        document.documentElement.classList.remove('light-mode');
        document.body.classList.remove('light-mode');
    }
    
    localStorage.setItem('theme', theme);
    
    // Update theme selector buttons if they exist (on settings page)
    document.querySelectorAll('.theme-option').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.theme === theme) {
            btn.classList.add('active');
        }
    });
}

// Make setTheme available globally for settings page
window.setTheme = setTheme;

// Auto-load theme on page load - must run immediately, before DOM is ready
// This ensures theme is applied instantly without any flash or delay
(function() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    if (savedTheme === 'light') {
        document.documentElement.classList.add('light-mode');
        document.body.classList.add('light-mode');
    } else {
        document.documentElement.classList.remove('light-mode');
        document.body.classList.remove('light-mode');
    }
})();

// Also call loadTheme when DOM is ready for UI updates (theme selector buttons)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadTheme);
} else {
    loadTheme();
}
