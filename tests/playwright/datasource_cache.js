/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded} = require('./common');
const config = buildScenarioConfig('datasource-cache');
(async () => {
    const browser = await chromium.launch({headless: config.headless});
    try {
        const page = await browser.newPage({viewport: config.viewport});
        await ensureAnalyticsLoaded(page, config);
        const result = await page.evaluate(async () => {
            const source = OCA.Analytics.Datasource;
            const originalFetch = window.fetch;
            const payload = {datasources: {99: 'Tables'}, options: {99: [{id: 'table'}]}, reportTemplates: {}};
            let calls = 0;
            let fail = false;
            window.fetch = async () => {
                calls++;
                await new Promise(resolve => setTimeout(resolve, 10));
                return {ok: !fail, json: async () => structuredClone(payload)};
            };
            try {
                const [first, second] = await Promise.all([
                    source.loadMetadata('/cache-test'), source.loadMetadata('/cache-test')
                ]);
                first.options[99][0].id = 'modified';
                const cached = await source.loadMetadata('/cache-test');
                const sharedCalls = calls;
                source.metadataCache.get('/cache-test').loadedAt -= 60001;
                await source.loadMetadata('/cache-test');
                const expiredCalls = calls;
                fail = true;
                await source.loadMetadata('/cache-failure').catch(() => {});
                const failureCached = source.metadataCache.has('/cache-failure');
                fail = false;
                await source.loadMetadata('/cache-failure');
                const retryCalls = calls;
                const select = document.createElement('select');
                select.id = 'cache-test-select';
                document.body.append(select);
                const loading = source.buildDropdown(select.id, 99998);
                select.remove();
                const replacement = document.createElement('select');
                replacement.id = select.id;
                document.body.append(replacement);
                const staleResult = await loading;
                replacement.remove();
                return {sharedCalls, expiredCalls, failureCached, retryCalls, staleResult,
                    copies: second.options[99][0].id === 'table' && cached.options[99][0].id === 'table'};
            } finally {
                window.fetch = originalFetch;
            }
        });
        assert.deepEqual(result, {sharedCalls: 1, expiredCalls: 2, failureCached: false,
            retryCalls: 4, staleResult: false, copies: true});
        await page.reload();
        await page.waitForFunction(() => !!window.OCA?.Analytics?.Datasource?.metadataCache);
        assert.equal(await page.evaluate(() => OCA.Analytics.Datasource.metadataCache.has('/cache-test')), false);
        console.log('PASS: cache reuse, concurrent requests, isolated copies, expiry, failure retry, stale dropdown and reload');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
