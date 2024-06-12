---
Feature: "[[features-to-requirements-mapping]]"
Title: Create account - Sign Up
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
Xray Key: 
---

# Description

As a merchant,  
I want to be redirected to the Worldline Account Creation Page,  
So that I can easily create or sign up for a Worldline account.

#### Scenario: Merchant setup - Redirecting to Account Creation
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Worldline settings page]
WHEN I @[click, on the "Create Account/Sign Up"]  
THEN I @[should see, the page, with the Worldline Account Creation Form]
