const loginAdmin = async (page)=>{
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-login.php');
    await page.locator('#user_login').fill(process.env.ADMIN_USER);
    await page.locator('#user_pass').fill(process.env.ADMIN_PASS);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Log in').click()
    ]);

}

module.exports = {loginAdmin};

