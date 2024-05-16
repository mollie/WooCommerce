---
Title: validate translations
Feature: translations
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

As a WordPress administrator in a multilingual environment,
I want to ensure that the plugin's translations for French (FR), Dutch (NL), German (DE), Spanish (ES), and Italian (IT) are accurate and reflect the latest version,
So that users can interact with the plugin in their preferred language without encountering language barriers.

#### Scenario: Ensuring Translation Accuracy for Supported Languages

GIVEN @[WooCommerce store is configured]
AND I @[set WP language to (FR, NL, DE, ES, IT)]
WHEN I @[visit, Worldline settings page]
THEN I @[should see, all text accurately translated into the selected language without any sections defaulting to English]

Data: supported languages, French (FR), Dutch (NL), German (DE), Spanish (ES), Italian (IT)
