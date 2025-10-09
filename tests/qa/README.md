# Mollie Tests Repo

Mollie Playwright tests. Depends on [`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) package.

## Table of Content

- [Repo structure](#repo-structure)
- [Local installation](#local-installation)
- [DDEV adjustments](#ddev-adjustments)
- [Troubleshooting](#troubleshooting)
- [Reporting to TestRail](#reporting-to-testrail)
- [Run tests](#run-tests)
- [Autotest Execution workflow](#autotest-execution-workflow)
- [Coding standards](#coding-standards)

## Repo structure

- `resources` - files with test-data, images, project related installation packages, types, etc.

- `tests` - test specifications. For payment plugins contains following folders:
	
	- `01-plugin-foundation` - general tests for plugin installation, uninstallation, activation, deactivation, display of plugin in __WooCommerce -> Settings -> Payments__.

	- `02-merchant-setup` - tests for connection of current plugin instance to the payment system provider API via merchant (seller) credentials.

	- `03-plugin-settings` - tests for various plugin settings, may include assertions of settings effect on frontend.

	- `04-frontend-ui` - tests for plugin UI on frontend: display of payment buttons, display of payment methods depending on customer's country, etc.

	- `05-transaction` - tests of payment process. Typically include: adding products to cart as precondition, payment (transaction) process, assertions on order received page, dashboard order edit page, payment via payment system provider API.

	- `06-refund` - tests for refund transactions. Typically include: finished transaction as precondition, refund via payment system provider API on dashboard order edit page, assertion of refund statuses.

	- `07-vaulting` - tests for transactions with enabled vaulting (saved payment methods for registered customers). Ability to remember payment methods and use them for transactions.

	- `08-subscriptions` - tests for transactions for subscription products. Requires WooCommerce Subscriptions plugin. Usually available to registered customers and also includes vaulting and renewal of subscription (with automatic payment). WooCommerce Subscriptions plugin (can be [downloaded here](https://woocommerce.com/my-account/downloads/), login credentials in 1Password).

	- `09-compatibility` - tests for compatibility with other themes, plugins, etc.

	> Note 1: - folders are numerated on purpose, to force correct sequence of tests - from basic to advanced. Although each test should be independent and work separately, it is better to start testing from `plugin-foundation` and move to more complex tests.

	> Note 2: - folders and numeration can be different, based on project requirements.

- `utils` - project related utility files, built on top of `@inpsyde/playwright-utils`.

	- `admin` - functionality for operating dashboard pages.

	- `frontend` - functionality for operating frontend pages, hosted checkout pages (payment system provider's pages).

	- `test.ts` - declarations of project related test fixtures.

	- other project related functionality, like helpers, APIs, urls.

- `.env`, `playwright.config.ts`, `package.json` - see below.

## Local installation

1. In VSCode open the terminal and clone Millie repository to your local PC:

	```bash
	git clone https://github.com/mollie/WooCommerce.git
	cd WooCommerce
	```

2. (Temporary, till autotests are not yet merged into main branch) Switch to `qa/e2e-tests` branch:

	```bash
	git checkout qa/e2e-tests
	```

3. In the terminal change directory to `./tests/qa` 

	```bash
	cd tests/qa
	```
	Run installation command:

	```bash
	npm run setup:tests
	```

4. Using `.env.example`, create and configure `.env` file in `./tests/qa/`:

	5.1 Set general variables. See also [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#env-variables).
	
	5.2 Set Mollie API keys and tested API method (variable values: `payment` (is applied by default) or `order`). See also `.env.example`.

5. Configure `playwright.config.ts` of the project following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#playwright-configuration).

6. To avoid conflicts make sure any other payment plugins are deleted.

> Note: Further configuration of the environment is done automatically via scripts in `./tests/.setup/woocommerce.setup.ts`.

## DDEV adjustments

For DDEV usage set `IGNORE_HTTPS_ERRORS=true` and leave the `WP_BASIC_AUTH_` credentials empty in `.env`.

## Troubleshooting

> See also [@inpsyde/playwright-utils documentation](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#installation).

- Make sure you're logged in the [Syde npm package registry](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3112894465/GitHub+Package+Registry+for+npm).

- Make sure that `"workspaces": [ "playwright-utils" ]` node isn't present in `./tests/qa/package.json`.

- Delete `@inpsyde/playwright-utils` from `./tests/qa/node_modules` and `package-lock.json` from `./tests/qa`.

- To avoid conflicts make sure any other payment plugins are deleted.

## Reporting to TestRail

Configure reporting to the __TestRail__ following [these steps](https://github.com/inpsyde/playwright-utils/blob/main/docs/test-report-api/report-to-testrail.md).

## Run tests

To execute all tests sequentially, in the terminal navigate to the `./tests/qa/` directory and run following command:

```bash
npx playwright test --project=all
```

### Additional options to run tests from command line

- Add scripts to `package.json` of the project (eligible for Windows, not tested on other OS):

	```json
	"scripts": {
		"test:smoke":  "npx playwright test --grep \"@Smoke\"",
		"test:critical": "npx playwright test --grep \"@Critical\"",
		"test:ui": "npx playwright test --grep \"UI\"",
		"test:functional": "npx playwright test --grep \"Functional\"",
		"test:all": "npm run test:ui & npm run test:functional"
	},
	```

	Run script with the following command:

	```bash
	npm run test:critical
	```

- Run several tests by test ID

	- MacOS, Linux:

	```bash
	npx playwright test --grep "C123|C124|C125"
	```

	- Windows (Powershell):

	```bash
	npx playwright test --grep --% "C123^|C124^|C125"
	```

	It may be required additionally to specify the project (if tests relate to more then one project):

	```bash
	npx playwright test --project "project-name" --grep --% "C123^|C124^|C125"
	```


## Autotest Execution workflow

1. Create test plan with run(s) in TestRail, named after the tested plugin version, for example "Test Plan for Release 1.2.3".

	> Note 1: For autotest run there's no need to manually add tests cases to the run - the executed test will be added automatically before automated execution.

	> Note 2: There can be > 1 test runs (for example, when testing API methods: one for Order, another for Payment).

2. Add link to TestRail Plan or Milestone to release ticket in Jira.

3. In the `.env` file:

	3.1 Add/update test plan with run IDs in `.env` file of the project (`TESTRAIL_PLAN_ID`, `TESTRAIL_RUN_ID`).

	3.2 In case of testing 2 API methods set the respective `TESTRAIL_RUN_ID` and `MOLLIE_API_METHOD` (`payment` (is applied by default) or `order`).

4. Download tested plugin `.zip` package (usually attached to release ticket) and add it to `/resources/files`. You may need to remove version number from the file name. Expected filename: `mollie-payments-for-woocommerce.zip`.

5. Optional: delete previous version of tested plugin from the website if you don't execute __plugin foundation__ tests.

6. Start autotest execution from command line for the defined scope of tests (e.g. all, Critical, etc.). You should see `Test plan ID: 001, Test run ID: 002` in the terminal.

7. When finished test results should be exported to the specified test run ticket in Testrail.

8. Analyze failed tests (if any). Restart execution for failed tests, possibly in debug mode (see section _Additional options to run tests from command line_):

	```bash
	npx playwright test --grep --% "C123^|C124^|C125" --debug
	```

	> Note: command for restarting failed/skipped tests is posted to the terminal after the execution.

9. Report bugs (if any) and attach them to the test-runs of failed tests.

10. If needed, fix failing tests in a new branch, create a PR and assign it for review.

11. If needed, update the `TESTRAIL_RUN_ID` and `MOLLIE_API_METHOD` and repeat the execution for another API method.

	> Note: depending on selected API method number of tests may vary (not all payment methods are eligible for 'Payment'). To find specific cases in specs perform a global code search for `testedApiMethod` and `mollieApiMethod` fixture.

## Coding standards

Before commiting changes run following command:

```bash
npm run lint:js:fix
```
