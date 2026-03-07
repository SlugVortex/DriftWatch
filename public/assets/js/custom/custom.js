// DriftWatch custom JS
// Handles preloader dismissal, theme settings, and UI initialization

(function() {
    "use strict";

    // Hide preloader after page load
    window.addEventListener('load', function() {
        var preloader = document.getElementById('preloader');
        if (preloader) {
            preloader.style.transition = 'opacity 0.3s ease';
            preloader.style.opacity = '0';
            setTimeout(function() {
                preloader.style.display = 'none';
            }, 300);
        }
    });

    // Fallback: hide preloader after 3 seconds even if load event doesn't fire
    setTimeout(function() {
        var preloader = document.getElementById('preloader');
        if (preloader && preloader.style.display !== 'none') {
            preloader.style.transition = 'opacity 0.3s ease';
            preloader.style.opacity = '0';
            setTimeout(function() {
                preloader.style.display = 'none';
            }, 300);
        }
    }, 3000);

    // ============================================
    // Theme Settings (Dark Mode, Sidebar, Header, Footer, Card styles)
    // Uses Trezo template data-attribute pattern
    // ============================================

    // Restore saved theme preferences on page load
    document.addEventListener('DOMContentLoaded', function() {
        var html = document.documentElement;

        // Dark mode
        if (localStorage.getItem('driftwatch-dark-mode') === 'true') {
            html.setAttribute('data-theme', 'dark');
            // Sync the toggle checkbox
            var slider = document.getElementById('slider');
            if (slider) slider.checked = true;
        }

        // Sidebar dark
        if (localStorage.getItem('driftwatch-sidebar-dark') === 'true') {
            html.setAttribute('sidebar-dark-light-data-theme', 'sidebar-dark');
        }

        // Header dark
        if (localStorage.getItem('driftwatch-header-dark') === 'true') {
            html.setAttribute('Header-dark-light-data-theme', 'Header-dark');
        }

        // Footer dark
        if (localStorage.getItem('driftwatch-footer-dark') === 'true') {
            html.setAttribute('Footer-dark-light-data-theme', 'Footer-dark');
        }

        // Boxed / Fluid
        if (localStorage.getItem('driftwatch-boxed') === 'false') {
            document.body.classList.remove('boxed-size');
        }

        // Card square
        if (localStorage.getItem('driftwatch-card-square') === 'true') {
            html.setAttribute('Card-radius-square-data-theme', 'Card-square');
        }

        // Card BG gray
        if (localStorage.getItem('driftwatch-card-bg-gray') === 'true') {
            html.setAttribute('Card-bg-data-theme', 'Card-bg');
        }
    });

    // --- Dark Mode Toggle (RTL/LTR button repurposed for dark mode) ---
    // The theme_settings partial has toggleTheme() on the RTL switch — we repurpose it
    window.toggleTheme = function() {
        var html = document.documentElement;
        var isDark = html.getAttribute('data-theme') === 'dark';
        if (isDark) {
            html.removeAttribute('data-theme');
            localStorage.setItem('driftwatch-dark-mode', 'false');
        } else {
            html.setAttribute('data-theme', 'dark');
            localStorage.setItem('driftwatch-dark-mode', 'true');
        }
    };

    // --- Sidebar Dark ---
    document.addEventListener('DOMContentLoaded', function() {
        var sidebarBtn = document.getElementById('sidebar-light-dark');
        if (sidebarBtn) {
            sidebarBtn.addEventListener('click', function() {
                var html = document.documentElement;
                var isSidebarDark = html.getAttribute('sidebar-dark-light-data-theme') === 'sidebar-dark';
                if (isSidebarDark) {
                    html.removeAttribute('sidebar-dark-light-data-theme');
                    localStorage.setItem('driftwatch-sidebar-dark', 'false');
                } else {
                    html.setAttribute('sidebar-dark-light-data-theme', 'sidebar-dark');
                    localStorage.setItem('driftwatch-sidebar-dark', 'true');
                }
            });
        }

        // --- Header Dark ---
        var headerBtn = document.getElementById('header-light-dark');
        if (headerBtn) {
            headerBtn.addEventListener('click', function() {
                var html = document.documentElement;
                var isHeaderDark = html.getAttribute('Header-dark-light-data-theme') === 'Header-dark';
                if (isHeaderDark) {
                    html.removeAttribute('Header-dark-light-data-theme');
                    localStorage.setItem('driftwatch-header-dark', 'false');
                } else {
                    html.setAttribute('Header-dark-light-data-theme', 'Header-dark');
                    localStorage.setItem('driftwatch-header-dark', 'true');
                }
            });
        }

        // --- Footer Dark ---
        var footerBtn = document.getElementById('footer-light-dark');
        if (footerBtn) {
            footerBtn.addEventListener('click', function() {
                var html = document.documentElement;
                var isFooterDark = html.getAttribute('Footer-dark-light-data-theme') === 'Footer-dark';
                if (isFooterDark) {
                    html.removeAttribute('Footer-dark-light-data-theme');
                    localStorage.setItem('driftwatch-footer-dark', 'false');
                } else {
                    html.setAttribute('Footer-dark-light-data-theme', 'Footer-dark');
                    localStorage.setItem('driftwatch-footer-dark', 'true');
                }
            });
        }

        // --- Boxed / Fluid ---
        var boxedBtn = document.getElementById('boxed-style');
        if (boxedBtn) {
            boxedBtn.addEventListener('click', function() {
                var isBoxed = document.body.classList.contains('boxed-size');
                if (isBoxed) {
                    document.body.classList.remove('boxed-size');
                    localStorage.setItem('driftwatch-boxed', 'false');
                } else {
                    document.body.classList.add('boxed-size');
                    localStorage.setItem('driftwatch-boxed', 'true');
                }
            });
        }

        // --- Card Radius / Square ---
        var cardBtn = document.getElementById('card-radius-square');
        if (cardBtn) {
            cardBtn.addEventListener('click', function() {
                var html = document.documentElement;
                var isSquare = html.getAttribute('Card-radius-square-data-theme') === 'Card-square';
                if (isSquare) {
                    html.removeAttribute('Card-radius-square-data-theme');
                    localStorage.setItem('driftwatch-card-square', 'false');
                } else {
                    html.setAttribute('Card-radius-square-data-theme', 'Card-square');
                    localStorage.setItem('driftwatch-card-square', 'true');
                }
            });
        }

        // --- Card BG White / Gray ---
        var cardBgBtn = document.getElementById('card-bg');
        if (cardBgBtn) {
            cardBgBtn.addEventListener('click', function() {
                var html = document.documentElement;
                var isGray = html.getAttribute('Card-bg-data-theme') === 'Card-bg';
                if (isGray) {
                    html.removeAttribute('Card-bg-data-theme');
                    localStorage.setItem('driftwatch-card-bg-gray', 'false');
                } else {
                    html.setAttribute('Card-bg-data-theme', 'Card-bg');
                    localStorage.setItem('driftwatch-card-bg-gray', 'true');
                }
            });
        }
    });
})();
