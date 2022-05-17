const { chromium } = require('@playwright/test');
const { loginAdmin } = require('./wpUtils');

module.exports = async config => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await loginAdmin(page);
    await page.context().storageState({ path: 'storageState.json' });
    await browser.close();
};
