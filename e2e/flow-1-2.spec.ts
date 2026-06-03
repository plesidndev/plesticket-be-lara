import { test, expect, type Page } from '@playwright/test';
import { navigate, loginEO } from './helpers';

const TS       = Date.now();
const EO_EMAIL = `eo12_${TS}@test.com`;
const EO_USER  = `eo12_${TS}`;
const EO_PASS  = 'password123';

// ── Flow 1: EO Registration & Login ──────────────────────────────────────────

test.describe.serial('Flow 1: EO Registration & Login', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('1.1 form visible at /admin/register', async () => {
        await navigate(page, '/admin/register');
        await expect(page.locator('input[placeholder="John Doe"]')).toBeVisible();
        await expect(page.locator('input[placeholder="johndoe"]')).toBeVisible();
        await expect(page.locator('input[placeholder="john@example.com"]')).toBeVisible();
    });

    test('1.2 submit valid registration → redirect to /events', async () => {
        await page.fill('input[placeholder="John Doe"]', 'Test EO User');
        await page.fill('input[placeholder="johndoe"]', EO_USER);
        await page.fill('input[placeholder="john@example.com"]', EO_EMAIL);
        await page.fill('input[placeholder="Min 8 characters"]', EO_PASS);
        await page.fill('input[placeholder="08123456789"]', '08111111111');
        await page.fill('input[type="date"]', '1995-01-15');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);
        expect(page.url()).toContain('/events');
    });

    test('1.3 submit without name → stays on register page', async () => {
        await navigate(page, '/admin/register');
        await page.fill('input[placeholder="john@example.com"]', `noname_${TS}@test.com`);
        await page.fill('input[placeholder="Min 8 characters"]', EO_PASS);
        await page.fill('input[placeholder="08123456789"]', '08111111111');
        await page.fill('input[type="date"]', '1995-01-15');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        expect(page.url()).toContain('register');
    });

    test('1.4 submit invalid email → blocked by validation', async () => {
        await navigate(page, '/admin/register');
        await page.fill('input[placeholder="John Doe"]', 'Test');
        await page.fill('input[placeholder="johndoe"]', `u4_${TS}`);
        await page.fill('input[placeholder="john@example.com"]', 'not-an-email');
        await page.fill('input[placeholder="Min 8 characters"]', EO_PASS);
        await page.fill('input[placeholder="08123456789"]', '08111111111');
        await page.fill('input[type="date"]', '1995-01-15');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        expect(page.url()).toContain('register');
    });

    test('1.5 submit password < 8 chars → validation error', async () => {
        await navigate(page, '/admin/register');
        await page.fill('input[placeholder="John Doe"]', 'Test');
        await page.fill('input[placeholder="johndoe"]', `u5_${TS}`);
        await page.fill('input[placeholder="john@example.com"]', `pw5_${TS}@test.com`);
        await page.fill('input[placeholder="Min 8 characters"]', 'abc');
        await page.fill('input[placeholder="08123456789"]', '08111111111');
        await page.fill('input[type="date"]', '1995-01-15');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);
        const hasError = await page.locator('p.text-red-500, .bg-red-50').first().isVisible().catch(() => false);
        expect(hasError || page.url().includes('register')).toBeTruthy();
    });

    test('1.6 submit duplicate email → error shown', async () => {
        await navigate(page, '/admin/register');
        await page.fill('input[placeholder="John Doe"]', 'Dup User');
        await page.fill('input[placeholder="johndoe"]', `dup_${TS}`);
        await page.fill('input[placeholder="john@example.com"]', EO_EMAIL);
        await page.fill('input[placeholder="Min 8 characters"]', EO_PASS);
        await page.fill('input[placeholder="08123456789"]', '08111111111');
        await page.fill('input[type="date"]', '1995-01-15');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);
        const hasError = await page.locator('.bg-red-50, p.text-red-500').first().isVisible().catch(() => false);
        expect(hasError || page.url().includes('register')).toBeTruthy();
    });

    test('1.7 login with valid credentials → /admin/events', async () => {
        await loginEO(page, EO_EMAIL, EO_PASS);
        expect(page.url()).toContain('/admin/events');
    });

    test('1.8 login with wrong password → error shown', async () => {
        await navigate(page, '/admin/login');
        await page.fill('input[placeholder="you@example.com"]', EO_EMAIL);
        await page.fill('input[placeholder="••••••••"]', 'wrongpassword');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2500);
        // Error div or stayed on login page both indicate correct behavior
        const hasError = await page.locator('.bg-red-50, [class*="red"]').first().isVisible().catch(() => false);
        const onLogin  = page.url().includes('login');
        expect(hasError || onLogin).toBeTruthy();
    });
});

// ── Flow 2: EO Creates Event ──────────────────────────────────────────────────

