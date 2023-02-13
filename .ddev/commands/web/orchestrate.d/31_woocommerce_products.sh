#!/bin/bash

wp plugin install wordpress-importer --activate
wp import ../sample_products.xml --authors=skip
