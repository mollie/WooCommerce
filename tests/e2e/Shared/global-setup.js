const { chromium } = require('@playwright/test');
const { loginAdmin } = require('../Shared/wpUtils');

module.exports = async config => {
    const { storageState } = config.projects[0].use;
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await loginAdmin(page);
    await page.context().storageState({ path: storageState });
    await browser.close();
};
