/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture} = require('./common');
const config = buildScenarioConfig('ocs-configuration');
(async () => {
    const browser = await chromium.launch({headless: config.headless,
        args: ['--host-resolver-rules=MAP localhost host.docker.internal']});
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const created = {reports: [], datasets: [], panoramas: [], dataloads: []};
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    const call = (path, method = 'GET', body) => page.evaluate(async ({path, method, body}) => {
        const r = await fetch(OC.generateUrl('/ocs/v2.php/apps/analytics/api/v1/' + path), {
            method, headers: Object.fromEntries([...OCA.Analytics.headers(), ['Accept', 'application/json']]),
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        return {status: r.status, envelope: await r.json()};
    }, {path, method, body});
    const ok = async (path, method, body) => {
        const r = await call(path, method, body);
        assert.ok(r.status >= 200 && r.status < 300, JSON.stringify(r));
        assert.equal(r.envelope.ocs.meta.status, 'ok', JSON.stringify(r));
        return r.envelope.ocs.data;
    };
    const create = async (kind, body) => {
        const r = await ok(kind, 'POST', body);
        assert.ok(Number(r.id) > 0, JSON.stringify(r));
        created[kind].push(Number(r.id));
        return r;
    };
    const regular = (path, method = 'GET', body) => page.evaluate(async ({path, method, body}) => {
        const r = await fetch(OC.generateUrl('/apps/analytics/' + path), {method,
            headers: OCA.Analytics.headers(), body: body === undefined ? undefined : JSON.stringify(body)});
        return r.json();
    }, {path, method, body});
    try {
        await ensureAnalyticsLoaded(page, config);
        const providers = await ok('datasources');
        assert.ok(providers.datasources['1']);
        const d = await create('datasets', {name: 'OCS configuration ' + Date.now(), dimension1: 'Region', dimension2: 'Month', value: 'Revenue', subheader: 'Created by OCS'});
        assert.equal(d.subheader, 'Created by OCS');
        await ok('datasets/' + d.id, 'PUT', {name: 'OCS data renamed'});
        const added = await ok('datasets/' + d.id + '/data/add', 'POST', {data: [
            {dimension1: 'North', dimension2: '2026-11', value: '42'},
            {dimension1: 'South', dimension2: '2026-11', value: '28'},
        ]});
        assert.equal(added.success, true);
        const options = {animation: {duration: 0}, elements: {line: {tension: 0.37}}};
        const r1 = await create('reports', {name: 'OCS chart', dataset: Number(d.id), chartoptions: options});
        const r2 = await create('reports', {name: 'OCS table', dataset: Number(d.id), visualization: 'table'});
        const implicit = await create('reports', {name: 'OCS implicit dataset', type: 2, dataset: 0});
        created.datasets.push(Number(implicit.dataset));
        assert.ok(Number(implicit.dataset) > 0);
        const edited = await ok('reports/' + r1.id, 'PUT', {chart: 'line'});
        assert.deepEqual(edited.chartoptions, options);
        assert.equal(edited.dataset, Number(d.id));
        const data = await ok('reports/' + r1.id + '/data');
        assert.equal(data.data.length, 2);
        assert.equal((await call('reports/' + r1.id, 'PUT', {dataset: 2147483647})).status, 400);
        assert.deepEqual((await ok('reports/' + r1.id + '/configuration')).chartoptions, options);
        const live = await create('reports', {name: 'OCS live settings', type: 6, dataset: 0,
            link: {url: 'https://example.test/?token=PRIVATE', path: 'sales', auth: 'PRIVATE', customHeaders: 'PRIVATE'}});
        assert.ok(!JSON.stringify(live).includes('PRIVATE'));
        assert.equal(live.value, 'Value');
        await ok('reports/' + live.id, 'PUT', {link: {path: 'orders'}});
        const rawLive = await regular('report/' + live.id);
        assert.equal(JSON.parse(rawLive.link).auth, 'PRIVATE');
        assert.equal(JSON.parse(rawLive.link).url, 'https://example.test/?token=PRIVATE');
        await ok('reports/' + live.id, 'PUT', {link: null});
        assert.deepEqual((await ok('reports/' + live.id + '/configuration')).link, {});
        const load = await create('dataloads', {name: 'OCS stored load', dataset: Number(d.id), datasource: 1, option: {link: '/test.csv', hasHeader: 'true'}});
        assert.equal((await ok('datasets/' + d.id + '/dataloads')).length, 1);
        await ok('dataloads/' + load.id, 'PUT', {name: 'OCS load renamed', schedule: 'e'});
        assert.equal((await ok('dataloads/' + load.id)).option.link, '/test.csv');
        const flexible = await create('datasets', {name: 'OCS flexible', storageMode: 'flexible_shared', columns: [
            {name: 'Region', type: 'text', role: 'dimension'}, {name: 'Revenue', type: 'decimal', role: 'measure'},
        ]});
        const firstCol = flexible.columns[0].ref, secondCol = flexible.columns[1].ref;
        const records = await ok('datasets/' + flexible.id + '/records', 'POST', {schemaVersion: flexible.schemaVersion, records: [{values: {[firstCol]: 'North', [secondCol]: '12.5'}}]});
        assert.ok(records);
        const queried = await ok('datasets/' + flexible.id + '/query', 'POST', {aggregate: false, limit: 10});
        assert.equal(queried.data.length, 1);
        const recordId = queried.recordIds[0];
        await ok('datasets/' + flexible.id + '/records/' + recordId, 'PUT', {schemaVersion: flexible.schemaVersion, values: {[firstCol]: 'North', [secondCol]: '20'}});
        await ok('datasets/' + flexible.id + '/records/' + recordId, 'DELETE');
        assert.equal((await ok('datasets/' + flexible.id + '/query', 'POST', {aggregate: false, limit: 10})).data.length, 0);
        await ok('datasets/' + flexible.id, 'PUT', {name: 'OCS flexible renamed', expectedSchemaVersion: flexible.schemaVersion});
        assert.equal((await call('datasets/' + flexible.id, 'PUT', {name: 'stale', expectedSchemaVersion: 999})).status, 409);
        const pages = [{name: 'Overview', layoutId: 5, reports: [{type: 0, value: Number(r1.id)}, {type: 0, value: Number(r2.id)}]}];
        const panorama = await create('panoramas', {name: 'OCS Panorama', pages});
        const failed = await call('panoramas', 'POST', {name: 'Invalid later call', pages: [{name: 'Bad', layoutId: 99, reports: []}]});
        assert.equal(failed.status, 400);
        assert.equal((await ok('reports/' + r1.id + '/configuration')).id, r1.id);
        pages[0].reports.reverse();
        pages[0].layoutId = 3;
        pages[0].reports.push({type: 1, value: 'Created using individual OCS calls'});
        const rearranged = await ok('panoramas/' + panorama.id, 'PUT', {pages});
        assert.equal(rearranged.pages[0].reports[0].value, Number(r2.id));
        // Verify a Chart.js setting absent from Analytics controls after navigation and reload.
        await page.goto(config.baseUrl + 'r/' + r1.id);
        await page.waitForFunction(() => Object.values(Chart.instances).some(c => c.options.elements.line.tension === 0.37));
        await page.reload();
        await page.waitForFunction(() => Object.values(Chart.instances).some(c => c.options.elements.line.tension === 0.37));
        await page.locator('#app-splash-screen').waitFor({state: 'hidden'});
        await capture('native-chart-option-after-reload');
        await page.goto(config.baseUrl + 'pa/' + panorama.id);
        await page.waitForFunction(() => document.querySelectorAll('.flex-item').length === 4 && Object.values(Chart.instances).some(c => c.options.elements.line.tension === 0.37));
        await page.reload();
        await page.waitForFunction(() => document.body.innerText.includes('Created using individual OCS calls'));
        await page.locator('#app-splash-screen').waitFor({state: 'hidden'});
        await capture('api-created-panorama-after-reload');
        const cleared = await ok('reports/' + r1.id, 'PUT', {chartoptions: null});
        assert.equal(cleared.chartoptions, null);
        assert.deepEqual(errors, []);
        console.log('PASS: individual OCS create/read/update, classic/flexible data, native option rendering/reload, source secret preservation/clear, schema conflict, and later-call failure.');
    } finally {
        // Deletion is deliberately outside the new OCS surface: use existing UI cleanup operations.
        for (const [kind, path] of [['dataloads', 'dataload'], ['panoramas', 'panorama'], ['reports', 'report'], ['datasets', 'dataset']]) {
            for (const id of created[kind].reverse()) await regular(path + '/' + id, 'DELETE').catch(() => {});
        }
        await browser.close();
    }
})().catch(e => {console.error(e); process.exit(1);});
