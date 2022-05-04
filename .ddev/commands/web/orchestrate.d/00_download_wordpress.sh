#!/bin/bash

if ! wp core download; then
 echo 'WordPress is already installed.'
 exit
fi
