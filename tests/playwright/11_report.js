const { chromium } = require('playwright');
const {
  buildScenarioConfig,
  buildUniqueName,
  openOrCreateStoredReport,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
} = require('./common');

const config = buildScenarioConfig('11');
const reportSubheader = process.env.REPORT_SUBHEADER || 'Subheader %currentDate%';
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

    steps.push(process.env.REPORT_NAME ? 'open or create shared regression report' : 'create regression report');
    const action = await openOrCreateStoredReport(page, reportName, reportSubheader);
    steps.push(`report ${action}`);
    const navItemCount = await page.locator('#app-navigation a', { hasText: reportName }).count();
    if (navItemCount === 0) {
      issues.push(`warning:navigation entry for "${reportName}" not found`);
    }
    await capture('created');

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
    await capture('failure_state').catch(() => {});
    console.log(JSON.stringify(result, null, 2));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
