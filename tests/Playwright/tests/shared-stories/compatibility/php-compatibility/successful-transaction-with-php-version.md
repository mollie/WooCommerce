---
Title: successful transaction with php version
Feature: third party compatibility
Background:
  - php-matrix
tags:
  - php
  - critical
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description

As a merchant,
I want to ensure that transactions are processed successfully on PHP versions from 7.4 to current,
So that there are no server-side compatibility issues affecting transactions.

#### Scenario: Processing Transactions on Supported PHP Versions

GIVEN @[WooCommerce store is configured, PHP7.4]
AND I @[perform a transaction, with any product, with Worldline as the payment method, status authorized]
WHEN I @[visit, the plugin log page]
THEN I @[should see, in the transaction log, no server-side compatibility issues]
