/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage({timezoneId: 'Europe/Berlin'});
        await page.evaluate(() => { window.OCA = {Analytics: {}}; });
        for (const asset of ['3rdParty/moment.min.js', 'visualization.js']) {
            await page.addScriptTag({path: path.resolve(__dirname, '../../js', asset)});
        }
        const results = await page.evaluate(() => {
            const visualization = OCA.Analytics.Visualization;
            return ['summation', 'average'].map(mode => {
                const response = {
                    options: {filteroptions: {timeAggregation: {dimension: 'dimension1', grouping: 'quarter', mode}}},
                    keyFigures: ['Revenue'],
                    data: [
                        ['2026-01-01', 'North', '2'], ['2026-03-31', 'North', '4'],
                        ['2026-04-01', 'North', '8'], ['2026-07-01', 'North', '10'],
                        ['2026-10-01', 'North', '12'], ['2027-01-01', 'North', '14'],
                        ['2026-03-31', 'South', '16'],
                    ],
                };
                const data = visualization.applyTimeAggregation(response).data;
                const config = visualization.getTimeAggregationTableDisplayConfig(response.options.filteroptions, {});
                return {data, labels: data.map(row => visualization.formatTimeAggregationDisplayValue(row[0], config))};
            });
        });
        results.forEach((result, index) => {
            assert.deepEqual(result.data, [
                ['2026-01-01', 'North', index === 0 ? '6' : '3'],
                ['2026-04-01', 'North', '8'], ['2026-07-01', 'North', '10'],
                ['2026-10-01', 'North', '12'], ['2027-01-01', 'North', '14'],
                ['2026-01-01', 'South', '16'],
            ]);
            assert.deepEqual(result.labels, ['2026-Q1', '2026-Q2', '2026-Q3', '2026-Q4', '2027-Q1', '2026-Q1']);
        });
        console.log('PASS: quarterly sums, averages, calendar boundaries, dimensions and labels');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
