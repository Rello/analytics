const { chromium } = require('playwright');
const {
  buildScenarioConfig,
  clickFirst,
  fillRequired,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  waitForIdle,
} = require('./playwright/common');

const config = buildScenarioConfig('20');
const reportSubheader = process.env.REPORT_SUBHEADER || 'Subheader %currentDate%';
const reportName =
  process.env.REPORT_NAME ||
  `Playwright PoC ${new Date().toISOString().replace(/[^\d]/g, '').slice(0, 14)}`;

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
    await capture('01_loaded');

    steps.push('open new report wizard');
    await clickFirst(
      page,
      ['#newReportButton', 'a#newReportButton', 'a:has-text("New report")', 'a:has-text("New")'],
      'new report button'
    );
    await capture('02_new_entrypoint');

    const wizardOpenedDirectly = await page
      .locator('#wizardNewName')
      .first()
      .isVisible()
      .catch(() => false);
    if (!wizardOpenedDirectly) {
      await clickFirst(
        page,
        ['#newMenuReport', 'a#newMenuReport', 'a:has-text("Report")'],
        'new menu report'
      );
      await capture('03_new_menu_report');
    }

    steps.push('fill report basics');
    await fillRequired(page, '#wizardNewName', reportName, 'wizard report name');
    await fillRequired(page, '#wizardNewSubheader', reportSubheader, 'wizard report subheader');
    await capture('04_basics_filled');
    await clickFirst(page, ['#wizardNext'], 'wizard next from basics');

    steps.push('choose dataset type');
    await clickFirst(page, ['#wizardNewTypeStored', 'button:has-text("Stored Data")'], 'wizard data type');
    await clickFirst(
      page,
      ['#wizardNewTypeStoredNew', 'button:has-text("New dataset")', '#wizardNewTypeStoredDataset'],
      'wizard stored dataset mode'
    );
    await capture('05_type_selected');

    await fillRequired(page, '#wizardNewDimension1', 'Column 1', 'dimension 1');
    await fillRequired(page, '#wizardNewDimension2', 'Column 2', 'dimension 2');
    await capture('06_dimensions_filled');
    await clickFirst(page, ['#wizardNext'], 'wizard next from data step');

    steps.push('configure visualization');
    const tableChoice = await clickFirst(
      page,
      [
        '#wizardNewVisualPage label:has-text("Table")',
        '#wizardNewVisualPage label:has-text("Tabelle")',
        '#wizardNewVisualPage button:has-text("Table")',
        '#wizardNewVisualPage button:has-text("Tabelle")',
      ],
      'table visualization',
      { throwOnMissing: false }
    );
    const chartChoice = await clickFirst(
      page,
      [
        '#wizardNewVisualPage label:has-text("Bar")',
        '#wizardNewVisualPage label:has-text("Balken")',
        '#wizardNewVisualPage button:has-text("Bar")',
        '#wizardNewVisualPage button:has-text("Balken")',
        '#wizardNewVisualBar',
      ],
      'bar visualization',
      { throwOnMissing: false }
    );
    if (!tableChoice.clicked && !chartChoice.clicked) {
      issues.push('warning:visualization choice not detected');
    }
    await capture('07_visualization');
    await clickFirst(
      page,
      ['#wizardNewCreate', 'button:has-text("Create")', 'button:has-text("Erstellen")'],
      'wizard create'
    );

    steps.push('verify report created');
    const safeReportName = reportName.replace(/"/g, '\\"');
    const openCreatedReport = await clickFirst(
      page,
      [
        `#app-navigation a:has-text("${safeReportName}")`,
        `a:has-text("${safeReportName}")`,
        `button[data-name="${safeReportName}"]`,
      ],
      'created report navigation item',
      { throwOnMissing: false }
    );
    if (openCreatedReport.clicked) {
      await waitForIdle(page, 15000);
      await capture('08_open_created_report');
    } else {
      issues.push(`warning:navigation entry for "${reportName}" not found`);
    }

    const header = page.locator('#reportHeader:visible, h2#reportHeader:visible, span#reportHeader:visible').first();
    await header.waitFor({ state: 'visible', timeout: 25000 });
    const headerText = (await header.innerText()).trim();
    if (!headerText.includes(reportName)) {
      throw new Error(`Report header mismatch. Expected to include "${reportName}", got "${headerText}"`);
    }

    const navItemCount = await page.locator('#app-navigation a', { hasText: reportName }).count();
    if (navItemCount === 0) {
      issues.push(`warning:navigation entry for "${reportName}" not found`);
    }

    await capture('09_created');

    const result = {
      scriptId: config.scriptId,
      status: issues.length ? 'WARN' : 'PASS',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      reportName,
      reportSubheaderTemplate: reportSubheader,
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
    await capture('99_failure').catch(() => {});
    console.log(JSON.stringify(result, null, 2));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
