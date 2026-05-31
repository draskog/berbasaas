const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  try {
    // Navigate to the app
    await page.goto('http://berbasaas.test/dashboard', { waitUntil: 'networkidle' });
    console.log('✅ Page loaded');
    
    // Wait for sidebar to be visible
    await page.waitForSelector('[data-flux-sidebar]', { timeout: 5000 });
    console.log('✅ Sidebar found');
    
    // Take screenshot of expanded sidebar
    await page.screenshot({ path: './sidebar-expanded.png' });
    console.log('✅ Expanded sidebar screenshot saved');
    
    // Find and click the collapse button - try multiple selectors
    let collapseClicked = false;
    
    // Try to find collapse button
    const buttons = await page.locator('button').all();
    for (const btn of buttons) {
      const ariaLabel = await btn.getAttribute('aria-label');
      if (ariaLabel && ariaLabel.includes('collapse')) {
        await btn.click();
        collapseClicked = true;
        console.log('✅ Collapse button clicked');
        break;
      }
    }
    
    if (!collapseClicked) {
      // Try clicking via keyboard shortcut or other means
      const sidebarElement = await page.locator('[data-flux-sidebar]').first();
      if (sidebarElement) {
        await page.keyboard.press('Escape');
      }
    }
    
    await page.waitForTimeout(500); // Wait for animation
    
    // Take screenshot of collapsed sidebar
    await page.screenshot({ path: './sidebar-collapsed.png' });
    console.log('✅ Collapsed sidebar screenshot saved');
    
    // Get the sidebar HTML to inspect structure
    const sidebarHtml = await page.locator('[data-flux-sidebar]').first().innerHTML();
    console.log('\n=== Sidebar HTML structure (first 500 chars) ===');
    console.log(sidebarHtml.substring(0, 500));
    
  } catch (error) {
    console.error('Error:', error.message);
  }
  
  await browser.close();
})();
