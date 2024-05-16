---
Feature: "[[features-to-requirements-mapping]]"
Title: Refund from the WooCommerce Order Page
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
I want to perform refunds from the plugin backend,  
So that I can manage these transactions directly within the platform.

#### Scenario: Refunding full amount

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
AND I @[capture the transaction]  
AND I @[visit, the order details page]  
WHEN I @[perform, a refund, with the full order amount]  
THEN I @[should see, the order status, 'Refunded']  
AND I @[should see, in the order items, the refund]  
AND I @[should see, the refunded payment, on the Worldline portal]

#### Scenario: Refunding partial amount

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
AND I @[capture the transaction]  
AND I @[visit, the order details page]  
WHEN I @[perform, a refund, with the partial order amount]  
THEN I @[should see, the order status, 'Processing']  
AND I @[should see, in the order items, the refund]  
AND I @[should see, the partial refund record, on the Worldline portal]
