---
Feature: "[[features-to-requirements-mapping]]"
Title: Connect to Test or Prod Platform
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
I want separate credential fields for Test and Live environments,  
So that I can manage my integration settings for different deployment stages.

#### Scenario: Merchant setup - Entering Live Credentials
 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Mollie settings page]  
WHEN I @[check, the "Live mode" checkbox]   
THEN I @[should see,
- Create a Mollie account link
- Enable Mollie Payments checkbox
- PSPID input field
- Use the live environment checkbox
- Live API Key input field
- Live API Secret input field
- Checkout type dropdown selected as  - Full redirection to Mollie Payment page
- Payment button title input field]

#### Scenario: Merchant setup - Entering Test Credentials
 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Mollie settings page]
WHEN I @[uncheck, the "Live mode" checkbox]  
THEN I @[should see,
- Create a Mollie account link
- Enable Mollie Payments checkbox
- PSPID input field
- Use the live environment checkbox
- Test API Key input field
- Test API Secret input field
- Checkout type dropdown selected as  - Full redirection to Mollie Payment page
- Payment button title input field]
