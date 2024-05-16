---
Feature: "[[features-to-requirements-mapping]]"
Title: Translate Default Payment Button Titles
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
I want to provide translated payment button titles in multiple languages (FR, NL, DE, ES, IT),
So that non-English speaking customers can understand the payment options clearly.

#### Scenario: Setting Translated Button Titles

GIVEN I @[visit, the Worldline settings page]
WHEN I @[select, on the payment button titles setting, the option languages to any of (FR, NL, DE, ES, IT)]
AND I @[save, the settings]
AND I @[visit, the checkout page]
THEN I @[should see, the payment button titles displayed in the selected language]```