test.describe.serial('Flow 2: EO Creates Event', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        await loginEO(page, EO_EMAIL, EO_PASS);
        // Wait until we're on /admin/events (login redirect)
        await page.waitForURL('**/admin/events', { timeout: 10_000 }).catch(() => {});
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('2.1 navigate to /admin/events — list shown', async () => {
        await navigate(page, '/admin/events');
        expect(page.url()).toContain('/admin/events');
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.2 create form loads with all sections', async () => {
        const createBtn = page.locator('a[href*="create"], button:has-text("Create")').first();
        if (await createBtn.isVisible()) await createBtn.click();
        else await page.goto('/admin/events/create');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await expect(page.locator('text=Basic Info')).toBeVisible();
        await expect(page.locator('text=Person in Charge')).toBeVisible();
        await expect(page.locator('text=Schedule')).toBeVisible();
        await expect(page.locator('h2:has-text("Ticket Types")')).toBeVisible();
    });

    test('2.3 submit empty form → stays on page (HTML5 required)', async () => {
        await navigate(page, '/admin/events/create');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        expect(page.url()).toContain('create');
    });

    test('2.5 fill required fields, no tickets → event created', async () => {
        await navigate(page, '/admin/events/create');
        await page.fill('input[placeholder="Event title"]', `QA Event 2.5 ${TS}`);
        await page.fill('textarea[placeholder="Event description"]', 'QA desc');
        await page.fill('input[placeholder="Full name"]', 'QA PIC');
        await page.fill('input[placeholder="ID number"]', '1234567890123456');
        const dates = await page.locator('input[type="date"]').all();
        if (dates.length >= 2) {
            const d1 = new Date(); d1.setDate(d1.getDate() + 1);
            const d2 = new Date(); d2.setDate(d2.getDate() + 2);
            await dates[0].fill(d1.toISOString().slice(0, 10));
            await dates[1].fill(d2.toISOString().slice(0, 10));
        }
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);
        expect(page.url()).toContain('/admin/events');
        expect(page.url()).not.toContain('create');
    });

    test('2.6 fill required fields + ticket type → event created', async () => {
        await navigate(page, '/admin/events/create');
        await page.fill('input[placeholder="Event title"]', `QA Event 2.6 ${TS}`);
        await page.fill('textarea[placeholder="Event description"]', 'QA with ticket');
        await page.fill('input[placeholder="Full name"]', 'QA PIC');
        await page.fill('input[placeholder="ID number"]', '1234567890123456');
        const dates = await page.locator('input[type="date"]').all();
        if (dates.length >= 2) {
            const d1 = new Date(); d1.setDate(d1.getDate() + 1);
            const d2 = new Date(); d2.setDate(d2.getDate() + 2);
            await dates[0].fill(d1.toISOString().slice(0, 10));
            await dates[1].fill(d2.toISOString().slice(0, 10));
        }
        await page.locator('button:has-text("Add Ticket Type")').click();
        await page.waitForTimeout(400);
        await page.fill('input[placeholder="e.g. Regular, VIP"]', 'Regular');
        await page.fill('input[placeholder="0 = free"]', '50000');
        await page.fill('input[placeholder="Total seats"]', '100');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);
        expect(page.url()).not.toContain('create');
        expect(page.url()).toContain('/admin/events');
    });

    test('2.11 select province → city dropdown populates', async () => {
        await navigate(page, '/admin/events/create');
        const selects = page.locator('select');
        const count = await selects.count();
        let provinceIdx = -1;
        for (let i = 0; i < count; i++) {
            const opts = await selects.nth(i).locator('option').all();
            if (opts.length < 2) continue;
            const txt = await opts[1].textContent().catch(() => '');
            if (/Aceh|Bali|Jawa|DKI|Kalimantan/i.test(txt ?? '')) { provinceIdx = i; break; }
        }
        expect(provinceIdx).toBeGreaterThanOrEqual(0);
        const opts = await selects.nth(provinceIdx).locator('option').all();
        await selects.nth(provinceIdx).selectOption(await opts[1].getAttribute('value') ?? '');
        await page.waitForTimeout(1500);
        let cityOpts = 0;
        for (let i = 0; i < count; i++) {
            const txt = await selects.nth(i).locator('option').nth(1).textContent().catch(() => '');
            if (/KOTA|KABUPATEN/i.test(txt ?? '')) { cityOpts = await selects.nth(i).locator('option').count(); break; }
        }
        expect(cityOpts).toBeGreaterThan(1);
    });

    test('2.12 online event checkbox hides venue fields', async () => {
        await navigate(page, '/admin/events/create');
        await expect(page.locator('input[placeholder="e.g. Gelora Bung Karno"]')).toBeVisible();
        await page.locator('#is_online').check();
        await page.waitForTimeout(400);
        await expect(page.locator('input[placeholder="e.g. Gelora Bung Karno"]')).not.toBeVisible();
    });
});
