/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const fs = require('fs/promises');
const path = require('path');
const { chromium } = require('playwright');
const {
  buildScenarioConfig,
  buildUniqueName,
  clickFirst,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  openNavigationShareDialog,
  openOrCreateStoredReport,
} = require('./common');

const config = buildScenarioConfig('50');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);
const sharePassword = process.env.SHARE_PASSWORD || 'test';

function shareRows(page) {
  return page.locator('#linkShareList li[data-id]:not([data-id="0"])');
}

async function capturePage(page, name) {
  const safeName = String(name || '')
    .trim()
    .replace(/[^\w.-]+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_+|_+$/g, '');
  const fileName = config.artifactFlat
    ? `${String(config.artifactPrefix || config.scriptId).replace(/[^\w.-]+/g, '_')}_${safeName}.png`
    : `${safeName}.png`;
  const dir = config.artifactFlat
    ? config.artifactRoot
    : path.join(config.artifactRoot, config.scriptId);

  await fs.mkdir(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, fileName), fullPage: true });
}

async function createLinkShare(page) {
  const result = await page.evaluate(async () => {
    const itemType = document.getElementById('shareItemType')?.value;
    const itemId = document.getElementById('shareItemId')?.value;
    const shareType = document.getElementById('newLinkShare')?.dataset?.shareType;
    const requestUrl = OC.generateUrl('apps/analytics/share');

    const response = await fetch(requestUrl, {
      method: 'POST',
      headers: OCA.Analytics.headers(),
      body: JSON.stringify({
        item_type: itemType,
        item_source: itemId,
        type: shareType,
      }),
    });

    const body = await response.text();
    let json = null;
    try {
      json = JSON.parse(body);
    } catch (error) {
      json = null;
    }

    if (response.ok && json) {
      await OCA.Analytics.Share.updateShareModal(itemType, itemId);
    }

    return {
      ok: response.ok,
      status: response.status,
      body,
      json,
      itemType,
      itemId,
      shareType,
    };
  });

  if (!result.ok || !result.json) {
    const snippet = (result.body || '').replace(/\s+/g, ' ').trim().slice(0, 240);
    throw new Error(
      `Create share failed (${result.status}) for ${result.itemType}/${result.itemId} type=${result.shareType}. Response: ${snippet}`
    );
  }
}

async function getCreatedShareRow(page) {
  const rows = shareRows(page);
  await rows.first().waitFor({ state: 'visible', timeout: 15000 });
  return rows.first();
}

async function openShareRowMenu(row) {
  const moreButton = row.locator('#moreIcon, .icon-more').first();
  await moreButton.waitFor({ state: 'visible', timeout: 10000 });
  await moreButton.click();
  const menu = row.locator('.popovermenu.menu').first();
  await menu.waitFor({ state: 'visible', timeout: 10000 });
  return menu;
}

async function setPasswordProtection(row, password) {
  const menu = await openShareRowMenu(row);
  const passwordToggle = menu.locator('input[id^="showPassword"]').first();
  if (!(await passwordToggle.isChecked())) {
    await menu.locator('label[for^="showPassword"]').first().click();
  }
  const passwordInput = row.locator('.linkPassText').first();
  await passwordInput.waitFor({ state: 'visible', timeout: 10000 });
  await passwordInput.fill(password);
  await row.locator('#linkPassSubmit, .share-pass-submit').first().click();
  await row.waitFor({ state: 'detached', timeout: 15000 }).catch(() => {});
}

async function setNavigationPermission(row, enabled) {
  const menu = await openShareRowMenu(row);
  const canNavigateToggle = menu.locator('input[id^="shareEditing"]').first();
  const isChecked = await canNavigateToggle.isChecked();
  if (isChecked !== enabled) {
    if (enabled) {
      await menu.locator('label[for^="shareEditing"]').first().click();
    } else {
      await menu.locator('label[for^="shareEditing"]').first().click();
    }
  }
  await row.waitFor({ state: 'detached', timeout: 15000 }).catch(() => {});
}

