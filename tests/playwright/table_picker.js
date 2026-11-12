/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded} = require('./common');
const config = buildScenarioConfig('table-picker');
(async () => {
    const browser = await chromium.launch({headless: config.headless});
    try {
        const page = await browser.newPage({viewport: config.viewport});
        await ensureAnalyticsLoaded(page, config);
        await page.evaluate(() => {
            const entries = [];
            for (let table = 1; table <= 50; table++) {
                entries.push(`${table}-Table ${table}`);
                for (let view = 1; view <= 10; view++) entries.push(`${table}:${view}-View ${view}`);
            }
            entries.push('99:1-Shared view');
            const input = OCA.Analytics.Datasource.buildOptionsSelect({id: 'tableId', type: 'tf', placeholder: entries.join('/')});
            input.value = '2:3';
            const host = document.createElement('div');
            host.style = 'position:fixed;top:100px;left:300px;z-index:1000';
            host.append(input);
            document.body.append(host);
        });
        const input = page.locator('#tableId');
        await input.click();
        const tree = page.locator('#analyticsDialogContent .jsonPickerTree');
        assert.equal(await tree.locator('details').count(), 51);
        assert.equal(await tree.locator('details[open]').count(), 0);
        await tree.getByText('Table 2', {exact: true}).click();
        const rowHeight = await tree.locator('details[open] label').first().evaluate(row => row.getBoundingClientRect().height);
        assert.ok(rowHeight <= 30, 'Compact view row height: ' + rowHeight);
        assert.equal(await tree.getByLabel('View 3', {exact: true}).filter({visible:true}).isChecked(), true);
        await tree.locator('details[open]').getByLabel('View 5', {exact:true}).check();
        await page.locator('#analyticsDialogBtnCancel').click();
        assert.equal(await input.inputValue(), '2:3');
        await input.focus();
        await input.press('Enter');
        await tree.getByText('Table 3', {exact:true}).click();
        await tree.locator('details[open]').getByLabel('Entire table', {exact:true}).check();
        await page.locator('#analyticsDialogBtnGo').click();
        assert.equal(await input.inputValue(), '3');
        await input.click();
        await tree.getByText('Shared views #99', {exact:true}).click();
        await tree.getByLabel('Shared view', {exact:true}).check();
        await page.locator('#analyticsDialogBtnGo').click();
        assert.equal(await input.inputValue(), '99:1');
        console.log('PASS: 50 tables, collapsed views, saved selection, cancel, keyboard, entire table and shared view');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
