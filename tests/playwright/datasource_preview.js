/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture, openReportBasicSettings} = require('./common');
const config = buildScenarioConfig('datasource-preview');
(async () => {
    const browser = await chromium.launch({headless: config.headless});
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    let reportId;
    const source = 'https://raw.githubusercontent.com/Rello/analytics/master/sample_data/sample1.csv';
    try {
        await ensureAnalyticsLoaded(page, config);
        await page.locator('#newReportButton').click();
        await page.locator('#newMenuReport').click();
        await page.locator('#wizardNewTemplateOwnReport').click();
        await page.locator('#wizardNext').click();
        await page.locator('#wizardNewTypeRealtime').click();
        await page.locator('#wizardNewDatasource').selectOption({label: 'External: csv'});
        await page.locator('#dataSourceOptions #link').fill(source);
        const type = await page.locator('#wizardNewDatasource').inputValue();
        await page.locator('#datasourcePreviewButton').click();
        await page.locator('#simulationData').waitFor();
        assert.equal(JSON.parse(await page.locator('#simulationData').innerText()).data.length, 20);
        await capture('wizard_preview');
        await page.locator('#analyticsDialogBtnClose').click();
        assert.equal(await page.locator('#dataSourceOptions #link').inputValue(), source);
        await page.keyboard.press('Escape');
        const name = 'Datasource preview ' + Date.now();
        reportId = await page.evaluate(async ({name, type, source}) => {
            const response = await fetch(OC.generateUrl('apps/analytics/report'), {
                method: 'POST', headers: OCA.Analytics.headers(),
                body: JSON.stringify({name, subheader:'', parent:0, type:Number(type), dataset:0,
                    link:JSON.stringify({link:source, columns:"", offset:""}), visualization:'table', chart:'line', dimension1:'', dimension2:'', value:''})
            });
            if (!response.ok) throw new Error("Create fixture HTTP " + response.status());
            return response.json();
        }, {name, type, source});
        assert.ok(Number(reportId) > 0, JSON.stringify(reportId));
        await page.reload();
        await ensureAnalyticsLoaded(page, config);
        await openReportBasicSettings(page, name);
        await page.locator('#reportDatasourceSectionHeader').click();
        if (await page.locator('#optionSectionHeader').isVisible()) await page.locator('#optionSectionHeader').click();
        await page.locator('#dataSourceOptions #columns').fill('1,3');
        await page.locator('#dataSourceOptions #offset').fill('59');
        await page.locator('#datasourcePreviewButton').click();
        await page.locator('#simulationData').waitFor();
        assert.deepEqual(JSON.parse(await page.locator('#simulationData').innerText()).data, [['Germany', 83073100]]);
        await capture('sidebar_unsaved_preview');
        await page.locator('#analyticsDialogBtnClose').click();
        await page.route('**/apps/analytics/data', route => route.fulfill({json:{error:'Preview failure <unsafe>', data:[]}}));
        await page.locator('#datasourcePreviewButton').click();
        await page.getByRole('alert').filter({hasText:'Preview failure <unsafe>'}).waitFor();
        assert.equal(await page.locator('#analyticsDialogContent unsafe').count(), 0);
        await page.locator('#analyticsDialogBtnClose').click();
        await page.unroute('**/apps/analytics/data');
        assert.deepEqual(errors, []);
        console.log('PASS: wizard preview, sidebar unsaved settings, errors, dialog return');
    } catch (error) {
        await capture('failure');
        throw error;
    } finally {
        if (Number(reportId) > 0) await page.evaluate(async id => fetch(OC.generateUrl('apps/analytics/report/') + id,
            {method:'DELETE', headers:OCA.Analytics.headers()}), reportId);
        await browser.close();
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
