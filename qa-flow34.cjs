const { chromium } = require('playwright');

const BASE = 'http://localhost:8000';
const TS = Date.now();
const EO_EMAIL = `eo34_${TS}@test.com`;
const EO_PASS  = 'password123';
const SA_EMAIL = 'superadmin@plesticket.com';
const SA_PASS  = 'adminpass';

const results = [];
function log(id, step, result, note = '') {
    const icon = result === 'PASS' ? '✅' : result === 'FAIL' ? '❌' : '⚠️';
    console.log(`${icon} [${id}] ${step} → ${result}${note ? ' | ' + note : ''}`);
    results.push({ id, step, result, note });
}

async function go(page, url) {
    await page.goto(url);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
}

async function loginEO(page) {
    await go(page, `${BASE}/admin/login`);
    await page.fill('input[placeholder="you@example.com"]', EO_EMAIL);
    await page.fill('input[placeholder="••••••••"]', EO_PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
}

async function loginAdmin(page) {
    await go(page, `${BASE}/plest-admin/login`);
    await page.fill('input[placeholder="admin@plesticket.com"]', SA_EMAIL);
    await page.fill('input[placeholder="••••••••"]', SA_PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
}

const fmt = d => d.toISOString().slice(0, 10);
const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
const dayAfter  = new Date(); dayAfter.setDate(dayAfter.getDate() + 2);

async function createEvent(page, title) {
    await go(page, `${BASE}/admin/events/create`);
    await page.fill('input[placeholder="Event title"]', title);
    await page.fill('textarea[placeholder="Event description"]', 'QA test event');
    await page.fill('input[placeholder="Full name"]', 'QA PIC');
    await page.fill('input[placeholder="ID number"]', '1234567890123456');
    const dates = await page.locator('input[type="date"]').all();
    if (dates.length >= 2) { await dates[0].fill(fmt(tomorrow)); await dates[1].fill(fmt(dayAfter)); }
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    return page.url().includes('/admin/events') && !page.url().includes('create');
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    const ctx  = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();

    // ── SETUP: Register EO + create two events ────────────────────────────
    console.log('── Setup: registering EO and creating events…');
    await go(page, `${BASE}/admin/register`);
    await page.fill('input[placeholder="John Doe"]', 'QA EO User');
    await page.fill('input[placeholder="johndoe"]', `eo34${TS}`);
    await page.fill('input[placeholder="john@example.com"]', EO_EMAIL);
    await page.fill('input[placeholder="Min 8 characters"]', EO_PASS);
    await page.fill('input[placeholder="08123456789"]', '08111111111');
    await page.fill('input[type="date"]', '1995-01-15');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await loginEO(page);

    // Event A — will be verified then suspended
    const eventATitle = `QA Event A ${TS}`;
    await createEvent(page, eventATitle);

    // Event B — will be rejected
    const eventBTitle = `QA Event B ${TS}`;
    await createEvent(page, eventBTitle);

    console.log('── Setup complete. Starting Flow 3…\n');

    // ── FLOW 3: Super Admin Verifies Event ────────────────────────────────

    // 3.1 Login as Super Admin → /plest-admin/events
    await loginAdmin(page);
    const url31 = page.url();
    log('3.1', 'Login as Super Admin → /plest-admin/events', url31.includes('/plest-admin/events') ? 'PASS' : 'FAIL', `url: ${url31}`);

    // 3.2 Find EO's event in list (status: pending)
    await go(page, `${BASE}/plest-admin/events`);
    const eventARow = page.locator(`tr:has-text("${eventATitle}")`).first();
    const pendingBadge = eventARow.locator('text=pending');
    const foundPending = await pendingBadge.isVisible().catch(() => false);
    log('3.2', 'EO event appears in admin list with pending badge', foundPending ? 'PASS' : 'FAIL');

    // 3.3 Click View → event detail page with full info
    const viewBtn = eventARow.locator('button:has-text("View"), a:has-text("View")').first();
    await viewBtn.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const onDetail = page.url().includes('/plest-admin/events/');
    const picSection = await page.locator('text=PIC').first().isVisible().catch(() => false);
    const verifyBtn = await page.locator('button:has-text("Verify")').isVisible().catch(() => false);
    log('3.3', 'Event detail shows full info + action buttons', (onDetail && picSection && verifyBtn) ? 'PASS' : 'FAIL',
        `detail:${onDetail} PIC:${picSection} VerifyBtn:${verifyBtn}`);

    // Store event A URL for later (suspend)
    const eventAUrl = page.url();

    // 3.4 Click Verify → status changes to verified
    await page.locator('button:has-text("Verify")').click();
    await page.waitForTimeout(2500);
    const statusAfterVerify = await page.locator('text=verified').first().isVisible().catch(() => false);
    const verifyBtnGone = !(await page.locator('button:has-text("Verify")').isVisible().catch(() => false));
    const suspendBtnVisible = await page.locator('button:has-text("Suspend")').isVisible().catch(() => false);
    log('3.4', 'Click Verify → status changes to verified', (statusAfterVerify && verifyBtnGone && suspendBtnVisible) ? 'PASS' : 'FAIL',
        `verified:${statusAfterVerify} verifyGone:${verifyBtnGone} suspendShown:${suspendBtnVisible}`);

    // 3.5 Reject event B
    await go(page, `${BASE}/plest-admin/events`);
    const eventBRow = page.locator(`tr:has-text("${eventBTitle}")`).first();
    const viewBtnB = eventBRow.locator('button:has-text("View"), a:has-text("View")').first();
    await viewBtnB.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await page.locator('button:has-text("Reject")').click();
    await page.waitForTimeout(1000);
    // modal opens
    const modalVisible = await page.locator('textarea[placeholder*="rejected"]').isVisible().catch(() => false);
    if (modalVisible) {
        await page.fill('textarea[placeholder*="rejected"]', 'Missing required documents');
        await page.locator('button:has-text("Confirm Reject")').click();
        await page.waitForTimeout(2500);
    }
    const statusAfterReject = await page.locator('text=rejected').first().isVisible().catch(() => false);
    const rejectionReason = await page.locator('text=Missing required documents').isVisible().catch(() => false);
    log('3.5', 'Reject with reason → status changes to rejected + reason shown',
        (statusAfterReject && rejectionReason) ? 'PASS' : 'FAIL',
        `rejected:${statusAfterReject} reason:${rejectionReason}`);

    // 3.6 Suspend verified event A
    await page.goto(eventAUrl);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const suspendBtn = page.locator('button:has-text("Suspend")');
    const canSuspend = await suspendBtn.isVisible().catch(() => false);
    if (canSuspend) {
        await suspendBtn.click();
        await page.waitForTimeout(2500);
    }
    const statusAfterSuspend = await page.locator('text=suspended').first().isVisible().catch(() => false);
    const suspendBtnGone = !(await page.locator('button:has-text("Suspend")').isVisible().catch(() => false));
    log('3.6', 'Suspend verified event → status changes to suspended',
        (statusAfterSuspend && suspendBtnGone) ? 'PASS' : 'FAIL',
        `suspended:${statusAfterSuspend} suspendGone:${suspendBtnGone}`);

    // ── FLOW 4: EO Edits Event ────────────────────────────────────────────

    // Login as EO
    await loginEO(page);

    // 4.1 Click edit on a rejected event (event B) → edit form loads pre-filled
    await go(page, `${BASE}/admin/events`);
    // Find event B card and click Edit
    const editBtnB = page.locator(`text="${eventBTitle}"`).locator('..').locator('..').locator('..').locator('..').locator('button:has-text("Edit")');
    // alternative: find any Edit button visible on the page
    const editLinks = page.locator('button:has-text("Edit"), a:has-text("Edit")');
    const editCount = await editLinks.count();
    let editClicked = false;
    for (let i = 0; i < editCount; i++) {
        const text = await editLinks.nth(i).textContent();
        if (text?.trim() === 'Edit') {
            await editLinks.nth(i).click();
            editClicked = true;
            break;
        }
    }
    if (!editClicked) {
        // try navigating via URL - get event ID from the page
        await go(page, `${BASE}/admin/events`);
        const detailLinks = page.locator('button:has-text("Detail")');
        if (await detailLinks.count() > 0) {
            await detailLinks.first().click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);
            const editBtn = page.locator('button:has-text("Edit Event")');
            if (await editBtn.isVisible()) { await editBtn.click(); editClicked = true; }
        }
    }
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const onEditForm = page.url().includes('/edit');
    const titlePrefilled = await page.locator('input[placeholder="Event title"]').inputValue().catch(() => '');
    log('4.1', 'Click Edit on pending/rejected event → form loads pre-filled',
        (onEditForm && titlePrefilled.length > 0) ? 'PASS' : 'FAIL',
        `onEdit:${onEditForm} titleValue:"${titlePrefilled}"`);

    // 4.2 Change title and save
    if (onEditForm) {
        const newTitle = `${eventBTitle} EDITED`;
        await page.fill('input[placeholder="Event title"]', newTitle);
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        const redirectedAfterEdit = page.url().includes('/admin/events') && !page.url().includes('edit');
        log('4.2', 'Change title and save → redirected back to events list',
            redirectedAfterEdit ? 'PASS' : 'FAIL', `url: ${page.url()}`);

        // Verify title updated
        await page.waitForTimeout(1000);
        const updatedTitle = await page.locator(`text="${newTitle}"`).isVisible().catch(() => false);
        log('4.2b', 'Updated title appears in events list', updatedTitle ? 'PASS' : 'FAIL');
    } else {
        log('4.2', 'Change title and save', 'FAIL', 'could not reach edit form');
        log('4.2b', 'Updated title appears in events list', 'FAIL', 'could not reach edit form');
    }

    // 4.3 PIC identity number — blank keeps existing value
    // Go back to edit form
    await go(page, `${BASE}/admin/events`);
    const detailBtns = page.locator('button:has-text("Detail")');
    if (await detailBtns.count() > 0) {
        await detailBtns.first().click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        const editEventBtn = page.locator('button:has-text("Edit Event")');
        if (await editEventBtn.isVisible()) {
            await editEventBtn.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);
        }
    }
    const idPlaceholder = await page.locator('input[placeholder="Leave blank to keep current"]').isVisible().catch(() => false);
    log('4.3', 'PIC identity number field shows "Leave blank to keep current" in edit mode',
        idPlaceholder ? 'PASS' : 'FAIL');

    // 4.4 Suspended/verified event has no Edit button — check event A detail page
    await page.goto(eventAUrl);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const statusLabel = await page.locator('text=suspended, text=verified').first().textContent().catch(() => '');
    const editBtnOnDetail = await page.locator('button:has-text("Edit Event")').isVisible().catch(() => false);
    log('4.4', 'Suspended/verified event detail has no Edit Event button',
        (!editBtnOnDetail) ? 'PASS' : 'FAIL',
        `status:"${statusLabel.trim()}" editBtnVisible:${editBtnOnDetail}`);

    await browser.close();

    // ── Summary ──────────────────────────────────────────────────────────
    console.log('\n─────────────────────────────');
    const passed = results.filter(r => r.result === 'PASS').length;
    const failed = results.filter(r => r.result === 'FAIL').length;
    console.log(`Total: ${results.length}  |  PASS: ${passed}  |  FAIL: ${failed}`);
    if (failed > 0) {
        console.log('\nFailed cases:');
        results.filter(r => r.result === 'FAIL').forEach(r =>
            console.log(`  ❌ [${r.id}] ${r.step}${r.note ? ' | ' + r.note : ''}`)
        );
    }
    process.exit(failed > 0 ? 1 : 0);
})();
