#!/bin/bash
#ddev-silent-no-warn

wp plugin is-installed akismet && wp plugin uninstall akismet
wp plugin is-installed hello && wp plugin uninstall hello
