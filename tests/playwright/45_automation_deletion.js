/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const { chromium } = require('playwright');
const {
  assertSimulationData,
  attachPageIssueListeners,
  buildScenarioConfig,
  buildUniqueName,
  clickFirst,
  closeAnalyticsDialog,
  createCapture,
  createDataloadEntry,
  deleteDataloadEntryIfExists,
  ensureAnalyticsLoaded,
  ensureStoredReportWithDefaultData,
  fillRequired,
  openReportAutomationTab,
  waitForToastAndDismiss,
} = require('./common');

const config = buildScenarioConfig('45');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);
const dataloadName = 'Test Deletion';

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

    steps.push('prepare stored dataset report');
    await ensureStoredReportWithDefaultData(page, reportName, 'Automation datasource regression');

    steps.push('open automation tab');
    await openReportAutomationTab(page, reportName);
    await deleteDataloadEntryIfExists(page, dataloadName);

    steps.push('create deletion automation');
    await createDataloadEntry(page, { deletion: true });
    await fillRequired(page, '#dataloadName', dataloadName, 'deletion name');
    await page.locator('#filterDimension').first().selectOption({ value: 'dimension2' });
    await fillRequired(page, '#filterValue', 'Dimension 2', 'deletion filter value');
    await clickFirst(page, ['#dataloadUpdateButton'], 'update deletion');
    await waitForToastAndDismiss(page);

    steps.push('execute deletion simulation');
    await clickFirst(page, ['#dataloadExecuteButton'], 'execute deletion');
    await assertSimulationData(page, '{"count":2}');

    await capture('automation_deletion');

    await closeAnalyticsDialog(page);

    steps.push('cleanup deletion automation');
    await deleteDataloadEntryIfExists(page, dataloadName);

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
