---
Title: successful transaction while running on woocommerce expected version
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

As a merchant,
I want to ensure that transactions are processed successfully on WooCommerce versions from 3.9 to the latest,
So that customers can complete purchases without issues.

#### Scenario: Processing Transactions on Supported Versions

GIVEN @[WooCommerce store is configured, version 3.9 to latest]
AND I @[perform a transaction, with any product, with Worldline as the payment method, status authorized]
WHEN I @[visit, the plugin log page]
THEN I @[should see, in the WooCommerce order details, the transaction processed successfully without compatibility issues]

Data: WooCommerce version
