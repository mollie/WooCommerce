---
Feature: "[[features-to-requirements-mapping]]"
Title: Updating Transaction Status Failed
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
I want the Notification Module to update the transaction status in WooCommerce,  
So that it reflects the actual payment status received from the PSP.

#### Scenario: Updating Status on Failed Payment

GIVEN @[WooCommerce store is ready for checkout]  
WHEN I @[perform a transaction with any product using Worldline as the payment method, status failed]
THEN I @[visit, the order details page]
AND I @[should see, in the order status, on-hold] 
