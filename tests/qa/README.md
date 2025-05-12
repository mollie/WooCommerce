# Mollie Tests Repo

Mollie Playwright tests. Depends on [`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) package.

## Table of Content

- [Repo structure](#repo-structure)
- [Local repo installation](#local-repo-installation)
- [Installation of `node_modules`](#installation-of-node_modules)
- [Installation of `playwright-utils` for local development](#installation-of-playwright-utils-for-local-development)
- [Project configuration](#project-configuration)
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

	\* - folders are numerated on purpose, to force correct sequence of tests - from basic to advanced. Although each test should be independent and work separately, it is better to start testing from `plugin-foundation` and move to more complex tests.

	\*\* - folders and numeration can be different, based on project requirements.

- `utils` - project related utility files, built on top of `@inpsyde/playwright-utils`.

	- `admin` - functionality for operating dashboard pages.

	- `frontend` - functionality for operating frontend pages, hosted checkout pages (payment system provider's pages).

	- `test.ts` - declarations of project related test fixtures.

	- other project related functionality, like helpers, APIs, urls.

- `.env`, `playwright.config.ts`, `package.json` - see below.

## Local repo installation

1. In VSCode open the open the terminal and clone Millie repository to your local PC:

	```bash
	git clone https://github.com/mollie/WooCommerce.git
	```

2. (Temporary, till autotests are not yet merged into main branch) Switch to `qa/e2e-tests` branch:

	```bash
	git checkout qa/e2e-tests
	```

3.  Change directory to `/tests/qa/`:

	```bash
	git clone https://github.com/mollie/WooCommerce.git
	```

## Installation of `node_modules`

1. Remove `"workspaces": [ "playwright-utils" ]` from `package.json`.

2. In the test project (`./tests/qa`) run following command:

```bash
npm run setup:tests
```

## Installation of `playwright-utils` for local development

> Note: skip this section if you're not going to update code in `playwright-utils`.

1. Add `"workspaces": [ "playwright-utils" ]` to `package.json`.

2. Delete `@inpsyde/playwright-utils` from `/node_modules`.

3. In the test project (`./tests/qa`) run following command:

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

1. [SSE setup](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3175907370/Self+Service+WordPress+Environment) - will be deprecated after Q1 of 2025:

	```bash
	ssh -l youruser php81.emp.pluginpsyde.com
	```

	After connection run:

	```bash
	rm -rf /var/www/html/* 2>/dev/null ; wp core download --version=6.7.1 ; wp config create ; wp db drop --yes ; wp db create ; wp core install ; exit
	```

2. Configure `.env` file following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#env-variables). See also `.env.example`.

3. Configure `playwright.config.ts` of the project following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#playwright-configuration).

4. Configure reporting to the TestRail following [these steps](https://github.com/inpsyde/playwright-utils/blob/main/docs/test-report-api/report-to-testrail.md).

5. Further configuration of the environment is done automatically via scripts in `./tests/.setup/woocommerce.setup.ts`. Consider commenting extra scripts when env is already configured.

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
		"parallel-transaction-eur-block": "npx playwright test --project=transaction-eur-block --workers=3"
	},
	```

	Run script with the following command:

	```bash
	npm run parallel-transaction-eur-block
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

5. Download tested plugin `.zip` package (usually attached to release ticket) and add it to `/resources/files`. You may need to remove version number from the file name. Expected filename: `mollie-payments-for-woocommerce.zip`.

6. Optional: delete previous version of tested plugin from the website if you don't execute __plugin foundation__ tests.

7. Start autotest execution from command line for the defined scope of tests (e.g. all, Critical, etc.). You should see `Test plan ID: 001, Test run ID: 002` in the terminal.

8. When finished test results should be exported to the specified test run ticket in Testrail.

9. Analyze failed tests (if any). Restart execution for failed tests, possibly in debug mode (see section _Additional options to run tests from command line_):

	```bash
	npx playwright test --grep --% "C123^|C124^|C125" --debug
	```

	\* - command for restarting failed/skipped tests is posted to the terminal after the execution.

10. Report bugs (if any) and attach them to the test-runs of failed tests.

11. If needed fix failing tests in a new branch, create a PR and assign it for review.

## Coding standards

Before commiting changes run following command:

```bash
npm run lint:js:fix
```
