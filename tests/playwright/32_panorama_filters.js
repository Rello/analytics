/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, ensureStoredReportWithDefaultData, createCapture} = require('./common');
const config = buildScenarioConfig('32-panorama-filters');

(async () => {
    const browser = await chromium.launch({headless: config.headless,
        // Reach the host's localhost-only test instance from the Docker test runner.
        args: new URL(config.baseUrl).hostname === 'localhost' ? ['--host-resolver-rules=MAP localhost host.docker.internal'] : [],
    });
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let panoramaId;
    let freshPanoramaId;
    let reportId;
    let copyId;
    let datasetId;
    const api = (path, method = 'GET', body) => page.evaluate(async ({path, method, body}) => {
        const response = await fetch(OC.generateUrl('apps/analytics/' + path), {
            method, headers: OCA.Analytics.headers(), body: body === undefined ? undefined : JSON.stringify(body),
        });
        return {status: response.status, data: await response.json(), cacheable: response.headers.get('X-Analytics-Cacheable')};
    }, {path, method, body});
    const idle = () => page.waitForFunction(() => OCA.Analytics.PanoramaFilters.state?.renders.size === 0);
    try {
        await ensureAnalyticsLoaded(page, config);
        // Exercise the actual creation response, before any navigation-list reload.
        await page.evaluate(() => OCA.Analytics.Panorama.newPanorama());
        await page.locator('#panoramaLayoutGrid .panoramaLayoutGridCell').first().waitFor();
        freshPanoramaId = await page.evaluate(() => OCA.Analytics.currentPanorama.id);
        await page.locator('#panoramaLayoutGrid .panoramaLayoutGridCell[id="5"]').click();
        await page.locator('#panoramaConfigureFilters').waitFor();
        assert.equal(await page.locator('#optionsMenuPanoramaEdit').isDisabled(), false);
        await page.locator('#panoramaConfigureFilters').click();
        await page.locator('#analyticsDialogBtnCancel').click();
        const [createdSave] = await Promise.all([
            page.waitForResponse(r => r.request().method() === 'PUT' && r.url().endsWith('/panorama/' + freshPanoramaId)),
            page.locator('#saveIcon').click(),
        ]);
        assert.equal(await createdSave.json(), true, 'a newly created panorama saves before reloading');
        assert.equal((await api('panorama/' + freshPanoramaId)).data.permissions, 2);

        const grouping = await page.evaluate(() => {
            const filters = OCA.Analytics.PanoramaFilters;
            const metadata = new Map([[1, {dimensions: {dimension1: ' Date ', dimension2: 'Country'}}],
                [2, {dimensions: {0: 'date', 1: 'Country'}}], [3, {dimensions: {0: 'Date', 1: 'DATE'}}]]);
            const suggestions = filters.suggestions([], metadata);
            const date = suggestions.find(f => f.label.trim() === 'Date');
            return {targets: date.mappings.map(m => m.reportId), count: suggestions.length,
                remaining: filters.suggestions([date], metadata).flatMap(f => f.mappings).filter(m => m.dimensionLabel.trim().toLowerCase() === 'date').length};
        });
        assert.deepEqual(grouping, {targets: [1, 2], count: 2, remaining: 0});
        await ensureStoredReportWithDefaultData(page, 'Panorama filters ' + Date.now(), 'Isolated panorama filter test');
        reportId = await page.evaluate(() => Number(OCA.Analytics.currentDataset));
        const initial = (await api('report/' + reportId)).data;
        assert.equal((await api('report/' + reportId, 'PUT', {...initial, link: initial.link || '{}', visualization: 'table'})).data, true);
        const original = (await api('report/' + reportId)).data;
        datasetId = original.dataset;
        copyId = (await api('report/copy', 'POST', {reportId, chartoptions: null, dataoptions: null, filteroptions: null, tableoptions: null})).data;
        panoramaId = (await api('panorama', 'POST', {type: 1, parent: 0})).data;
        const panorama = {name: 'Panorama filters regression', type: 1, parent: 0, filters: [], pages: [
            {name: 'Overview', layoutId: 5, reports: [{type: 0, value: reportId}, {type: 0, value: reportId}]},
            {name: 'Details', layoutId: 5, reports: [{type: 0, value: copyId}]},
        ]};
        assert.equal((await api('panorama/' + panoramaId, 'PUT', panorama)).data, true);
        await page.goto(config.baseUrl + 'pa/' + panoramaId);
        await page.waitForFunction(() => OCA.Analytics.PanoramaFilters?.state?.metadata.size > 0);
        await idle();
        assert.equal(await page.locator('#addFilterIcon').isVisible(), false);
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        await page.locator('#panoramaConfigureFilters').click();
        await page.locator('.panoramaFilterDefinition').first().waitFor();
        const rows = page.locator('.panoramaFilterDefinition');
        assert.equal(await rows.count(), 2);
        // Enable Column 2. The owner can map dimensions manually using the checkboxes.
        const variable = rows.nth(1);
        assert.equal(await variable.locator('.panoramaFilterSettings input').first().isDisabled(), true);
        await variable.locator('input[type=checkbox]').first().check();
        assert.equal(await variable.locator('.panoramaFilterSettings input').first().isDisabled(), false);
        assert.ok(await variable.locator('.panoramaFilterMappingRow').count() > 0);
        await variable.locator('input[type=text]').first().fill('Shared category');
        await variable.locator('input[type=text]').nth(1).fill('Dimension 2');
        await variable.locator('.panoramaFilterMappings input[type=checkbox]').nth(1).uncheck();
        await variable.locator('.panoramaFilterMappings input[type=checkbox]').nth(1).check();
        await capture('configuration');
        await page.locator('#analyticsDialogBtnGo').click();
        const definitions = await page.evaluate(() => OCA.Analytics.currentPanorama.filters);
        assert.equal(definitions.filter(f => f.enabled).length, 1);
        panorama.filters = definitions;
        const [saved] = await Promise.all([
            page.waitForResponse(r => r.request().method() === 'PUT' && r.url().endsWith('/panorama/' + panoramaId)),
            page.locator('#saveIcon').click(),
        ]);
        assert.equal(await saved.json(), true);
        await idle();
        // Legacy callers omit the new field without erasing saved definitions.
        const legacy = {...panorama}; delete legacy.filters;
        assert.equal((await api('panorama/' + panoramaId, 'PUT', legacy)).data, true);
        assert.deepEqual(JSON.parse((await api('panorama/' + panoramaId)).data.filters), definitions);
        const variableId = definitions.find(f => f.enabled).id;
        let filteredRequests = 0;
        page.on('request', request => { if (request.url().includes('/data/pa/') && request.url().includes('variables=')) filteredRequests++; });
        await page.reload();
        await page.locator('#addFilterIcon').waitFor();
        await page.locator('#app-splash-screen').waitFor({state: 'hidden'});
        await idle();
        assert.equal(filteredRequests, 2, 'each distinct report has one request across all widgets');
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 2);
        await page.locator('#addFilterIcon').click();
        const targets = await page.locator('.filterReportNames').innerText();
        assert.match(targets, /Panorama filters/);
        assert.doesNotMatch(targets, /Overview|Details|\(1\)|\(2\)/);
        assert.equal(await page.locator('.filterReportNames > div').count(), 2);
        await page.locator('#filterDialogValue').fill('Threshold Test');
        await page.locator('#analyticsDialogBtnCancel').click();
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 2);
        await page.locator('#addFilterIcon').click();
        await page.locator('#filterDialogOption').selectOption('LIKE');
        await page.locator('#filterDialogValue').fill('Threshold');
        await page.locator('#analyticsDialogBtnGo').click();
        await idle();
        assert.match(await page.locator('#myWidget0-0').innerText(), /2/);
        assert.deepEqual(await page.evaluate(() => OCA.Analytics.PanoramaFilters.state.values[OCA.Analytics.PanoramaFilters.state.definitions.find(f => f.enabled).id]), [{option: 'LIKE', value: 'Threshold'}]);
        assert.equal(await page.locator('#filterVisualisation .filterVisualizationItem').count(), 1);
        await page.locator('#filterVisualisation .filterVisualizationItem').first().locator('.filterVisualizationRemove').click();
        await idle();
        assert.equal(await page.locator('#filterVisualisation .filterVisualizationItem').count(), 0);
        await page.locator('#addFilterIcon').click();
        await page.locator('#filterDialogOption').selectOption('LIKE');
        await page.locator('#filterDialogValue').fill('Threshold');
        await page.locator('#addFilterRowButton').click();
        assert.equal(await page.locator('#filterDialogOption1').inputValue(), 'EQ');
        await page.locator('#filterDialogValue1').fill('Dimension 2');
        await page.locator('#analyticsDialogBtnGo').click();
        await idle();
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 3);
        await page.locator('#addFilterIcon').click();
        await page.locator('.icon-analytics-filterRow-remove').click();
        await page.locator('#analyticsDialogBtnGo').click();
        await idle();
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 2);
        // Superseded responses must not replace the final selection.
        await page.evaluate(id => {
            OCA.Analytics.PanoramaFilters.apply({[id]: 'missing'});
            OCA.Analytics.PanoramaFilters.apply({[id]: 'Dimension 2'});
        }, variableId);
        await idle();
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 2);
        await page.evaluate(() => OCA.Analytics.Panorama.navigatePage('next'));
        await page.locator('#addFilterIcon').click();
        await page.locator('#analyticsDialogContainer').evaluate(async node => {
            await Promise.all(node.getAnimations().map(animation => animation.finished));
        });
        await capture('viewer');
        await page.locator('#analyticsDialogBtnLeading').click();
        await idle();
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 4);
        const query = '?panoramaId=' + panoramaId + '&variables=' + encodeURIComponent(JSON.stringify({[variableId]: 'Dimension 2'}));
        const filtered = await api('data/pa/' + reportId + query);
        assert.equal(filtered.status, 200);
        assert.equal(filtered.cacheable, 'false');
        assert.equal(filtered.data.data.length, 2);
        assert.equal((await api('data/pa/' + reportId + '?panoramaId=' + panoramaId + '&variables=' + encodeURIComponent('{"unknown":"x"}'))).status, 400);
        const broken = structuredClone(panorama);
        broken.filters.find(f => f.enabled).mappings[0].dimensionLabel = 'Renamed';
        assert.equal((await api('panorama/' + panoramaId, 'PUT', broken)).data, true);
        assert.equal((await api('data/pa/' + reportId + query)).status, 400);
        await page.reload();
        await page.waitForFunction(() => OCA.Analytics.PanoramaFilters?.state?.errors.size > 0);
        await idle();
        assert.match(await page.locator('#myWidget0-0').innerText(), /dimension has changed/);
        assert.deepEqual((await api('report/' + reportId)).data, original, 'source report is unchanged');
        assert.deepEqual(errors, []);
        console.log('PASS panorama configuration, defaults, mapping, apply/cancel/reset, pages, deduplication, stale requests, cache and dimension validation');
    } catch (error) {
        await capture('failure');
        console.error(error);
        console.error('Page errors:', errors);
        process.exitCode = 1;
    } finally {
        if (freshPanoramaId) await api('panorama/' + freshPanoramaId, 'DELETE').catch(() => {});
        if (panoramaId) await api('panorama/' + panoramaId, 'DELETE').catch(() => {});
        if (copyId) await api('report/' + copyId, 'DELETE').catch(() => {});
        if (reportId) await api('report/' + reportId, 'DELETE').catch(() => {});
        if (datasetId) await api('dataset/' + datasetId, 'DELETE').catch(() => {});
        await browser.close();
    }
})();
