---
Feature: "[[features-to-requirements-mapping]]"
Title: Handle Captured Payments
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


#### Scenario: Updating Status on Successful Payment
@[xrayKey: 

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[perform a transaction with any product using Worldline as the payment method, status captured]
WHEN I @[visit, the order details page]
THEN I @[should see, in the order status, processing]
