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

const config = buildScenarioConfig('26');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

async function openChartOptions(page, label) {
  await openOptionsMenuItem(page, 'optionsMenuChartOptions', label);
  await page.locator('#optionsYAxis0').waitFor({ state: 'visible', timeout: 15000 });
}

async function getSelectedAnalyticsModel(page) {
  return page.locator('input[name="analyticsModel"]:checked').getAttribute('id');
}

async function setAnalyticsModel(page, selector) {
  await page.locator(selector).evaluate((node) => {
    node.checked = true;
    node.dispatchEvent(new Event('change', { bubbles: true }));
  });
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
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Chart options regression');
    steps.push(`report ${seedState}`);

    steps.push('configure chart options');
    await openChartOptions(page, 'chart options');
    const initialYAxis = await page.locator('#optionsYAxis0').inputValue();
    const initialChartType = await page.locator('#optionsChartType0').inputValue();
    const initialColor = await page.locator('#optionsColor0').inputValue();
    const initialModel = await getSelectedAnalyticsModel(page);
    await page.locator('#optionsYAxis0').selectOption('secondary');
    await page.locator('#optionsChartType0').selectOption('line');
    await page.locator('#optionsColor0').fill('#cec7e8');
    await setAnalyticsModel(page, '#analyticsModelOpt2');
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply chart options');

    steps.push('save reload and validate chart options');
    await saveAndReloadReport(page, reportName);

    await capture('final_chart_options');

    if (!(await page.locator('#myChart').isVisible().catch(() => false))) {
      throw new Error('Expected chart to remain visible after saving chart options');
    }
    await openChartOptions(page, 'chart options validation');
    if ((await page.locator('#optionsYAxis0').inputValue()) !== 'secondary') {
      throw new Error('Expected saved Y axis selection to stay on secondary');
    }
    if ((await page.locator('#optionsChartType0').inputValue()) !== 'line') {
      throw new Error('Expected saved chart type to stay on line');
    }
    if ((await page.locator('#optionsColor0').inputValue()).toLowerCase() !== '#cec7e8') {
      throw new Error('Expected saved chart color to stay on #cec7e8');
    }
    if ((await getSelectedAnalyticsModel(page)) !== 'analyticsModelOpt2') {
      throw new Error('Expected saved analytics model to stay on columns');
    }
    await clickFirst(page, ['#analyticsDialogBtnCancel'], 'cancel chart options validation');

    steps.push('revert chart options');
    await openChartOptions(page, 'chart options revert');
    await page.locator('#optionsYAxis0').selectOption(initialYAxis);
    await page.locator('#optionsChartType0').selectOption(initialChartType);
    await page.locator('#optionsColor0').fill(initialColor);
    await setAnalyticsModel(page, `#${initialModel}`);
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply reverted chart options');

    steps.push('save reload and validate original report');
    await saveAndReloadReport(page, reportName);
    await openChartOptions(page, 'chart options reverted validation');
    if ((await page.locator('#optionsYAxis0').inputValue()) !== initialYAxis) {
      throw new Error('Saved Y axis selection did not return to its original state');
    }
    if ((await page.locator('#optionsChartType0').inputValue()) !== initialChartType) {
      throw new Error('Saved chart type did not return to its original state');
    }
    if ((await page.locator('#optionsColor0').inputValue()).toLowerCase() !== initialColor.toLowerCase()) {
      throw new Error('Saved chart color did not return to its original state');
    }
    if ((await getSelectedAnalyticsModel(page)) !== initialModel) {
      throw new Error('Saved analytics model did not return to its original state');
    }
    await clickFirst(page, ['#analyticsDialogBtnCancel'], 'cancel reverted chart options validation');

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
