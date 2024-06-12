---
Feature: "[[features-to-requirements-mapping]]"
Title: Edit Payment Method Title
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
I want to edit the title of the payment method for WooCommerce Payments Page,
So that it aligns with the merchants expectations.

#### Scenario: Plugin settings - Customizing Payment method Title

GIVEN @[WooCommerce Plugin is installed]
AND I @[visit, Worldline settings page]
WHEN I @[fill in, "Payment payment title" field, with the value "My custom payment title"]
AND I @[click, Save settings button] 
AND I @[visit, WooCommerce Payments page]
THEN I @[should see, under Method, "My custom payment title"]

#### Scenario: Plugin settings - Keeping Default Payment Button Title

GIVEN @[WooCommerce Plugin is installed]
AND I @[visit, Worldline settings page]
WHEN I @[fill in, "Payment payment title" field, with empty value]
AND I @[click, Save settings button] 
AND I @[visit, WooCommerce Payments page]
THEN I @[should see, under Method, "Worldline payments"]
