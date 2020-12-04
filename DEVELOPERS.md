# Developers
This is a guide for software engineers who wish to take part in the development of this product.

## Environment Setup
This project declares all of its dependencies, and configures a Docker environment. Follow the
steps described below to set everything up. For more information about the environment, see
template package [`wp-oop/plugin-boilerplate`][].

1. Clone the repo, if you haven't already.
2. Copy `.env.example` to `.env`.

    On most systems, this should be enough. Tweak values if necessary.
    
3. Map the project domain.

    In your `hosts` file, map the host of the Docker machine (for Docker Desktop users it's 
    localhost) to the value of the `WP_DOMAIN` env variable.

4. Install PHP dependencies with Composer.
   
   Use the PHPStorm integration, or CLI. Either way, All operations with Composer
   have to be done in the `build` service and the corresponding PHPStorm remote interpreter.
   
5. Install JS dependencies with Yarn.
   
   Use the PHPStorm integration, or CLI. Either way, All operations with Yarn
   have to be done in the `build` service.

6. Process the assets.

    Many assets like JS and CSS files exist in source form in the repo. In order
    for the plugin to be able to use them, they need to be processed, and the result
    to be added to the correct folder. Running `gulp buildAssets` in the `build` service
    will take care of that, pupulating the `public` folder with processed assets.
   
6. Bring up the environment.

    ```
    docker-compose up -d wp_dev
    ```
   
   This will install and configure the WP instance, including any required plugins.
   
   
[`wp-oop/plugin-boilerplate`]: https://github.com/wp-oop/plugin-boilerplate/
