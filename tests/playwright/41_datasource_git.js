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
  fillRequired,
  waitForIdle,
  openReportBasicSettings,
} = require('./common');

const config = buildScenarioConfig('41');
const reportName = buildUniqueName('Playwright Git Datasource', process.env.GIT_REPORT_NAME);

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

    steps.push('create realtime github report');
    await clickFirst(
      page,
      ['#newReportButton', 'a#newReportButton', 'a:has-text("New report")', 'a:has-text("New")'],
      'new report button'
    );

    const wizardOpenedDirectly = await page.locator('#wizardNewTemplateOwnReport').first().isVisible().catch(() => false);
    if (!wizardOpenedDirectly) {
      await clickFirst(page, ['#newMenuReport', 'a#newMenuReport', 'a:has-text("Report")'], 'new menu report');
    }

    await clickFirst(
      page,
      ['#wizardNewTemplateOwnReport', 'button:has-text("Own report")', 'button:has-text("Eigenen Bericht")'],
      'wizard own report'
    );
    await fillRequired(page, '#wizardNewName', reportName, 'wizard report name');
    await clickFirst(page, ['#wizardNext'], 'wizard next');
    await clickFirst(page, ['#wizardNewTypeRealtime', 'button:has-text("Real-time Data")'], 'realtime data type');
    await page.locator('#wizardNewDatasource').first().waitFor({ state: 'visible', timeout: 15000 });
    await page.locator('#wizardNewDatasource').first().selectOption({ label: 'GitHub' });
    await fillRequired(page, '#wizardNewDatasourceSection #user, #user', 'rello', 'github user');
    await fillRequired(page, '#wizardNewDatasourceSection #repository, #repository', 'analytics', 'github repository');
    await fillRequired(page, '#wizardNewDatasourceSection #limit, #limit', '10', 'github limit');
    await page.locator('#wizardNewDatasourceSection #timestamp, #timestamp').first().selectOption({ value: 'false' });
    await clickFirst(page, ['#wizardNext'], 'wizard next to visualization');
    await clickFirst(page, ['#chartBar', 'label[for="chartBar"]'], 'bar chart');
    await clickFirst(page, ['#chartTable', 'label[for="chartTable"]'], 'table chart combination');
    await clickFirst(page, ['#wizardNewCreate', 'button:has-text("Create")'], 'wizard create');
    await waitForIdle(page, 25000);

    steps.push('verify created report');
    await page.locator('#reportHeader').first().waitFor({ state: 'visible', timeout: 30000 });
    const headerText = (await page.locator('#reportHeader').first().innerText()).trim();
    if (!headerText.includes(reportName)) {
      throw new Error(`Report header mismatch. Expected "${reportName}", got "${headerText}"`);
    }

    steps.push('verify datasource settings');
    await openReportBasicSettings(page, reportName);

    const userValue = await page.locator('#reportDatasourceSection #user, #user').first().inputValue();
    if (userValue !== 'rello') {
      throw new Error(`Expected GitHub user "rello", got "${userValue}"`);
    }

    const datasourceSectionHeader = page.locator('#reportDatasourceSectionHeaderH3').first();
    if (!(await page.locator('#reportDatasourceSection #repository, #repository').first().isVisible().catch(() => false))) {
      await datasourceSectionHeader.click();
    }

    const repositoryValue = await page.locator('#reportDatasourceSection #repository, #repository').first().inputValue();
    const limitValue = await page.locator('#reportDatasourceSection #limit, #limit').first().inputValue();
    const timestampValue = await page.locator('#reportDatasourceSection #timestamp, #timestamp').first().inputValue();
    const visualizationValue = await page.locator('#sidebarReportVisualization').first().inputValue();

    if (repositoryValue !== 'analytics') {
      throw new Error(`Expected repository "analytics", got "${repositoryValue}"`);
    }
    if (limitValue !== '10') {
      throw new Error(`Expected limit "10", got "${limitValue}"`);
    }
    if (timestampValue !== 'false') {
      throw new Error(`Expected timestamp "false", got "${timestampValue}"`);
    }
    if (visualizationValue !== 'ct') {
      throw new Error(`Expected visualization "ct", got "${visualizationValue}"`);
    }

    await capture('datasource_git');

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
