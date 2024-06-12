---
Feature: "[[features-to-requirements-mapping]]"
Title: Updating Taxes with Selected Payment Method
Background:
  - default
  - updating-taxes
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
I want the taxes to be updated correctly when I select the plugin's payment method,  
So that I am charged the correct tax amount for my purchase.

#### Scenario: Accurate Tax Calculation

GIVEN @[WooCommerce store is ready for checkout]
AND I @[fill in, the checkout form, with country details]
WHEN I @[select, in the checkout gateways, the plugin's payment method]
THEN I @[should see, in the total amount, the tax charges accurately updated]
