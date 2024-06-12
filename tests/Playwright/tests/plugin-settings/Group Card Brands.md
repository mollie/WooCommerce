---
Feature: "[[features-to-requirements-mapping]]"
Title: Group Card Brands
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

As a merchant using the Hosted Checkout Page,
I want to choose whether to show card brands individually or grouped together,
So that I can provide a tailored checkout experience for my customers.

#### Scenario: Configuring Card Brand Display Grouped

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, WooCommerce settings page]
WHEN I @[select, the card brand display setting, to grouped]
THEN I @[should see, on the checkout page, the card brands grouped together]

#### Scenario: Configuring Card Brand Display 

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, WooCommerce settings page]
WHEN I @[select, the card brand display setting, to individual]
THEN I @[should see, on the checkout page, the card brands displayed individually]
