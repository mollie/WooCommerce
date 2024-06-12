---
Feature: "[[features-to-requirements-mapping]]"
Title: Updating Shipping Charges with Selected Payment Method
Background:
  - default
  - updating-shipping-charges
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

As a buyer,  
I want the shipping charges to be updated correctly when I select the plugin's payment method,  
So that I am informed of the accurate shipping costs for my purchase.

#### Scenario: Correct Shipping Charge Updates

GIVEN @[WooCommerce store is ready for checkout]
AND I @[fill in, the checkout form, with country details]
WHEN I @[select, in the checkout gateways, the plugin's payment method]
THEN I @[should see, in the total amount, the shipping charges accurately updated]

