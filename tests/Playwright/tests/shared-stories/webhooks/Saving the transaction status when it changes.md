---
Feature: "[[features-to-requirements-mapping]]"
Title: Saving the transaction status when it changes
Background:
  - default
  - webhooks
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
I want the Webhooks Module to trigger relevant actions based on the payment notification,  
So I need to save the transaction status whenever I receive a webhook with an update.

#### Scenario: Saving the Transaction Status

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[perform a transaction with any product using Worldline as the payment method]   
WHEN I @[inspect, in the database, the transaction status]   
THEN I @[should see, in the database, the transaction status] 

#### Scenario: Displaying the Transaction Status

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[perform a transaction, with any product, using Worldline as the payment method]   
WHEN I @[inspect, in the order details page, the order notes]   
THEN I @[should see, in the WC order notes, the transaction status]
