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


## Using ngrok
You will often need to test and debug webhooks which require your development environment to be reachable from the outside
DDEV provides integration with `ngrok` via the `ddev share` command. Unfortunately, this is not very helpful with WordPress
since it needs correct URLs in the database.
Therefore, we have a wrapper command that sets up & restores the URLs in the database before and after a sharing session.

To start a sharing session, simply run 
```shell
bin/ddev-share
```

