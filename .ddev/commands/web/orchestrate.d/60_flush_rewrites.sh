#!/bin/bash
#ddev-silent-no-warn

# Needed for generating the .htaccess file
echo "apache_modules:
  - mod_rewrite
" > wp-cli.yml

wp rewrite structure '/%postname%'
wp rewrite flush --hard
