---
Title: cross browser successful transaction
Feature: cross browser
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
I want transactions to be successfully processed across all major browsers,
So that every customer can complete their purchase regardless of their browser choice.

#### Scenario: Ensuring Cross-Browser Transaction Success

GIVEN @[WooCommerce store is ready for checkout]
AND I @[perform, a transaction, with any product, using Worldline as the payment method, status success]
THEN I @[should see, the transaction processed successfully without any browser-specific issues]

Data: major browsers, Chrome, Firefox, Safari, Edge, Opera, Internet Explorer
