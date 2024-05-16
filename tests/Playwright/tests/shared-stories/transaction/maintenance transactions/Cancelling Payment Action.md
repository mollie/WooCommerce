---
Feature: "[[features-to-requirements-mapping]]"
Title: Cancelling Payment Action
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
I want a custom order action to void an authorized payment,  
So that I can manage payment voids efficiently.

#### Scenario: Cancelling an Authorized Payment

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, Worldline settings page]
AND I @[select, in the transaction mode setting, Authorization]
WHEN I @[perform a transaction, with any product, with Worldline gateway, with status authorised]
AND I @[visit, the order details page]
THEN I @[should see, an option to void the payment]
