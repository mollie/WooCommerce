---
Feature: "[[features-to-requirements-mapping]]"
Title: Auto Trim Spaces at End of Keys
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
I want automatic trimming of spaces at the end of API, webhook keys, and URLs,  
So that unintentional errors during data entry are minimized.

#### Scenario: Merchant setup - Auto Trimming Spaces
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Worldline settings page] 
WHEN I @[click, any input API credentials field] 
AND I @[fill in, the input field, with a value with spaces on the end]
AND I @[click, on Save Changes Button] 
THEN I @[should see, in the input field, that the spaces are trimmed]
