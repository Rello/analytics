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
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  ensureStoredReportWithDefaultData,
  openOptionsMenuItem,
  saveAndReloadReport,
} = require('./common');

const config = buildScenarioConfig('23');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

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
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Sort regression');
    steps.push(`report ${seedState}`);

    steps.push('sort by value descending');
    await openOptionsMenuItem(page, 'optionsMenuSort', 'sort dialog');
    await page.locator('#sortOptionDimension').selectOption({ label: 'Value' });
    await page.locator('#sortOptionDirection').selectOption({ label: 'Descending' });
    await page.locator('#analyticsDialogBtnGo').click();
    steps.push('save reload and validate sorted report');
    await saveAndReloadReport(page, reportName);

    await capture('value_descending');

    let firstRow = (await page.locator('#tableContainer tbody tr').first().innerText()).replace(/\s+/g, ' ').trim();
    if (firstRow !== 'Top N Dimension 2 5') {
      throw new Error(`Expected first row "Top N | Dimension 2 | 5" after descending value sort, got "${firstRow}"`);
    }

    steps.push('reset sort direction to default');
    await openOptionsMenuItem(page, 'optionsMenuSort', 'sort dialog reset');
    await page.locator('#sortOptionDirection').selectOption({ label: 'Default' });
    await page.locator('#analyticsDialogBtnGo').click();
    steps.push('save reload and validate original report');
    await saveAndReloadReport(page, reportName);

    firstRow = (await page.locator('#tableContainer tbody tr').first().innerText()).replace(/\s+/g, ' ').trim();
    if (!firstRow.startsWith('Dimension 1 Dimension 2')) {
      throw new Error(`Expected default ordering to restore the original first row, got "${firstRow}"`);
    }

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
