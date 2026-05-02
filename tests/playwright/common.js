const fs = require('fs/promises');
const path = require('path');

function buildScenarioConfig(scriptId, options = {}) {
  return {
    scriptId,
    baseUrl: process.env.BASE_URL || options.baseUrl || 'http://host.docker.internal:8032/apps/analytics/',
    user: process.env.NC_USER || options.user || 'admin',
    pass: process.env.NC_PASS || options.pass || 'admin',
    headless: process.env.HEADLESS !== 'false',
    viewport: options.viewport || { width: 1600, height: 1000 },
    artifactRoot: process.env.ARTIFACT_DIR || path.join(process.cwd(), 'tests', 'ui-artifacts'),
    artifactFlat: process.env.ARTIFACT_FLAT === '1' || options.artifactFlat === true,
    artifactPrefix: process.env.ARTIFACT_PREFIX || options.artifactPrefix || scriptId,
  };
}

function artifactDir(config) {
  if (config.artifactFlat) {
    return config.artifactRoot;
  }
  return path.join(config.artifactRoot, config.scriptId);
}

function sanitizeNameFragment(value) {
  return String(value || '')
    .replace(/[^\w\s-]/g, '')
    .trim()
    .replace(/\s+/g, ' ');
}

function sanitizeFileFragment(value) {
  return String(value || '')
    .trim()
    .replace(/[^\w.-]+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_+|_+$/g, '');
}

function buildUniqueName(prefix, explicitName) {
  if (explicitName) {
    return explicitName;
  }
  const safePrefix = sanitizeNameFragment(prefix) || 'Playwright';
  const suffix = new Date().toISOString().replace(/[^\d]/g, '').slice(0, 17);
  return `${safePrefix} ${suffix}`;
}

async function waitForIdle(page, timeout = 20000) {
  await page.waitForLoadState('networkidle', { timeout }).catch(() => {});
}

async function clickFirst(page, selectors, label, options = {}) {
  const throwOnMissing = options.throwOnMissing !== false;
  const timeout = options.timeout || 8000;
  const waitAfterMs = options.waitAfterMs || 300;

  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (!(await locator.count())) {
      continue;
    }
    try {
      await locator.click({ timeout });
      await waitForIdle(page);
      if (waitAfterMs > 0) {
        await page.waitForTimeout(waitAfterMs);
      }
      return { clicked: true, selector };
    } catch (error) {
      // try fallback selector
    }
  }

  if (throwOnMissing) {
    throw new Error(`Could not click ${label}. Selectors: ${selectors.join(', ')}`);
  }
  return { clicked: false, selector: null };
}

async function fillRequired(page, selector, value, label) {
  const locator = page.locator(selector).first();
  await locator.waitFor({ state: 'visible', timeout: 12000 });
  await locator.fill(value);
  const currentValue = await locator.inputValue();
  if (currentValue !== value) {
    throw new Error(`Could not fill ${label}. Expected "${value}", got "${currentValue}"`);
  }
}

