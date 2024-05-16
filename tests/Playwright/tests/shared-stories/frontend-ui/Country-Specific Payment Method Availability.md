---
Feature: "[[features-to-requirements-mapping]]"
Title: Country-Specific Payment Method Availability
Background:
  - default
  - country-specific-payment-method-availability
tags:
  - php
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description

As a buyer,  
I want the payment method to be available only for supported countries,  
So that there is no confusion or error during checkout.

#### Scenario: Country-Specific Visibility with allowed countries

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, the checkout page]
WHEN I @[select, in the checkout form, any allowed country] 
THEN I @[should see, in the checkout gateways, the plugin's payment method]

#### Scenario: Country-Specific Visibility with disallowed countries

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, the checkout page]
WHEN I @[select, in the checkout form, any disallowed country]
THEN I @[should NOT see, in the checkout gateways, the plugin's payment method]

data: allowed country, disallowed country
```
