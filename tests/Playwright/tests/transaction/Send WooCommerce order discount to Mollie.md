---
Feature: "[[features-to-requirements-mapping]]"
Title: Send WooCommerce order discount to Mollie
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
I want to send the discount data to the Mollie Checkout Page,  
So that all necessary transaction details are accurately conveyed.

#### Scenario: Transmitting Discount to Mollie

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[apply, the coupon]  
WHEN @[perform, a transaction, with any product, using Mollie as the payment method]  
THEN I @[should see, the correct discount, in the Mollie Checkout]  
