---
Title: the latest plugin version is displayed per ui design
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
I want the latest version of the plugin to be displayed within the plugin's UI,
So that I can easily verify I am using the most current version for compatibility and support purposes.

## Scenario: Viewing the latest plugin version

GIVEN I @[install, the Worldline plugin]
WHEN I @[visit, the WordPress plugins page]
THEN I @[should see, the latest version number, displayed prominently according to the UI design specifications].
