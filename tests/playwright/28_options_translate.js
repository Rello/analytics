/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const {
  buildScenarioConfig,
  buildUniqueName,
  clickFirst,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  ensureStoredReportWithDefaultData,
} = require('./common');

const config = buildScenarioConfig('28');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

async function waitForHeaders(page, column2Label, valueLabel) {
  await page.waitForFunction(
    ({ expectedColumn2, expectedValue }) => {
      const headers = Array.from(document.querySelectorAll('#tableContainer thead tr th'))
        .map((node) => (node.textContent || '').replace(/\s+/g, ' ').trim());
      return headers[1] === expectedColumn2 && headers[2] === expectedValue;
    },
    { expectedColumn2: column2Label, expectedValue: valueLabel },
    { timeout: 60000 }
  );
}

async function openTranslateMenu(page, label) {
  await clickFirst(page, ['#optionsMenuIcon'], 'options menu icon');
  const translateButton = page.locator('#optionsMenuTranslate').first();
  await translateButton.waitFor({ state: 'visible', timeout: 15000 });
  if (await translateButton.isDisabled().catch(() => false)) {
    return false;
  }
  await clickFirst(page, ['#optionsMenuTranslate'], label);
  await page.locator('#translateLanguage').waitFor({ state: 'visible', timeout: 15000 });
  return true;
}

(async () => {
  const browser = await chromium.launch({ headless: config.headless });
  const page = await browser.newPage({ viewport: config.viewport });
  const issues = [];
  const steps = [];
  const capture = createCapture(page, config);
  const translationRequests = [];

  // Exercise the real translation UI and response parser without relying on
  // provider latency or an LLM returning exactly the expected wording.
  await page.route('**/ocs/v2.php/translation/translate', async route => {
    const request = route.request();
    const body = request.postDataJSON();
    translationRequests.push({method: request.method(), ...body});
    const translations = /^de(?:-|$)/i.test(body.toLanguage)
      ? {'Column 1': 'Spalte 1', 'Column 2': 'Spalte 2', 'Value': 'Wert'}
      : {'Spalte 1': 'Column 1', 'Spalte 2': 'Column 2', 'Wert': 'Value'};
    const text = (translations[body.text] || body.text)
      .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
    await route.fulfill({
      status: 200,
      contentType: 'application/xml',
      body: `<ocs><meta><status>ok</status><statuscode>200</statuscode></meta><data><text>${text}</text></data></ocs>`,
    });
  });

  attachPageIssueListeners(page, issues);

  try {
    steps.push('open analytics');
    await ensureAnalyticsLoaded(page, config);

    steps.push('open seeded report');
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Translate regression');
    steps.push(`report ${seedState}`);

    steps.push('translate report labels to german');
    const translateAvailable = await openTranslateMenu(page, 'translate menu');
    if (!translateAvailable) {
      issues.push('warn:Translate option is disabled in this environment');
      steps.push('translate option unavailable in this environment');
      const result = {
        scriptId: config.scriptId,
        status: 'WARN',
        baseUrl: config.baseUrl,
        finalUrl: page.url(),
        reportName,
        steps,
        issues,
      };
      console.log(JSON.stringify(result, null, 2));
      return;
    }
    const languages = await page.locator('#translateLanguage option').evaluateAll(options => options.map(option => option.value));
    const german = languages.find(language => /^de(?:-|$)/i.test(language));
    const english = languages.find(language => /^en(?:-|$)/i.test(language));
    assert.ok(german && english, 'Translation scenario requires advertised German and English languages');
    await page.locator('#translateLanguage').selectOption(german);
    await waitForHeaders(page, 'Spalte 2', 'Wert');

    steps.push('translate report labels back to english');
    await openTranslateMenu(page, 'translate menu revert');
    await page.locator('#translateLanguage').selectOption(english);
    await waitForHeaders(page, 'Column 2', 'Value');
    for (const [toLanguage, text] of [[german, 'Column 2'], [german, 'Value'], [english, 'Spalte 2'], [english, 'Wert']]) {
      assert.ok(translationRequests.some(request => request.method === 'POST'
        && request.toLanguage === toLanguage && request.text === text && request.fromLanguage === null),
      `Expected a translation request for ${text} to ${toLanguage}`);
    }
    await capture('final_translate');

    const result = {
      scriptId: config.scriptId,
      status: issues.length ? 'WARN' : 'PASS',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      reportName,
      steps,
      issues,
    };
    console.log(JSON.stringify(result, null, 2));
    if (result.status !== 'PASS') {
      process.exitCode = 1;
    }
  } catch (error) {
    const result = {
      scriptId: config.scriptId,
      status: 'FAIL',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      reportName,
      steps,
      issues: issues.concat([`fatal:${error.message}`]),
    };
    await capture('failure_state').catch(() => {});
    console.log(JSON.stringify(result, null, 2));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
