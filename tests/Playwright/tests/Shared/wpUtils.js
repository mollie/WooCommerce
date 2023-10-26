async function gotoWPPage(page, url) {
    await page.goto(url);
}

async function gotoWPLogin(page) {
    await gotoWPPage(page, '/wp-login.php');
}

async function gotoWPPlugins(page) {
    await gotoWPPage(page, '/wp-admin/plugins.php');
}

const loginAdmin = async (page) => {
    await gotoWPLogin(page);
    await page.locator('#user_login').fill(process.env.E2E_AUTH_USERNAME);
    await page.locator('#user_pass').fill(process.env.E2E_AUTH_PW);
    console.log(process.env.E2E_AUTH_USERNAME)
    await Promise.all([
        page.waitForNavigation(),
        page.locator('input:has-text("Log In")').click()
    ]);
}

async function deactivateWPPlugin(page, pluginName) {
    await page.getByRole('link', {name: `Deactivate ${pluginName}`, exact: true}).click();
}

async function activateWPPlugin(page, pluginName) {
    await page.getByRole('cell', {name: `${pluginName} Activate ${pluginName} | Delete ${pluginName}`}).getByRole('link', {name: `Activate ${pluginName}`}).click();
}

const enableCheckboxSetting = async (page, settingName, settingsTabUrl) => {
    await page.goto(settingsTabUrl);
    await page.locator(`input[name="${settingName}"]`).check();
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

const disableCheckboxSetting = async (page, settingName, settingsTabUrl) => {
    await page.goto(settingsTabUrl);
    await page.locator(`input[name="${settingName}"]`).uncheck();
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

async function saveSettings(page) {
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

const selectOptionSetting = async (page, settingName, settingsTabUrl, optionValue) => {
    await page.goto(settingsTabUrl);
    await page.selectOption(`select[name="${settingName}"]`, optionValue);
    await saveSettings(page);
}

const fillTextSettings = async (page, settingName, settingsTabUrl, value) => {
    await page.goto(settingsTabUrl);
    let field = await page.locator(`input[name="${settingName}"]`);
    await field.fill(value);
    await saveSettings(page);
}

const fillNumberSettings = async (page, settingName, settingsTabUrl, value) => {
    await page.goto(settingsTabUrl);
    await page.locator(`input#${settingName}`).fill('');
    await page.type(`input#${settingName}`, value.toString());
    await saveSettings(page);
}

module.exports = {loginAdmin, deactivateWPPlugin, activateWPPlugin, gotoWPPlugins, enableCheckboxSetting, disableCheckboxSetting, selectOptionSetting, fillTextSettings, fillNumberSettings};

