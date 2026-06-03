const { chromium } = require('playwright');

const BASE      = 'http://localhost:8000';
const TS        = Date.now();
const EO_EMAIL  = `eo56_${TS}@test.com`;
const EO_PASS   = 'password123';
const SA_EMAIL  = 'superadmin@plesticket.com';
const SA_PASS   = 'adminpass';
const BUY_EMAIL = `buyer56_${TS}@test.com`;
const BUY_PASS  = 'password123';

const results = [];
function log(id, step, result, note = '') {
    const icon = result === 'PASS' ? '✅' : result === 'FAIL' ? '❌' : result === 'BLOCKED' ? '⛔' : '⚠️';
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

// Short unique prefix to reliably find event across pages
const EVENT_PREFIX  = `QAM${TS}`;
const EVENT_TITLE   = `${EVENT_PREFIX} Music Festival`;
const PENDING_TITLE = `QAP${TS} Pending Event`;

(async () => {
    const browser = await chromium.launch({ headless: true });
    const ctx  = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await ctx.newPage();

    // ── SETUP ─────────────────────────────────────────────────────────────
    console.log('── Setup: register EO, create events, verify music event…');

    // Register EO
    await go(page, `${BASE}/admin/register`);
    await page.fill('input[placeholder="John Doe"]', 'QA EO 56');
    await page.fill('input[placeholder="johndoe"]', `eo56${TS}`);
    await page.fill('input[placeholder="john@example.com"]', EO_EMAIL);
    await page.fill('input[placeholder="Min 8 characters"]', EO_PASS);
    await page.fill('input[placeholder="08123456789"]', '08111111111');
    await page.fill('input[type="date"]', '1995-01-15');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await loginEO(page);

    // Event A — music with ticket (will be verified)
    await go(page, `${BASE}/admin/events/create`);
    await page.fill('input[placeholder="Event title"]', EVENT_TITLE);
    await page.fill('textarea[placeholder="Event description"]', 'A QA test music event');
    await page.locator('select').first().selectOption({ label: 'Music' });
    await page.fill('input[placeholder="Full name"]', 'QA PIC');
    await page.fill('input[placeholder="ID number"]', '1234567890123456');
    const d1 = await page.locator('input[type="date"]').all();
    if (d1.length >= 2) { await d1[0].fill(fmt(tomorrow)); await d1[1].fill(fmt(dayAfter)); }
    await page.locator('button:has-text("Add Ticket Type")').click();
    await page.waitForTimeout(500);
    await page.fill('input[placeholder="e.g. Regular, VIP"]', 'Regular');
    await page.fill('input[placeholder="0 = free"]', '75000');
    await page.fill('input[placeholder="Total seats"]', '200');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Event B — pending only (visibility test)
    await go(page, `${BASE}/admin/events/create`);
    await page.fill('input[placeholder="Event title"]', PENDING_TITLE);
    await page.fill('textarea[placeholder="Event description"]', 'Should not be public');
    await page.fill('input[placeholder="Full name"]', 'QA PIC');
    await page.fill('input[placeholder="ID number"]', '1234567890123456');
    const d2 = await page.locator('input[type="date"]').all();
    if (d2.length >= 2) { await d2[0].fill(fmt(tomorrow)); await d2[1].fill(fmt(dayAfter)); }
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Admin verifies Event A
    await loginAdmin(page);
    await go(page, `${BASE}/plest-admin/events`);
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"]').first();
    if (await searchInput.isVisible()) {
        await searchInput.fill(EVENT_PREFIX);
        await page.waitForTimeout(1500);
    }
    const eventRow = page.locator(`tr:has-text("${EVENT_PREFIX}")`).first();
    await eventRow.locator('button:has-text("View"), a:has-text("View")').click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    await page.locator('button:has-text("Verify")').click();
    await page.waitForTimeout(2500);
    const verified = await page.locator('text=verified').first().isVisible().catch(() => false);
    console.log(`Setup: event verified = ${verified}`);
    console.log('── Setup complete. Starting Flow 5…\n');

    // ── FLOW 5: Audience Registration & Browsing ──────────────────────────

    // 5.1 Go to /register — buyer form shown
    await go(page, `${BASE}/register`);
    const buyerForm = await page.locator('input[placeholder="Your full name"]').isVisible().catch(() => false);
    log('5.1', 'Go to /register — buyer form shown', buyerForm ? 'PASS' : 'FAIL');

    // 5.2 Register buyer → /home
    await page.fill('input[placeholder="Your full name"]', 'QA Buyer');
    await page.fill('input[placeholder="you@example.com"]', BUY_EMAIL);
    await page.fill('input[placeholder="+62 812 3456 7890"]', '+62812345678');
    await page.fill('input[placeholder="Min. 8 characters"]', BUY_PASS);
    await page.fill('input[placeholder="Repeat password"]', BUY_PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    const url52 = page.url();
    log('5.2', 'Register buyer → /home', url52.includes('/home') ? 'PASS' : 'FAIL', `url: ${url52}`);

    // 5.3 Home page sections + verified event visible
    const recommendedSection = await page.locator('text=Recommended For You').isVisible().catch(() => false);
    const nearestSection     = await page.locator('text=Nearest Events').isVisible().catch(() => false);
    // Event cards render title text; wait a bit more for API
    await page.waitForTimeout(2000);
    const eventOnHome = await page.locator(`text=${EVENT_PREFIX}`).first().isVisible().catch(() => false);
    log('5.3', 'Home page shows Recommended/Nearest sections + verified event',
        (recommendedSection && nearestSection && eventOnHome) ? 'PASS' : 'FAIL',
        `recommended:${recommendedSection} nearest:${nearestSection} eventOnHome:${eventOnHome}`);

    // 5.4 "See all →" → /events with search + categories
    const seeAllBtn = page.locator('button:has-text("See all")').first();
    if (await seeAllBtn.isVisible()) await seeAllBtn.click();
    else await page.goto(`${BASE}/events`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const onEvents   = page.url().includes('/events') && !page.url().includes('/events/');
    const hasSearch  = await page.locator('input[placeholder="Search events…"]').isVisible().catch(() => false);
    const hasChips   = await page.locator('button:has-text("All")').isVisible().catch(() => false);
    log('5.4', 'See all → /events with search + category chips', (onEvents && hasSearch && hasChips) ? 'PASS' : 'FAIL',
        `url:${page.url()} search:${hasSearch} chips:${hasChips}`);

    // 5.5 Search by title prefix → result found
    await go(page, `${BASE}/events`);
    await page.fill('input[placeholder="Search events…"]', EVENT_PREFIX);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(2500);
    const searchResult = await page.locator(`text=${EVENT_PREFIX}`).first().isVisible().catch(() => false);
    log('5.5', 'Search by event title → result found', searchResult ? 'PASS' : 'FAIL');

    // 5.6 Filter by Music category chip → event visible
    await go(page, `${BASE}/events`);
    await page.locator('button', { hasText: /^🎵 Music$/ }).click();
    await page.waitForTimeout(2500);
    const musicChipActive = await page.locator('button.bg-violet-600').filter({ hasText: 'Music' }).isVisible().catch(() => false);
    const musicResult     = await page.locator(`text=${EVENT_PREFIX}`).first().isVisible().catch(() => false);
    log('5.6', 'Filter Music category → chip active + event visible',
        (musicChipActive && musicResult) ? 'PASS' : 'FAIL',
        `chipActive:${musicChipActive} result:${musicResult}`);

    // 5.7 Click event card → detail page with title, date, location
    const eventCard = page.locator(`text=${EVENT_PREFIX}`).first();
    await eventCard.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const onDetail       = page.url().includes('/events/') && page.url() !== `${BASE}/events/`;
    const titleVisible   = await page.locator(`text=${EVENT_PREFIX}`).first().isVisible().catch(() => false);
    const dateRow        = await page.locator('text=📅').isVisible().catch(() => false);
    const locationRow    = await page.locator('text=📍').isVisible().catch(() => false);
    log('5.7', 'Click event card → detail page with title, date, location',
        (onDetail && titleVisible && dateRow && locationRow) ? 'PASS' : 'FAIL',
        `url: ${page.url()}`);

    // 5.8 Ticket card shows name, price, quota
    const ticketName  = await page.getByText('Regular', { exact: false }).first().isVisible().catch(() => false);
    const ticketPrice = await page.getByText(/Rp75/, { exact: false }).first().isVisible().catch(() => false);
    const ticketQuota = await page.getByText(/200 left/, { exact: false }).first().isVisible().catch(() => false);
    log('5.8', 'Ticket card shows name (Regular), price (Rp75.000), quota (200 left)',
        (ticketName && ticketPrice && ticketQuota) ? 'PASS' : 'FAIL',
        `name:${ticketName} price:${ticketPrice} quota:${ticketQuota}`);

    // 5.9 Click Select on ticket → Selected badge + bottom bar updates
    const selectBtn = page.locator('button:has-text("Select")').first();
    if (await selectBtn.isVisible()) await selectBtn.click();
    await page.waitForTimeout(500);
    const selectedBadge  = await page.locator('text=✓ Selected').isVisible().catch(() => false);
    const bottomBarPrice = await page.locator('text=Rp75').last().isVisible().catch(() => false);
    log('5.9', 'Click ticket → Selected badge + bottom bar shows price',
        (selectedBadge && bottomBarPrice) ? 'PASS' : 'FAIL',
        `selected:${selectedBadge} bottomPrice:${bottomBarPrice}`);

    // 5.10 Buy Now — BLOCKED (button exists, no handler)
    const buyNowBtn = page.locator('button:has-text("Buy Now")');
    const buyNowExists = await buyNowBtn.isVisible().catch(() => false);
    log('5.10', 'Buy Now button present but purchase flow not yet built',
        buyNowExists ? 'BLOCKED' : 'FAIL',
        buyNowExists ? 'button renders, no checkout action wired' : 'Buy Now button not found');

    // ── FLOW 6: Edge Cases ────────────────────────────────────────────────
    console.log('\n── Flow 6…');

    // 6.1 Unauthenticated → /admin/events redirects to login
    await page.evaluate(() => localStorage.clear());
    await go(page, `${BASE}/admin/events`);
    const url61 = page.url();
    log('6.1', 'Unauthenticated /admin/events → redirect to /admin/login',
        url61.includes('/admin/login') ? 'PASS' : 'FAIL', `url: ${url61}`);

    // 6.2 EO accessing /plest-admin/events → redirected to /admin/events
    await loginEO(page);
    await go(page, `${BASE}/plest-admin/events`);
    const url62 = page.url();
    log('6.2', 'EO accessing /plest-admin/events → redirected away',
        (url62.includes('/admin/events') && !url62.includes('plest-admin')) ? 'PASS' : 'FAIL', `url: ${url62}`);

    // 6.3 Invalid slug → redirect to /events
    await go(page, `${BASE}/events/nonexistent-slug-xyz-${TS}`);
    const url63 = page.url();
    log('6.3', 'Invalid event slug → redirect to /events',
        (url63.endsWith('/events') || url63.includes('/events?')) ? 'PASS' : 'FAIL', `url: ${url63}`);

    // 6.4 Pending event NOT in public /events listing
    await go(page, `${BASE}/events`);
    await page.waitForTimeout(1000);
    const pendingVisible = await page.locator(`text=${PENDING_TITLE.slice(0, 12)}`).isVisible().catch(() => false);
    log('6.4', 'Pending event absent from public /events listing',
        !pendingVisible ? 'PASS' : 'FAIL',
        pendingVisible ? 'BUG: pending event shown publicly' : 'correctly hidden');

    // 6.5 Auth persists after hard reload
    await loginEO(page);
    const urlBefore = page.url();
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const urlAfter = page.url();
    log('6.5', 'Auth persists after page reload',
        !urlAfter.includes('login') ? 'PASS' : 'FAIL',
        `before:${urlBefore} after:${urlAfter}`);

    await browser.close();

    // ── Summary ──────────────────────────────────────────────────────────
    console.log('\n─────────────────────────────');
    const passed  = results.filter(r => r.result === 'PASS').length;
    const failed  = results.filter(r => r.result === 'FAIL').length;
    const blocked = results.filter(r => r.result === 'BLOCKED').length;
    console.log(`Total: ${results.length}  |  PASS: ${passed}  |  FAIL: ${failed}  |  BLOCKED: ${blocked}`);
    if (failed > 0) {
        console.log('\nFailed:');
        results.filter(r => r.result === 'FAIL').forEach(r =>
            console.log(`  ❌ [${r.id}] ${r.step}${r.note ? ' | ' + r.note : ''}`)
        );
    }
    process.exit(failed > 0 ? 1 : 0);
})();
