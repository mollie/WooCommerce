---
Title: install plugin when woocommerce not available warning
Feature: third party compatibility
Background:
  - default
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
I want to receive a warning if I attempt to install the plugin when WooCommerce is not available on my site,
So that I understand WooCommerce is a prerequisite for the plugin.

#### Scenario: Displaying Warning for Missing WooCommerce

GIVEN @[Wordpress without WooCommerce environment]
AND I @[visit, the WordPress plugins page]
WHEN I @[install, the plugin]
THEN I @[should see, on the page, a warning message indicating that WooCommerce is required]
