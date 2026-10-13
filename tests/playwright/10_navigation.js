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
    if (await page.locator('#navigationDatasets li.collapsible.open').count()) {
      throw new Error('Expected Overview to start with all navigation groups collapsed');
    }
    await page.evaluate(() => {
      document.querySelectorAll('#navigationDatasets li.collapsible').forEach((element) => element.classList.add('open'));
    });

    await page.setViewportSize({ width: 390, height: 844 });
    const mobileNavigationToggle = page.locator('#mobileNavigationToggle');
    await mobileNavigationToggle.waitFor({ state: 'visible', timeout: 10000 });
    const mobileNavigationToggleBounds = await mobileNavigationToggle.evaluate((element) => {
      const rect = element.getBoundingClientRect();
      return { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom };
    });
    if (
      mobileNavigationToggleBounds.left < 0 ||
      mobileNavigationToggleBounds.right > 390 ||
      mobileNavigationToggleBounds.top < 0 ||
      mobileNavigationToggleBounds.bottom > 844
    ) {
      throw new Error('Expected the mobile navigation toggle to be inside the viewport');
    }
    if (await mobileNavigationToggle.getAttribute('aria-expanded') !== 'false') {
      throw new Error('Expected the mobile navigation drawer to start closed');
    }
    await mobileNavigationToggle.click();
    await page.locator('#app-navigation.analytics-mobile-navigation-open').waitFor({ state: 'visible', timeout: 10000 });
    if (await mobileNavigationToggle.getAttribute('aria-expanded') !== 'true') {
      throw new Error('Expected the mobile navigation toggle to report an open drawer');
    }
    await page.locator('#overviewButton').click();
    if (await page.locator('#navigationDatasets li.collapsible.open').count()) {
      throw new Error('Expected selecting Overview to collapse all navigation groups');
    }
    await page.waitForFunction(() => !document.getElementById('app-navigation')?.classList.contains('analytics-mobile-navigation-open'));
    await mobileNavigationToggle.waitFor({ state: 'visible', timeout: 10000 });
    if (!(await page.locator('#menuBar').evaluate((element) => element.classList.contains('analytics-mobile-navigation-only')))) {
      throw new Error('Expected the overview dashboard to retain only the mobile navigation control');
    }
    if (await page.locator('#optionsMenuIcon').isVisible()) {
      throw new Error('Expected overview dashboard report options to remain hidden');
    }
    visited.push('mobile-navigation-toggle');
    await page.setViewportSize(config.viewport);

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
      await page.evaluate(() => {
        document.querySelector('#navigationDatasets li[data-section-id="section-favorites"]')?.classList.add('open');
      });
      await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {});
      await waitForIdle(page, 10000);
      if (await page.locator('#navigationDatasets li.collapsible[data-section-id="section-favorites"].open').count()) {
        throw new Error('Expected content links to leave Favorites collapsed by default');
      }
      const contentType = new URL(targetUrl).pathname.match(/\/(r|pa|d)\/\d+$/)?.[1];
      if (contentType) {
        const section = { r: 'section-reports', pa: 'section-panoramas', d: 'section-datasets' }[contentType];
        const openSections = await page.locator('#navigationDatasets > li.collapsible.open').evaluateAll((nodes) => nodes.map((node) => node.dataset.sectionId));
        if (openSections.length !== 1 || openSections[0] !== section) {
          throw new Error('Expected only the URL-selected content section to open');
        }
        await page.evaluate(() => document.querySelectorAll('#navigationDatasets li.collapsible').forEach((node) => node.classList.add('open')));
        await page.reload({ waitUntil: 'domcontentloaded' });
        await waitForIdle(page, 10000);
        const restoredSections = await page.locator('#navigationDatasets > li.collapsible.open').evaluateAll((nodes) => nodes.map((node) => node.dataset.sectionId));
        if (restoredSections.length !== 1 || restoredSections[0] !== section) {
          throw new Error('Expected reload to ignore previously opened sections and follow the URL');
        }
        const selectedId = new URL(targetUrl).pathname.split('/').pop();
        const itemType = { r: 'report', pa: 'panorama', d: 'dataset' }[contentType];
        const selectedItem = page.locator(`#${section} .active > a[data-id="${selectedId}"][data-item_type="${itemType}"]`);
        if (await selectedItem.count() !== 1 || !(await selectedItem.isVisible())) {
          throw new Error('Expected the URL-selected item to be selected and visible after reload');
        }
        const contentUrl = page.url();
        await page.locator('#navigationDatasets > li[data-section-id="section-reports"] > .app-navigation-entry > a').click();
        if (page.url() !== contentUrl) {
          throw new Error('Expected toggling a section to preserve the selected content URL');
        }
      }
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
