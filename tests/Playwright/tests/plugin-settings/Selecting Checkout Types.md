---
Feature: "[[features-to-requirements-mapping]]"
Title: Selecting Checkout Types
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
I want to choose the checkout type for my store, specifically full redirection to Worldline Payment Page,
So that customers can complete their payments securely on Worldline’s platform.

#### Scenario: Plugin settings - Selecting Checkout Types to Full Redirection
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Worldline settings page]
WHEN I @[click, on Checkout type dropdown setting]
THEN I @[should see, on Checkout type dropdown, 'Full redirection to Worldline Payment Page']
