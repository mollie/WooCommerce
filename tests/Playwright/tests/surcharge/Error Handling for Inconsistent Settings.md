---
Feature: "[[features-to-requirements-mapping]]"
Title: Error Handling for Inconsistent Settings
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
I want error handling for cases where WooCommerce settings differ from merchant configurations,
So that surcharge discrepancies can be identified and addressed.

#### Scenario: Detecting and Handling Configuration Errors

GIVEN @[WooCommerce store is configured]
AND I @[set in worldline settings page, differ from the merchant's configuration in Worldline backend]
WHEN I @[perform, a transaction, with any product, using Worldline as the payment method]
THEN I @[should see, in the page, an error message]
AND I @[I should not see, in the woocommerce orders page, the transaction]

