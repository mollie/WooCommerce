---
Feature: "[[features-to-requirements-mapping]]"
Title: Test Connection with WLOP Platform
Background:
  - default
  - webhooks
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
I want to verify the validity of my credentials,  
So that I can ensure they are correct for connecting to the WLOP platform.

#### Scenario: Merchant setup - Testing Connection
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Worldline settings page]
WHEN @[Insert API credentials, in test type, with incorrect values]
THEN @[Failed connection to API] should be established

Params: live, test
API credentials: key, secret, PSPID
```
