---
Feature: "[[features-to-requirements-mapping]]"
Title: Display Surcharge Warning on Checkout
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

As a customer,
I want to see a warning about potential surcharges when checking out,
So that I am aware of any additional charges that may apply to my order.

#### Scenario: Viewing Surcharge Warning

GIVEN @[WooCommerce store is ready for checkout]
AND I @[enable surcharge for card payments]
WHEN I @[fill in the checkout form with payment details]
AND I @[visit, the checkout page]
THEN I @[should see, on the page, a warning about potential additional charges]


