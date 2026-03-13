/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

(function() {
    'use strict';

    const DEFAULT_SPLASH_DURATIONS = Object.freeze({
        draw: 500,
        reveal: 800,
        hold: 1000,
        fade: 320
    });

    function parseDuration(value, fallback) {
        const parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
    }

    function initSplash(splash) {
        if (window.__analyticsAppScriptLoaded !== true) {
            return false;
        }

        splash.hidden = false;
        splash.setAttribute('aria-hidden', 'false');

        const appContent = splash.closest('#app-content') || document.getElementById('app-content');
        if (appContent) {
            appContent.classList.add('has-splash');
        }

        const splashName = splash.querySelector('[data-splash-name]');
        if (splashName && !splashName.textContent.trim()) {
            splashName.textContent = typeof t === 'function'
                ? t('analytics', 'Analytics for Nextcloud')
                : 'Analytics for Nextcloud';
        }

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!prefersReducedMotion) {
            const drawMs = parseDuration(splash.dataset.drawMs, DEFAULT_SPLASH_DURATIONS.draw);
            const revealMs = parseDuration(splash.dataset.revealMs, DEFAULT_SPLASH_DURATIONS.reveal);
            const holdMs = parseDuration(splash.dataset.holdMs, DEFAULT_SPLASH_DURATIONS.hold);
            const fadeMs = parseDuration(splash.dataset.fadeMs, DEFAULT_SPLASH_DURATIONS.fade);

            splash.style.setProperty('--splash-draw-ms', `${drawMs}ms`);
            splash.style.setProperty('--splash-reveal-ms', `${revealMs}ms`);
            splash.style.setProperty('--splash-hold-ms', `${holdMs}ms`);
            splash.style.setProperty('--splash-fade-ms', `${fadeMs}ms`);
        }

        window.requestAnimationFrame(() => {
            splash.classList.add('is-running');
        });

        return true;
    }

    function initAvailableSplashes() {
        const splashes = document.querySelectorAll('[data-splash]:not([data-splash-ready])');
        splashes.forEach(splash => {
            if (initSplash(splash)) {
                splash.setAttribute('data-splash-ready', '1');
            }
        });
    }

    if (document.readyState !== 'loading') {
        initAvailableSplashes();
    }
    document.addEventListener('DOMContentLoaded', initAvailableSplashes);

    const observer = new MutationObserver(() => {
        initAvailableSplashes();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
