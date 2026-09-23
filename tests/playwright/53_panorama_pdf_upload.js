/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage();
        await page.evaluate(() => {
            window.OCA = {Analytics: {
                headers: () => new Headers({'requesttoken': 'test'}),
                Notification: {notification: (type, message) => window.notifications.push({type, message})},
            }};
            window.OC = {
                currentUser: 'recipient@example.com',
                linkToRemote: () => 'https://cloud.test/remote.php/dav/files/',
            };
            window.t = (_app, message) => message;
            window.notifications = [];
            window.requests = [];
            window.responseOk = true;
            window.fetch = async (url, options) => {
                window.requests.push({url, headers: [...options.headers], method: options.method, body: options.body});
                return {ok: window.responseOk};
            };
        });
        await page.addScriptTag({path: path.resolve(__dirname, '../../js/panorama.js')});

        for (const name of ['../target.pdf#', '..\\target?x', '%2F%2E%2E%2F', 'Änalytics']) {
            const result = await page.evaluate(async name => {
                OCA.Analytics.Panorama.Backend.uploadPdf('/Exports/Ä', name + '_2026-09-17.pdf', 'pdf-bytes');
                await new Promise(resolve => setTimeout(resolve, 0));
                return {request: requests.pop(), notification: notifications.pop()};
            }, name);
            const url = new URL(result.request.url);
            assert.equal(url.pathname.split('/').slice(0, -1).join('/'), '/remote.php/dav/files/recipient%40example.com/Exports/%C3%84');
            assert.equal(decodeURIComponent(url.pathname.split('/').at(-1)), name.replace(/[\\/]/g, '_') + '_2026-09-17.pdf');
            assert.equal(result.request.method, 'PUT');
            assert.equal(result.request.body, 'pdf-bytes');
            assert.equal(new Headers(result.request.headers).get('If-None-Match'), '*');
            assert.equal(result.notification.type, 'success');
        }

        const conflict = await page.evaluate(async () => {
            responseOk = false;
            OCA.Analytics.Panorama.Backend.uploadPdf('/', 'existing.pdf', 'pdf-bytes');
            await new Promise(resolve => setTimeout(resolve, 0));
            return {request: requests.pop(), notifications: [...notifications]};
        });
        assert.equal(new URL(conflict.request.url).pathname, '/remote.php/dav/files/recipient%40example.com/existing.pdf');
        assert.deepEqual(conflict.notifications.map(item => item.type), ['error']);

        const invalidFolder = await page.evaluate(() => {
            const requestsBefore = requests.length;
            OCA.Analytics.Panorama.Backend.uploadPdf('/Exports/../Other', 'report.pdf', 'pdf-bytes');
            return {requestsMade: requests.length - requestsBefore, notification: notifications.pop()};
        });
        assert.equal(invalidFolder.requestsMade, 0);
        assert.equal(invalidFolder.notification.type, 'error');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