async function ensureAnalyticsLoaded(page, config) {
  await page.goto(config.baseUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(1200);

  const hasLogin = await page.locator('#user, input[name="user"]').first().count();
  if (hasLogin) {
    await page.locator('#user, input[name="user"]').first().fill(config.user);
    await page.locator('#password, input[name="password"]').first().fill(config.pass);
    await clickFirst(page, ['#submit-form', 'button[type="submit"]', 'input[type="submit"]'], 'login submit');
  }

  if (!page.url().includes('/apps/analytics')) {
    await page.goto(config.baseUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
  }
  await waitForIdle(page);
  await dismissAnalyticsOnboarding(page);
  await waitForIdle(page);
}

async function dismissAnalyticsOnboarding(page) {
  const splash = page.locator('#app-splash-screen').first();
  const splashVisible = await splash.isVisible().catch(() => false);
  if (splashVisible) {
    await splash.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
  }

  const wizard = page.locator('#analyticsWizard').first();
  const wizardVisible = await wizard.isVisible().catch(() => false);
  if (!wizardVisible) {
    return;
  }

  const wizardEnd = page.locator('#wizardEnd').first();
  for (let index = 0; index < 8; index += 1) {
    const endVisible = await wizardEnd.isVisible().catch(() => false);
    if (endVisible) {
      await wizardEnd.click();
      await wizard.waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
      return;
    }
    const nextVisible = await page.locator('#wizardNext').first().isVisible().catch(() => false);
    if (!nextVisible) {
      break;
    }
    await page.locator('#wizardNext').first().click();
    await page.waitForTimeout(250);
  }

  const closeVisible = await page.locator('#wizardClose').first().isVisible().catch(() => false);
  if (closeVisible) {
    await page.locator('#wizardClose').first().click();
    await wizard.waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
  }
}

async function waitForSplashScreenHidden(page, timeout = 10000) {
  const splash = page.locator('#app-splash-screen').first();
  if (!(await splash.count().catch(() => 0))) {
    return;
  }
  await splash.waitFor({ state: 'hidden', timeout }).catch(() => {});
}

async function ensureNavigationSection(page, section) {
  const configs = {
    reports: {
      sectionList: '#section-reports',
      toggle: 'li[data-section-id="section-reports"] > .app-navigation-entry > a',
      fallbacks: ['#section-reports a', 'a:has-text("Reports")'],
    },
    datasets: {
      sectionList: '#section-datasets',
      toggle: 'li[data-section-id="section-datasets"] > .app-navigation-entry > a',
      fallbacks: ['#section-datasets a', 'a:has-text("Datasets")'],
    },
    panoramas: {
      sectionList: '#section-panoramas',
      toggle: 'li[data-section-id="section-panoramas"] > .app-navigation-entry > a',
      fallbacks: ['#section-panoramas a', 'a:has-text("Panoramas")'],
    },
  };
  const config = configs[section];
  if (!config) {
    throw new Error(`Unknown navigation section: ${section}`);
  }

  const sectionList = page.locator(config.sectionList).first();
  if (await sectionList.count().catch(() => 0)) {
    const isOpen = await sectionList.evaluate((node) => node.closest('li.collapsible')?.classList.contains('open') ?? true).catch(() => false);
    if (!isOpen) {
      const toggle = page.locator(config.toggle).first();
      if (await toggle.count().catch(() => 0)) {
        await toggle.click().catch(() => {});
      }
    }
  } else {
    await clickFirst(page, [config.toggle, ...config.fallbacks], `${section} navigation`, { throwOnMissing: false });
  }

  await waitForIdle(page, 10000);
}

function navigationEntryLink(page, name) {
  return page.locator(`#app-navigation .app-navigation-entry > a[data-name="${name.replace(/"/g, '\\"')}"]`).first();
}

function navigationEntryMenuButton(page, name) {
  return page.locator(`button[data-name="${name.replace(/"/g, '\\"')}"]`).first();
}

function navigationEntryMenu(page, name) {
  return page.locator(`#navigationMenu[data-name="${name.replace(/"/g, '\\"')}"]`);
}

function reportHeaderLocator(page) {
  return page.locator('#reportHeader:visible, h2#reportHeader:visible, span#reportHeader:visible').first();
}

async function openNavigationEntry(page, name) {
  const links = page.locator(`#app-navigation .app-navigation-entry > a[data-name="${name.replace(/"/g, '\\"')}"]`);
  await links.first().waitFor({ state: 'attached', timeout: 20000 });

  let link = links.filter({ visible: true }).first();
  if (!(await link.count())) {
    await ensureNavigationSection(page, 'reports');
    link = links.filter({ visible: true }).first();
  }
  if (!(await link.count())) {
    link = links.first();
  }

  await link.waitFor({ state: 'visible', timeout: 20000 });
  await link.click();
  await waitForIdle(page, 15000);
}

async function assertReportHeader(page, reportName) {
  const header = reportHeaderLocator(page);
  await header.waitFor({ state: 'visible', timeout: 25000 });
  const headerText = (await header.innerText()).trim();
  if (!headerText.includes(reportName)) {
    throw new Error(`Report header mismatch. Expected to include "${reportName}", got "${headerText}"`);
  }
}

async function openNavigationEntryMenu(page, name) {
  const buttons = page.locator(`button[data-name="${name.replace(/"/g, '\\"')}"]`);
  await buttons.first().waitFor({ state: 'attached', timeout: 15000 });

  let button = buttons.filter({ visible: true }).first();
  if (!(await button.count())) {
    await ensureNavigationSection(page, 'reports');
    button = buttons.filter({ visible: true }).first();
  }

  if (!(await button.count())) {
    button = buttons.first();
  }

  let menu = button.locator('xpath=ancestor::div[contains(@class,"app-navigation-entry-utils")]/following-sibling::div[@id="navigationMenu"][1]').first();
  if (!(await menu.count())) {
    menu = page.locator(`.app-navigation-entry-menu.open[data-name="${name.replace(/"/g, '\\"')}"]`).first();
  }
  if (!(await menu.count())) {
    menu = navigationEntryMenu(page, name).filter({ visible: true }).first();
  }
  if (!(await menu.count())) {
    menu = navigationEntryMenu(page, name).first();
  }

  const openMenu = async () => {
    await button.hover().catch(() => {});
    await button.click().catch(() => {});
    const visibleAfterClick = await menu.isVisible().catch(() => false);
    if (visibleAfterClick) {
      return true;
    }

    await button.evaluate((element) => element.click()).catch(() => {});
    return page.waitForFunction((target) => {
      return target instanceof HTMLElement &&
        target.classList.contains('open') &&
        window.getComputedStyle(target).display !== 'none' &&
        window.getComputedStyle(target).visibility !== 'hidden';
    }, await menu.elementHandle(), { timeout: 3000 }).then(() => true).catch(() => false);
  };

  const opened = await openMenu();
  if (!opened) {
    await button.click({ force: true }).catch(() => {});
  }

  await menu.waitFor({ state: 'visible', timeout: 10000 });
  return menu;
}

async function clickNavigationMenuAction(menu, action, label = action) {
  const actions = {
    edit: ['#navigationMenuEdit', 'a#navigationMenuEdit', 'a:has-text("Basic settings")'],
    share: ['#navigationMenuShare', 'a#navigationMenuShare', 'a:has-text("Share")'],
    rename: ['#navigationMenuRename', 'a#navigationMenuRename', 'a:has-text("Rename")'],
    newGroup: ['#navigationMenuNewGroup', 'a#navigationMenuNewGroup', 'a:has-text("Add to new group")'],
    delete: ['#navigationMenuDelete', 'a#navigationMenuDelete', 'a:has-text("Delete")'],
  };
  const selectors = actions[action];
  if (!selectors) {
    throw new Error(`Unknown navigation menu action: ${action}`);
  }

  for (const selector of selectors) {
    const locator = menu.locator(selector).first();
    if (!(await locator.count())) {
      continue;
    }
    try {
      await locator.click({ timeout: 8000 });
      return;
    } catch (error) {
      // try fallback selector
    }
  }

  throw new Error(`Could not click ${label}. Selectors: ${selectors.join(', ')}`);
}

async function confirmVisibleDialogs(page, maxDialogs = 3) {
  let confirmed = 0;
  for (let index = 0; index < maxDialogs; index += 1) {
    const confirmButton = page.locator('#analyticsDialogBtnGo').first();
    const visible = await confirmButton.isVisible().catch(() => false);
    if (!visible) {
      break;
    }
    await confirmButton.click();
    confirmed += 1;
    await page.waitForTimeout(500);
  }
  return confirmed;
}

async function waitForNavigationEntryState(page, name, state) {
  const entry = navigationEntryLink(page, name);
  if (state === 'visible') {
    await entry.waitFor({ state: 'visible', timeout: 20000 });
    return;
  }
  await page.waitForFunction(
    (targetName) => {
      return !Array.from(document.querySelectorAll('#app-navigation .app-navigation-entry > a')).some((node) => {
        return (node.textContent || '').trim() === targetName;
      });
    },
    name,
    { timeout: 20000 }
  );
}

async function waitForNavigationEntryExists(page, name) {
  await page.waitForFunction(
    (targetName) => {
      const links = Array.from(document.querySelectorAll('#app-navigation .app-navigation-entry > a'));
      const buttons = Array.from(document.querySelectorAll('#app-navigation button[data-name]'));
      return links.some((node) => (node.textContent || '').includes(targetName)) ||
        buttons.some((node) => (node.getAttribute('data-name') || '').includes(targetName));
    },
    name,
    { timeout: 25000 }
  );
}

async function reportExists(page, name) {
  await ensureNavigationSection(page, 'reports');
  const byLink = await navigationEntryLink(page, name).count().catch(() => 0);
  const byButton = await navigationEntryMenuButton(page, name).count().catch(() => 0);
  return byLink > 0 || byButton > 0;
}

async function renameNavigationEntry(page, currentName, nextName) {
  const menu = await openNavigationEntryMenu(page, currentName);
  await clickNavigationMenuAction(menu, 'rename', 'navigation rename');
  const input = page.locator('.navigationRenameInput').first();
  await input.waitFor({ state: 'visible', timeout: 10000 });
  await input.fill(nextName);
  await clickFirst(
    page,
    ['.icon-checkmark', '.navigationRenameInput + .icon-checkmark', 'button:has-text("Save")'],
    'rename confirm'
  );
  await waitForNavigationEntryState(page, nextName, 'visible');
}

async function deleteNavigationEntry(page, name) {
  const menu = await openNavigationEntryMenu(page, name);
  await clickNavigationMenuAction(menu, 'delete', 'navigation delete');
  await confirmVisibleDialogs(page, 3);
  await waitForIdle(page, 15000);
  await waitForNavigationEntryState(page, name, 'hidden');
}

async function openReportBasicSettings(page, reportName) {
  const header = reportHeaderLocator(page);
  const headerVisible = await header.isVisible().catch(() => false);
  const headerText = headerVisible ? ((await header.innerText()).trim()) : '';
  if (!headerVisible || !headerText.includes(reportName)) {
    await openExistingReport(page, reportName);
  }
  for (let attempt = 0; attempt < 2; attempt += 1) {
    const menu = await openNavigationEntryMenu(page, reportName);
    await clickNavigationMenuAction(menu, 'edit', 'report basic settings');
    const sidebarName = page.locator('#sidebarReportName').first();
    const visible = await sidebarName.isVisible().catch(() => false);
    if (visible) {
      return;
    }
    await sidebarName.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    if (await sidebarName.isVisible().catch(() => false)) {
      return;
    }
    await openExistingReport(page, reportName);
  }
  await page.locator('#sidebarReportName').first().waitFor({ state: 'visible', timeout: 15000 });
}

async function openReportDataTab(page, reportName) {
  await openReportBasicSettings(page, reportName);
  await clickFirst(page, ['#tabHeaderData', '.tabHeader#tabHeaderData'], 'data tab');
  await page.locator('#DataDimension1').first().waitFor({ state: 'visible', timeout: 15000 });
}

async function addReportDataRow(page, dimension1, dimension2, value) {
  await fillRequired(page, '#DataDimension1', dimension1, 'data dimension 1');
  await fillRequired(page, '#DataDimension2', dimension2, 'data dimension 2');
  await fillRequired(page, '#DataValue', value, 'data value');
  await clickFirst(page, ['#updateDataButton'], 'update data button');
  await waitForIdle(page, 15000);
}

async function openOptionsMenuItem(page, itemId, label) {
  await clickFirst(page, ['#optionsMenuIcon'], 'options menu icon');
  await clickFirst(page, [`#${itemId}`], label);
}

async function openNavigationShareDialog(page, name) {
  const menu = await openNavigationEntryMenu(page, name);
  await clickNavigationMenuAction(menu, 'share', 'navigation share');
  await page.locator('#shareInput').first().waitFor({ state: 'visible', timeout: 15000 });
}

async function waitForSaveComplete(page, timeout = 30000) {
  const saveIcon = page.locator('#saveIcon').first();
  const isVisible = await saveIcon.isVisible().catch(() => false);
  if (!isVisible) {
    return;
  }
  await saveIcon.waitFor({ state: 'hidden', timeout });
}

async function saveAndReloadReport(page, reportName, options = {}) {
  const {
    clickSave = true,
    reloadTimeout = 45000,
    saveVisibleTimeout = 8000,
  } = options;

  if (clickSave) {
    const saveIcon = page.locator('#saveIcon').first();
    await saveIcon.waitFor({ state: 'visible', timeout: saveVisibleTimeout }).catch(() => {});
    const isVisible = await saveIcon.isVisible().catch(() => false);
    if (isVisible) {
      await saveIcon.click();
      await waitForSaveComplete(page);
    }
  }

  await page.reload({ waitUntil: 'domcontentloaded', timeout: reloadTimeout });
  await waitForIdle(page, 20000);
  await dismissAnalyticsOnboarding(page);
  await waitForIdle(page, 10000);

  if (!reportName) {
    return;
  }

  const header = reportHeaderLocator(page);
  const headerVisible = await header.isVisible().catch(() => false);
  const headerText = headerVisible ? (await header.innerText()).trim() : '';
  if (!headerVisible || !headerText.includes(reportName)) {
    await openExistingReport(page, reportName);
    return;
  }
  await assertReportHeader(page, reportName);
}

async function openExistingReport(page, reportName) {
  await ensureNavigationSection(page, 'reports');
  await waitForNavigationEntryExists(page, reportName);
  await openNavigationEntry(page, reportName);
  await assertReportHeader(page, reportName);
}

async function createStoredReport(page, reportName, reportSubheader, options = {}) {
  const {
    dimension1 = 'Column 1',
    dimension2 = 'Column 2',
    visualization = 'table',
  } = options;

  await clickFirst(
    page,
    ['#newReportButton', 'a#newReportButton', 'a:has-text("New report")', 'a:has-text("New")'],
    'new report button'
  );

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
  }

  await clickFirst(
    page,
    ['#wizardNewTemplateOwnReport', 'button:has-text("Own report")', 'button:has-text("Eigenen Bericht")'],
    'wizard own report'
  );
  await fillRequired(page, '#wizardNewName', reportName, 'wizard report name');
  await fillRequired(page, '#wizardNewSubheader', reportSubheader, 'wizard report subheader');
  await clickFirst(page, ['#wizardNext'], 'wizard next from basics');

  await clickFirst(page, ['#wizardNewTypeStored', 'button:has-text("Stored Data")'], 'wizard stored data');
  await clickFirst(
    page,
    ['#wizardNewTypeStoredNew', 'button:has-text("New dataset")', '#wizardNewTypeStoredDataset'],
    'wizard stored dataset mode'
  );
  await fillRequired(page, '#wizardNewDimension1', dimension1, 'dimension 1');
  await fillRequired(page, '#wizardNewDimension2', dimension2, 'dimension 2');
  await clickFirst(page, ['#wizardNext'], 'wizard next from data step');

  if (visualization === 'chart') {
    await clickFirst(
      page,
      [
        '#chartBar',
        'label[for="chartBar"]',
      ],
      'bar visualization'
    );
  } else {
    await clickFirst(
      page,
      [
        '#chartBar',
        'label[for="chartBar"]',
      ],
      'bar visualization'
    );
  }

  await clickFirst(
    page,
    [
      '#chartTable',
      'label[for="chartTable"]',
    ],
    'table visualization'
  );

  if (visualization === 'chart-only') {
    await clickFirst(
      page,
      [
        '#chartTableNone',
        'label[for="chartTableNone"]',
      ],
      'disable table visualization'
    );
  }

  await clickFirst(
    page,
    ['#wizardNewCreate', 'button:has-text("Create")', 'button:has-text("Erstellen")'],
    'wizard create'
  );
  await waitForIdle(page, 25000);
  const header = reportHeaderLocator(page);
  const headerVisible = await header.isVisible().catch(() => false);
  if (!headerVisible) {
    await waitForNavigationEntryExists(page, reportName);
    await ensureNavigationSection(page, 'reports');
    await waitForNavigationEntryState(page, reportName, 'visible');
    await openNavigationEntry(page, reportName);
  }
  await assertReportHeader(page, reportName);
}

async function openOrCreateStoredReport(page, reportName, reportSubheader, options = {}) {
  if (await reportExists(page, reportName)) {
    await openExistingReport(page, reportName);
    return 'opened';
  }
  await createStoredReport(page, reportName, reportSubheader, options);
  return 'created';
}

async function waitForToast(page, timeout = 15000) {
  const toast = page.locator('.toastify').first();
  await toast.waitFor({ state: 'visible', timeout });
  return toast;
}

async function dismissToast(page) {
  const closeButton = page.locator('.toast-close').first();
  const visible = await closeButton.isVisible().catch(() => false);
  if (!visible) {
    return false;
  }
  await closeButton.click();
  return true;
}

async function waitForToastAndDismiss(page, timeout = 15000) {
  await waitForToast(page, timeout);
  await dismissToast(page).catch(() => false);
}

async function openReportAutomationTab(page, reportName) {
  if (await reportExists(page, reportName)) {
    const menu = await openNavigationEntryMenu(page, reportName);
    const maintenance = menu.locator('#navigationMenuAdvanced, a:has-text("Dataset maintenance")').first();
    if (await maintenance.count().catch(() => 0)) {
      await maintenance.click();
      await waitForIdle(page, 15000);
      await clickFirst(
        page,
        ['#tabHeaderDataload', '#tabHeaderDataload a', 'a:has-text("Automation")', 'a:has-text("Dataload")'],
        'automation tab'
      );
      await page.locator('#datasourceSelect').first().waitFor({ state: 'visible', timeout: 15000 });
      return;
    }
  }

  let datasetId = await page.locator('#datasetId').first().inputValue().catch(() => '');
  if (!datasetId) {
    datasetId = await page.evaluate(() => String(window.OCA?.Analytics?.currentReportData?.options?.dataset || '')).catch(() => '');
  }
  await ensureNavigationSection(page, 'datasets');
  if (!datasetId) {
    datasetId = await page.locator('#datasetId').first().inputValue().catch(() => '');
  }
  let datasetEntry = datasetId
    ? page.locator(`#navigationDatasets a[data-id="${datasetId}"][data-item_type="dataset"]`).first()
    : page.locator('#navigationDatasets a[data-item_type="dataset"]').filter({ hasText: reportName }).first();
  if (!(await datasetEntry.count().catch(() => 0))) {
    datasetEntry = page.locator('#navigationDatasets a[data-item_type="dataset"]').filter({ hasText: reportName }).first();
  }
  await datasetEntry.waitFor({ state: 'attached', timeout: 20000 });
  const entryVisible = await datasetEntry.isVisible().catch(() => false);
  if (entryVisible) {
    await datasetEntry.click();
  } else {
    await datasetEntry.evaluate((node) => node.click());
  }
  await waitForIdle(page, 15000);
  await clickFirst(
    page,
    ['#tabHeaderDataload', '#tabHeaderDataload a', 'a:has-text("Automation")', 'a:has-text("Dataload")'],
    'automation tab'
  );
  await page.locator('#datasourceSelect').first().waitFor({ state: 'visible', timeout: 15000 });
}

async function selectDataloadSource(page, label) {
  const datasource = page.locator('#datasourceSelect').first();
  await datasource.waitFor({ state: 'visible', timeout: 15000 });
  await datasource.selectOption({ label });
}

async function createDataloadEntry(page, options = {}) {
  const { deletion = false } = options;
  await clickFirst(
    page,
    deletion ? ['#createDataDeletionButton'] : ['#createDataloadButton'],
    deletion ? 'create deletion button' : 'create dataload button'
  );
  await clickFirst(page, ['#dataloadList a:has-text("New")', 'a:has-text("New")'], 'new dataload entry', {
    throwOnMissing: false,
    waitAfterMs: 200,
  });
  await page.locator('#dataloadName').first().waitFor({ state: 'visible', timeout: 15000 });
}

function dataloadEntryLink(page, name) {
  return page.locator('#dataLoadList a, #dataDeleteList a').filter({ hasText: name }).first();
}

async function openDataloadEntry(page, name) {
  const entry = dataloadEntryLink(page, name);
  await entry.waitFor({ state: 'visible', timeout: 15000 });
  await entry.click();
  await page.locator('#dataloadName').first().waitFor({ state: 'visible', timeout: 15000 });
}

async function deleteCurrentDataloadEntry(page) {
  await clickFirst(page, ['#dataloadDeleteButton'], 'delete dataload button');
  await clickFirst(
    page,
    ['#analyticsDialogBtnGo', '.oc-dialog-buttonrow > .primary', 'button:has-text("Yes")'],
    'confirm dataload deletion'
  );
  await waitForIdle(page, 15000);
}

async function deleteDataloadEntryIfExists(page, name) {
  const entry = dataloadEntryLink(page, name);
  const count = await entry.count().catch(() => 0);
  if (!count) {
    return false;
  }
  await openDataloadEntry(page, name);
  await deleteCurrentDataloadEntry(page);
  await page.waitForFunction(
    (targetName) => !Array.from(document.querySelectorAll('#dataLoadList a, #dataDeleteList a')).some((node) =>
      (node.textContent || '').includes(targetName)
    ),
    name,
    { timeout: 15000 }
  );
  return true;
}

async function assertSimulationData(page, expectedText) {
  await page.locator('#analyticsDialogContainer').first().waitFor({ state: 'visible', timeout: 30000 });
  const simulation = page.locator('#simulationData').first();
  await simulation.waitFor({ state: 'visible', timeout: 30000 });
  const actual = (await simulation.innerText()).trim();
  if (actual !== expectedText) {
    throw new Error(`Simulation data mismatch. Expected "${expectedText}", got "${actual}"`);
  }
}

async function closeAnalyticsDialog(page) {
  await clickFirst(
    page,
    ['#analyticsDialogBtnGo', '.oc-dialog-buttonrow > .primary', 'button:has-text("OK")'],
    'analytics dialog primary button'
  );
}

async function chooseLocalFile(page, fileName) {
  await page.locator('.dialog__name, .oc-dialog-title').filter({ hasText: 'Select file' }).first()
    .waitFor({ state: 'visible', timeout: 15000 });

  const homeButton = page.locator('.home-icon, .home-icon > .material-design-icon__svg').first();
  if (await homeButton.isVisible().catch(() => false)) {
    await homeButton.click();
    await page.waitForTimeout(300);
  }

  let fileRow = page.locator('.file-picker__row').filter({ hasText: fileName }).first();
  if (!(await fileRow.count().catch(() => 0))) {
    const firstRow = page.locator('.file-picker__row .file-picker__file-name').first();
    await firstRow.waitFor({ state: 'visible', timeout: 15000 });
    await firstRow.click();
    await page.waitForTimeout(500);
    fileRow = page.locator('.file-picker__row').filter({ hasText: fileName }).first();
  }

  if (!(await fileRow.count().catch(() => 0))) {
    throw new Error(`Could not find local file "${fileName}" in file picker`);
  }

  await fileRow.locator('.file-picker__name-container, .file-picker__file-name').first().click();
  await clickFirst(
    page,
    ['.dialog__actions > .button-vue', '.oc-dialog-buttonrow .primary', `button:has-text("${fileName}")`],
    'file picker choose'
  );
}

async function ensureStoredReportWithDefaultData(page, reportName, reportSubheader, options = {}) {
  const reportAction = await openOrCreateStoredReport(page, reportName, reportSubheader, options);
  const requiredMarkers = ['Dimension 1', 'Dimension 11', 'komma, string', 'Top N'];
  const existingRows = await page.locator('#tableContainer tbody tr').evaluateAll((nodes) =>
    nodes.map((node) => node.innerText.replace(/\s+/g, ' ').trim())
  ).catch(() => []);
  const hasSeedData = requiredMarkers.every((marker) => existingRows.some((text) => text.includes(marker)));

  if (!hasSeedData) {
    await openReportDataTab(page, reportName);
    await addReportDataRow(page, 'Dimension 1', 'Dimension 2', '1.1');
    await addReportDataRow(page, 'Dimension 11', 'Threshold Test', '2');
    await addReportDataRow(page, 'komma, string', '123', '1');
    await addReportDataRow(page, 'Top N', 'Dimension 2', '5');
    await openExistingReport(page, reportName);
  }

  return hasSeedData ? `${reportAction}-seeded` : `${reportAction}-seeded-now`;
}

function attachPageIssueListeners(page, issues) {
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      issues.push(`console:${msg.text()}`);
    }
  });
  page.on('pageerror', (err) => issues.push(`pageerror:${err.message}`));
}

