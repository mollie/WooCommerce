#!/bin/bash

pushd "${DDEV_DOCROOT}"

wp plugin activate "${$DDEV_PROJECT}"
