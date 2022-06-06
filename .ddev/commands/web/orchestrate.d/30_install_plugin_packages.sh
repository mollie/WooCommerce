#!/bin/bash

popd

composer install
npm install
npm run build
