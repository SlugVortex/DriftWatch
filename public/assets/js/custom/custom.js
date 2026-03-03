// DriftWatch custom JS
// Handles preloader dismissal and other UI initialization

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
})();
