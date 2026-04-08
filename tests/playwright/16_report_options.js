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
  createStoredReport,
  openExistingReport,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  openReportBasicSettings,
  fillRequired,
  waitForIdle,
} = require('./common');

const config = buildScenarioConfig('16');
const reportName = buildUniqueName('Playwright Report Options', process.env.REPORT_NAME);
const chartOptions = '{"scales": {"x": {"time": {"unit" : "month"}}}}';
const dataOptions = '{"0": {"type":"line"}}';

async function chartVisible(page) {
  return page.locator('#chartContainer').first().isVisible().catch(() => false);
}

async function tableVisible(page) {
  return page.locator('#tableContainer').first().isVisible().catch(() => false);
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

    if (process.env.REPORT_NAME) {
      steps.push('open shared report');
      await openExistingReport(page, reportName);
    } else {
      steps.push('create report');
      await createStoredReport(page, reportName, 'Sidebar report options regression');
    }

    steps.push('open visualization settings');
    await openReportBasicSettings(page, reportName);
    await clickFirst(page, ['#reportVisualizationSectionHeaderH3'], 'visualization section');
    await page.locator('#sidebarReportChartOptions').waitFor({ state: 'visible', timeout: 15000 });

    steps.push('save custom chart and data options');
    await fillRequired(page, '#sidebarReportChartOptions', chartOptions, 'sidebar report chart options');
    await fillRequired(page, '#sidebarReportDataOptions', dataOptions, 'sidebar report data options');
    await clickFirst(page, ['#sidebarReportUpdateButton'], 'sidebar report update');
    await waitForIdle(page, 15000);

    const toastCount = await page.locator('.toastify').count().catch(() => 0);
    if (toastCount !== 0) {
      throw new Error('Unexpected toast notification after saving report options');
    }

    const storedChartOptions = await page.locator('#sidebarReportChartOptions').inputValue();
    const storedDataOptions = await page.locator('#sidebarReportDataOptions').inputValue();
    if (storedChartOptions !== chartOptions) {
      throw new Error(`Chart options were not preserved after update: "${storedChartOptions}"`);
    }
    if (storedDataOptions !== dataOptions) {
      throw new Error(`Data options were not preserved after update: "${storedDataOptions}"`);
    }

    steps.push('clear custom chart and data options');
    await fillRequired(page, '#sidebarReportChartOptions', '', 'clear sidebar report chart options');
    await fillRequired(page, '#sidebarReportDataOptions', '', 'clear sidebar report data options');
    await clickFirst(page, ['#sidebarReportUpdateButton'], 'sidebar report update after clear');
    await waitForIdle(page, 15000);

    const chartOptionsAfterClear = await page.locator('#sidebarReportChartOptions').inputValue();
    const dataOptionsAfterClear = await page.locator('#sidebarReportDataOptions').inputValue();
    if (chartOptionsAfterClear !== '') {
      throw new Error(`Chart options were not cleared: "${chartOptionsAfterClear}"`);
    }
    if (dataOptionsAfterClear !== '') {
      throw new Error(`Data options were not cleared: "${dataOptionsAfterClear}"`);
    }

    steps.push('switch visualization to table only');
    await page.locator('#sidebarReportVisualization').selectOption({ label: 'Table' });
    await clickFirst(page, ['#sidebarReportUpdateButton'], 'sidebar report update table only');
    await waitForIdle(page, 15000);

    if (!(await tableVisible(page))) {
      throw new Error('Expected table to stay visible in table-only mode');
    }
    if (await chartVisible(page)) {
      throw new Error('Expected chart to be hidden in table-only mode');
    }

    steps.push('restore chart and table visualization');
    await openReportBasicSettings(page, reportName);
    await clickFirst(page, ['#reportVisualizationSectionHeaderH3'], 'visualization section restore');
    await page.locator('#sidebarReportVisualization').selectOption({ label: 'Chart & Table' });
    await clickFirst(page, ['#sidebarReportUpdateButton'], 'sidebar report update restore chart and table');
    await waitForIdle(page, 15000);

    if (!(await tableVisible(page))) {
      throw new Error('Expected table to be visible again after restoring chart and table mode');
    }
    if (!(await chartVisible(page))) {
      throw new Error('Expected chart to be visible again after restoring chart and table mode');
    }

    await capture('report_options');

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
