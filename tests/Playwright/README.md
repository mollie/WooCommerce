
### Setup Playwright tests
In your test environment
- Import the products
- Set WooCommerce taxes, shipping, and payment methods
- Check the language of the site, must be English unless specified
- Update the env with url and credentials
- Create the WooCommerce API key and insert in env
- If you make a dump of this environment, you can use it to restore the environment for the tests
- Suggestion to create different dumps for different preconditions
- In ddev playwright is already installed, in local you will need to [install it](https://playwright.dev/docs/intro#installing-playwright)
  

This runs one particular test
```
$ npx playwright test name-of-the-test.spec.js
```
This runs a project that can cover different tests against another environment

```
$ npx playwright test --project=project-name
```



