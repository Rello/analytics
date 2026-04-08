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
  ensureNavigationSection,
  attachPageIssueListeners,
  reportExists,
} = require('./common');

const config = buildScenarioConfig('91');
const reportName = buildUniqueName('Playwright Delete Report', process.env.REPORT_NAME);
const gitDatasourcePrefix = 'Playwright Git Datasource';

async function deleteNavigationEntriesByPrefix(page, prefix) {
  await ensureNavigationSection(page, 'reports');

  while (true) {
    const entryName = await page.locator('#app-navigation button[data-name^="Playwright Git Datasource"]').first()
      .getAttribute('data-name')
      .catch(() => null);
    if (!entryName || !entryName.startsWith(prefix)) {
      return;
    }
    await deleteNavigationEntry(page, entryName);
    await ensureNavigationSection(page, 'reports');
  }
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

    const existing = await reportExists(page, reportName);
    if (existing) {
      steps.push('delete existing report');
      await deleteNavigationEntry(page, reportName);
    } else {
      steps.push('skip report cleanup because report does not exist');
    }

    steps.push('delete leftover git datasource reports');
    await deleteNavigationEntriesByPrefix(page, gitDatasourcePrefix);

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
