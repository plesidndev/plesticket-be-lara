import { test, expect, type Page } from '@playwright/test';
import { navigate, loginEO, loginAdmin, registerEO, createEvent, adminVerifyEvent } from './helpers';

const TS          = Date.now();
const EO_EMAIL    = `eo56_${TS}@test.com`;
const EO_USER     = `eo56_${TS}`;
const EO_PASS     = 'password123';
const BUY_EMAIL   = `buyer56_${TS}@test.com`;
const BUY_PASS    = 'password123';
const EVENT_PREFIX = `QAM${TS}`;
const EVENT_TITLE  = `${EVENT_PREFIX} Music Festival`;
const PENDING_TITLE = `QAP${TS} Pending`;

// ── Flow 5: Audience Registration & Browsing ──────────────────────────────────

test.describe.serial('Flow 5: Audience Registration & Browsing', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        page.setViewportSize({ width: 390, height: 844 });

        // Setup: EO + verified event with Music category + ticket
        await registerEO(page, EO_EMAIL, EO_USER, EO_PASS);
        await loginEO(page, EO_EMAIL, EO_PASS);
        await createEvent(page, EVENT_TITLE, { category: 'Music', withTicket: true, ticketPrice: '75000', ticketQuota: '200' });
        await createEvent(page, PENDING_TITLE); // stays pending for 6.4

        // Admin verifies the music event
        await loginAdmin(page);
        await adminVerifyEvent(page, EVENT_PREFIX);
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('5.1 /register shows buyer form', async () => {
        await navigate(page, '/register');
        await expect(page.locator('input[placeholder="Your full name"]')).toBeVisible();
        await expect(page.locator('input[placeholder="you@example.com"]')).toBeVisible();
    });

    test('5.2 register buyer → /home', async () => {
        await page.fill('input[placeholder="Your full name"]', 'QA Buyer');
        await page.fill('input[placeholder="you@example.com"]', BUY_EMAIL);
        await page.fill('input[placeholder="+62 812 3456 7890"]', '+62812345678');
        await page.fill('input[placeholder="Min. 8 characters"]', BUY_PASS);
        await page.fill('input[placeholder="Repeat password"]', BUY_PASS);
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        expect(page.url()).toContain('/home');
    });

    test('5.3 home shows Recommended + Nearest sections + verified event', async () => {
        await page.waitForTimeout(1500);
        await expect(page.locator('text=Recommended For You')).toBeVisible();
        await expect(page.locator('text=Nearest Events')).toBeVisible();
        await expect(page.locator(`text=${EVENT_PREFIX}`).first()).toBeVisible();
    });

    test('5.4 "See all →" → /events with search + category chips', async () => {
        const seeAllBtn = page.locator('button:has-text("See all")').first();
        if (await seeAllBtn.isVisible()) await seeAllBtn.click();
        else await page.goto('/events');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        expect(page.url()).toContain('/events');
        await expect(page.locator('input[placeholder="Search events…"]')).toBeVisible();
        await expect(page.locator('button:has-text("All")')).toBeVisible();
    });

    test('5.5 search by event title prefix → result found', async () => {
        await navigate(page, '/events');
        await page.fill('input[placeholder="Search events…"]', EVENT_PREFIX);
        await page.keyboard.press('Enter');
        await page.waitForTimeout(2000);
        await expect(page.locator(`text=${EVENT_PREFIX}`).first()).toBeVisible();
    });

    test('5.6 filter by Music category → chip active + event visible', async () => {
        await navigate(page, '/events');
        await page.locator('button', { hasText: /^🎵 Music$/ }).click();
        await page.waitForTimeout(2000);
        await expect(page.locator('button.bg-violet-600').filter({ hasText: 'Music' })).toBeVisible();
        await expect(page.locator(`text=${EVENT_PREFIX}`).first()).toBeVisible();
    });

    test('5.7 click event card → detail page with title, date, location', async () => {
        await page.locator(`text=${EVENT_PREFIX}`).first().click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        expect(page.url()).toMatch(/\/events\/.+/);
        await expect(page.locator(`text=${EVENT_PREFIX}`).first()).toBeVisible();
        await expect(page.locator('text=📅')).toBeVisible();
        await expect(page.locator('text=📍')).toBeVisible();
    });

    test('5.8 ticket card shows name, price, quota', async () => {
        await expect(page.getByText('Regular', { exact: false }).first()).toBeVisible();
        await expect(page.getByText(/Rp75/, { exact: false }).first()).toBeVisible();
        await expect(page.getByText(/200 left/, { exact: false }).first()).toBeVisible();
    });

    test('5.9 click ticket → Selected badge + bottom bar updates', async () => {
        await page.locator('button:has-text("Select")').first().click();
        await page.waitForTimeout(400);
        await expect(page.locator('text=✓ Selected')).toBeVisible();
        await expect(page.getByText(/Rp75/, { exact: false }).last()).toBeVisible();
    });

    test('5.10 Buy Now button exists — purchase flow BLOCKED (not yet built)', async () => {
        // This test documents the known gap: button renders but has no handler.
        // Mark as todo until checkout is implemented.
        await expect(page.locator('button:has-text("Buy Now")')).toBeVisible();
        test.info().annotations.push({ type: 'BLOCKED', description: 'Ticket purchase flow not yet implemented' });
    });
});

// ── Flow 6: Edge Cases ────────────────────────────────────────────────────────

test.describe.serial('Flow 6: Edge Cases', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('6.1 unauthenticated access to /admin/events → redirect to /admin/login', async () => {
        // Navigate to app first so localStorage is accessible, then clear auth
        await navigate(page, '/home');
        await page.evaluate(() => localStorage.clear());
        await navigate(page, '/admin/events');
        expect(page.url()).toContain('/admin/login');
    });

    test('6.2 EO accessing /plest-admin/events → redirected to /admin/events', async () => {
        await loginEO(page, EO_EMAIL, EO_PASS);
        await navigate(page, '/plest-admin/events');
        expect(page.url()).toContain('/admin/events');
        expect(page.url()).not.toContain('plest-admin');
    });

    test('6.3 invalid event slug → redirect to /events', async () => {
        await navigate(page, `/events/nonexistent-slug-xyz-${TS}`);
        expect(page.url()).toMatch(/\/events$|\/events\?/);
    });

    test('6.4 pending event not visible in public /events listing', async () => {
        await navigate(page, '/events');
        await page.waitForTimeout(800);
        const pendingVisible = await page.locator(`text=${PENDING_TITLE.slice(0, 12)}`).isVisible().catch(() => false);
        expect(pendingVisible).toBe(false);
    });

    test('6.5 auth persists after page reload', async () => {
        await loginEO(page, EO_EMAIL, EO_PASS);
        await page.reload();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);
        expect(page.url()).not.toContain('login');
    });
});
