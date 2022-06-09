#!/bin/bash

pushd "${DDEV_DOCROOT}"

wp plugin activate "${PLUGIN_NAME:-$DDEV_PROJECT}"
