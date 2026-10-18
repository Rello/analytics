/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture} = require('./common');
const config = buildScenarioConfig('json-picker');
(async () => {
    const browser = await chromium.launch({headless: config.headless});
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.url().endsWith('/datasource/json/content') && response.status() !== 200) {
            console.error('JSON browse HTTP', response.status());
        }
    });
    const name = 'analytics-json-picker-' + Date.now() + '.json';
    const fixture = {
        'by-channel': {stable: 381081, enterprise: 113, beta: 1282, daily: 0},
        records: [{name: 'First', detail: {count: 0}, active: false}, {name: 'Second', detail: {count: 5}, active: true, extra: 7}],
        empty: [], 'unsafe/key': 12, '<unsafe>': 5,
        many: Object.fromEntries(Array.from({length: 55}, (_, i) => ['key' + i, i]))
    };
    let uploaded = false;
    try {
        await ensureAnalyticsLoaded(page, config);
        const code = await page.evaluate(async ({name, fixture, user}) => {
            const response = await fetch(OC.generateUrl('/remote.php/dav/files/') + encodeURIComponent(user) + '/' + name,
                {method: 'PUT', headers: OCA.Analytics.headers(), body: JSON.stringify(fixture)});
            return response.status;
        }, {name, fixture, user: config.user});
        assert.ok([201, 204].includes(code), 'Upload fixture: ' + code);
        uploaded = true;
        await page.locator('#newReportButton').click();
        await page.locator('#newMenuReport').click();
        await page.locator('#wizardNewTemplateOwnReport').click();
        await page.locator('#wizardNext').click();
        await page.locator('#wizardNewTypeRealtime').click();
        await page.locator('#wizardNewDatasource').selectOption({label: 'Local: JSON'});
        await page.locator('#dataSourceOptions #link').fill('/' + name);
        await capture('picker_icon');
        const open = async () => {
            await page.locator('.jsonPickerBrowse').click();
            await page.locator('.jsonPickerTree summary').filter({hasText:'by-channel'}).waitFor();
        };
        const group = key => page.locator('.jsonPickerTree summary').filter({hasText: key}).first();
        await open();
        assert.equal(await page.locator('.jsonPickerTree details[open]').count(), 0);
        assert.equal(await page.locator('.jsonPickerTree unsafe').count(), 0);
        assert.equal(await page.getByLabel('unsafe/key: 12', {exact:true}).isDisabled(), true);
        await group('by-channel').click();
        await page.getByRole('button', {name:'Use entries as rows', exact:true}).click();
        assert.equal(await page.locator('#jsonPickerPath').textContent(), 'by-channel');
        await page.getByRole('button', {name:'Preview result', exact:true}).click();
        await page.locator('.jsonPicker [data-role="preview"] td').filter({hasText:'381081'}).waitFor();
        assert.equal(await page.locator('.jsonPicker tbody tr').count(), 4);
        await capture('named_values');
        await page.getByRole('button', {name:'Apply selection', exact:true}).click();
        assert.equal(await page.locator('#dataSourceOptions #path').inputValue(), 'by-channel');
        await open();
        await group('records').click();
        await page.getByRole('button', {name:'Use all records and choose the relevant fields', exact:true}).click();
        await page.getByLabel('name: "First"', {exact:true}).check();
        await group('detail').click();
        await page.getByLabel('count: 0', {exact:true}).check();
        await page.getByLabel('active: false', {exact:true}).check();
        assert.equal(await page.locator('#jsonPickerPath').textContent(), 'records{name,detail/count,active}');
        assert.equal(await page.getByLabel('extra: 7', {exact:true}).count(), 1);
        await page.getByRole('button', {name:'Preview result', exact:true}).click();
        await page.locator('.jsonPicker tbody tr').first().waitFor();
        assert.deepEqual(await page.locator('.jsonPicker tbody tr').first().locator('td').allTextContents(), ['First','0','false']);
        await capture('array_fields');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#dataSourceOptions #path').inputValue(), 'by-channel');
        await open();
        await group('many').click();
        await page.getByRole('button', {name:'Show more', exact:true}).click();
        assert.equal(await page.getByLabel('key54: 54', {exact:true}).count(), 1);
        await group('empty').click();
        await page.getByText('No sample rows available.', {exact:true}).waitFor();
        await page.setViewportSize({width:390,height:844});
        await capture('mobile');
        assert.ok(await page.locator('#analyticsDialogContainer').evaluate(el => el.scrollWidth <= el.clientWidth + 1));
        await page.keyboard.press('Escape');
        await page.locator('#dataSourceOptions #path').fill('by-channel/beta');
        await page.locator('#wizardNewDatasource').selectOption({label:'External: JSON'});
        await page.route('**/apps/analytics/datasource/json/content', route => route.fulfill({json:{content:JSON.stringify(fixture)}}));
        await open();
        await group('by-channel').click();
        await page.getByLabel('daily: 0', {exact:true}).check();
        await page.getByRole('button', {name:'Apply selection', exact:true}).click();
        assert.equal(await page.locator('#dataSourceOptions #path').inputValue(), 'by-channel/daily');
        await page.unroute('**/apps/analytics/datasource/json/content');
        // Root arrays and JSON Lines use the same legacy {field,...} syntax.
        await page.route('**/apps/analytics/datasource/json/content', route => route.fulfill({json:{content:fixture.records.map(row => JSON.stringify(row)).join('\n')}}));
        await page.locator('.jsonPickerBrowse').click();
        await page.getByRole('button', {name:'Use all entries and choose the relevant fields', exact:true}).click();
        await page.getByLabel('name: "First"', {exact:true}).check();
        await page.getByLabel('active: false', {exact:true}).check();
        assert.equal(await page.locator('#jsonPickerPath').textContent(), '{name,active}');
        await page.getByRole('button', {name:'Apply selection', exact:true}).click();
        await page.locator('.jsonPickerBrowse').click();
        await page.getByLabel('name: "First"', {exact:true}).waitFor();
        assert.equal(await page.getByLabel('name: "First"', {exact:true}).isChecked(), true);
        assert.equal(await page.getByLabel('active: false', {exact:true}).isChecked(), true);
        await page.keyboard.press('Escape');
        await page.unroute('**/apps/analytics/datasource/json/content');
        await page.route('**/apps/analytics/datasource/json/content', route => route.fulfill({json:{content:'invalid json'}}));
        await page.locator('.jsonPickerBrowse').click();
        await page.getByRole('alert').filter({hasText:'valid JSON'}).waitFor();
        assert.equal(await page.getByRole('button', {name:'Apply selection', exact:true}).isDisabled(), true);
        await page.keyboard.press('Escape');
        assert.deepEqual(errors, []);
        console.log('PASS: live local JSON browse and parser preview, array fields, cancel, pagination, empty groups, mobile, external picker (mocked response), invalid JSON');
    } catch (error) {
        await capture('failure');
        throw error;
    } finally {
        if (uploaded) await page.evaluate(async ({name, user}) => fetch(OC.generateUrl('/remote.php/dav/files/') + encodeURIComponent(user) + '/' + name,
            {method:'DELETE', headers:OCA.Analytics.headers()}), {name, user:config.user});
        await browser.close();
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
