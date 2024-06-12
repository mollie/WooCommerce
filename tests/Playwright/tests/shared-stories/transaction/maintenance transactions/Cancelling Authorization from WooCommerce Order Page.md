---
Feature: "[[features-to-requirements-mapping]]"
Title: Cancelling Transaction
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
I want to perform cancellations (complete or partial) of authorizations from the plugin backend,  
So that I can manage these transactions directly within the platform.

#### Scenario: Cancelling full amount

GIVEN @[WooCommerce store is ready for checkout]  
AND @[authorization mode is (Final authorization, Pre-authorization)]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
AND I @[visit, the order details page]  
WHEN I @[perform, a refund, with the full order amount]  
THEN I @[should see, the order status, 'Refunded']  
AND I @[should see, in the order items, the refund]  
AND I @[should see, the cancelled payment, on the Worldline portal]

#### Scenario: Cancelling partial amount

GIVEN @[WooCommerce store is ready for checkout]  
AND @[authorization mode is (Final authorization, Pre-authorization)]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
AND I @[visit, the order details page]  
WHEN I @[perform, a refund, with the partial order amount]  
THEN I @[should see, the order status, 'On hold']  
AND I @[should see, in the order items, the refund]  
AND I @[should see, the partial cancellation record, on the Worldline portal]

Data: Cancellation complete, Cancellation partial
