---
Title: theme compatibility
Feature: third party compatibility
Background:
  - theme compatibility
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
I want the plugin to be compatible with the default WordPress theme and Storefront,
So that the plugin functions correctly and maintains a consistent user experience.

#### Scenario: Ensuring Compatibility with Default Themes

GIVEN @[WooCommerce store is configured, default theme]
AND I @[perform a transaction, with any product, with Worldline as the payment method, status authorized]
WHEN I @[visit, the plugin log page]
THEN I @[should see, in the WooCommerce order details, the transaction processed successfully without disrupting the site’s layout or user experience]
