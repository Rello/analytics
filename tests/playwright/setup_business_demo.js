/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// Opt-in persistent setup uses the same versioned bundle and installer as first start.
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture} = require('./common');
const config = buildScenarioConfig('business-demo');
(async () => {
    const browser = await chromium.launch({headless: config.headless,
        args: ['--host-resolver-rules=MAP localhost host.docker.internal']});
    const page = await browser.newPage({viewport: config.viewport});
    try {
        await ensureAnalyticsLoaded(page, config);
        const result = await page.evaluate(async () => {
            const response = await fetch(OC.generateUrl('apps/analytics/demo'), {method: 'POST', headers: OCA.Analytics.headers()});
            if (!response.ok) throw new Error('Demo installation failed');
            return response.json();
        });
        assert.ok(result.panoramaId > 0);
        await page.goto(config.baseUrl + 'pa/' + result.panoramaId);
        await page.waitForFunction(() => document.querySelectorAll('.kpiWidgetValue').length === 2
            && ['0-2', '0-3', '0-4', '0-5'].every(id => Chart.getChart('myWidget' + id)?.data.datasets.length));
        await page.locator('#app-splash-screen').waitFor({state: 'hidden'});
        await createCapture(page, config)('overview');
        console.log(JSON.stringify({status: 'PASS', ...result, url: config.baseUrl + 'pa/' + result.panoramaId}));
    } finally {
        await browser.close();
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
