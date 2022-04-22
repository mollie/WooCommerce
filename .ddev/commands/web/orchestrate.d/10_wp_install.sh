#!/bin/bash

if [ ! -z "${RECREATE_ENV}" ]; then
  echo "Deleting database before creating a new one"
  wp db clean --yes
fi

wp core install \
  --title="${WP_TITLE}" \
  --admin_user="${ADMIN_USER}" \
  --admin_password="${ADMIN_PASS}" \
  --url="${DDEV_PRIMARY_URL}" \
  --admin_email="${ADMIN_EMAIL}" \
  --skip-email