async function deleteShare(page, row) {
  const shareId = await row.getAttribute('data-id');
  if (!shareId) {
    throw new Error('Missing share id for delete');
  }

  const result = await page.evaluate(async (targetShareId) => {
    const response = await fetch(OC.generateUrl(`apps/analytics/share/${targetShareId}`), {
      method: 'DELETE',
      headers: OCA.Analytics.headers(),
    });
    const body = await response.text();
    let json = null;
    try {
      json = JSON.parse(body);
    } catch (error) {
      json = null;
    }

    const itemType = document.getElementById('shareItemType')?.value;
    const itemId = document.getElementById('shareItemId')?.value;
    if (response.ok && json) {
      await OCA.Analytics.Share.updateShareModal(itemType, itemId);
    }

    return { ok: response.ok, status: response.status, body, json, shareId: targetShareId };
  }, shareId);

  if (!result.ok || !result.json) {
    const snippet = (result.body || '').replace(/\s+/g, ' ').trim().slice(0, 240);
    throw new Error(`Delete share failed (${result.status}) for share ${result.shareId}. Response: ${snippet}`);
  }

  await row.waitFor({ state: 'detached', timeout: 15000 });
}

async function openPublicShare(page, row) {
  const href = await row.locator('#shareOpenDirect').first().getAttribute('href');
  if (!href) {
    throw new Error('Missing public share href');
  }
  const targetUrl = href.startsWith('http') ? href : new URL(href, page.url()).toString();
  const sharedPage = await page.context().newPage();
  await sharedPage.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
  return sharedPage;
}

async function unlockPublicShare(sharedPage, password) {
  const passwordInput = sharedPage.locator('#password').first();
  const visible = await passwordInput.isVisible().catch(() => false);
  if (!visible) {
    return;
  }
  await passwordInput.fill(password);
  await clickFirst(sharedPage, ['#password-submit'], 'public share password submit');
}

async function assertRestrictedPublicShare(sharedPage, expectedName) {
  await sharedPage.locator('#reportHeader').first().waitFor({ state: 'visible', timeout: 30000 });
  const headerText = (await sharedPage.locator('#reportHeader').first().innerText()).trim();
  if (!headerText.includes(expectedName)) {
    throw new Error(`Expected shared report header to include "${expectedName}", got "${headerText}"`);
  }
}

async function assertNavigationEnabledPublicShare(sharedPage) {
  await sharedPage.locator('#reportHeader').first().waitFor({ state: 'visible', timeout: 30000 });
  await sharedPage.locator('#menuBar').first().waitFor({ state: 'visible', timeout: 15000 });
  await sharedPage.locator('#addFilterIcon').first().waitFor({ state: 'visible', timeout: 15000 });
}

(async () => {
  const browser = await chromium.launch({ headless: config.headless });
  const context = await browser.newContext({ viewport: config.viewport });
  const page = await context.newPage();
  const issues = [];
  const steps = [];
  const capture = createCapture(page, config);

  attachPageIssueListeners(page, issues);

  try {
    steps.push('open analytics');
    await ensureAnalyticsLoaded(page, config);

    steps.push('open or create shared regression report');
    const reportState = await openOrCreateStoredReport(page, reportName, 'Navigation share regression');
    steps.push(`report ${reportState}`);

    steps.push('open share dialog');
    await openNavigationShareDialog(page, reportName);

    steps.push('create password protected public share');
    await createLinkShare(page);
    let row = await getCreatedShareRow(page);
    await setPasswordProtection(row, sharePassword);
    row = await getCreatedShareRow(page);

    steps.push('verify restricted public share');
    let sharedPage = await openPublicShare(page, row);
    await unlockPublicShare(sharedPage, sharePassword);
    await assertRestrictedPublicShare(sharedPage, reportName);
    await capturePage(sharedPage, 'public_share_restricted');
    await sharedPage.close();

    steps.push('enable navigation permission on share');
    await setNavigationPermission(row, true);
    row = await getCreatedShareRow(page);
    const canNavigateEnabled = await row.locator('input[id^="shareEditing"]').first().isChecked().catch(() => false);
    if (!canNavigateEnabled) {
      throw new Error('Expected can navigate toggle to stay enabled after updating the share');
    }

    steps.push('verify navigation enabled public share');
    sharedPage = await openPublicShare(page, row);
    await unlockPublicShare(sharedPage, sharePassword);
    await assertNavigationEnabledPublicShare(sharedPage);
    await capturePage(sharedPage, 'public_share_navigation');
    await sharedPage.close();

    steps.push('delete public share');
    await deleteShare(page, row);
    await page.locator('#newLinkShare').first().waitFor({ state: 'visible', timeout: 15000 });

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
