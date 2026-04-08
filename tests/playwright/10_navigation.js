const { chromium } = require('playwright');
const {
  buildScenarioConfig,
  clickFirst,
  ensureAnalyticsLoaded,
  waitForIdle,
  waitForSplashScreenHidden,
  attachPageIssueListeners,
  createCapture,
} = require('./common');

const config = buildScenarioConfig('10');

(async () => {
  const browser = await chromium.launch({ headless: config.headless });
  const page = await browser.newPage({ viewport: config.viewport });
  const issues = [];
  const visited = [];
  const capture = createCapture(page, config);

  attachPageIssueListeners(page, issues);

  try {
    await ensureAnalyticsLoaded(page, config);
    visited.push(`loaded:${page.url()}`);

    const datasets = await clickFirst(
      page,
      ['#navigationDatasets a', '#app-navigation a[href*="dataset"]', '#navigationDatasets'],
      'datasets',
      { throwOnMissing: false }
    );
    visited.push(datasets.clicked ? `datasets via ${datasets.selector}` : 'datasets not found');
    const reports = await clickFirst(
      page,
      ['#navigationReports a', '#app-navigation a[href*="report"]', '#navigationReports'],
      'reports',
      { throwOnMissing: false }
    );
    visited.push(reports.clicked ? `reports via ${reports.selector}` : 'reports not found');
    const panoramas = await clickFirst(
      page,
      ['#navigationPanoramas a', '#app-navigation a[href*="panorama"]', '#navigationPanoramas'],
      'panoramas',
      { throwOnMissing: false }
    );
    visited.push(panoramas.clicked ? `panoramas via ${panoramas.selector}` : 'panoramas not found');
    const newReport = await clickFirst(
      page,
      ['#newReportButton', 'a#newReportButton', 'a:has-text("New report")', 'a:has-text("New")'],
      'new-report-button',
      { throwOnMissing: false }
    );
    visited.push(newReport.clicked ? `new-report-button via ${newReport.selector}` : 'new-report-button not found');
    await page.waitForTimeout(800);

    const linkHrefs = await page
      .locator('#app-navigation a[href*="/apps/analytics"]')
      .evaluateAll((nodes) => {
        const hrefs = [];
        for (const node of nodes) {
          const href = node.getAttribute('href');
          if (href && !hrefs.includes(href)) {
            hrefs.push(href);
          }
        }
        return hrefs.slice(0, 5);
      })
      .catch(() => []);

    let index = 0;
    for (const href of linkHrefs) {
      index += 1;
      const targetUrl = href.startsWith('http') ? href : new URL(href, page.url()).toString();
      await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {});
      await waitForIdle(page, 10000);
      visited.push(`nav:${href}`);
    }

    const bodyText = (await page.locator('body').innerText().catch(() => '')).toLowerCase();
    const obviousError = /internal server error|something went wrong|error 500|exception/.test(bodyText);

    const result = {
      scriptId: config.scriptId,
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      visited,
      issues,
      obviousError,
      status: !obviousError && issues.length === 0 ? 'PASS' : 'WARN',
    };

    await waitForSplashScreenHidden(page, 12000);
    await capture('state');

    console.log(JSON.stringify(result, null, 2));
    if (result.status !== 'PASS') {
      process.exitCode = 1;
    }
  } catch (error) {
    const result = {
      scriptId: config.scriptId,
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      visited,
      issues: issues.concat([`fatal:${error.message}`]),
      status: 'FAIL',
    };
    await capture('failure_state').catch(() => {});
    console.log(JSON.stringify(result, null, 2));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
