---
Feature: "[[features-to-requirements-mapping]]"
Title: Add Surcharge as Line Item
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
I want the surcharge to be added as an additional line item to the order,
So that the surcharge is clearly itemized for the customer's reference.

#### Scenario: Surcharge Itemization in Order

GIVEN @[WooCommerce store is ready for checkout]
AND I @[enable surcharge for card payments]
WHEN I @[fill in the checkout form with payment details]
AND I @[perform a transaction, with any product, using Worldline as the payment method, status authorized]
AND I @[visit, the order details]
THEN I @[should see, in the order details, the surcharge as an additional line item]
