---
Title: validate automatic plugin update
Feature: plugin activation deativation
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

As a WordPress administrator,
I want to ensure that the plugin updates automatically to the latest version,
So that I can benefit from the latest features and security patches without manual intervention.

#### Scenario: Confirming Automatic Update Success

GIVEN I @[install, the Worldline plugin]
AND I @[click, on automatic update setting]
AND @[a new version is released]
WHEN I @[check, the plugin's version]
THEN I @[should see, the latest version number, displayed prominently according to the UI design specifications].
