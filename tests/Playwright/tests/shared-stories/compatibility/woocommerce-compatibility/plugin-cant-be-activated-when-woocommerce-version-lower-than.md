---
Title: plugin cant be activated when woocommerce version lower than
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
I want the plugin to prevent activation if the WooCommerce version is lower than the supported version,
So that I avoid running into compatibility issues that could affect my online store.

#### Scenario: Blocking Activation on Older WooCommerce Versions

GIVEN @[WooCommerce store, version lower than required]
AND I @[visit, the WordPress plugins page]
WHEN I @[click, under 'Plugin Name', the activate button]
THEN I @[should see, on the page, a message indicating the required minimum WooCommerce version]

Data: minimum WooCommerce version
