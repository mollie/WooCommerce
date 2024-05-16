---
Feature: "[[features-to-requirements-mapping]]"
Title: API Keys Configuration
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
I need to enter API Key & Secret in the configuration,  
So that they can be used for the connection to the Mollie platform via the API/SDK.

#### Scenario: Merchant setup - Configuring API Keys
 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Mollie settings page]
WHEN @[Insert API credentials, with test type, with default values]  
THEN @[Successful connection to API] should be established

Data:live, test, API credentials: key, secret, PSPID

