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
        const headingStyles = await page.locator('#panoramaHeader, .panoramaSubHeader').evaluateAll(headings =>
            headings.map(heading => {
                const properties = ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'color', 'padding', 'margin', 'borderWidth', 'borderRadius', 'backgroundColor'];
                const styles = () => Object.fromEntries(properties.map(property => [property, getComputedStyle(heading)[property]]));
                const editing = styles();
                heading.removeAttribute('contenteditable');
                const viewing = styles();
                heading.setAttribute('contenteditable', 'true');
                return {editing, viewing};
            }));
        headingStyles.forEach(({editing, viewing}) => assert.deepEqual(editing, viewing, 'editing retains the normal heading typography and spacing'));
        assert.equal(await page.locator('#panoramaQuickFilters #panoramaConfigureFilters').isVisible(), true);
        await capture('inline-title-editing');
        assert.equal(await page.locator('#optionsMenuPanoramaEdit').isDisabled(), false);
        assert.equal(await page.locator('#optionsMenuPanoramaLayout').isDisabled(), false);
        assert.equal(await page.locator('#optionsMenuPanoramaDeletePage').isDisabled(), false);
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
        const pruning = await page.evaluate(() => {
            const filters = OCA.Analytics.PanoramaFilters;
            const original = OCA.Analytics.currentPanorama;
            const editMode = OCA.Analytics.editMode;
            const mapping = reportId => ({reportId, dimension: 'dimension1', dimensionLabel: 'Category'});
            const definition = (id, ids, enabled = true) => ({id, label: id, enabled, operator: 'EQ',
                defaultValue: '', mappings: ids.map(mapping)});
            try {
                OCA.Analytics.editMode = true;
                OCA.Analytics.currentPanorama = {...original,
                    pages: [{reports: [{type: 0, value: 1}, {type: 0, value: 1}]}, {reports: [{type: 0, value: 2}]}],
                    filters: [definition('exclusive', [1]), definition('shared', [1, 2]),
                        definition('disabled', [1], false)]};
                filters.reset();
                filters.state.values = {exclusive: 'A', shared: 'B'};
                OCA.Analytics.currentPanorama.pages[0].reports.pop();
                filters.pruneRemovedReports();
                const retained = filters.definitions().map(f => f.id);
                // Replacing the last occurrence removes exclusive filters, including disabled ones.
                OCA.Analytics.currentPanorama.pages[0].reports[0] = {type: 0, value: 3};
                filters.pruneRemovedReports();
                const replaced = structuredClone(filters.definitions());
                const values = structuredClone(filters.state.values);
                // Deleting the remaining mapped page removes the shared filter as well.
                OCA.Analytics.currentPanorama.pages.pop();
                filters.pruneRemovedReports();
                return {retained, replaced: replaced.map(f => [f.id, f.mappings.map(m => m.reportId)]),
                    values, remaining: filters.definitions().length, stateRemaining: filters.state.definitions.length};
            } finally {
                OCA.Analytics.currentPanorama = original;
                OCA.Analytics.editMode = editMode;
                filters.reset();
                filters.updateButtons();
            }
        });
        assert.deepEqual(pruning, {retained: ['exclusive', 'shared', 'disabled'],
            replaced: [['shared', [2]]], values: {shared: 'B'}, remaining: 0, stateRemaining: 0});

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
        assert.equal(await rows.first().locator('.panoramaFilterSettings').isVisible(), false);
        assert.equal(await variable.locator('.panoramaFilterSettings').isVisible(), false);
        await variable.locator('.panoramaFilterSummary').focus();
        await page.keyboard.press('Enter');
        assert.equal(await variable.locator('.panoramaFilterSummary').getAttribute('aria-expanded'), 'true');
        assert.equal(await variable.locator('.panoramaFilterSettings input').first().isDisabled(), true);
        await variable.locator('input[type=checkbox]').first().check();
        assert.equal(await variable.locator('.panoramaFilterSettings input').first().isDisabled(), false);
        assert.ok(await variable.locator('.panoramaFilterMappingRow').count() > 0);
        const showInHeader = variable.getByLabel('Show in panorama header', {exact: true});
        assert.equal(await showInHeader.isChecked(), true);
        await showInHeader.uncheck();
        await showInHeader.check();
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
        assert.equal(await page.locator('#optionsMenuPanoramaEdit').isDisabled(), false);
        assert.equal(await page.locator('#optionsMenuPanoramaLayout').isDisabled(), true);
        assert.equal(await page.locator('#optionsMenuPanoramaDeletePage').isDisabled(), true);
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        assert.equal(await page.locator('#optionsMenuPanoramaLayout').isDisabled(), false);
        assert.equal(await page.locator('#optionsMenuPanoramaDeletePage').isDisabled(), false);
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        assert.equal(await page.locator('#optionsMenuPanoramaLayout').isDisabled(), true);
        assert.equal(await page.locator('#optionsMenuPanoramaDeletePage').isDisabled(), true);
        assert.equal(filteredRequests, 2, 'each distinct report has one request across all widgets');
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 2);
        const quick = page.locator('#panoramaQuickFilters select');
        await quick.waitFor();
        assert.equal(await quick.count(), 1);
        await page.waitForFunction(() => document.querySelector('#panoramaQuickFilters select').options.length > 2);
        const quickOptions = await quick.locator('option').allTextContents();
        assert.ok(quickOptions.includes('Dimension 2'));
        assert.ok(quickOptions.includes('Threshold Test'));
        await quick.selectOption('Threshold Test');
        await idle();
        assert.deepEqual(await page.evaluate(id => OCA.Analytics.PanoramaFilters.state.values[id], variableId),
            [{option: 'EQ', value: 'Threshold Test'}]);
        assert.equal(await page.locator('#filterVisualisation .filterVisualizationItem').count(), 0);
        assert.deepEqual(await quick.locator('option').allTextContents(), quickOptions, 'drilldown choices survive filtering');
        await page.locator('#panoramaQuickFilters .panoramaQuickFilterReset').click();
        await idle();
        assert.equal(await quick.inputValue(), 'Dimension 2', 'reset restores the configured default');
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        assert.equal(await page.locator('#panoramaQuickFilters').isVisible(), true);
        assert.equal(await page.locator('#panoramaConfigureFilters').isVisible(), true);
        assert.equal(await quick.isVisible(), false);
        assert.equal(await page.locator('#saveIcon').isVisible(), true, 'save remains available when entering edit mode without changes');
        assert.equal(await page.locator('#fullscreenToggle').isVisible(), false);
        assert.equal(await page.locator('#panoramaQuickFilters .panoramaQuickFilterReset').isVisible(), false);
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        assert.equal(await page.locator('#panoramaQuickFilters').isVisible(), true);
        assert.equal(await page.locator('#fullscreenToggle').isVisible(), true);
        const wheelNavigation = await page.evaluate(() => {
            const panorama = OCA.Analytics.Panorama;
            const root = document.getElementById('analytics-content-panorama');
            const wheel = (options, target = root) => {
                const event = new WheelEvent('wheel', {bubbles: true, cancelable: true, ...options});
                target.dispatchEvent(event);
                return {page: OCA.Analytics.currentPage, prevented: event.defaultPrevented};
            };
            panorama.navigatePage('start');
            panorama.pageWheel = null;
            const vertical = wheel({deltaY: 100});
            const forward = wheel({deltaX: 100});
            const momentum = wheel({deltaX: 100});
            const back = wheel({deltaX: -100});
            const backMomentum = wheel({deltaX: -100});
            const forwardAgain = wheel({deltaX: 100});
            const shiftBack = wheel({deltaY: -100, shiftKey: true});
            panorama.pageWheel = null;
            const boundary = wheel({deltaX: -100});
            panorama.pageWheel = null;
            const zoom = wheel({deltaX: 100, ctrlKey: true});
            const table = document.createElement('div');
            table.style.cssText = 'width:100px;overflow-x:auto';
            table.innerHTML = '<div style="width:300px;height:10px"></div>';
            root.appendChild(table);
            const nested = wheel({deltaX: 100}, table);
            table.remove();
            OCA.Analytics.editMode = true;
            const editing = wheel({deltaX: 100});
            OCA.Analytics.editMode = false;
            panorama.pageWheel = null;
            return {vertical, forward, momentum, back, backMomentum, forwardAgain, shiftBack, boundary, zoom, nested, editing};
        });
        assert.deepEqual(wheelNavigation, {
            vertical: {page: 0, prevented: false},
            forward: {page: 1, prevented: true},
            momentum: {page: 1, prevented: true},
            back: {page: 0, prevented: true},
            backMomentum: {page: 0, prevented: true},
            forwardAgain: {page: 1, prevented: true},
            shiftBack: {page: 0, prevented: true},
            boundary: {page: 0, prevented: true},
            zoom: {page: 0, prevented: false},
            nested: {page: 0, prevented: false},
            editing: {page: 0, prevented: false},
        });
        await page.waitForTimeout(550);
        await page.locator('#panoramaHeader').hover();
        await page.mouse.wheel(120, 0);
        await page.waitForFunction(() => OCA.Analytics.currentPage === 1);
        await page.mouse.wheel(-120, 0);
        await page.waitForFunction(() => OCA.Analytics.currentPage === 0);
        assert.equal(await page.locator('#app-content').evaluate(node => node.scrollLeft), 0,
            'real wheel input switches pages without scrolling the screen sideways');
        await page.waitForTimeout(550);
        // Responsive canvases must shrink after fullscreen and browser resizing.
        await page.evaluate(() => {
            const row = document.createElement('div');
            row.id = 'panoramaResizeRegression';
            row.className = 'flex-row';
            for (let i = 0; i < 2; i++) {
                const tile = document.createElement('div');
                tile.className = 'flex-item';
                tile.innerHTML = '<div style="position:relative;height:150px"><canvas></canvas></div>';
                row.appendChild(tile);
            }
            document.querySelector('#panoramaPages > .flex-container').appendChild(row);
            row.querySelectorAll('canvas').forEach(canvas => new Chart(canvas, {
                type: 'bar',
                data: {labels: ['A', 'B'], datasets: [{data: [1, 2]}]},
                options: {responsive: true, maintainAspectRatio: false, animation: false},
            }));
        });
        const chartWidth = () => page.locator('#panoramaResizeRegression canvas').first().evaluate(canvas => canvas.getBoundingClientRect().width);
        await page.waitForTimeout(500);
        const originalChartWidth = await chartWidth();
        await page.locator('#fullscreenToggle').click();
        await page.waitForTimeout(500);
        assert.ok(await chartWidth() > originalChartWidth, 'charts expand in fullscreen');
        await page.locator('#fullscreenToggle').click();
        await page.waitForTimeout(500);
        assert.ok(Math.abs(await chartWidth() - originalChartWidth) <= 2, 'charts return to their original width after fullscreen');
        await page.setViewportSize({width: 1000, height: 900});
        await page.waitForTimeout(500);
        assert.equal(await page.locator('#panoramaResizeRegression').evaluate(row =>
            row.getBoundingClientRect().right <= document.querySelector('#app-content').getBoundingClientRect().right + 1
        ), true, 'chart tiles fit inside the restored window');
        await page.evaluate(() => {
            const row = document.getElementById('panoramaResizeRegression');
            row.querySelectorAll('canvas').forEach(canvas => Chart.getChart(canvas).destroy());
            row.remove();
        });
        await page.setViewportSize(config.viewport);
        const wideHeaderRadii = await page.evaluate(() => {
            const radii = selector => {
                const style = getComputedStyle(document.querySelector(selector));
                return [style.borderTopLeftRadius, style.borderTopRightRadius, style.borderBottomRightRadius, style.borderBottomLeftRadius];
            };
            return {
                main: radii('#panoramaHeader'),
                sub: radii('#panoramaSubHeader-0'),
                mainPadding: getComputedStyle(document.querySelector('#panoramaHeader')).padding,
            };
        });
        await page.setViewportSize({width: 520, height: 900});
        const narrowLayout = await page.evaluate(() => {
            const bounds = selector => document.querySelector(selector).getBoundingClientRect().toJSON();
            const radii = selector => {
                const style = getComputedStyle(document.querySelector(selector));
                return [style.borderTopLeftRadius, style.borderTopRightRadius, style.borderBottomRightRadius, style.borderBottomLeftRadius];
            };
            const items = [...document.querySelectorAll('#panoramaPages > .flex-container:first-child .flex-item')]
                .map(item => item.getBoundingClientRect().toJSON());
            const row = document.createElement('div');
            row.className = 'flex-row';
            row.style.height = '50%';
            row.append(document.createElement('div'), document.createElement('div'));
            [...row.children].forEach(item => item.className = 'flex-item');
            document.querySelector('#panoramaPages > .flex-container:first-child').appendChild(row);
            const result = {
                headerRow: bounds('#panoramaHeaderRow'),
                titleHeading: bounds('.panoramaTitleHeading'),
                pageHeading: bounds('.panoramaPageHeading'),
                menuBar: bounds('#menuBar'),
                mainHeader: bounds('#panoramaHeader'),
                subHeader: bounds('#panoramaSubHeader-0'),
                firstPage: bounds('#panoramaPages > .flex-container:first-child'),
                items,
                wrappedItems: [...row.children].map(item => item.getBoundingClientRect().toJSON()),
                mainHeaderFontSize: getComputedStyle(document.querySelector('#panoramaHeader')).fontSize,
                subHeaderFontSize: getComputedStyle(document.querySelector('#panoramaSubHeader-0')).fontSize,
                mainHeaderPadding: getComputedStyle(document.querySelector('#panoramaHeader')).padding,
                mainHeaderRadii: radii('#panoramaHeader'),
                subHeaderRadii: radii('#panoramaSubHeader-0'),
                rowDirection: getComputedStyle(row).flexDirection,
            };
            row.remove();
            return result;
        });
        assert.equal(narrowLayout.rowDirection, 'column');
        assert.ok(narrowLayout.wrappedItems[1].top >= narrowLayout.wrappedItems[0].bottom, 'panorama tiles wrap onto separate rows');
        assert.ok(Math.abs(narrowLayout.wrappedItems[0].left - narrowLayout.wrappedItems[1].left) <= 1);
        assert.ok(Math.abs(narrowLayout.wrappedItems[0].width - narrowLayout.wrappedItems[1].width) <= 1);
        for (const heading of [narrowLayout.titleHeading, narrowLayout.pageHeading]) {
            assert.ok(Math.abs(heading.left - narrowLayout.items[0].left) <= 1, 'shared headers align with tile left edges');
            assert.ok(Math.abs(heading.right - narrowLayout.items[0].right) <= 1, 'shared headers align with tile right edges: ' + JSON.stringify({heading, tile: narrowLayout.items[0], title: narrowLayout.titleHeading, page: narrowLayout.pageHeading}));
        }
        assert.ok(Math.abs(narrowLayout.titleHeading.bottom - narrowLayout.pageHeading.top) <= 1, 'title and page headings join without a gap');
        assert.ok(Math.abs(narrowLayout.mainHeader.left - narrowLayout.subHeader.left) <= 1);
        assert.ok(narrowLayout.menuBar.right <= narrowLayout.titleHeading.right + 1, 'wrapped toolbar fits the header');
        assert.equal(narrowLayout.mainHeaderFontSize, '24px');
        assert.equal(narrowLayout.subHeaderFontSize, '16px');
        assert.equal(narrowLayout.mainHeaderPadding, wideHeaderRadii.mainPadding, 'narrow layout preserves report header padding');
        assert.deepEqual(narrowLayout.mainHeaderRadii, wideHeaderRadii.main, 'narrow layout preserves main header corner radii');
        assert.deepEqual(narrowLayout.subHeaderRadii, wideHeaderRadii.sub, 'narrow layout preserves subheader corner radii');
        await capture('viewer-narrow');
        await page.setViewportSize(config.viewport);
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
        await page.locator('#filterDialogTable .filterRow').last().locator('.icon-analytics-filterRow-remove').click();
        await page.locator('#analyticsDialogBtnGo').click();
        await idle();
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 1);
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
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        await page.locator('#panoramaConfigureFilters').click();
        const enabledRow = page.locator('.panoramaFilterDefinition').filter({has: page.getByText('Shared category', {exact: true})});
        await enabledRow.locator('.panoramaFilterSummary').click();
        await enabledRow.getByLabel('Show in panorama header', {exact: true}).uncheck();
        await page.locator('#analyticsDialogBtnGo').click();
        const [hiddenSave] = await Promise.all([
            page.waitForResponse(r => r.request().method() === 'PUT' && r.url().endsWith('/panorama/' + panoramaId)),
            page.locator('#saveIcon').click(),
        ]);
        assert.equal(await hiddenSave.json(), true);
        await page.reload();
        await page.waitForFunction(() => OCA.Analytics.PanoramaFilters?.state?.metadata.size > 0);
        await idle();
        assert.equal(await page.locator('#panoramaQuickFilters').isVisible(), false);
        assert.equal(await page.locator('#addFilterIcon').isVisible(), true);
        assert.equal(await page.locator('#filterVisualisation .filterVisualizationItem').count(), 1,
            'hidden header selectors still apply their defaults and show the existing filter summary');
        assert.equal(await page.locator('#myWidget0-0 tbody tr').count(), 2);
        // A disabled stale mapping and an unavailable report must not block visibility edits.
        const outage = structuredClone(panorama);
        const disabled = outage.filters.find(filter => !filter.enabled);
        disabled.mappings[0].dimensionLabel = 'Previously named dimension';
        assert.equal((await api('panorama/' + panoramaId, 'PUT', outage)).data, true);
        const unavailableRoute = '**/data/pa/' + copyId + '*';
        await page.route(unavailableRoute, route => route.fulfill({status: 503, contentType: 'application/json',
            body: JSON.stringify({error: 'Temporarily unavailable'})}));
        await page.reload();
        await page.waitForFunction(() => OCA.Analytics.PanoramaFilters?.state?.metadata.size > 0);
        await idle();
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        await page.locator('#panoramaConfigureFilters').click();
        await page.getByText('Some report dimensions could not be loaded. Reopen this dialog to retry.', {exact: true}).waitFor();
        await enabledRow.locator('.panoramaFilterSummary').click();
        await page.locator('.panoramaFilterDefinition').filter({has: page.getByText('Shared category', {exact: true})})
            .getByLabel('Show in panorama header', {exact: true}).uncheck();
        await page.locator('#analyticsDialogBtnGo').click();
        await page.locator('#analyticsDialogContainer').waitFor({state: 'hidden'});
        const [outageSave] = await Promise.all([
            page.waitForResponse(r => r.request().method() === 'PUT' && r.url().endsWith('/panorama/' + panoramaId)),
            page.locator('#saveIcon').click(),
        ]);
        assert.equal(await outageSave.json(), true);
        const outageSaved = JSON.parse((await api('panorama/' + panoramaId)).data.filters);
        assert.equal(outageSaved.find(filter => filter.enabled).showInHeader, false);
        assert.deepEqual(outageSaved.find(filter => filter.enabled).mappings, outage.filters.find(filter => filter.enabled).mappings);
        assert.deepEqual(outageSaved.find(filter => filter.id === disabled.id).mappings, disabled.mappings);
        await page.unroute(unavailableRoute);
        const broken = structuredClone(panorama);
        const brokenMapping = broken.filters.find(f => f.enabled).mappings.find(mapping => mapping.reportId === reportId);
        assert.ok(brokenMapping, 'the requested report has a filter mapping');
        brokenMapping.dimensionLabel = 'Renamed';
        assert.equal((await api('panorama/' + panoramaId, 'PUT', broken)).data, true);
        const invalidDimension = await api('data/pa/' + reportId + query);
        // Data-loading errors use the report error payload; malformed variables above return HTTP 400.
        assert.equal(invalidDimension.status, 200);
        assert.match(invalidDimension.data.error, /dimension has changed/);
        assert.deepEqual(invalidDimension.data.data, []);
        await page.reload();
        await page.waitForFunction(() => OCA.Analytics.PanoramaFilters?.state?.errors.size > 0);
        await idle();
        assert.match(await page.locator('#myWidget0-0').innerText(), /dimension has changed/);
        await page.evaluate(() => OCA.Analytics.Panorama.handleEditButton());
        await page.locator('#panoramaConfigureFilters').click();
        await page.locator('#analyticsDialogBtnGo').click();
        assert.equal(await page.locator('#analyticsDialogContainer').isVisible(), true,
            'a confirmed changed dimension on an enabled filter still requires repair');
        assert.match(await page.locator('.panoramaFilters [role=alert]').innerText(), /repair missing dimensions/);
        assert.equal(await page.locator('.panoramaFilters [role=alert]').evaluate(node => {
            const bounds = node.getBoundingClientRect();
            return bounds.top >= 0 && bounds.bottom <= window.innerHeight;
        }), true, 'validation errors are scrolled into view');
        await page.locator('#analyticsDialogBtnCancel').click();
        assert.deepEqual((await api('report/' + reportId)).data, original, 'source report is unchanged');
        assert.deepEqual(errors, []);
        console.log('PASS: panorama configuration, defaults, mapping, apply/cancel/reset, pages, deduplication, stale requests, cache and dimension validation');
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
