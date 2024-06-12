---
Feature: "[[features-to-requirements-mapping]]"
Title: Documentation link
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
I want to be able to quickly open the plugin documentation,  
So that I can get information about the plugin.

#### Scenario: Redirecting to Contact Support

GIVEN @[WooCommerce store is configured]
WHEN I @[visit, Worldline settings page]
AND I @[click, the 'Documentation' option]
THEN I @[should see, the plugin documentation page]
