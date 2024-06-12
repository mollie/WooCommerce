---
Feature: "[[features-to-requirements-mapping]]"
Title: Enable eDCC
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
I want to enable or disable eDCC (Dynamic Currency Conversion) via a radio button,
So that I can offer dynamic currency conversion options to customers.

#### Scenario: Setting eDCC Option
GIVEN @[WooCommerce store is ready for checkout]
WHEN I @[visit, Worldline settings page]
AND I @[click, in the payment settings, enable eDCC]
AND I @[click, save button]
AND I @[perform a transaction, with any product, with Worldline gateway]
THEN I @[should see, the transaction processed with eDCC]

