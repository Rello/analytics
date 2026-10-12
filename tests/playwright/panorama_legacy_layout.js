/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Seed an old-format panorama in the local test database first, and record its
// id and reports in tests/ui-artifacts/legacy-panorama/fixture.json.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture} = require('./common');
const config = buildScenarioConfig('legacy-panorama');
const fixture = JSON.parse(fs.readFileSync('tests/ui-artifacts/legacy-panorama/fixture.json', 'utf8'));

(async () => {
    const browser = await chromium.launch({headless: config.headless,
        args: new URL(config.baseUrl).hostname === 'localhost' ? ['--host-resolver-rules=MAP localhost host.docker.internal'] : [],
    });
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') console.log('BROWSER:', message.text()); });
    const read = () => page.evaluate(async id => {
        const response = await fetch(OC.generateUrl('apps/analytics/panorama'), {headers: OCA.Analytics.headers()});
        const panorama = (await response.json()).find(item => Number(item.id) === id);
        return JSON.parse(panorama.pages);
    }, fixture.id);
    const checkRendered = async () => {
        await page.locator('#app-splash-screen').waitFor({state: 'hidden'});
        await page.locator('#panoramaPages .flex-item').nth(3).waitFor({state: 'visible'});
        await page.waitForFunction(() => OCA.Analytics.PanoramaFilters.state.renders.size === 0);
        assert.equal(await page.locator('#panoramaPages .flex-item').count(), 4);
        assert.equal(await page.locator('#panoramaHeader').innerText(), 'Legacy panorama compatibility test');
        const state = await page.evaluate(() => OCA.Analytics.currentPanorama.pages[0]);
        assert.equal(state.layoutId, 3);
        assert.equal(state.layout, undefined);
        assert.deepEqual(state.reports, fixture.reports);
        // Every report must have produced a chart or populated table in its tile.
        for (let index = 0; index < 4; index++) {
            await page.waitForFunction(index => {
                const tile = document.getElementById('0-' + index);
                return !!(tile?.querySelector('canvas') && Chart.getChart(tile.querySelector('canvas'))) || !!tile?.querySelector('tbody tr');
            }, index);
        }
    };
    try {
        await ensureAnalyticsLoaded(page, config);
        const legacy = (await read())[0];
        assert.equal(legacy.layoutId, undefined);
        assert.ok(legacy.layout.includes('panoramaSubHeaderRow'));
        await page.goto(config.baseUrl + 'pa/' + fixture.id);
        await checkRendered();
        await capture('legacy-rendered');
        // Opening alone must leave the stored legacy format untouched.
        assert.equal((await read())[0].layoutId, undefined);
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        await page.locator('#panoramaSubHeader-0').fill('Overview saved');
        const [saved] = await Promise.all([
            page.waitForResponse(response => response.request().method() === 'PUT' && response.url().endsWith('/panorama/' + fixture.id)),
            page.locator('#saveIcon').click(),
        ]);
        assert.equal(await saved.json(), true);
        const stored = (await read())[0];
        assert.equal(stored.layoutId, 3);
        assert.equal(stored.layout, undefined);
        assert.deepEqual(stored.reports, fixture.reports);
        await page.reload();
        await checkRendered();
        await capture('saved-reloaded');
        assert.deepEqual(errors, []);
        console.log('PASS: legacy database panorama renders four reports, saves as layoutId 3, and renders again after reload');
    } catch (error) {
        console.log('TILES:', await page.locator('#panoramaPages .flex-item').allTextContents());
        await capture('failure');
        throw error;
    } finally {
        await page.evaluate(async id => {
            await fetch(OC.generateUrl('apps/analytics/panorama/' + id), {method: 'DELETE', headers: OCA.Analytics.headers()});
        }, fixture.id).catch(error => console.log('Cleanup failed:', error.message));
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
