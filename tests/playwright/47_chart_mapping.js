/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture, attachPageIssueListeners} = require('./common');
const config = buildScenarioConfig('47');

(async () => {
    const browser = await chromium.launch({headless: config.headless});
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const issues = [];
    attachPageIssueListeners(page, issues);
    page.on('response', response => {
        if (response.status() >= 500) console.error('HTTP', response.status(), response.url());
    });
    const previewData = () => page.evaluate(() => {
        const chart = Chart.getChart(document.getElementById('chartColumnPreviewCanvas'));
        return chart ? {labels: chart.data.labels, datasets: chart.data.datasets.map(d => ({label: d.label, data: d.data}))} : null;
    });
    const waitPreview = () => page.waitForFunction(() => !!Chart.getChart(document.getElementById('chartColumnPreviewCanvas')));
    const open = async (fixture = {}) => {
        await page.evaluate(fixture => {
            OCA.Analytics.Notification.dialogClose();
            OCA.Analytics.currentReportData = {
                header: ['Segment', 'Year', '€', 'Cost'],
                data: [['Retail', 2025, 12000, 7000], ['Retail', 2026, 15000, 8500],
                    ['Online', 2025, 18000, 11000], ['Online', 2026, 24000, 14000]],
                options: {chart: 'column', chartoptions: {}, dataoptions: [], filteroptions: {}},
                ...fixture,
            };
            window.mappingBefore = JSON.stringify(OCA.Analytics.currentReportData);
            window.mappingChartCount = Object.keys(Chart.instances).length;
            OCA.Analytics.Filter.openChartOptionsDialog();
        }, fixture);
        await page.locator('.analyticsEnhancedDialogNavButton').filter({hasText: 'Data mapping'}).click();
        if (fixture.data?.length === 0) {
            await page.locator('#chartColumnPreviewMessage').filter({hasText:'No data'}).waitFor();
        } else {
            await waitPreview();
        }
    };
    try {
        await ensureAnalyticsLoaded(page, config);
        await open();
        assert.deepEqual(await page.locator('.analyticsEnhancedDialogNavButton').allTextContents(),
            ['Data format', 'Data mapping', 'Labels', 'Visualization']);
        assert.equal(await page.locator('#chartColumnCategory.optionsInput').count(), 1);
        assert.equal(await page.locator('#chartColumnValueAdd.optionsInput').count(), 1);
        assert.equal(await page.locator('#chartColumnSeriesAdd.optionsInput').count(), 1);
        await page.locator('#chartColumnMappingSuggest').click();
        await page.waitForFunction(() => Chart.getChart(document.getElementById('chartColumnPreviewCanvas'))?.data.datasets.length === 4);
        assert.equal(await page.locator('#chartColumnMappingSummary').innerText(), 'Show € and Cost by Segment, broken down by Year.');
        assert.equal(await page.locator('#chartColumnMappingSummary strong').count(), 3);
        assert.deepEqual((await previewData()).datasets.map(d => d.label), ['2025 · €', '2025 · Cost', '2026 · €', '2026 · Cost']);
        await page.locator('#chartColumnValues [data-column-id="3"] .chartColumnRemove').click();
        await page.waitForFunction(() => Chart.getChart(document.getElementById('chartColumnPreviewCanvas'))?.data.datasets.length === 2);
        assert.deepEqual((await previewData()).datasets[0].data, [{x:'Retail', y:12000}, {x:'Online', y:18000}]);
        await page.locator('.analyticsEnhancedDialogNavButton').filter({hasText:'Data mapping'}).click();
        await page.waitForTimeout(400);
        await capture('mapping_live_preview');
        await page.locator('#chartColumnMappingSwap').click();
        assert.equal(await page.locator('#chartColumnMappingSummary').innerText(), 'Show € by Year, broken down by Segment.');
        await page.locator('#chartColumnValueAdd').selectOption('3');
        await page.locator('#chartColumnValues [data-column-id="3"] button').first().click();
        assert.deepEqual(await page.evaluate(() => OCA.Analytics.Filter.getChartColumnMapping().measures), [3, 2]);
        await page.locator('label[for="analyticsModelOpt2"]').click();
        assert.equal(await page.locator('#chartColumnSeriesFieldset').isHidden(), true);
        assert.match(await page.locator('#chartColumnMappingSummary').innerText(), /one series per Year row/);
        await page.locator('label[for="analyticsModelOpt1"]').click();
        assert.deepEqual(await page.evaluate(() => OCA.Analytics.Filter.getChartColumnMapping()), {category:1,series:[0],measures:[3,2]});
        assert.equal(await page.evaluate(() => JSON.stringify(OCA.Analytics.currentReportData) === window.mappingBefore), true);
        await page.locator('#analyticsDialogBtnCancel').click();
        assert.equal(await page.evaluate(() => Object.keys(Chart.instances).length === window.mappingChartCount), true);
        assert.equal(await page.evaluate(() => JSON.stringify(OCA.Analytics.currentReportData) === window.mappingBefore), true);

        // Samples follow the currently loaded/filtered response, and never fetch raw source rows.
        await open({data:[['Online',2026,24000,14000]]});
        await page.locator('#chartColumnMappingSuggest').click();
        await page.waitForTimeout(200);
        assert.deepEqual((await previewData()).labels, ['Online']);
        assert.match(await page.locator('#chartColumnPreviewStatus').innerText(), /1 of 1 report rows/);
        await page.locator('#chartColumnValues [data-column-id="2"] .chartColumnRemove').click();
        await page.locator('#chartColumnValues [data-column-id="3"] .chartColumnRemove').click();
        assert.equal(await page.evaluate(() => OCA.Analytics.Filter.getChartColumnMapping()), false);
        await page.locator('#chartColumnMappingError').waitFor({state:'visible'});
        await page.waitForFunction(() => !Chart.getChart(document.getElementById('chartColumnPreviewCanvas')));
        await page.locator('#chartColumnValueAdd').selectOption('2');
        await waitPreview();
        // Apply through the actual handler, intercepting only the backend reload so
        // this UI fixture never writes a report or requests a nonexistent report ID.
        await page.evaluate(() => {
            window.mappingOriginalReload = OCA.Analytics.Report.Backend.getData;
            OCA.Analytics.Report.Backend.getData = () => {window.mappingReloaded = true;};
        });
        await page.locator('#analyticsDialogBtnGo').click();
        assert.deepEqual(await page.evaluate(() => OCA.Analytics.ChartOptions.getGuiState(OCA.Analytics.currentReportData.options.chartoptions).columnMapping),
            {category:0,series:[1],measures:[2]});
        assert.equal(await page.evaluate(() => window.mappingReloaded), true);
        await page.evaluate(() => {OCA.Analytics.Report.Backend.getData = window.mappingOriginalReload;});

        await open({header:['Date','Revenue','Cost'], data:[['2026-01-01',10,6],['2026-02-01',20,9]],
            options:{chart:'line',chartoptions:{__analytics_gui:{model:'timeSeriesModel',columnMapping:{category:0,series:[],measures:[1,2]}}},dataoptions:[]}});
        assert.equal(await page.locator('#chartColumnCategoryLabel').innerText(), 'Time / X-axis');
        assert.equal(await page.locator('#chartColumnMappingSummary').innerText(), 'Show Revenue and Cost over time using Date.');
        assert.deepEqual((await previewData()).datasets[0].data, [{x:'2026-01-01',y:10},{x:'2026-02-01',y:20}]);

        const manyRows = Array.from({length:40}, (_,i) => ['Segment '+i,2026,i+1,i]);
        await open({data:manyRows});
        await page.locator('#chartColumnMappingSuggest').click();
        await page.waitForTimeout(200);
        assert.equal((await previewData()).labels.length, 12);
        assert.match(await page.locator('#chartColumnPreviewStatus').innerText(), /12 of 40 report rows/);
        await page.locator('#chartColumnPreviewMessage').waitFor({state:'visible'});
        await page.setViewportSize({width:800,height:1000});
        await page.locator('.analyticsEnhancedDialogNavButton').filter({hasText:'Data mapping'}).click();
        await page.waitForTimeout(500);
        assert.equal(await page.locator('#chartColumnMappingSection').evaluate(el => el.scrollWidth > el.clientWidth), false);
        await capture('mapping_narrow');
        await page.setViewportSize(config.viewport);

        const limits = await page.evaluate(() => {
            const report = {header:['Category','Group','Value'], options:{chart:'columnSt100',chartoptions:{},dataoptions:[]},
                data:Array.from({length:20},(_,i)=>['A','Group '+i,10])};
            const mapping = {category:0,series:[1],measures:[2]};
            const limited = OCA.Analytics.Visualization.chartMappingPreview(report,'kpiModel',mapping);
            const full = OCA.Analytics.Visualization.chartMappingPreview({...report,data:report.data.slice(0,2)},'kpiModel',mapping);
            return {limitedSeries:limited.config.data.datasets.length, limited:limited.limited,
                raw:limited.config.data.datasets[0].data[0].y, percent:full.config.data.datasets[0].data[0].y};
        });
        assert.deepEqual(limits, {limitedSeries:6,limited:true,raw:10,percent:50});

        await open({header:['<img src=x onerror="window.mappingXss=1">','Year','€'],data:[['A',2026,10]]});
        await page.locator('#chartColumnMappingSuggest').click();
        assert.equal(await page.locator('#chartColumnMappingSummary img').count(), 0);
        assert.equal(await page.evaluate(() => window.mappingXss || 0), 0);
        // Opening another dialog must release the preview just like Cancel does.
        await page.evaluate(() => OCA.Analytics.Notification.htmlDialogInitiate('Other dialog', () => {}));
        assert.equal(await page.evaluate(() => Object.keys(Chart.instances).length === window.mappingChartCount), true);
        await page.evaluate(() => OCA.Analytics.Notification.dialogClose());
        await open({data:[]});
    } catch (error) {
        await capture('failure');
        throw error;
    } finally {
        await browser.close();
    }
    assert.equal(issues.length, 0, JSON.stringify(issues));
    console.log('PASS: chart mapping controls, live-data preview, sampling, sentence, modes, Apply/Cancel, escaping and cleanup.');
})();
