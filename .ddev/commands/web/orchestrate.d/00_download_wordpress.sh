#!/bin/bash
#ddev-silent-no-warn

if ! wp core download --version="${WP_VERSION:-latest}"; then
 echo 'WordPress is already installed.'
 exit
fi
