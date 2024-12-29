# Developers
This is a guide for software engineers who wish to take part in the development of this product.

## Environment Setup
This project uses DDEV to provide a complete development environment

### Composer & SSH authentication
In case you haven't done so already, you need to supply your [packagist token](https://packagist.com/orgs/inpsyde) so the dev container can pull from our private repositories

Copy/hardlink your Composer auth.json (located at `~/.config/composer/auth.json` or `~/.composer/auth.json`) to `~/.ddev/homeadditions/.composer/auth.json`
To import your SSH keys for use within DDEV, run `ddev auth ssh`

### Project bootstrapping

1. Clone the repo, if you haven't already.
2. Run `ddev start` to download container images and start services
3. Run `ddev composer install` to prepare PHP dependencies
4. Run `ddev composer compile-assets` to prepare JS and CSS once
5. Run `ddev orchestrate` to set up the WordPress environment (You can pass the `-f` flag if you ever wish to start from scratch)

## Using ngrok
You will often need to test and debug webhooks which require your development environment to be reachable from the outside
DDEV provides integration with `ngrok` via the `ddev share` command. Unfortunately, this is not very helpful with WordPress
since it needs correct URLs in the database.
Therefore, we have a wrapper command that sets up & restores the URLs in the database before and after a sharing session.
You will need to have ngrok and jq command previously installed. You also need to sign up for an ngrok account and connect with the token, if you have not done it before. 

To start a sharing session, simply run 
```shell
bin/ddev-share
```

## Build
Build are produced by GitHub Actions. Visit [the workflow page on GitHub](https://github.com/mollie/WooCommerce/actions/workflows/release.yml)
where you can request a build of your specified branch with a custom release version

## E2E Testing using Playwright

This repository comes with a *batteries NOT included* configuration for executing Playwright tests and accessing its UI.
Due to the size of the required dependencies (multiple browser packages), these must be setup once per project:

For the initial setup, run the following command:

```shell
ddev playwright-install
```

Afterwards, you can execute tests with this command:

```shell
ddev playwright test
```

You can access Playwright's UI via `https://mollie-payments-for-woocommerce.ddev.site:8444`.
For authentication, use `root:secret`

If you execute `ddev playwright test --ui` now, you should see its "UI mode" pop up

### Running on directly on the host

In case the supplied Playwright configuration is not suitable for you, you can install Playwright on the host normally as well:

```shell
cd tests/Playwright
yarn install
yarn playwright install --with-deps
cp -n .env.example .env || true
```

and then run tests using `yarn playwright test`

Note: Local installation might fail if the playwright install script does not recognize your OS/distribution

## Debugging GHA

If you need to debug the GitHub Actions, you can do so by installing the [ACT tool](https://github.com/nektos/act). This is an example command to run the workflow locally:

```shell
act -e .github/workflows/sample_dispatch.json -W .github/workflows/release.yml -P ubuntu-latest=shivammathur/node:latest --artifact-server-path ./artifacts

```
