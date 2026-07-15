/* ============================================================
   theme.js — Theme switch mechanism (localStorage-backed)
   Vanilla JS, no dependencies, no build step.

   Must be loaded with a plain, non-deferred/non-async <script>
   tag placed in <head> immediately after the tokens.css <link>,
   BEFORE any page <style> block, so the data-theme attribute is
   set on <html> before first paint (avoids flash-of-wrong-theme
   on reload).
   ============================================================ */
(function () {
    'use strict';

    var VALID_THEMES = ['light', 'dark', 'high-contrast'];
    var STORAGE_KEY = 'theme';

    function getStoredTheme() {
        var stored = null;
        try {
            stored = localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            // localStorage unavailable (private mode / disabled) — fall back to light.
            stored = null;
        }
        return VALID_THEMES.indexOf(stored) !== -1 ? stored : 'light';
    }

    function applyTheme(name) {
        // 'light' is the base :root in tokens.css — no attribute needed, and
        // omitting it (rather than setting data-theme="light") means the
        // [data-theme="dark"] / [data-theme="high-contrast"] override blocks
        // simply don't match, so :root's values apply as-is.
        if (name === 'light') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', name);
        }
    }

    // Apply immediately (this script runs synchronously in <head>, before
    // the rest of the page renders).
    applyTheme(getStoredTheme());

    // Public API used by toggle controls (sidebars + pre-auth pages).
    window.setTheme = function (name) {
        if (VALID_THEMES.indexOf(name) === -1) return;
        applyTheme(name);
        try {
            localStorage.setItem(STORAGE_KEY, name);
        } catch (e) {
            // Non-fatal — theme still applies for this page load, just won't persist.
        }
        // Reflect the new active theme on any toggle controls already in the DOM.
        var buttons = document.querySelectorAll('[data-theme-option]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            btn.classList.toggle('active', btn.getAttribute('data-theme-option') === name);
            btn.setAttribute('aria-pressed', btn.getAttribute('data-theme-option') === name ? 'true' : 'false');
        }
    };

    // Wires up any [data-theme-option] buttons present on the page and marks
    // the currently-active one. Call this inline, right after the toggle
    // control's markup, so the elements already exist in the DOM at call time.
    window.initThemeToggle = function () {
        var current = getStoredTheme();
        var buttons = document.querySelectorAll('[data-theme-option]');
        for (var i = 0; i < buttons.length; i++) {
            (function (btn) {
                var isActive = btn.getAttribute('data-theme-option') === current;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                btn.addEventListener('click', function () {
                    window.setTheme(btn.getAttribute('data-theme-option'));
                });
            })(buttons[i]);
        }
    };
})();
