/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

(function() {
    'use strict';

    let observer = null;
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

    function removeSplash(splash, appContent) {
        if (splash.dataset.splashRemoved === '1') {
            return;
        }

        splash.dataset.splashRemoved = '1';
        splash.hidden = true;
        splash.setAttribute('aria-hidden', 'true');

        if (appContent) {
            appContent.classList.remove('has-splash');
        }

        splash.remove();
    }

    function initSplash(splash) {
        if (window.__analyticsAppScriptLoaded !== true) {
            return false;
        }

        splash.hidden = false;
        splash.setAttribute('aria-hidden', 'false');

        const appContent = splash.closest('#content') || document.getElementById('content');
        if (appContent) {
            appContent.classList.add('has-splash');
        }

        const splashName = splash.querySelector('[data-splash-name]');
        if (splashName && !splashName.textContent.trim()) {
            splashName.textContent = typeof t === 'function'
                ? t('analytics', 'Analytics for Nextcloud')
                : 'Analytics for Nextcloud';
        }

        let cleanupDelayMs = 8000;
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!prefersReducedMotion) {
            const drawMs = parseDuration(splash.dataset.drawMs, DEFAULT_SPLASH_DURATIONS.draw);
            const revealMs = parseDuration(splash.dataset.revealMs, DEFAULT_SPLASH_DURATIONS.reveal);
            const holdMs = parseDuration(splash.dataset.holdMs, DEFAULT_SPLASH_DURATIONS.hold);
            const fadeMs = parseDuration(splash.dataset.fadeMs, DEFAULT_SPLASH_DURATIONS.fade);
            cleanupDelayMs = drawMs + revealMs + holdMs + fadeMs + 100;

            splash.style.setProperty('--splash-draw-ms', `${drawMs}ms`);
            splash.style.setProperty('--splash-reveal-ms', `${revealMs}ms`);
            splash.style.setProperty('--splash-hold-ms', `${holdMs}ms`);
            splash.style.setProperty('--splash-fade-ms', `${fadeMs}ms`);
        }

        splash.addEventListener('animationend', event => {
            if (event.animationName === 'splashHide' || event.animationName === 'splashFallbackHide') {
                removeSplash(splash, appContent);
            }
        });
        window.setTimeout(() => {
            removeSplash(splash, appContent);
        }, cleanupDelayMs);

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

        if (observer !== null && document.querySelector('[data-splash]:not([data-splash-ready])') === null) {
            observer.disconnect();
            observer = null;
        }
    }

    if (document.readyState !== 'loading') {
        initAvailableSplashes();
    }
    document.addEventListener('DOMContentLoaded', initAvailableSplashes);

    observer = new MutationObserver(() => {
        initAvailableSplashes();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
