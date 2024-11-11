# Mollie Tests Repo

Mollie Playwright tests. Depends on [`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) package.

__\* - Currently the `@inpsyde/playwright-utils` needs to be installed locally (see section _Installation for local development_ below) and switched to branch `work/misha`.__

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

	\* - folders are numerated on purpose, to force correct sequence of tests - from basic to advanced. Although each test should be independent and work separately, it is better to start testing from `plugin-foundation` and move to more complex tests.

	\*\* - folders and numeration can be different, based on project requirements.

- `utils` - project related utility files, built on top of `@inpsyde/playwright-utils`.

	- `admin` - functionality for operating dashboard pages.

	- `frontend` - functionality for operating frontend pages, hosted checkout pages (payment system provider's pages).

	- `test.ts` - declarations of project related test fixtures.

	- other project related functionality, like helpers, APIs, urls.

- `.env`, `playwright.config.ts`, `package.json` - see below.

## Installation of `@inpsyde/playwright-utils`

### Installation as a node package

1. Remove `"workspaces": [ "playwright-utils" ]` from `package.json`.

2. In the root of the monorepo run following command:

```bash
npm run setup:tests
```

### Installation for local development

1. Add `"workspaces": [ "playwright-utils" ]` to `package.json`.

2. Delete `@inpsyde/playwright-utils` from `/node_modules`.

3. In the root of the monorepo run following command:

	```bash
	git clone https://github.com/inpsyde/playwright-utils.git
	```

	[`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) repository should be cloned as `playwright-utils` right inside the root directory of monorepo.

4. Restart VSCode editor. This will create `playwright-utils` instance in the source control tab of VSCode editor.

5. Run following command:

	```bash
	npm run setup:utils
	```

6. `@inpsyde/playwright-utils` should reappear in node_modules. Following message (coming from `tsc-watch`) should be displayed in the terminal:

	```bash
	10:00:00 - Found 0 errors. Watching for file changes.
	```

7. If you plan to make changes in `playwright-utils` keep current terminal window opened and create another instance of terminal.

## Project configuration

Project from the monorepo requires a working WordPress website with WooCommmerce, `.env` file and configured Playwright.

1. [SSE setup](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3175907370/Self+Service+WordPress+Environment) - will be deprecated in Q1 of 2025.

2. Tested user with Administrator role should be created
  
2. In the Dashboard navigate to __Settings -> Permalinks__ and select `Post name` in __Permalink structure__ for correct format of REST path.

3. Install __Storefront__ theme.
   
4. Install __WooCommerce__ plugin.

5. In __WooCommerce -> Settings -> Advanced -> REST API__ create _Consumer Key_ and _Secret_ with Read/Write permissions and store them in `.env`.

6. To avoid conflicts make sure any other payment plugins are deleted.

7. Configure `.env` file following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#env-variables). See also `.env.example`.

8. Configure `playwright.config.ts` of the project following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#playwright-configuration).

9. Reporting. Add `testrail-reporter` in the reporter section of `playwright.config.ts`:

	```ts
	reporter: [
		// other reporters ...
		[ '@inpsyde/playwright-utils/build/integration/testrail/testrail-reporter.js' ],
	],
	```

	Configure connection to TestRail API in `.env` (see `.env.example`):

	```bash
	# Testrail
	TESTRAIL_URL=https://website.testrail.io
	TESTRAIL_USERNAME=user@company.com
	TESTRAIL_PASSWORD=*************
	TESTRAIL_PLAN_ID=
	TESTRAIL_RUN_ID=
	```

## Run tests

To execute all tests sequentially, in the terminal navigate to the `./tests/qa` directory and run following command:

```bash
npx playwright test --project=all
```

### Additional options to run tests from command line

- Add scripts to `package.json` of the project (eligible for Windows, not tested on other OS):

	```json
	"scripts": {
		"all": "npx playwright test --project=all --workers=1",
		"sequential": "npx playwright test --project=sequential --workers=1",
		"parallel-surcharge": "npx playwright test --project=surcharge --workers=3"
	},
	```

	Run script with the following command:

	```bash
	npm run parallel-transactions
	```

	\* - there's a number of tests which can be executed in parallel to speed up test execution (see `projects` section in `playwright.config.ts`).

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

1. Create test plan with run in TestRail, named after the tested plugin version, for example "Test Plan for Release 1.2.3".

	\* - for autotest run there's no need to manually add tests cases to the run - the executed test will be added automatically before automated execution.

2. Link release ticket (via `tests: JIR-234`).

3. Set Test Execution ticket status `In progress`.

4. Add/update test plan with run IDs in `.env` file of the project (`TESTRAIL_PLAN_ID, TESTRAIL_RUN_ID`).

5. Download tested plugin `.zip` package (usually attached to release ticket) and add it to `/project/<project-name>/resources/files`. You may need to remove version number from the file name.

6. Optional: delete previous version of tested plugin from the website if you don't execute __plugin foundation__ tests.

7. Start autotest execution from command line for the defined scope of tests (e.g. all, Critical, etc.). You should see `Test plan ID: 001, Test run ID: 002` in the terminal.

8. When finished test results should be exported to the specified test run ticket in Testrail.

9. Analyze failed tests (if any). Restart execution for failed tests, possibly in debug mode (see section _Additional options to run tests from command line_):

	```bash
	npx playwright test --grep --% "C123^|C124^|C125" --debug
	```

10. Report bugs (if any) and attach them to the test-runs of failed tests.

11. If needed fix failing tests in a new branch, create a PR and assign it for review.

## Coding standards

Before commiting changes run following command:

```bash
npm run lint:js:fix
```
