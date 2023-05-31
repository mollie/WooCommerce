import { chromium } from '@playwright/test';
const { loginAdmin } = require('./Shared/wpUtils');
async function globalSetup(config) {
    const { baseURL, storageState } = config.projects[0].use;
    const browser = await chromium.launch();
    const page = await browser.newPage({ baseURL: baseURL, extraHTTPHeaders: {'ngrok-skip-browser-warning': '123'}});
    await loginAdmin(page);
    await page.context().storageState({ path: storageState });
    await browser.close();
}

export default globalSetup;
