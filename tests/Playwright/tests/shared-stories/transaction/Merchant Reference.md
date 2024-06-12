---
Feature: "[[features-to-requirements-mapping]]"
Title: Merchant Reference
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
I want to store Worldline PAYID in order data and platform order reference in Worldline Payment Data,
So that I can match orders to Worldline payments for reconciliation purposes.

#### Scenario: Matching Orders with Payments on WooCommerce side

GIVEN an order is processed with Worldline  
WHEN I view the order in the WooCommerce backend  
THEN I see the Worldline PAYID in order notes  

#### Scenario: Matching Orders with Payments on Worldline side

GIVEN an order is processed with Worldline  
WHEN I view the transaction on the Worldline portal  
THEN I see the WooCommerce order ID in the "Merchant reference"    
