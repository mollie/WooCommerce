const loginAdmin = async (page)=>{
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill(process.env.ADMIN_USER);
    await page.locator('#user_pass').fill(process.env.ADMIN_PASS);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('input:has-text("Log In")').click()
    ]);

}

module.exports = {loginAdmin};

