const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await page.goto('http://localhost:8000/events/qam1780468443518-music-festival');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    // Find ticket section text
    const ticketTexts = await page.locator('button[class*="rounded-2xl"]').allTextContents();
    console.log('Ticket card texts:', ticketTexts);
    const allText = await page.locator('body').textContent();
    const priceIdx = allText.indexOf('Rp');
    console.log('Price text sample:', allText.slice(priceIdx, priceIdx + 20));
    await browser.close();
})();
