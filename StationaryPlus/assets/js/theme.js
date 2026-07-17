/* ============================================================
   theme.js — Theme switch mechanism (localStorage-backed)
   Vanilla JS, no dependencies, no build step.

   Must be loaded with a plain, non-deferred/non-async <script>
   tag placed in <head> immediately after the tokens.css <link>,
   BEFORE any page <style> block, so the data-theme attribute is
   set on <html> before first paint (avoids flash-of-wrong-theme
   on reload).

   UI is a single button that cycles Light -> Dark -> High
   Contrast -> Light on each click ([data-theme-cycle]).
   ============================================================ */
(function () {
    'use strict';

    var VALID_THEMES = ['light', 'dark', 'high-contrast'];
    var STORAGE_KEY = 'theme';
    var ICONS = { light: 'fa-sun', dark: 'fa-moon', 'high-contrast': 'fa-adjust' };
    var LABELS = { light: 'Light theme', dark: 'Dark theme', 'high-contrast': 'High contrast theme' };

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

    function updateToggleButtons(name) {
        var buttons = document.querySelectorAll('[data-theme-cycle]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var icon = btn.querySelector('i');
            if (icon) icon.className = 'fas ' + ICONS[name];
            var label = LABELS[name] + ' — click to switch theme';
            btn.setAttribute('title', label);
            btn.setAttribute('aria-label', label);
        }
    }

    // Apply immediately (this script runs synchronously in <head>, before
    // the rest of the page renders).
    applyTheme(getStoredTheme());

    // Public API used by the toggle control (sidebars + pre-auth pages).
    window.getTheme = getStoredTheme;

    window.setTheme = function (name) {
        if (VALID_THEMES.indexOf(name) === -1) return;
        applyTheme(name);
        try {
            localStorage.setItem(STORAGE_KEY, name);
        } catch (e) {
            // Non-fatal — theme still applies for this page load, just won't persist.
        }
        updateToggleButtons(name);
    };

    window.cycleTheme = function () {
        var current = getStoredTheme();
        var next = VALID_THEMES[(VALID_THEMES.indexOf(current) + 1) % VALID_THEMES.length];
        window.setTheme(next);
    };

    // Wires up any [data-theme-cycle] button present on the page and sets
    // its initial icon/label. Call this inline, right after the toggle
    // control's markup, so the element already exists in the DOM at call time.
    window.initThemeToggle = function () {
        updateToggleButtons(getStoredTheme());
        var buttons = document.querySelectorAll('[data-theme-cycle]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].addEventListener('click', window.cycleTheme);
        }
    };
})();
