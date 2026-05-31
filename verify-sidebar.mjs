import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 720 } });
  
  try {
    // Navigate to the app
    await page.goto('http://berbasaas.test/dashboard', { waitUntil: 'networkidle' });
    console.log('✅ Page loaded');
    
    // Wait for sidebar to be visible
    await page.waitForSelector('[data-flux-sidebar]', { timeout: 5000 });
    console.log('✅ Sidebar found');
    
    // Take screenshot of expanded sidebar
    const sidebarBox = await page.locator('[data-flux-sidebar]').boundingBox();
    if (sidebarBox) {
      await page.screenshot({ path: './sidebar-expanded.png', clip: sidebarBox });
    } else {
      await page.screenshot({ path: './sidebar-expanded.png' });
    }
    console.log('✅ Expanded sidebar screenshot saved');
    
    // Find the collapse button - looking for a button near the top of sidebar
    const collapseButtons = await page.locator('button').all();
    let collapseClicked = false;
    
    for (const btn of collapseButtons) {
      const html = await btn.innerHTML();
      // Look for a collapse/toggle button
      if (html.includes('chevron') || html.includes('arrow') || (await btn.getAttribute('aria-label') || '').includes('collapse')) {
        await btn.click();
        collapseClicked = true;
        console.log('✅ Collapse button found and clicked');
        break;
      }
    }
    
    if (!collapseClicked) {
      console.log('⚠️ Could not find collapse button, trying keyboard...');
    }
    
    await page.waitForTimeout(500); // Wait for animation
    
    // Take screenshot of collapsed sidebar
    const collapsedBox = await page.locator('[data-flux-sidebar]').boundingBox();
    if (collapsedBox) {
      await page.screenshot({ path: './sidebar-collapsed.png', clip: collapsedBox });
    } else {
      await page.screenshot({ path: './sidebar-collapsed.png' });
    }
    console.log('✅ Collapsed sidebar screenshot saved');
    
    // Check if labels are visible in collapsed state
    const dashboardText = await page.locator('text=Dashboard').isVisible();
    const uploadText = await page.locator('text=Upload').isVisible();
    const harvestersText = await page.locator('text=Harvesters').isVisible();
    
    console.log('\n=== Label visibility in collapsed state ===');
    console.log(`Dashboard: ${dashboardText ? '✅ visible' : '❌ hidden'}`);
    console.log(`Upload: ${uploadText ? '✅ visible' : '❌ hidden'}`);
    console.log(`Harvesters: ${harvestersText ? '✅ visible' : '❌ hidden'}`);
    
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
  
  await browser.close();
})();
