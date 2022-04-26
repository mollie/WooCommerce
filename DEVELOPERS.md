# Developers
This is a guide for software engineers who wish to take part in the development of this product.

## Environment Setup
This project uses DDEV to provide a complete development environment

### Composer & SSH authentication
In case you haven't done so already, you need to supply your [packagist token](https://packagist.com/orgs/inpsyde) so the dev container can pull from our private repositories

Copy/hardlink your Composer auth.json (located at `~/.config/composer/auth.json` or `~/.composer/auth.json`) to `~/.ddev/homeadditions/.composer/auth.json`
To import your SSH keys for use within DDEV, run `ddev auth ssh`


### Environment Setup
This project declares all of its dependencies, and configures a Docker environment. Follow the
steps described below to set everything up. For more information about the environment, see
template package [`wp-oop/plugin-boilerplate`][].

1. Clone the repo, if you haven't already.
2. Run `ddev start` to download container images and start services
3. Run `ddev orchestrate` to set up the WordPress environment. (You can pass the `-f` flag if you ever wish to start from scratch)
4. TODO: document how to build assets
