#!/bin/sh

source "$PWD/wp-content/plugins/mollie-wc/.env"
rm -f wp-config.php
wp config create \
  --dbname="$DB_NAME" \
  --dbuser="$DB_USER" \
  --dbpass="$DB_PASSWORD" \
  --dbhost=mariadb \
  --force
wp core install \
  --url=$PROJECT_BASE_URL \
  --title="$PROJECT_NAME" \
  --admin_user="$WP_ADMIN_USER" \
  --admin_password="$WP_ADMIN_PASSWORD" \
  --admin_email="$WP_ADMIN_EMAIL"\
  --skip-email
wp plugin activate woocommerce-blocks
wp plugin activate woocommerce-rest-api
wp plugin activate woocommerce
wp plugin activate mollie-wc
