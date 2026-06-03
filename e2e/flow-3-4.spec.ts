import { test, expect, type Page } from '@playwright/test';
import { navigate, loginEO, loginAdmin, registerEO, createEvent, adminVerifyEvent, futureDate } from './helpers';

const TS       = Date.now();
const EO_EMAIL = `eo34_${TS}@test.com`;
const EO_USER  = `eo34_${TS}`;
const EO_PASS  = 'password123';

const EVENT_A = `QA-A-${TS}`; // will be verified → suspended
const EVENT_B = `QA-B-${TS}`; // will be rejected

// ── Flow 3: Super Admin Verifies Event ────────────────────────────────────────

test.describe.serial('Flow 3: Super Admin Verifies Event', () => {
    let page: Page;
    let eventAUrl: string;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();

        // Register EO + create two events
        await registerEO(page, EO_EMAIL, EO_USER, EO_PASS);
        await loginEO(page, EO_EMAIL, EO_PASS);
        await createEvent(page, EVENT_A);
        await createEvent(page, EVENT_B);
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('3.1 login as Super Admin → /plest-admin/events', async () => {
        await loginAdmin(page);
        expect(page.url()).toContain('/plest-admin/events');
    });

    test('3.2 EO event appears in list with pending badge', async () => {
        await navigate(page, '/plest-admin/events');
        const row = page.locator(`tr:has-text("${EVENT_A}")`).first();
        await expect(row.locator('text=pending')).toBeVisible();
    });

    test('3.3 event detail shows full info + action buttons', async () => {
        const row = page.locator(`tr:has-text("${EVENT_A}")`).first();
        await row.locator('button:has-text("View"), a:has-text("View")').click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        eventAUrl = page.url();
        expect(page.url()).toContain('/plest-admin/events/');
        await expect(page.locator('text=PIC').first()).toBeVisible();
        await expect(page.locator('button:has-text("Verify")')).toBeVisible();
        await expect(page.locator('button:has-text("Reject")')).toBeVisible();
    });

    test('3.4 click Verify → status changes to verified, Suspend appears', async () => {
        await page.locator('button:has-text("Verify")').click();
        await page.waitForTimeout(2000);

        await expect(page.locator('text=verified').first()).toBeVisible();
        await expect(page.locator('button:has-text("Verify")')).not.toBeVisible();
        await expect(page.locator('button:has-text("Suspend")')).toBeVisible();
    });

    test('3.5 reject event B with reason → status rejected + reason shown', async () => {
        await navigate(page, '/plest-admin/events');
        const rowB = page.locator(`tr:has-text("${EVENT_B}")`).first();
        await rowB.locator('button:has-text("View"), a:has-text("View")').click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await page.locator('button:has-text("Reject")').click();
        await page.waitForTimeout(800);
        await expect(page.locator('textarea[placeholder*="rejected"]')).toBeVisible();
        await page.fill('textarea[placeholder*="rejected"]', 'Missing required documents');
        await page.locator('button:has-text("Confirm Reject")').click();
        await page.waitForTimeout(2000);

        await expect(page.locator('text=rejected').first()).toBeVisible();
        await expect(page.locator('text=Missing required documents')).toBeVisible();
    });

    test('3.6 suspend verified event A → status changes to suspended', async () => {
        await page.goto(eventAUrl);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await page.locator('button:has-text("Suspend")').click();
        await page.waitForTimeout(2000);

        await expect(page.locator('text=suspended').first()).toBeVisible();
        await expect(page.locator('button:has-text("Suspend")')).not.toBeVisible();
    });
});

// ── Flow 4: EO Edits Event ────────────────────────────────────────────────────

test.describe.serial('Flow 4: EO Edits Event', () => {
    let page: Page;
    let eventAUrl: string;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        await loginEO(page, EO_EMAIL, EO_PASS);
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('4.1 click Edit on rejected event → form pre-filled', async () => {
        await navigate(page, '/admin/events');
        // Click Detail on any event, then Edit Event button
        const detailBtns = page.locator('button:has-text("Detail")');
        await detailBtns.first().click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        eventAUrl = page.url();
        const editBtn = page.locator('button:has-text("Edit Event")');
        await expect(editBtn).toBeVisible();
        await editBtn.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        expect(page.url()).toContain('/edit');
        const titleValue = await page.locator('input[placeholder="Event title"]').inputValue();
        expect(titleValue.length).toBeGreaterThan(0);
    });

    test('4.2 change title and save → updated in events list', async () => {
        const newTitle = `QA Edited ${TS}`;
        await page.fill('input[placeholder="Event title"]', newTitle);
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        expect(page.url()).not.toContain('edit');
        expect(page.url()).toContain('/admin/events');
        await expect(page.locator(`text=${newTitle.slice(0, 15)}`).first()).toBeVisible();
    });

    test('4.3 PIC identity number shows "Leave blank to keep current" in edit mode', async () => {
        await navigate(page, '/admin/events');
        const detailBtns = page.locator('button:has-text("Detail")');
        await detailBtns.first().click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        const editBtn = page.locator('button:has-text("Edit Event")');
        if (await editBtn.isVisible()) {
            await editBtn.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1500);
        }
        await expect(page.locator('input[placeholder="Leave blank to keep current"]')).toBeVisible();
    });

    test('4.4 suspended/verified event detail has no Edit button', async () => {
        // Event A was verified then suspended in flow 3 — go back to its detail page
        await loginAdmin(page);
        await navigate(page, '/plest-admin/events');
        const rowA = page.locator(`tr:has-text("${EVENT_A}")`).first();
        const viewBtn = rowA.locator('button:has-text("View"), a:has-text("View")').first();
        await viewBtn.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        // Get the UUID from URL, then check EO detail view
        const adminUrl = page.url();
        const uuid = adminUrl.split('/').pop();

        await loginEO(page, EO_EMAIL, EO_PASS);
        await page.goto(`/admin/events/${uuid}`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await expect(page.locator('button:has-text("Edit Event")')).not.toBeVisible();
    });
});
