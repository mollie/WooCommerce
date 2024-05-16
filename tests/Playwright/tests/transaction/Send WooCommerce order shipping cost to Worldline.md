---
Feature: "[[features-to-requirements-mapping]]"
Title: Send WooCommerce order shipping cost to Worldline
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
I want to the shipping cost to the Worldline Checkout Page,  
So that all necessary transaction details are accurately conveyed.

#### Scenario: Transmitting Shipping Cost to Worldline

GIVEN @[WooCommerce store is ready for checkout]  
WHEN @[perform, a transaction, with physical product, using Worldline as the payment method]  
THEN I @[should see, the correct shipping cost, in the Worldline Checkout]  
