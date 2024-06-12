---
Feature: "[[features-to-requirements-mapping]]"
Title: Payment Method Visibility for Different User Types
Background:
  - default
  - different-user-types
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
I want the payment method to be appropriately visible for both guests and logged-in users,  
So that it is accessible to all users regardless of their login status.

#### Scenario: Payment Method Visibility for Guests
@[xrayKey: 

GIVEN I @[logged out, from the WooCommerce store]
AND @[WooCommerce store is ready for checkout]
WHEN I @[visit, the checkout page]
THEN I @[should see, in the checkout gateways, the plugin's payment method]

#### Scenario: Payment Method Visibility for Logged-In Users
@[xrayKey: 

GIVEN I @[logged in, to the WooCommerce store]
AND @[WooCommerce store is ready for checkout]
WHEN I @[visit, the checkout page]
THEN I @[should see, in the checkout gateways, the plugin's payment method]