function createCapture(page, config) {
  const dir = artifactDir(config);
  return async function capture(name) {
    await fs.mkdir(dir, { recursive: true });
    const safeName = sanitizeFileFragment(name);
    const fileName = config.artifactFlat
      ? `${sanitizeFileFragment(config.artifactPrefix)}_${safeName}.png`
      : `${safeName}.png`;
    await page.screenshot({ path: path.join(dir, fileName), fullPage: true });
  };
}

module.exports = {
  addReportDataRow,
  buildScenarioConfig,
  buildUniqueName,
  waitForIdle,
  clickFirst,
  confirmVisibleDialogs,
  createStoredReport,
  createDataloadEntry,
  ensureStoredReportWithDefaultData,
  openOrCreateStoredReport,
  chooseLocalFile,
  closeAnalyticsDialog,
  assertSimulationData,
  dismissToast,
  dismissAnalyticsOnboarding,
  deleteCurrentDataloadEntry,
  deleteDataloadEntryIfExists,
  waitForSplashScreenHidden,
  fillRequired,
  ensureAnalyticsLoaded,
  ensureNavigationSection,
  deleteNavigationEntry,
  attachPageIssueListeners,
  createCapture,
  artifactDir,
  navigationEntryLink,
  openOptionsMenuItem,
  openNavigationEntry,
  openExistingReport,
  openNavigationEntryMenu,
  openNavigationShareDialog,
  openReportBasicSettings,
  openReportAutomationTab,
  openReportDataTab,
  openDataloadEntry,
  reportExists,
  renameNavigationEntry,
  saveAndReloadReport,
  selectDataloadSource,
  waitForToast,
  waitForToastAndDismiss,
  waitForSaveComplete,
  waitForNavigationEntryExists,
  waitForNavigationEntryState,
};
