---
Feature: "[[features-to-requirements-mapping]]"
Title: PSPID
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
I want to input my unique gateway merchant ID (PSPID) at Worldline,  
So that my transactions are correctly associated with my account.

#### Scenario: Merchant setup - Entering PSPID
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Worldline settings page]
WHEN  @[Insert API credentials, in test type, with default values]   
THEN @[Successful connection to API] should be established  
WHEN I @[perform a transaction, with any product, with Worldline as the payment method, status authorized]
THEN I @[should see, in the woocommerce order details, the correct PSPID]

Params: live, test
API credentials: key, secret, PSPID
