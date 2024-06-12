---
Title: plugin cant be activated with php version lower than
Feature: third party compatibility
Background:
  - php-lower-than-7.4
tags:
  - php
  - js
  - critical
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description

As a WordPress administrator,
I want the plugin to prevent activation if the PHP version is lower than 7.4,
So that I avoid potential issues due to outdated PHP versions.

#### Scenario: Blocking Activation on Older PHP Versions

GIVEN @[WooCommerce store, PHP7.3]
AND I @[visit, the WordPress plugins page]
WHEN I @[click, under 'Plugin Name', the activate button]
THEN I @[should see, on the page, a message indicating the required minimum PHP version of 7.4]
