/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded} = require('./common');

(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage({deviceScaleFactor: 2});
        await page.route('https://svg.test/**', route => route.fulfill({path: path.resolve(__dirname, '../../js/3rdParty/svgcanvas.esm.js'), contentType: 'text/javascript', headers: {'Access-Control-Allow-Origin': '*'}}));
        await page.setContent('<canvas id="chart" width="800" height="400"></canvas>');
        await page.evaluate(() => { window.OCA = {Analytics: {}}; window.OC = {filePath: () => 'https://svg.test/svgcanvas.js'}; });
        for (const file of ['js/3rdParty/chart.umd.js', 'js/3rdParty/moment.min.js', 'js/3rdParty/chartjs-adapter-moment.js', 'js/3rdParty/chartjs-plugin-datalabels.min.js', 'js/3rdParty/chartjs-plugin-annotation.min.js', 'js/3rdParty/chartjs-plugin-funnel.min.js', 'js/report.js']) {
            await page.addScriptTag({path: path.resolve(__dirname, '../..', file)});
        }
        await page.evaluate(async () => {
            const chart = new Chart(document.getElementById('chart'), {
                type: 'line',
                data: {labels: ['A', 'B', 'C'], datasets: [
                    {label: 'Visible', data: [1, 3, 2], borderColor: 'red'},
                    {label: 'Hidden', data: [4, 5, 6]},
                ]},
                options: {responsive: false, animation: false, scales: {y: {min: 1, max: 4}}},
            });
            chart.setDatasetVisibility(1, false);
            chart.update();
            const snapshot = () => JSON.stringify({
                width: chart.canvas.width, height: chart.canvas.height,
                ratio: chart.currentDevicePixelRatio, hidden: chart.isDatasetVisible(1),
                min: chart.scales.y.min, max: chart.scales.y.max,
                image: chart.toBase64Image(),
            });
            const before = snapshot();
            const svg = await OCA.Analytics.Report.renderChartSvg(chart);
            const parsed = new DOMParser().parseFromString(svg, 'image/svg+xml');
            if (parsed.querySelector('parsererror, image') || !parsed.querySelector('path') || !parsed.querySelector('text')) throw new Error('Invalid vector SVG');
            if (!svg.includes('Visible')) throw new Error('Missing SVG legend');
            if (snapshot() !== before) throw new Error('SVG export changed chart');
            chart.destroy();
        });
        const comparisons = await page.evaluate(async () => {
            const results = [];
            for (const mode of ['line', 'bar', 'doughnut', 'funnel', 'datetime', 'stacked', 'mixed']) {
                const type = mode === 'datetime' ? 'line' : ['stacked', 'mixed'].includes(mode) ? 'bar' : mode;
                const canvas = document.createElement('canvas');
                canvas.width = 800;
                canvas.height = 400;
                const chart = new Chart(canvas, {
                    type,
                    plugins: [ChartDataLabels],
                    data: {labels: ['Alpha & <Beta>', 'B', 'C'], datasets: [{
                        label: 'Series', data: [9, 6, 3], borderColor: '#d02020', borderWidth: 2,
                        fill: type === 'line',
                        backgroundColor: type === 'line' ? context => {
                            const gradient = context.chart.ctx.createLinearGradient(0, 0, 0, 400);
                            gradient.addColorStop(0, '#d02020');
                            gradient.addColorStop(1, 'rgba(208,32,32,0)');
                            return gradient;
                        } : ['#d02020', '#20a020', '#2020d0'],
                    }]},
                    options: {responsive: false, animation: false, devicePixelRatio: 1,
                        indexAxis: type === 'funnel' ? 'y' : 'x',
                        plugins: {datalabels: {color: '#000000'}, annotation: {
                            annotations: type === 'bar' ? {limit: {type: 'line', yMin: 5, yMax: 5,
                                borderColor: '#000000', label: {display: true, content: 'Threshold'}}} : {},
                        }},
                    },
                });
                if (mode === 'datetime') {
                    chart.data.labels = ['2026-01-01', '2026-02-01', '2026-03-01'];
                    chart.options.scales.x.type = 'time';
                    chart.options.scales.x.min = Date.parse('2026-01-15');
                    chart.update('none');
                }
                if (['stacked', 'mixed'].includes(mode)) {
                    chart.data.datasets.push({label: 'Second series', data: [2, 4, 1],
                        type: mode === 'mixed' ? 'line' : 'bar', backgroundColor: '#20a020', borderColor: '#20a020'});
                    chart.options.scales.x.stacked = mode === 'stacked';
                    chart.options.scales.y.stacked = mode === 'stacked';
                    chart.update('none');
                }
                if (type === 'doughnut') { chart.toggleDataVisibility(1); chart.update('none'); }
                const before = chart.toBase64Image();
                const instanceCount = Object.keys(Chart.instances).length;
                const svg = await OCA.Analytics.Report.renderChartSvg(chart);
                if (chart.toBase64Image() !== before || Object.keys(Chart.instances).length !== instanceCount) throw new Error('State changed or chart leaked');
                const doc = new DOMParser().parseFromString(svg, 'image/svg+xml');
                if (doc.querySelector('parsererror, image') || !doc.querySelector('path')) throw new Error('Not vector SVG: ' + type);
                if (mode !== 'datetime' && !doc.documentElement.textContent.includes('Alpha & <Beta>')) throw new Error('Label escaping failed');
                if (type === 'bar' && !doc.documentElement.textContent.includes('Threshold')) throw new Error('Missing annotation');
                if (type === 'line' && !doc.querySelector('linearGradient')) throw new Error('Missing gradient');
                const image = new Image();
                image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
                await image.decode();
                const rendered = document.createElement('canvas');
                rendered.width = 800; rendered.height = 400;
                const ctx = rendered.getContext('2d');
                ctx.drawImage(image, 0, 0);
                const actual = ctx.getImageData(0, 0, 800, 400).data;
                const expected = chart.ctx.getImageData(0, 0, 800, 400).data;
                // Compare colored plot coverage, tolerating SVG/Canvas edge antialiasing.
                let union = 0, intersection = 0;
                const colored = (pixels, index) => pixels[index + 3] > 30 && Math.max(...pixels.slice(index, index + 3)) - Math.min(...pixels.slice(index, index + 3)) > 40;
                for (let i = 0; i < actual.length; i += 4) {
                    const a = colored(actual, i), b = colored(expected, i);
                    if (a || b) union++;
                    if (a && b) intersection++;
                }
                const overlap = intersection / union;
                if (!(overlap > 0.9)) throw new Error(mode + ' SVG plot differs from canvas: ' + overlap);
                results.push({mode, overlap});
                const brokenPlugin = {id: 'exportFailure', beforeDraw: exported => {
                    if (exported !== chart) throw new Error('Simulated SVG rendering failure');
                }};
                chart.config.plugins.push(brokenPlugin);
                let failed = false;
                try { await OCA.Analytics.Report.renderChartSvg(chart); } catch { failed = true; }
                chart.config.plugins.pop();
                if (!failed || Object.keys(Chart.instances).length !== instanceCount || chart.toBase64Image() !== before) throw new Error('SVG failure cleanup failed');
                chart.destroy();
            }
            return results;
        });
        console.log('SVG visual comparisons:', comparisons);
        await ensureAnalyticsLoaded(page, buildScenarioConfig('chart-export'));
        await page.addScriptTag({path: path.resolve(__dirname, '../../js/3rdParty/chart.umd.js')});
        await page.evaluate(() => {
            const canvas = document.createElement('canvas');
            canvas.width = 800;
            canvas.height = 400;
            document.body.appendChild(canvas);
            OCA.Analytics.chartObject = new Chart(canvas, {
                type: 'bar', data: {labels: ['A', 'B'], datasets: [{data: [2, 5]}]},
                options: {responsive: false, animation: false},
            });
            OCA.Analytics.currentReportData = {options: {name: 'Export test'}};
            OCA.Analytics.Report.downloadChart();
        });
        await page.locator('#chartExportFormat').selectOption('png');
        assert.equal(await page.locator('#chartExportWidth').count(), 0);
        const originalPng = await page.evaluate(() => OCA.Analytics.chartObject.toBase64Image());
        const downloadPromise = page.waitForEvent('download');
        await page.locator('#analyticsDialogBtnGo').click();
        const download = await downloadPromise;
        assert.equal(download.suggestedFilename(), 'Export test.png');
        const pngBytes = require('node:fs').readFileSync(await download.path());
        assert.equal(pngBytes.toString('base64'), originalPng.split(',')[1]);
        await page.locator('#analyticsDialogContainer').waitFor({state: 'detached'});
        await page.evaluate(() => OCA.Analytics.Report.downloadChart());
        assert.equal(await page.locator('#chartExportFormat').inputValue(), 'svg');
        const svgDownloadPromise = page.waitForEvent('download');
        await page.locator('#analyticsDialogBtnGo').click();
        const svgDownload = await svgDownloadPromise;
        assert.equal(svgDownload.suggestedFilename(), 'Export test.svg');
        const svgText = require('node:fs').readFileSync(await svgDownload.path(), 'utf8');
        assert.match(svgText, /<svg/);
        assert.match(svgText, /viewBox="0 0 800 400"/);
        assert.doesNotMatch(svgText, /<image/);
        await page.locator('#analyticsDialogContainer').waitFor({state: 'detached'});
        await page.evaluate(() => OCA.Analytics.Report.downloadChart());
        await page.locator('#analyticsDialogBtnCancel').click();
        await page.locator('#analyticsDialogContainer').waitFor({state: 'detached'});
        console.log('PASS: SVG and PNG downloads, vector output, visual comparisons, state preservation, failure cleanup and cancel');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
