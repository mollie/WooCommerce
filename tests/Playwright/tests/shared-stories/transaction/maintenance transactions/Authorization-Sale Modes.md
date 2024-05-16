---
Feature: "[[features-to-requirements-mapping]]"
Title: Authorization-Sale Modes
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
I want to choose between different transaction modes - Sale, Pre-Authorization and Final-Authorization  
So that I can process payments according to the required transaction protocol.

#### Scenario: Selecting Transaction Mode
@[xrayKey: 

GIVEN @[WooCommerce store is ready for checkout]  
WHEN I @[visit, Worldline settings page]  
AND I @[select, an authorization mode (Sale, Pre-Authorization, Final-Authorization)]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
AND I @[visit, the order details page]  
THEN I @[should see, the transaction processed according to the chosen mode]  

Data: Sale, Pre-Authorization, Final-Authorization
