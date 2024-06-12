---
Feature: "[[features-to-requirements-mapping]]"
Title: Refunding Orders with Discounts and Promotions
Background:
  - default
tags:
  - php
  - js
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description

As a merchant,  
I want to accurately process refunds for orders that have discounts or promotions applied,  
So that the refund amount correctly reflects the actual amount paid by the customer.

#### Scenario: Refunding an Order with a Discount

GIVEN @[WooCommerce store is ready for checkout]
AND I @[create, a manual order, with a discount applied]
WHEN I @[perform, a transaction, with status authorized]
AND I @[visit, the WooCommerce orders page]
AND I @[click, in the order details, on the 'Refund' button]
AND I @[confirm, the refund]
THEN I @[should see, the refund amount based on the discounted price]

#### Scenario: Refunding an Order with a 2x1 Promotion

GIVEN @[WooCommerce store is ready for checkout]
AND I @[create, a manual order, with a 2x1 promotion applied]
WHEN I @[perform, a transaction, with status authorized]
AND I @[visit, the WooCommerce orders page]
AND I @[click, in the order details, on the 'Refund' button]
AND I @[confirm, the refund]
THEN I @[should see, the refund adjusted for the 2x1 promotion]
AND I @[should see, the refund amount reflecting the actual amount paid for retained items]
