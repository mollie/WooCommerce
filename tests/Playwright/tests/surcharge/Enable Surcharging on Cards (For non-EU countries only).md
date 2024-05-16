---
Feature: "[[features-to-requirements-mapping]]"
Title: Enable surcharging on cards (For non-EU countries only)
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

As a merchant operating in non-EU countries,
I want to set a surcharge for card payments via a True/False radio button,
So that I can apply additional charges for card transactions when necessary.

#### Scenario: Setting Surcharging Option

GIVEN @[WooCommerce store is configured]
AND I @[visit, Worldline settings page]
WHEN I @[select, the surcharge option for card payments]
AND I @[click on, Enable/Disable]
AND I @[perform a transaction, with any product, using Worldline as the payment method, status authorized]
THEN I @[should see, in the total amount, the surcharge reflected]
