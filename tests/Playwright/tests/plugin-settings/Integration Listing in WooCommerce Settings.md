---
Feature: "[[features-to-requirements-mapping]]"
Title: Integration Listing in WooCommerce Settings
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
I want to list the Worldline integration within the WooCommerce payments settings page,
So that I can manage its activation status and configure it as needed.

#### Scenario: Viewing Integration in WooCommerce Settings

GIVEN I @[activate, the Worldline plugin]
AND I @[visit, the WooCommerce payment settings page]
WHEN I @[click, the Worldline payment method]
THEN I @[should see, in the page, the Worldline payment method settings]

