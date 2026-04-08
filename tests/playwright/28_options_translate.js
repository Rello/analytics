/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
    await page.locator('#translateLanguage').selectOption('de');
    await waitForHeaders(page, 'Spalte 2', 'Wert');

    steps.push('translate report labels back to english');
    await openTranslateMenu(page, 'translate menu revert');
    await page.locator('#translateLanguage').selectOption('en-gb');
    await waitForHeaders(page, 'Column 2', 'Value');
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
