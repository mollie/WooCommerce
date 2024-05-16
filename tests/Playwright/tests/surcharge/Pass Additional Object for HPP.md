---
Feature: "[[features-to-requirements-mapping]]"
Title: Pass Additional Object for HPP
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

As a merchant using Hosted Payment Pages (HPP),
I want an additional object (onBehalfOf) to be passed for surcharge transactions,
So that the payment gateway can process the surcharge accurately.

#### Scenario: Handling HPP Surcharge

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, Worldline settings page]
WHEN I @[fill in, in the surcharge field, the value 10]
AND I @[perform a transaction, with any product, using Worldline as the payment method, status authorized]
THEN I @[should see, in the log data, the additional object (onBehalfOf) included]

