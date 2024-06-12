---
Feature: "[[features-to-requirements-mapping]]"
Title: Capture Payment Action
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
I want a custom order action to capture payment for an authorized transaction,  
So that I can easily manage payment captures within the order interface.

#### Scenario: Viewing an Authorized Payment

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[visit, Worldline settings page]  
AND I @[select, in the transaction mode setting, Authorization]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
WHEN I @[visit, the order details page]  
THEN I @[should see, the order status, on hold]  
AND I @[should see, an option to capture the payment]  

#### Scenario: Viewing a Captured Payment

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[visit, Worldline settings page]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
AND I @[capture the transaction]  
WHEN I @[visit, the order details page]  
THEN I @[should not see, an option to capture the payment]  


#### Scenario: Executing a Maintenance Transaction - Capturing

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[select, in the transaction mode setting, Authorization]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
AND I @[visit, the order details page]  
WHEN I @[perform, a capture]  
THEN I @[should see, the transaction captured]  
AND I @[should see, the order status, processing]  
AND I @[should see, the capture, on the Worldline portal]
