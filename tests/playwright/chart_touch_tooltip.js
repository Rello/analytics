/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

(async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({hasTouch: true, deviceScaleFactor: 2});
        await page.setContent('<div style="width: 360px; height: 240px"><canvas></canvas></div>');
        await page.evaluate(() => { window.OCA = {}; });
        for (const asset of [
            '3rdParty/cloner.js', '3rdParty/chart.umd.js', '3rdParty/moment.min.js', '3rdParty/chartjs-adapter-moment.js',
            '3rdParty/chartjs-plugin-datalabels.min.js', 'app.js', 'flexible.js', 'chartOptions.js',
            'visualization.js', 'report.js',
        ]) {
            await page.addScriptTag({path: path.resolve(__dirname, '../../js', asset)});
        }

        for (const type of ['line', 'datetime']) {
            await page.evaluate(chartType => {
                const analytics = OCA.Analytics;
                analytics.chartObject?.destroy();
                const data = [];
                for (let day = 1; day <= 31; day++) {
                    const date = '2026-01-' + String(day).padStart(2, '0');
                    data.push(['Daily', date, day]);
                    if (day % 2 === 0) data.push(['Even days', date, 100 + day]);
                }
                analytics.Visualization.buildChart(document.querySelector('canvas').getContext('2d'), {
                    header: ['Series', 'Date', 'Value'], data,
                    options: {chart: chartType, chartoptions: {
                        // With a 360px plot and 30 intervals, exact midpoints can be tapped.
                        layout: {autoPadding: false},
                        scales: {x: {display: false}, primary: {display: false}},
                    }},
                }, analytics.Report.getDefaultChartOptions());
            }, type);

            for (const input of ['touch', 'mouse']) {
                for (const target of [15, 16, 15.5]) {
                    const position = await page.evaluate(target => {
                        const chart = OCA.Analytics.chartObject;
                        const rect = chart.canvas.getBoundingClientRect();
                        const points = chart.getDatasetMeta(0).data;
                        const x = Number.isInteger(target) ? points[target - 1].x + 2
                            : (points[Math.floor(target) - 1].x + points[Math.ceil(target) - 1].x) / 2;
                        return {x: rect.left + x, y: rect.top + chart.height / 2};
                    }, target);
                    if (!Number.isInteger(target)) {
                        assert.ok(Number.isInteger(position.x), `${type} midpoint must fall on a whole CSS pixel`);
                    }
                    if (input === 'touch') {
                        await page.touchscreen.tap(position.x, position.y);
                    } else {
                        await page.mouse.move(position.x, position.y);
                    }
                    const {items, highlights} = await page.evaluate(async () => {
                        // Chart.js dispatches canvas input on the next animation frame.
                        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                        const chart = OCA.Analytics.chartObject;
                        return {
                            items: chart.tooltip.dataPoints?.map(item => [item.dataset.label, item.raw.x, item.raw.y]),
                            highlights: chart.getActiveElements().map(({datasetIndex, index}) => {
                                const dataset = chart.data.datasets[datasetIndex];
                                const point = dataset.data[index];
                                return [dataset.label, point.x, point.y];
                            }),
                        };
                    });
                    const description = `${type} chart ${input} at day ${target}`;
                    const day = items?.[0]?.[2];
                    assert.ok(day === Math.floor(target) || day === Math.ceil(target),
                        `${description}: selected ${JSON.stringify(items)}`);
                    const date = '2026-01-' + day;
                    const expected = [['Daily', date, day]];
                    if (day % 2 === 0) expected.push(['Even days', date, 100 + day]);
                    assert.deepEqual(items, expected, description + ' tooltip');
                    assert.deepEqual(highlights, expected, description + ' highlighted points');
                }
            }
        }
        console.log('Touch and mouse select a single date on category and time axes, including sparse series.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
