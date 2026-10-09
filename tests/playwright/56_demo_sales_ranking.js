/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, buildUniqueName, ensureAnalyticsLoaded, openExistingReport,
    saveAndReloadReport, createCapture, attachPageIssueListeners} = require('./common');

const config = buildScenarioConfig('56');
const reportName = buildUniqueName('Demo sales ranking regression', process.env.REPORT_NAME);

(async () => {
    const browser = await chromium.launch({headless: config.headless,
        args: ['--host-resolver-rules=MAP localhost host.docker.internal'],
    });
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const issues = [];
    attachPageIssueListeners(page, issues);
    try {
        await ensureAnalyticsLoaded(page, config);
        const pair = await page.evaluate(async name => {
            const demo = JSON.parse(OCA.Analytics.Wizard.salesRankingDemo());
            demo.report.name = name;
            const rankingId = await OCA.Analytics.Sidebar.Report.import(null, JSON.stringify(demo), null, {rethrowError: true});
            const rawId = await OCA.Analytics.Wizard.salesRawDataDemo(rankingId);
            const read = async id => (await fetch(OC.generateUrl('apps/analytics/report/' + id), {headers: OCA.Analytics.headers()})).json();
            const ranking = await read(rankingId);
            const raw = await read(rawId);
            return {rankingId, rawId, rankingDataset: ranking.dataset, rawDataset: raw.dataset,
                rawName: raw.name, rawVisualization: raw.visualization};
        }, reportName);
        assert.equal(Number(pair.rankingDataset), Number(pair.rawDataset));
        assert.ok(Number(pair.rawDataset) > 0);
        assert.equal(pair.rawName, 'Demo: Sales raw data');
        assert.equal(pair.rawVisualization, 'table');
        await openExistingReport(page, reportName);
        await page.waitForFunction(() => OCA.Analytics.chartObject?.data.datasets.length === 2
            && document.querySelectorAll('#tableContainer tbody tr').length === 4);

        const validate = async () => {
            const result = await page.evaluate(() => {
                const report = OCA.Analytics.currentReportData;
                const table = OCA.Analytics.tableObject[report.options.id];
                const chart = OCA.Analytics.chartObject;
                return {
                    rows: report.data,
                    topN: report.options.filteroptions.topN,
                    headers: [...document.querySelectorAll('#tableContainer thead th')].map(cell => cell.textContent.trim()),
                    firstRow: [...document.querySelectorAll('#tableContainer tbody tr:first-child td')].map(cell => cell.textContent.trim()),
                    footer: [...document.querySelectorAll('#tableContainer tfoot td')].map(cell => cell.textContent.trim()),
                    tableData: table.rows({order: 'applied'}).data().toArray(),
                    datasets: chart.data.datasets.map(dataset => ({type: dataset.type, data: dataset.data})),
                    legendPosition: chart.options.plugins.legend.position,
                    chartIndicator: document.getElementById('optionsMenuChartOptions').classList.contains('report-option-active'),
                };
            });
            assert.equal(result.rows.length, 8);
            assert.deepEqual([...new Set(result.rows.map(row => row[1]))].sort(), ['2025', '2026']);
            assert.deepEqual([...new Set(result.rows.map(row => row[0]))].sort(), ['Channel partners', 'Online shop', 'Retail stores', 'others']);
            assert.equal(result.topN.number, 3);
            assert.equal(result.topN.others, true);
            assert.deepEqual(result.headers, ['Sales channel', '2026', '2025', 'Total revenue', 'Revenue increase', '2026 vs. 2025']);
            assert.equal(result.firstRow[0], 'Online shop');
            assert.ok(result.firstRow[1].includes('76,000') && result.firstRow[1].includes('€'));
            const totals = result.tableData.map(row => Number(row[3]));
            assert.deepEqual(totals, [134000, 114000, 93000, 84000]);
            assert.equal(result.footer[3].replace(/[^0-9]/g, ''), '425000');
            assert.deepEqual(result.datasets.map(dataset => dataset.type), ['bar', 'line']);
            assert.equal(result.datasets.every(dataset => dataset.data.length === 4), true);
            assert.equal(result.legendPosition, 'bottom');
            assert.equal(result.chartIndicator, true);
        };
        await validate();
        await capture('sales_ranking');
        await saveAndReloadReport(page, reportName);
        await page.waitForFunction(() => document.querySelectorAll('#tableContainer tbody tr').length === 4);
        await validate();
        const indicatorCases = await page.evaluate(() => {
            const options = OCA.Analytics.currentReportData.options;
            const original = {chartoptions: options.chartoptions, dataoptions: options.dataoptions};
            const cases = [
                {chartoptions: null, dataoptions: null},
                {chartoptions: OCA.Analytics.ChartOptions.parseAndNormalize('{}'), dataoptions: '[{},{}]'},
                {chartoptions: {plugins: {legend: {position: 'bottom'}}}, dataoptions: []},
                {chartoptions: {}, dataoptions: '[{"type":"line","borderColor":"#123456"}]'},
                {chartoptions: {__analytics_gui: {model: 'timeSeriesModel'}}, dataoptions: []},
                {chartoptions: {__analytics_gui: {columnMapping: {category: 0, series: [1], measures: [2]}}}, dataoptions: []},
                {chartoptions: {__analytics_gui: {doughnutLabelStyle: 'absolute'}}, dataoptions: []},
                {chartoptions: {}, dataoptions: []},
            ];
            try {
                return cases.map(settings => {
                    Object.assign(options, settings);
                    OCA.Analytics.Filter.updateReportMenuIndicators();
                    return document.getElementById('optionsMenuChartOptions').classList.contains('report-option-active');
                });
            } finally {
                Object.assign(options, original);
                OCA.Analytics.Filter.updateReportMenuIndicators();
            }
        });
        assert.deepEqual(indicatorCases, [false, false, true, true, true, true, true, false]);
        const rawUrl = await page.evaluate(id => OC.generateUrl('apps/analytics/r/' + id), pair.rawId);
        await page.goto(new URL(rawUrl, config.baseUrl).href, {waitUntil: 'domcontentloaded'});
        await page.waitForFunction(id => Number(OCA.Analytics.currentReportData?.options?.id) === Number(id)
            && document.querySelectorAll('#tableContainer tbody tr').length > 0, pair.rawId);
        const validateRaw = async () => {
            const raw = await page.evaluate(() => ({
                rows: OCA.Analytics.currentReportData.data,
                headers: [...document.querySelectorAll('#tableContainer thead th')].map(cell => cell.textContent.trim()),
                options: OCA.Analytics.currentReportData.options,
                chartHidden: getComputedStyle(document.getElementById('chartContainer')).display === 'none',
                totalRows: OCA.Analytics.tableObject[OCA.Analytics.currentReportData.options.id].rows().count(),
            }));
            assert.equal(raw.rows.length, 18);
            assert.equal(raw.totalRows, 18);
            assert.equal(new Set(raw.rows.map(row => row[0])).size, 6);
            assert.deepEqual([...new Set(raw.rows.map(row => row[1]))].sort(), ['2024', '2025', '2026']);
            assert.deepEqual(raw.headers, ['Sales channel', 'Year', 'Revenue']);
            assert.equal(raw.chartHidden, true);
            assert.ok(!raw.options.filteroptions?.topN && !raw.options.filteroptions?.filter);
            assert.ok(!raw.options.tableoptions?.layout && !raw.options.tableoptions?.calculatedColumns);
        };
        await validateRaw();
        await capture('sales_raw_data');
        await page.reload({waitUntil: 'domcontentloaded'});
        await page.waitForFunction(id => Number(OCA.Analytics.currentReportData?.options?.id) === Number(id)
            && document.querySelectorAll('#tableContainer tbody tr').length > 0, pair.rawId);
        await validateRaw();
        assert.deepEqual(issues, []);
        console.log(JSON.stringify({status: 'PASS', reportName, pair, checks: 'Shared dataset, raw table with all 18 rows, ranking options, chart indicator, reload'}));
    } catch (error) {
        await capture('failure').catch(() => {});
        console.error(JSON.stringify({status: 'FAIL', error: error.message, issues}));
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();
