const { chromium } = require('playwright');

const BASE = 'http://localhost:8000';
const TS = Date.now();
const EO_EMAIL = `eo_test_${TS}@test.com`;
const EO_USER  = `eo${TS}`;
const EO_PASS  = 'password123';

const results = [];
function log(id, step, result, note = '') {
    const icon = result === 'PASS' ? '✅' : result === 'FAIL' ? '❌' : '⚠️';
    console.log(`${icon} [${id}] ${step} → ${result}${note ? ' | ' + note : ''}`);
    results.push({ id, step, result, note });
}

// Wait for React to mount
async function gotoAndWait(page, url) {
    await page.goto(url);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
}

const fmt = d => d.toISOString().slice(0, 10);
const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
const dayAfter  = new Date(); dayAfter.setDate(dayAfter.getDate() + 2);

async function fillRegForm(page, { name, username, email, password, phone, dob }) {
    if (name     !== undefined) await page.fill('input[placeholder="John Doe"]',       name);
    if (username !== undefined) await page.fill('input[placeholder="johndoe"]',         username);
    if (email    !== undefined) await page.fill('input[placeholder="john@example.com"]', email);
    if (password !== undefined) await page.fill('input[placeholder="Min 8 characters"]', password);
    if (phone    !== undefined) await page.fill('input[placeholder="08123456789"]',      phone);
    if (dob      !== undefined) await page.fill('input[type="date"]',                   dob);
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    const ctx  = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();

    // ── FLOW 1: EO Registration & Login ──────────────────────────────────

    // 1.1 Form visible
    await gotoAndWait(page, `${BASE}/admin/register`);
    const hasForm = await page.locator('input[placeholder="John Doe"]').isVisible();
    log('1.1', 'Go to /admin/register — form visible', hasForm ? 'PASS' : 'FAIL');

    // 1.2 Submit all required fields → redirected
    await fillRegForm(page, {
        name: 'Test EO User', username: EO_USER, email: EO_EMAIL,
        password: EO_PASS, phone: '08123456789', dob: '1995-01-15',
    });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const url12 = page.url();
    log('1.2', 'Submit valid registration → redirected', url12.includes('/events') ? 'PASS' : 'FAIL', `url: ${url12}`);

    // 1.3 Submit without name → error
    await gotoAndWait(page, `${BASE}/admin/register`);
    await fillRegForm(page, { name: '', username: `u${TS}`, email: `nn_${TS}@t.com`, password: EO_PASS, phone: '08111111111', dob: '1995-01-15' });
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    const nameErr = await page.locator('p.text-red-500').first().isVisible().catch(() => false);
    const genErr13 = await page.locator('.bg-red-50').isVisible().catch(() => false);
    log('1.3', 'Submit without name → validation error', (nameErr || genErr13 || page.url().includes('register')) ? 'PASS' : 'FAIL',
        nameErr ? 'field error' : genErr13 ? 'general error' : 'stayed on page');

    // 1.4 Invalid email → browser/server blocks
    await gotoAndWait(page, `${BASE}/admin/register`);
    await fillRegForm(page, { name: 'Test', username: `u2${TS}`, email: 'bad-email', password: EO_PASS, phone: '08111111111', dob: '1995-01-15' });
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    log('1.4', 'Submit invalid email → blocked', page.url().includes('register') ? 'PASS' : 'FAIL');

    // 1.5 Password < 8 chars → error
    await gotoAndWait(page, `${BASE}/admin/register`);
    await fillRegForm(page, { name: 'Test', username: `u3${TS}`, email: `pw_${TS}@t.com`, password: 'abc', phone: '08111111111', dob: '1995-01-15' });
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    const pwErr = await page.locator('p.text-red-500, .bg-red-50').first().isVisible().catch(() => false);
    log('1.5', 'Submit password < 8 chars → validation error', (pwErr || page.url().includes('register')) ? 'PASS' : 'FAIL');

    // 1.6 Duplicate email → error
    await gotoAndWait(page, `${BASE}/admin/register`);
    await fillRegForm(page, { name: 'Dup', username: `dup${TS}`, email: EO_EMAIL, password: EO_PASS, phone: '08111111111', dob: '1995-01-15' });
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2500);
    const dupErr = await page.locator('.bg-red-50, p.text-red-500').first().isVisible().catch(() => false);
    log('1.6', 'Duplicate email → error shown', (dupErr || page.url().includes('register')) ? 'PASS' : 'FAIL',
        dupErr ? 'error visible' : 'stayed on register');

    // 1.7 Login valid credentials → /admin/events
    await gotoAndWait(page, `${BASE}/admin/login`);
    await page.fill('input[placeholder="you@example.com"]', EO_EMAIL);
    await page.fill('input[placeholder="••••••••"]', EO_PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const url17 = page.url();
    log('1.7', 'Login valid credentials → /admin/events', url17.includes('/admin/events') ? 'PASS' : 'FAIL', `url: ${url17}`);

    // 1.8 Login wrong password → error
    await gotoAndWait(page, `${BASE}/admin/login`);
    await page.fill('input[placeholder="you@example.com"]', EO_EMAIL);
    await page.fill('input[placeholder="••••••••"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2500);
    const loginErr = await page.locator('.bg-red-50').isVisible().catch(() => false);
    log('1.8', 'Login wrong password → error shown', (loginErr || page.url().includes('login')) ? 'PASS' : 'FAIL');

    // ── FLOW 2: EO Creates Event ─────────────────────────────────────────

    // Re-login as EO
    await gotoAndWait(page, `${BASE}/admin/login`);
    await page.fill('input[placeholder="you@example.com"]', EO_EMAIL);
    await page.fill('input[placeholder="••••••••"]', EO_PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // 2.1 My Events list visible
    await gotoAndWait(page, `${BASE}/admin/events`);
    const onList = page.url().includes('/admin/events') && !page.url().includes('create');
    const listHead = await page.locator('h1').first().isVisible().catch(() => false);
    log('2.1', 'Navigate to /admin/events — list shown', (onList && listHead) ? 'PASS' : 'FAIL', `url: ${page.url()}`);

    // 2.2 Create form has all sections
    const createBtn = page.locator('a[href*="create"], button:has-text("Create")').first();
    if (await createBtn.isVisible()) await createBtn.click();
    else await page.goto(`${BASE}/admin/events/create`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const s1 = await page.locator('text=Basic Info').isVisible().catch(() => false);
    const s2 = await page.locator('text=Person in Charge').isVisible().catch(() => false);
    const s3 = await page.locator('text=Schedule').isVisible().catch(() => false);
    const s4 = await page.locator('h2:has-text("Ticket Types")').isVisible().catch(() => false);
    log('2.2', 'Create form loads with all sections', (s1 && s2 && s3 && s4) ? 'PASS' : 'FAIL',
        `BasicInfo:${s1} PIC:${s2} Schedule:${s3} Tickets:${s4}`);

    // 2.3 Submit empty form → stays on page (HTML5 required blocks submit)
    await gotoAndWait(page, `${BASE}/admin/events/create`);
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    const stayedOnCreate = page.url().includes('create');
    log('2.3', 'Submit empty form → blocked (HTML5 required, stays on page)', stayedOnCreate ? 'PASS' : 'FAIL', `url: ${page.url()}`);

    // 2.5 All required fields, no tickets → created
    await gotoAndWait(page, `${BASE}/admin/events/create`);
    await page.fill('input[placeholder="Event title"]', `QA Test Event ${TS}`);
    await page.fill('textarea[placeholder="Event description"]', 'QA description');
    await page.fill('input[placeholder="Full name"]', 'QA PIC Name');
    await page.fill('input[placeholder="ID number"]', '1234567890123456');
    const dates1 = await page.locator('input[type="date"]').all();
    if (dates1.length >= 2) { await dates1[0].fill(fmt(tomorrow)); await dates1[1].fill(fmt(dayAfter)); }
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const url25 = page.url();
    const redirected25 = url25.includes('/admin/events') && !url25.includes('create');
    log('2.5', 'Required fields only, no tickets → redirect to /admin/events',
        redirected25 ? 'PASS' : 'FAIL',
        redirected25 ? `url: ${url25}` : 'FAIL — API 422: is_online/show_status boolean sent as string via FormData (bug)');

    // 2.6 Create with ticket type
    await gotoAndWait(page, `${BASE}/admin/events/create`);
    await page.fill('input[placeholder="Event title"]', `QA Ticket Event ${TS}`);
    await page.fill('textarea[placeholder="Event description"]', 'With ticket');
    await page.fill('input[placeholder="Full name"]', 'QA PIC');
    await page.fill('input[placeholder="ID number"]', '1234567890123456');
    const dates2 = await page.locator('input[type="date"]').all();
    if (dates2.length >= 2) { await dates2[0].fill(fmt(tomorrow)); await dates2[1].fill(fmt(dayAfter)); }
    await page.locator('button:has-text("Add Ticket Type")').click();
    await page.waitForTimeout(500);
    await page.fill('input[placeholder="e.g. Regular, VIP"]', 'Regular');
    await page.fill('input[placeholder="0 = free"]', '50000');
    await page.fill('input[placeholder="Total seats"]', '100');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    const url26 = page.url();
    const redirected26 = url26.includes('/admin/events') && !url26.includes('create');
    log('2.6', 'Create with ticket type (Rp50.000, quota 100) → success',
        redirected26 ? 'PASS' : 'FAIL',
        redirected26 ? `url: ${url26}` : 'FAIL — same bug as 2.5: boolean fields sent as string');

    // 2.11 Province → city dropdown populates
    await gotoAndWait(page, `${BASE}/admin/events/create`);
    const allSelects = page.locator('select');
    const selectCount = await allSelects.count();
    let provinceIdx = -1;
    for (let i = 0; i < selectCount; i++) {
        const opts = await allSelects.nth(i).locator('option').all();
        if (opts.length < 2) continue;
        const txt = await opts[1].textContent().catch(() => '');
        if (/Aceh|Bali|Jawa|Sumatra|DKI|Kalimantan|Papua/i.test(txt)) { provinceIdx = i; break; }
    }
    if (provinceIdx >= 0) {
        const provinceOpts = await allSelects.nth(provinceIdx).locator('option').all();
        const val = await provinceOpts[1].getAttribute('value');
        await allSelects.nth(provinceIdx).selectOption(val);
        await page.waitForTimeout(2000);
        let cityOpts = 0;
        for (let i = 0; i < selectCount; i++) {
            const txt = await allSelects.nth(i).locator('option').nth(1).textContent().catch(() => '');
            if (/KOTA|KABUPATEN/i.test(txt)) { cityOpts = await allSelects.nth(i).locator('option').count(); break; }
        }
        log('2.11', 'Select province → city dropdown populates', cityOpts > 1 ? 'PASS' : 'FAIL', `city options: ${cityOpts}`);
    } else {
        log('2.11', 'Select province → city dropdown populates', 'FAIL', 'province select not found');
    }

    // 2.12 Online event checkbox hides venue fields
    await gotoAndWait(page, `${BASE}/admin/events/create`);
    const venueBefore = await page.locator('input[placeholder="e.g. Gelora Bung Karno"]').isVisible().catch(() => false);
    await page.locator('#is_online').check();
    await page.waitForTimeout(500);
    const venueAfter = await page.locator('input[placeholder="e.g. Gelora Bung Karno"]').isVisible().catch(() => false);
    log('2.12', 'Online event checkbox hides venue fields', (venueBefore && !venueAfter) ? 'PASS' : 'FAIL',
        `before:${venueBefore} after:${venueAfter}`);

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
