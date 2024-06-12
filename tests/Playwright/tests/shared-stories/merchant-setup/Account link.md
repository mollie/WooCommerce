---
Feature: "[[features-to-requirements-mapping]]"
Title: Documentation
Background:
  - default
tags:
  - php
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description

As a merchant,  
I want to be able to quickly open the Worldline account page,  
So that I can get information about the Worldline account.

#### Scenario: Redirecting to Contact Support

GIVEN @[WooCommerce store is configured]
AND I @[visit, Merchant setup in plugin's admin]
AND I @[click, the 'View Account' option]
THEN I @[should see, the Worldline account page]
