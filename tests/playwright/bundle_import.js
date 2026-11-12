/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture} = require('./common');
const config = buildScenarioConfig('bundle-import');
const bundle = JSON.parse(fs.readFileSync('resources/demo.json', 'utf8'));
(async () => {
    const browser = await chromium.launch({headless: config.headless,
        args: ['--host-resolver-rules=MAP localhost host.docker.internal']});
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const issues = [];
    page.on('pageerror', error => issues.push(error.message));
    const api = (path, method = 'GET', body) => page.evaluate(async ({path, method, body}) => {
        const response = await fetch(OC.generateUrl('apps/analytics/' + path), {
            method, headers: OCA.Analytics.headers(), body: body === undefined ? undefined : JSON.stringify(body),
        });
        return {status: response.status, data: await response.json()};
    }, {path, method, body});
    let initial;
    const createdObjects = {reports: new Set(), datasets: new Set(), panoramas: new Set()};
    const track = result => {
        for (const kind of Object.keys(createdObjects)) for (const id of Object.values(result[kind] || {})) createdObjects[kind].add(Number(id));
        for (const folder of bundle.folders) if (result.folders?.[folder.key]) createdObjects[folder.scope].add(Number(result.folders[folder.key]));
    };
    let filePath;
    const state = async () => ({reports: (await api('report')).data, datasets: (await api('dataset')).data, panoramas: (await api('panorama')).data});
    try {
        await ensureAnalyticsLoaded(page, config);
        initial = await state();
        const invalid = structuredClone(bundle);
        invalid.panoramas[0].pages[0].reports[0].value = 'foreign-report';
        assert.equal((await api('report/import/', 'POST', {raw: JSON.stringify(invalid)})).status, 400);
        const afterInvalid = await state();
        for (const kind of Object.keys(initial)) assert.equal(afterInvalid[kind].length, initial[kind].length);

        const first = await page.evaluate(async bundle => OCA.Analytics.Sidebar.Report.import(null, JSON.stringify(bundle), null,
            {refreshNavigation: false, rethrowError: true}), bundle);
        track(first);
        assert.equal(first.kind, 'bundle');
        assert.equal(first.reports['annual-revenue'] > 0, true);
        const revenue = (await api('report/' + first.reports['annual-revenue'])).data;
        const growth = (await api('report/' + first.reports['revenue-growth'])).data;
        const ranking = (await api('report/' + first.reports['sales-ranking'])).data;
        const raw = (await api('report/' + first.reports['sales-raw-data'])).data;
        assert.equal(revenue.dataset, growth.dataset);
        assert.equal(ranking.dataset, raw.dataset);
        assert.equal(Number(revenue.parent), first.folders['demo-reports']);
        const panorama = (await api('panorama/' + first.panoramaId)).data;
        const pages = JSON.parse(panorama.pages);
        assert.equal(pages[0].reports[5].value, first.reports['sales-ranking']);
        for (const filter of JSON.parse(panorama.filters)) {
            assert.equal(filter.showInHeader, true);
            for (const mapping of filter.mappings) assert.ok(Object.values(first.reports).includes(mapping.reportId));
        }
        const show = async id => {
            await page.goto(config.baseUrl + 'pa/' + id);
            await page.waitForFunction(() => document.querySelectorAll('.kpiWidgetValue').length === 2
                && ['0-2', '0-3', '0-4', '0-5'].every(id => Chart.getChart('myWidget' + id)?.data.datasets.length));
            await page.locator('#app-splash-screen').waitFor({state: 'hidden'});
            await page.locator('#panoramaQuickFilters select').first().waitFor({state: 'visible'});
            const values = await page.locator('.kpiWidgetValue').allTextContents();
            assert.ok(values[0].includes('€'));
            assert.ok(values[1].includes('%'));
        };
        await show(first.panoramaId);
        await capture('imported-six-tile-panorama');
        await show(first.panoramaId);
        // Header filter selections target the newly imported reports, not source installation IDs.
        const scenario = JSON.parse(panorama.filters).find(filter => filter.label === 'Scenario');
        const filtered = await api('data/pa/' + first.reports['monthly-revenue-vs-target'] + '?'
            + new URLSearchParams({panoramaId: first.panoramaId, variables: JSON.stringify({[scenario.id]: 'Actual'})}));
        assert.equal(filtered.status, 200);
        assert.equal(Number(filtered.data.error), 0);
        assert.equal(filtered.data.data.length, 12);
        assert.ok(filtered.data.data.every(row => row.includes('Actual')));

        // Exercise the normal Nextcloud file import route as well as inline JSON.
        filePath = '/analytics-bundle-test-' + Date.now() + '.json';
        const upload = await page.evaluate(async ({user, filePath, bundle}) => {
            const response = await fetch(OC.generateUrl('/remote.php/dav/files/' + encodeURIComponent(user) + filePath), {
                method: 'PUT', headers: OCA.Analytics.headers(), body: JSON.stringify(bundle),
            });
            return response.status;
        }, {user: config.user, filePath, bundle});
        assert.ok([201, 204].includes(upload));
        const second = (await api('report/import/', 'POST', {path: filePath})).data;
        track(second);
        assert.equal(second.kind, 'bundle');
        assert.notEqual(second.panoramaId, first.panoramaId);
        assert.notEqual(second.datasets['performance-kpis'], first.datasets['performance-kpis']);

        const legacyReport = bundle.reports.find(report => report.key === 'sales-ranking');
        const legacyDataset = bundle.datasets.find(dataset => dataset.key === legacyReport.dataset);
        const oldFile = JSON.stringify({report: {...legacyReport, type: 2, link: '{}',
            dimension1: legacyDataset.dimension1, dimension2: legacyDataset.dimension2, value: legacyDataset.value,
            ...Object.fromEntries(['chartoptions', 'dataoptions', 'filteroptions', 'tableoptions'].map(key => [key, JSON.stringify(legacyReport[key])]))},
            data: legacyDataset.data, dataload: [], threshold: []});
        const legacy = await api('report/import/', 'POST', {raw: oldFile});
        assert.equal(legacy.status, 200);
        assert.ok(Number.isInteger(legacy.data) && legacy.data > 0);
        createdObjects.reports.add(legacy.data);
        createdObjects.datasets.add(Number((await api('report/' + legacy.data)).data.dataset));
        assert.equal((await api('data/' + legacy.data)).data.error, 0);

        // Install through the first-start endpoint, then exercise its actual UI launcher.
        const installation = await api('demo', 'POST');
        assert.equal(installation.status, 200);
        track(installation.data);
        await Promise.all([
            page.waitForURL(url => /\/pa\/\d+$/.test(url.pathname) && !url.pathname.endsWith('/pa/' + first.panoramaId)),
            page.evaluate(() => { OCA.Analytics.Wizard.demo(); }),
        ]);
        const installed = {panoramaId: Number(new URL(page.url()).pathname.split('/').pop())};
        assert.ok(installed.panoramaId > 0);
        const again = await api('demo', 'POST');
        assert.equal(again.data.panoramaId, installed.panoramaId);
        assert.equal(again.data.reused, true);
        if (!initial.panoramas.some(item => Number(item.id) === installed.panoramaId)) {
            await api('panorama/' + installed.panoramaId + '/rename', 'PUT', {name: 'User-edited installed demo'});
            await api('demo', 'POST');
            assert.equal((await api('panorama/' + installed.panoramaId)).data.name, 'User-edited installed demo');
        }
        assert.deepEqual(issues, []);
        console.log('PASS: inline/file bundles, shared datasets, remapped tiles/filters, normal report import, first-start navigation and repeat-install preservation.');
    } catch (error) {
        await capture('failure').catch(() => {});
        throw error;
    } finally {
        // Never remove the user's source demo or any pre-existing object.
        if (initial) {
            if (filePath) await page.evaluate(async ({user, filePath}) => fetch(OC.generateUrl('/remote.php/dav/files/' + encodeURIComponent(user) + filePath),
                {method: 'DELETE', headers: OCA.Analytics.headers()}), {user: config.user, filePath}).catch(() => {});
            const current = await state().catch(() => null);
            if (current) for (const [kind, endpoint] of [['panoramas', 'panorama'], ['reports', 'report'], ['datasets', 'dataset']]) {
                const created = current[kind].filter(item => createdObjects[kind].has(Number(item.id))).sort((a, b) => Number(b.type) - Number(a.type));
                for (const item of created) await api(endpoint + '/' + item.id, 'DELETE');
            }
        }
        await browser.close();
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
