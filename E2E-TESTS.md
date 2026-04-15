# Mollie Tests

Mollie Playwright tests. Depends on [`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) package.

## Table of Content

- [Repo structure](#repo-structure)
- [Local installation](#local-installation)
- [DDEV adjustments](#ddev-adjustments)
- [Troubleshooting](#troubleshooting)
- [Reporting to TestRail](#reporting-to-testrail)
- [Run tests](#run-tests)
- [Run Refund tests](#run-refund-tests)
- [Run Multistep Checkout tests](#run-multistep-checkout-tests)
- [Autotest Execution workflow](#autotest-execution-workflow-local)
- [Automated environment setup scripts](#automated-environment-setup-scripts)
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

2. In the terminal run installation command:

	```bash
	npm run e2e:setup:tests
	```

3. Create the `.env` file. Copy-paste content from `Mollie .env` vault of 1Password replacing all the data for your test env. Alternatively use `.env.example.e2e` to create and configure `.env` file:

	3.1 Set general variables. See also [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#env-variables).
	
	3.2 Set Mollie API keys.

	3.3 For Kinsta envs set ssh parameters (lookup in Kinsta dashboard). See also [Reset Kinsta env](#reset-kinsta-env) section.

4. Copy following packages into `/tests/qa/resources/files`:

	* Configured Mollie plugin package (e.g. v8.1.6) named as `mollie-payments-for-woocommerce.zip`
	
	* WooCommerce Subscriptions package named as `woocommerce-subscriptions.zip`

	* Germanized Pro package named as `germanized-for-woocommerce-pro.zip`

> Note 1: Further configuration of the environment is done automatically via scripts in `./tests/qa/tests/_setup/woocommerce.setup.ts`.

> Note 2: To avoid conflicts make sure any other payment plugin is deleted.

## DDEV adjustments

For DDEV usage set `IGNORE_HTTPS_ERRORS=true` and leave the `WP_BASIC_AUTH_` credentials empty in `.env`.

## Troubleshooting

> See also [@inpsyde/playwright-utils documentation](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#installation).

- Make sure you're logged in the [Syde npm package registry](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3112894465/GitHub+Package+Registry+for+npm).

- Make sure that `"workspaces": [ "playwright-utils" ]` is not present in `package.json`.

- Delete `@inpsyde/playwright-utils` from `node_modules/` and delete `package-lock.json`.

- To avoid conflicts make sure any other payment plugins are deleted.

## Reporting to TestRail

Configure reporting to the __TestRail__ following [these steps](https://github.com/inpsyde/playwright-utils/blob/main/docs/test-report-api/report-to-testrail.md).

## Run tests

To execute all tests sequentially, run the following command in the terminal:

```bash
# Run tests from playwright project "payment-api"
npm run e2e:test:payment-api

# Run only smoke tests from playwright project "payment-api"
npm run e2e:test:payment-api:smoke

# Run tests from playwright project "order-api"
npm run e2e:test:order-api
```

## Run Refund tests

Refunds require long wait for the webhook arrival and therefore are executed in a separate project:

```bash
npm run e2e:test:payment-api:refund
# OR
npx playwright test --project=refund-payment-api --workers=4
```

## Run Multistep Checkout tests

_Multistep Checkout_ requires installation of both Germanized and Germanized Pro plugins. **Currently not available in CI (because Germanized Pro is a paid plugin and can't be commited as a .zip).**

Additional actions for local execution:

1. Add Germanized Pro plugin `.zip` file into `/tests/qa/resources/files/` as `germanized-for-woocommerce-pro.zip`. Germanized Pro can be downloaded from [Inpsyde Packagist](https://packagist.com/orgs/inpsyde/packages/4592207).

2. Multistep setup might require env reset. Use one of the following commands:

	```bash
	# Setup multistep checkout with env reset:
	npm run env:reset:multistep

	# Setup multistep checkout:
	npm run env:setup:multistep

	# Run smoke tests for multistep checkout:
	npm run e2e:test:payment-api:multistep:smoke
	# OR
	npm run e2e:test:order-api:multistep:smoke

	# Run all available tests for multistep checkout:
	npm run e2e:test:payment-api:multistep
	# OR
	npm run e2e:test:order-api:multistep
	```


### Additional options to run tests from command line

- Run several tests by test ID

	```bash
	npx playwright test --project=payment-api --grep "C123|C124|C125"
	```


## Autotest Execution workflow (local)

1. Create test plan with run(s) in TestRail, named after the tested plugin version, for example "Test Plan for Release 1.2.3".

	> Note 1: For autotests create a test run with one test (e.g. `C419986`). There's no need to manually add tests cases to the run. The executed tests will be added automatically before automated execution.

	> Note 2: There can be > 1 test runs (for example, when testing API methods: one for Order, another for Payment and Multistep).

2. Add link to TestRail Plan or Milestone to release ticket in Jira.

3. In the `.env` file:

	3.1 Add/update test plan with run IDs in `.env` file of the project (`TESTRAIL_PLAN_ID`, `TESTRAIL_RUN_ID`).

	3.2 In case of testing 2 API methods set the respective `TESTRAIL_RUN_ID`.

	3.3 For multistep checkout follow stepd described in [this section](#run-multistep-checkout-tests).

4. Download tested plugin `.zip` package and copy it into `./tests/qa/resources/files` as `mollie-payments-for-woocommerce.zip`.

5. Optional: restart test env or delete previous version of tested plugin from the website if you don't execute __plugin foundation__ tests.

6. Start autotest execution from command line for the defined scope of tests (see [Run tests](#run-tests) section). You should see `Test plan ID: 001, Test run ID: 002` in the terminal.

7. Test results will be exported to the specified test run ticket in Testrail after each test.

8. Analyze failed tests (if any). Restart execution for failed tests, possibly in debug mode (see section _Additional options to run tests from command line_):

	```bash
	npx playwright test --project=payment-api --grep "C123|C124|C125" --debug
	```

	> Note: command for restarting failed/skipped tests is posted to the terminal after the execution.

9. Report bugs (if any) and attach them to the test-runs of failed tests.

10. If needed, fix failing tests in a new branch, create a PR and assign it for review.

11. If needed, update the `TESTRAIL_RUN_ID` and repeat the execution for another API method using respective Playwright project.

	> Note: depending on selected API method number of tests may vary (not all payment methods are eligible for 'Payment'). To find specific cases in specs perform a global code search for `Test is not eligible for ${ mollieApiMethod } API method` text.


## Reset Kinsta env

> **Note:** the staging env on Kinsta should be created and the script to reset env [provided by devops](https://inpsyde.atlassian.net/wiki/spaces/ENG/pages/6240338010/WordPress+hosting+FAQs#How-can-QA-reset-a-test-environment%3F) (if not - create a ticket on [SDO board](https://inpsyde.atlassian.net/jira/software/c/projects/SDO/boards/395)).

Find SSH data in [Kinsta dashboard](https://my.kinsta.com/sites/details) for your tested env. Replace data in the following one-line command and run it in the terminal to reset the env:

```bash
ssh <your-ssh-username>@<your-ssh-host> -p <your-ssh-port> '${HOME}/bin/reset-wp.sh --wp-version=6.9 --wp-type=single && exit'
```

## Automated environment setup scripts

Local usage of _automated env setup scripts_ assumes that [Local installation](#local-installation) and, optionally, [DDEV adjustments](#ddev-adjustments) are done.

### Reset environment and store

Reset the env and store to a clean state:

- Resets env
- Installs WooCommerce, Storefront theme, additional plugins (Disable Nonce, Subscriptions, etc.).
- Configures website permalinks (`%postname%`).
- Configures WooCommerce default settings (country, currency, taxes, shipping, API keys, emails).
- Creates classic pages, products, coupons, registered customer.

Can be combined with Mollie and multistep checkout setup:

```bash
# Reset env only
npm run env:reset

# Reset env, WooCommerce
npm run env:reset:wc

# Reset env, WooCommerce, install and connect Mollie
npm run env:reset:mollie

# Reset env, WooCommerce, install and connect Mollie, setup Multistep checkout
npm run env:reset:mollie:multistep
```

### Setup store

```bash
npm run env:setup:wc
```

### Setup Mollie plugin

- Installs Mollie
- Connects default merchant
- Payment API

```bash
npm run env:setup:mollie
```

### Setup Mollie API method

```bash
npm run env:setup:payment-api
npm run env:setup:order-api
```

### Other setup scripts

```bash
# Multistep checkout
npm run env:setup:multistep

# Block checkout pages
npm run env:setup:checkout:block

# Classic checkout pages
npm run env:setup:checkout:classic

# Taxes included
npm run env:setup:tax:inc

# Taxes excluded
npm run env:setup:tax:exc
```


## Coding standards

Before committing changes run following command:

```bash
npm run e2e:lint:js:fix
```
