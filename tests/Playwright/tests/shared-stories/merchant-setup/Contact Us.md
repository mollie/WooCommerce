---
Feature: "[[features-to-requirements-mapping]]"
Title: Contact Us
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
I want to be able to quickly contact Worldline for support,  
So that I can get assistance with my queries or issues.

#### Scenario: Redirecting to Contact Support
@[xrayKey: 

GIVEN @[WooCommerce store is configured]
WHEN I @[visit, Worldline settings page]
AND I @[click, the 'Contact Us' option]
THEN I @[should see, the Worldline support page]
