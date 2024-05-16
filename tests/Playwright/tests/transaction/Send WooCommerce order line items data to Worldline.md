---
Feature: "[[features-to-requirements-mapping]]"
Title: Send WooCommerce order line items data to Worldline
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
I want to send detailed line items data to the Worldline Checkout Page,  
So that all necessary transaction details are accurately conveyed.

#### Scenario: Transmitting Order Data to Worldline

GIVEN @[WooCommerce store is ready for checkout]  
WHEN I @[fill in, in the checkout form, the customer details]  
AND I @[click, on worldline gateway]  
AND I @[click, place order button]  
THEN I @[should see, the order line items in the Worldline Checkout]  
