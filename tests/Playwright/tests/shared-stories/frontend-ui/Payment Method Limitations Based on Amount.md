---
Feature: "[[features-to-requirements-mapping]]"
Title: Payment Method Limitations Based on Amount
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
I want the payment method to be available only within its specified transaction limits,  
So that I can choose an appropriate payment method for my transaction amount.

#### Scenario: Checking Payment Method Availability with Amount under Limit

GIVEN @[WooCommerce store is configured]
AND I @[add to cart, a product with value under the limit]
WHEN I @[visit, the checkout page]
THEN I @[should not see, in the checkout gateways, the plugin's payment method]

#### Scenario: Checking Payment Method Availability with Amount over Limit

GIVEN @[WooCommerce store is configured]
AND I @[add to cart, a product with value over the limit]
WHEN I @[visit, the checkout page]
THEN I @[should not see, in the checkout gateways, the plugin's payment method]

#### Scenario: Checking Payment Method Availability with Amount within the Limit

GIVEN @[WooCommerce store is configured]
AND I @[add to cart, a product with value within the limit]
WHEN I @[visit, the checkout page]
THEN I @[should see, in the checkout gateways, the plugin's payment method]


data: product value under the limit, product value over the limit, product value within the limit
```
