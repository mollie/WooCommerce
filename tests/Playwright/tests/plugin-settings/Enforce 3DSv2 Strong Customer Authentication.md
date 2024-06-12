---
Feature: "[[features-to-requirements-mapping]]"
Title: Enforce 3DSv2 Strong Customer Authentication
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
I want to enforce strong customer authentication (SCA) for every transaction,
So that I can provide enhanced security for my customers.

#### Scenario: Setting SCA Enforcement
GIVEN @[WooCommerce store is ready for checkout]
WHEN I @[visit, Worldline settings page]
AND I @[select, in the payment settings, enforce 3DSv2]
AND I @[click, save button]
AND I @[perform a transaction, with any product, with Worldline gateway]
THEN I @[should see, the transaction processed with 3DSv2 authentication]
