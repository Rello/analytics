/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

const root = path.resolve(__dirname, '../..');
const chartAssets = [
    '3rdParty/cloner.js',
    '3rdParty/chart.umd.js',
    '3rdParty/chartjs-adapter-moment.js',
    '3rdParty/chartjs-plugin-datalabels.min.js',
    '3rdParty/chartjs-plugin-funnel.min.js',
    '3rdParty/chartjs-plugin-annotation.min.js',
];

(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage();
        const requests = [];
        const pageErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        await page.route('https://analytics-assets.test/**', async route => {
            const url = new URL(route.request().url());
            const relativePath = decodeURIComponent(url.pathname).replace(/^\//, '');
            assert.match(relativePath, /^(js|css)\/[\w/.-]+$/);
            requests.push(relativePath);
            await route.fulfill({path: path.join(root, relativePath)});
        });
        await page.evaluate(() => {
            window.OCA = {};
            window.OC = {
                filePath: (_app, type, name) => `https://analytics-assets.test/${type}/${name}`,
            };
            window.t = (_app, message) => message;
            window._registerWidget = () => {};
        });
        await page.addScriptTag({path: path.join(root, 'js/reference.js')});

        const previewState = await page.evaluate(async () => {
            const reference = OCA.Analytics.Reference;
            await reference.ensureCoreAssets();
            const container = document.createElement('div');
            document.body.appendChild(container);
            reference.buildTable = () => {};
            reference.buildChart = () => {};
            OCA.Analytics.Visualization.buildKpiDisplay = () => {};

            await reference.renderVisualization(null, container, {
                options: {visualization: 'table'}, data: [['KPI', 1]],
            }, false);
            const afterKpi = {
                chart: !!window.Chart,
                table: !!window.DataTable,
                chartRequested: !!reference.chartAssetsPromise,
            };

            await reference.renderVisualization(null, container, {
                options: {visualization: 'table'}, data: [['A', 1], ['B', 2]],
            }, false);
            const afterTable = {
                chart: !!window.Chart,
                table: !!window.DataTable,
                chartRequested: !!reference.chartAssetsPromise,
            };

            await reference.renderVisualization(null, container, {
                options: {visualization: 'chart'}, data: [['A', 1]],
            }, false);
            const canvas = document.createElement('canvas');
            container.appendChild(canvas);
            const chart = new Chart(canvas, {
                type: 'bar',
                data: {labels: ['A'], datasets: [{data: [1]}]},
            });
            const afterChart = {
                chart: !!window.Chart,
                labels: !!window.ChartDataLabels,
                cloner: !!window.cloner,
                rendered: chart.getDatasetMeta(0).data.length === 1,
            };
            chart.destroy();

            await reference.renderVisualization(null, container, {
                options: {visualization: 'ct'}, data: [['A', 1]],
            }, false);
            return {afterKpi, afterTable, afterChart};
        });

        assert.deepEqual(previewState.afterKpi, {chart: false, table: false, chartRequested: false});
        assert.deepEqual(previewState.afterTable, {chart: false, table: true, chartRequested: false});
        assert.deepEqual(previewState.afterChart, {
            chart: true, labels: true, cloner: true, rendered: true,
        });
        assert.equal(requests.filter(name => name === 'js/3rdParty/datatables.min.js').length, 1);
        for (const name of chartAssets) {
            assert.equal(requests.filter(request => request === `js/${name}`).length, 1, name);
        }
        assert.deepEqual(pageErrors, []);
        console.log('PASS: KPI and table previews skip chart libraries; chart and combined previews load them once.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
