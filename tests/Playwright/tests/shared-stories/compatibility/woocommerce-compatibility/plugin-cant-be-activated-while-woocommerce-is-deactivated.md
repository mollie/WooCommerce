---
Title: plugin cant be activated while woocommerce is deactivated
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
I want to ensure that the plugin cannot be activated unless WooCommerce is also activated,
So that functional dependencies are maintained.

#### Scenario: Preventing Activation Without Active WooCommerce

GIVEN @[WooCommerce store is configured]
AND I @[deactivate, WooCommerce]
WHEN I @[visit, the WordPress plugins page]
AND I @[click, under 'Plugin Name', the activate button]
THEN I @[should see, on the page, a message indicating that WooCommerce must be activated first]
