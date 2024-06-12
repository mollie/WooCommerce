---
Feature: "[[features-to-requirements-mapping]]"
Title: Payment Method Limitations Based on Currency
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

As a buyer,  
I want the payment method to be available only within its specified allowed currencies,  
So that I can choose an appropriate payment method for my transaction.

#### Scenario: Checking Payment Method Availability with allowed currency

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, the WooComerce settings page]
WHEN I @[select, in the currency setting, the allowed currency]
AND I @[save, the settings]
AND I @[visit, the checkout page]
THEN I @[should see, in the checkout gateways, the payment method available]

#### Scenario: Checking Payment Method Availability with not allowed currency

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, the WooComerce settings page]
WHEN I @[select, in the currency setting, a disallowed currency]
AND I @[save, the settings]
AND I @[visit, the checkout page]
THEN I @[should not see, in the checkout gateways, the payment method available]

data: allowed currency, disallowed currency
```
