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

    // Update the toggle button icon if it exists
    updateToggleButtonIcon(savedTheme);
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

    // Update the toggle button icon
    updateToggleButtonIcon(theme);
}

function toggleTheme() {
    const currentTheme = localStorage.getItem('theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
}

function updateToggleButtonIcon(theme) {
    const toggleBtns = document.querySelectorAll('.theme-toggle-btn i');
    toggleBtns.forEach(icon => {
        if (theme === 'light') {
            icon.className = 'fas fa-moon'; // Show moon icon when in light mode (to switch to dark)
        } else {
            icon.className = 'fas fa-sun'; // Show sun icon when in dark mode (to switch to light)
        }
    });
}

// Make setTheme and toggleTheme available globally for settings and dashboards
window.setTheme = setTheme;
window.toggleTheme = toggleTheme;

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

// Also call loadTheme when DOM is ready for UI updates (theme selector buttons & toggle)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadTheme);
} else {
    loadTheme();
}
