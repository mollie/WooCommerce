#!/bin/bash

popd

wp plugin install environment-debug-admin-toolbar --activate

composer install
npm install
npm run build
