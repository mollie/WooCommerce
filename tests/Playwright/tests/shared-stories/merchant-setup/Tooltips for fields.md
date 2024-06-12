---
Feature: "[[features-to-requirements-mapping]]"
Title: Tooltips for fields
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
I want contextual help about various fields in the configuration,  
So that I understand what information is required for each field.

#### Scenario: Viewing Tooltips

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Worldline settings page] 
WHEN I @[hover over, any configuration field] 
THEN I @[should see, a tooltip providing contextual help about that field]
