---
Feature: "[[features-to-requirements-mapping]]"
Title: Send WooCommerce order billing and shipping address data to Worldline
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
I want to send detailed customer billing and shipping data to the Worldline Checkout Page,  
So that all necessary transaction details are accurately conveyed allowing to manage the order on the Worldline side and support payment methods requiring such details.

#### Scenario: Transmitting Order Data to Worldline

GIVEN @[WooCommerce store is ready for checkout]  
WHEN I @[fill in, in the checkout form, the customer details]  
AND I @[perform, a transaction, with any product, using Worldline as the payment method, status authorized]  
THEN I @[should see, the customer details, in the Worldline Back Office]  
