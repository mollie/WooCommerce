#!/bin/bash
#ddev-silent-no-warn

popd

wp plugin install environment-debug-admin-toolbar --activate

composer install
npm install
npm run build
