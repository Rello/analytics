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
  openExistingReport,
  openOrCreateStoredReport,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  openReportBasicSettings,
  renameNavigationEntry,
  waitForNavigationEntryState,
} = require('./common');

const config = buildScenarioConfig('12');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);
const groupName = buildUniqueName('Playwright Regression Group', process.env.GROUP_NAME);

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

    steps.push('open or create shared regression report');
    await openOrCreateStoredReport(page, reportName, 'Group creation seed');
    await openExistingReport(page, reportName);

    steps.push('open report basic settings');
    await openReportBasicSettings(page, reportName);

    steps.push('create group from basic settings');
    await clickFirst(page, ['#sidebarReportGroupHint', '#sidebarReportGroupHint .icon-add'], 'report group hint');
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'create new group confirm');
    await waitForNavigationEntryState(page, 'New', 'visible');

    steps.push('rename created group');
    await renameNavigationEntry(page, 'New', groupName);

    await capture('created');

    const result = {
      scriptId: config.scriptId,
      status: issues.length ? 'WARN' : 'PASS',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      reportName,
      groupName,
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
      groupName,
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
