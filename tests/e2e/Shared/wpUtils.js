const loginAdmin = async (page)=>{
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill(process.env.E2E_AUTH_USERNAME);
    await page.locator('#user_pass').fill(process.env.E2E_AUTH_PW);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('input:has-text("Log In")').click()
    ]);
}

async function deactivateWPPlugin(page, pluginName) {
    await page.getByRole('link', { name: `Deactivate ${pluginName}` }).click();
}
async function activateWPPlugin(page, pluginName) {
    await page.getByRole('link', { name: `Activate ${pluginName}` }).click();
}

module.exports = {loginAdmin, deactivateWPPlugin, activateWPPlugin};

