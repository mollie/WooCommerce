---
Feature: "[[features-to-requirements-mapping]]"
Title: Backend Order Transaction ID
Background:
  - default
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
I want to open an order in the backend and see the appropriate payment ID,  
So that I can match orders to Worldline payments for reconciliation purposes.

#### Scenario: Viewing Payment ID in Order Management

GIVEN @[WooCommerce store is ready for checkout]
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]
WHEN I @[visit, the order details page]
THEN I @[should see, in the order details, the transaction ID]
