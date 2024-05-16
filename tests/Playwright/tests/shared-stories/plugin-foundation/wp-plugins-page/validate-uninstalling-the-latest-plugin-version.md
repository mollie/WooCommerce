---
Title: validate uninstalling the latest plugin version
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
I want to uninstall the latest version of the plugin without leaving residual files or data,
So that I can keep my WordPress installation clean and optimized.

#### Scenario: Plugin foundation - Clean Uninstallation of Plugin
@[xrayKey: 

GIVEN @[WooCommerce - Store is configured]
AND I @[deactivate, the plugin]
AND I @[visit, the WordPress plugins page]
WHEN I @[click, delete plugin action]
THEN I @[should see, in the page, "was successfully deleted"]
AND @[DB should not show data of the plugin]
