---
Feature: "[[features-to-requirements-mapping]]"
Title: Updating Transaction Status Success
Background:
  - default
  - webhooks
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
I want the Webhooks Module to update the transaction status in WooCommerce,  
So that it reflects the actual payment status received from the PSP.

#### Scenario: Updating Status on Successful Payment when Manual Capture

GIVEN @[WooCommerce store is ready for checkout]  
AND @[authorization mode is (Pre-Authorization, Final-Authorization)]  
AND I @[perform a transaction with any product using Worldline as the payment method, status authorized]  
AND I @[capture, on the Worldline portal, the transaction]  
WHEN I @[inspect, in the order details page, the order status]  
THEN I @[should see, in the order status, processing]  

#### Scenario: Updating Status on Successful Payment when Sale Mode

GIVEN @[WooCommerce store is ready for checkout]  
AND @[authorization mode is Sale]  
AND I @[perform a transaction with any product using Worldline as the payment method, status authorized]  
WHEN I @[inspect, in the order details page, the order status]  
THEN I @[should see, in the order status, processing]  
