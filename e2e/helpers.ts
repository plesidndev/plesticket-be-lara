import { type Page } from '@playwright/test';

export const SA_EMAIL = 'superadmin@plesticket.com';
export const SA_PASS  = 'adminpass';

export function uniqueEmail(prefix: string) {
    return `${prefix}_${Date.now()}@test.com`;
}

export function futureDate(offsetDays: number) {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    return d.toISOString().slice(0, 10);
}

export async function navigate(page: Page, path: string) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
}

export async function loginEO(page: Page, email: string, password: string) {
    await navigate(page, '/admin/login');
    await page.fill('input[placeholder="you@example.com"]', email);
    await page.fill('input[placeholder="••••••••"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/events', { timeout: 10_000 }).catch(() => {});
    await page.waitForTimeout(1000);
}

export async function loginAdmin(page: Page) {
    await navigate(page, '/plest-admin/login');
    await page.fill('input[placeholder="admin@plesticket.com"]', SA_EMAIL);
    await page.fill('input[placeholder="••••••••"]', SA_PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
}

export async function registerEO(page: Page, email: string, username: string, password: string) {
    await navigate(page, '/admin/register');
    await page.fill('input[placeholder="John Doe"]', 'QA EO User');
    await page.fill('input[placeholder="johndoe"]', username);
    await page.fill('input[placeholder="john@example.com"]', email);
    await page.fill('input[placeholder="Min 8 characters"]', password);
    await page.fill('input[placeholder="08123456789"]', '08111111111');
    await page.fill('input[type="date"]', '1995-01-15');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
}

export async function createEvent(page: Page, title: string, opts: {
    category?: string;
    withTicket?: boolean;
    ticketPrice?: string;
    ticketQuota?: string;
} = {}) {
    await navigate(page, '/admin/events/create');
    await page.fill('input[placeholder="Event title"]', title);
    await page.fill('textarea[placeholder="Event description"]', 'QA test event');
    if (opts.category) {
        await page.locator('select').first().selectOption({ label: opts.category });
    }
    await page.fill('input[placeholder="Full name"]', 'QA PIC');
    await page.fill('input[placeholder="ID number"]', '1234567890123456');
    const dates = await page.locator('input[type="date"]').all();
    if (dates.length >= 2) {
        await dates[0].fill(futureDate(1));
        await dates[1].fill(futureDate(2));
    }
    if (opts.withTicket) {
        await page.locator('button:has-text("Add Ticket Type")').click();
        await page.waitForTimeout(400);
        await page.fill('input[placeholder="e.g. Regular, VIP"]', 'Regular');
        await page.fill('input[placeholder="0 = free"]', opts.ticketPrice ?? '50000');
        await page.fill('input[placeholder="Total seats"]', opts.ticketQuota ?? '100');
    }
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
}

export async function adminVerifyEvent(page: Page, eventTitlePrefix: string) {
    await navigate(page, '/plest-admin/events');
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"]').first();
    if (await searchInput.isVisible()) {
        await searchInput.fill(eventTitlePrefix);
        await page.waitForTimeout(1200);
    }
    const row = page.locator(`tr:has-text("${eventTitlePrefix}")`).first();
    await row.locator('button:has-text("View"), a:has-text("View")').click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
    await page.locator('button:has-text("Verify")').click();
    await page.waitForTimeout(2000);
    return page.url(); // returns event detail URL
}
