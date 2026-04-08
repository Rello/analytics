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
  openOptionsMenuItem,
  saveAndReloadReport,
} = require('./common');

const config = buildScenarioConfig('29');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

async function secondRowFirstCell(page) {
  return page.locator('#tableContainer tbody tr').nth(1).locator('td').first().innerText();
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
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Top N regression');
    steps.push(`report ${seedState}`);

    steps.push('configure top n grouping');
    await openOptionsMenuItem(page, 'optionsMenuTopN', 'top n dialog');
    await page.locator('#groupOptionDimension').waitFor({ state: 'visible', timeout: 15000 });
    await page.locator('#groupOptionDimension').selectOption({ label: 'Column 1' });
    await page.locator('#groupOptionType').selectOption('top');
    await page.locator('#groupOptionNumber').fill('2');
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply top n');

    steps.push('save reload and validate grouped report');
    await saveAndReloadReport(page, reportName);

    await capture('top_n');

    const topNSecondRow = (await secondRowFirstCell(page)).trim();
    if (topNSecondRow !== 'Top N') {
      throw new Error(`Expected "Top N" on the second row after reload, got "${topNSecondRow}"`);
    }
    await openOptionsMenuItem(page, 'optionsMenuTopN', 'top n validation');
    if ((await page.locator('#groupOptionType').inputValue()) !== 'top') {
      throw new Error('Expected saved Top N type to remain active after reload');
    }
    if ((await page.locator('#groupOptionNumber').inputValue()) !== '2') {
      throw new Error('Expected saved Top N number to remain 2 after reload');
    }
    await clickFirst(page, ['#analyticsDialogBtnCancel'], 'cancel top n validation');

    steps.push('revert top n grouping');
    await openOptionsMenuItem(page, 'optionsMenuTopN', 'top n revert');
    await page.locator('#groupOptionType').selectOption('none');
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply reverted top n');

    steps.push('save reload and validate original report');
    await saveAndReloadReport(page, reportName);
    const revertedSecondRow = (await secondRowFirstCell(page)).trim();
    if (revertedSecondRow !== 'Dimension 11') {
      throw new Error(`Expected "Dimension 11" on the second row after reverting Top N, got "${revertedSecondRow}"`);
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
