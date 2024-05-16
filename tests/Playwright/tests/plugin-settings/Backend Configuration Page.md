---
Feature: "[[features-to-requirements-mapping]]"
Title: Backend Configuration Page
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
I want a backend configuration page in the admin panel,
This page is integrated in the WooCommerce payments admin panel  
So I can set up the integration and design the checkout page experience.

#### Scenario: Plugin settings - Accessing and Configuring Plugin settings Page
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
WHEN I @[visit, Worldline settings page]
THEN I @[should see,
- Create a Worldline account link
- Enable Worldline Payments checkbox
- PSPID input field
- Use the live environment checkbox
- Test API Key input field
- Test API Secret input field
- Checkout type dropdown selected as  - Full redirection to Worldline Payment page
- Payment button title input field]
