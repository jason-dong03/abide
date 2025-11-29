
(function() {
'use strict';

function getCurrentTheme() {
    return localStorage.getItem('theme') || 'light';
}

function saveTheme(theme) {
    localStorage.setItem('theme', theme);
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    updateThemeSelect(theme);
}

function updateThemeSelect(theme) {
    const themeSelect = document.getElementById('themeSelect');
    if (themeSelect) {
    themeSelect.value = theme === 'light' ? 'Light Mode' : 'Dark Mode';
    }
}

function toggleTheme() {
    const currentTheme = getCurrentTheme();
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    saveTheme(newTheme);
    applyTheme(newTheme);
}

function initTheme() {
    const theme = getCurrentTheme();
    applyTheme(theme);
}


function setupThemeSelect() {
    const themeSelect = document.getElementById('themeSelect');
    if (themeSelect) {
    themeSelect.addEventListener('change', function() {
        const newTheme = this.value === 'Light Mode' ? 'light' : 'dark';
        saveTheme(newTheme);
        applyTheme(newTheme);
    });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    setupThemeSelect();
    });
} else {
    initTheme();
    setupThemeSelect();
}

window.toggleTheme = toggleTheme;
})();