#!/bin/bash

wp plugin install wordpress-importer --activate
wp import ../wordpress/wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip
