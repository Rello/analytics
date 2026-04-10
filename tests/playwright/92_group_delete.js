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
  createCapture,
  deleteNavigationEntry,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  reportExists,
} = require('./common');

const config = buildScenarioConfig('92');
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

    const reportPresent = await reportExists(page, reportName);
    if (!reportPresent) {
      steps.push('skip group cleanup because report does not exist');
    } else {
      const groupPresent = await reportExists(page, groupName);
      if (!groupPresent) {
        steps.push('skip group cleanup because group does not exist');
      } else {
        steps.push('delete existing group');
        await deleteNavigationEntry(page, groupName);
      }
    }

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
