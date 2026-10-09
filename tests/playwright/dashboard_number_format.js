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
        for (const locale of ['en-US', 'de-DE']) {
            const context = await browser.newContext({locale});
            const page = await context.newPage();
            await page.setContent(`<html lang="${locale}"><body><li id="analyticsWidgetItem-report-1"></li></body></html>`);
            await page.evaluate(() => {
                window.OCA = {Analytics: {}};
                window.OC = {generateUrl: value => '/' + value};
                window.moment = {};
            });
            for (const asset of ['visualization.js', 'dashboard.js']) {
                await page.addScriptTag({path: path.resolve(__dirname, '../../js', asset)});
            }
            const cases = await page.evaluate(() => {
                const results = [];
                const visualization = OCA.Analytics.Visualization;
                for (const entry of [
                    {value: '0.00', expected: '0'},
                    {value: 0, expected: '0'},
                    {value: '1234.50'},
                    {value: '-1234.50'},
                    {value: '0.00', format: {format: 'number', decimals: '2'}},
                    {value: '12.34', format: {format: 'number', decimals: '0'}},
                    {value: '1234.50', format: {format: 'currency', currency: 'EUR', decimals: '2'}, stable: true},
                    {value: '0.25', format: {format: 'percent', decimals: '1'}},
                    {value: '0.00', format: {format: 'text'}, expected: '0.00'},
                    {value: 'No data', expected: 'No data'},
                    {value: null, expected: ''},
                ]) {
                    const report = {
                        header: ['Item', 'Value'],
                        columnRefs: entry.stable ? ['dimension1', 'value'] : [],
                        data: [['Earlier', 42], ['Last', entry.value]],
                        options: {id: 1, name: 'Report', visualization: 'table', tableoptions: {}},
                        thresholds: [{dimension: 1, option: 'LT', value: 100, severity: 2}],
                    };
                    if (entry.format) {
                        report.options.tableoptions.columnFormats = [{
                            ...entry.format,
                            reference: visualization.getTableColumnReference('source', 1, 'Value', null, report.columnRefs[1]),
                        }];
                    }
                    const tableOptions = report.options.tableoptions;
                    const table = visualization.convertDataToDataTableFormat(report.data, tableOptions, report.header, null, report.columnRefs);
                    visualization.applyTableColumnFormats(table.columns, tableOptions);
                    const cell = document.createElement('div');
                    cell.innerHTML = table.columns[1].render(table.data[1][1], 'display');
                    OCA.Analytics.Dashboard.createWidgetContent(report, 0);
                    const widget = document.querySelector('.analyticsWidgetValue');
                    results.push({
                        value: entry.value, actual: widget.textContent, table: cell.textContent,
                        expected: entry.expected, threshold: widget.style.color,
                        expectedThreshold: visualization.validateThreshold(1, entry.value, report.thresholds),
                        subheader: document.querySelector('.analyticsWidgetSmall').textContent,
                    });
                }
                return results;
            });
            for (const result of cases) {
                assert.equal(result.actual, result.table, `${locale}: ${result.value}`);
                if (result.expected !== undefined) assert.equal(result.actual, result.expected);
                assert.equal(result.subheader, 'Last');
                assert.equal(!!result.threshold, !!result.expectedThreshold);
            }
            await context.close();
        }
        console.log('Dashboard values match table formatting in en-US and de-DE; thresholds retain raw values.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exit(1);
});
