/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture} = require('./common');
const config = buildScenarioConfig('55');

(async () => {
    const browser = await chromium.launch({headless: config.headless,
        args: new URL(config.baseUrl).hostname === 'localhost' ? ['--host-resolver-rules=MAP localhost host.docker.internal'] : [],
    });
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const errors = [];
    page.on('pageerror', error => errors.push(error.stack));
    try {
        await ensureAnalyticsLoaded(page, config);
        const openWizard = async () => {
            await page.locator('#newReportButton').focus();
            await page.keyboard.press('Enter');
            await page.locator('#newMenuReport').focus();
            await page.keyboard.press('Enter');
            await page.locator('#wizardNewTemplateOwnReport').focus();
            await page.keyboard.press('Enter');
            await page.locator('#wizardNewName').waitFor({state: 'visible'});
        };
        await openWizard();
        assert.equal(await page.locator('#analyticsWizard').getAttribute('role'), 'dialog');
        await page.locator('#wizardNext').focus();
        await page.keyboard.press('Tab');
        assert.equal(await page.evaluate(() => document.activeElement.id), 'wizardPrevious');
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.evaluate(() => document.activeElement.id), 'wizardNext');
        await page.keyboard.press('Enter');
        await page.locator('#wizardNewTypePage').waitFor({state: 'visible'});
        await page.locator('#wizardPrevious').focus();
        await page.keyboard.press('Enter');
        await page.locator('#wizardNewName').waitFor({state: 'visible'});
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#analyticsWizard').count(), 0);
        assert.equal(await page.evaluate(() => document.activeElement.id), 'newReportButton');

        // All shared modal variants use native buttons, contain Tab and restore focus.
        for (const variant of ['simple', 'enhanced', 'confirm', 'info']) {
            await page.locator('#newReportButton').focus();
            await page.evaluate(variant => {
                const notification = OCA.Analytics.Notification;
                if (variant === 'confirm') notification.confirm('Keyboard check', 'Confirmation', notification.dialogClose);
                else if (variant === 'info') notification.info('Keyboard check', 'Information', 'Guidance');
                else {
                    notification.htmlDialogInitiate('Keyboard check', notification.dialogClose, {variant});
                    const content = document.createElement('div');
                    content.innerHTML = '<label>Value <input></label>';
                    notification.htmlDialogUpdate(content, 'Guidance');
                }
            }, variant);
            const dialog = page.getByRole('dialog', {name: 'Keyboard check'});
            assert.equal(await dialog.getByRole('button', {name: 'OK', exact: true}).count(), 1);
            await dialog.getByRole('button', {name: 'OK', exact: true}).focus();
            await page.keyboard.press('Tab');
            assert.equal(await page.evaluate(() => document.activeElement.id), 'analyticsDialogBtnClose');
            await page.keyboard.press('Shift+Tab');
            assert.equal(await page.evaluate(() => document.activeElement.id), 'analyticsDialogBtnGo');
            await page.keyboard.press('Escape');
            assert.equal(await dialog.count(), 0);
            assert.equal(await page.evaluate(() => document.activeElement.id), 'newReportButton');
        }

        for (const width of [390, 320]) {
            await page.setViewportSize({width, height: 844});
            await page.locator('#mobileNavigationToggle').click();
            await openWizard();
            for (const id of ['wizardNewName', 'wizardNewSubheader', 'wizardNewGrouping']) {
                await page.locator('#' + id).scrollIntoViewIfNeeded();
                const bounds = await page.locator('#' + id).evaluate(element => {
                    const input = element.getBoundingClientRect();
                    const body = document.getElementById('pageBody').getBoundingClientRect();
                    return {left: input.left, right: input.right, top: input.top, bottom: input.bottom,
                        bodyLeft: body.left, bodyRight: body.right, bodyTop: body.top, bodyBottom: body.bottom};
                });
                assert.ok(bounds.left >= bounds.bodyLeft && bounds.right <= bounds.bodyRight, id + ' must fit horizontally');
                assert.ok(bounds.top >= bounds.bodyTop && bounds.bottom <= bounds.bodyBottom, id + ' must be reachable by scrolling');
            }
            await page.locator('#wizardNewName').scrollIntoViewIfNeeded();
            await capture('wizard_mobile_' + width);
            await page.keyboard.press('Escape');
        }
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({scriptId: '55', status: 'PASS'}));
    } catch (error) {
        await capture('failure').catch(() => {});
        console.log(JSON.stringify({scriptId: '55', status: 'FAIL', error: error.message, errors}));
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();
