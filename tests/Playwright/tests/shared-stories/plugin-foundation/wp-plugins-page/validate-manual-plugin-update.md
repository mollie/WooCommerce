---
Title: validate manual plugin update
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
I want to manually update the plugin to the latest version when automatic updates are not available,
So that I can control when and how updates are applied.

#### Scenario: Plugin foundation - Completing a Manual Update
@[xrayKey: 

GIVEN @[WooCommerce - Store is configured]
AND I @[visit, the WordPress plugins page] 
AND an @[update is available for the plugin]
WHEN I @[update, the plugin, manually]
THEN I @[should see, in the page, "Plugin updated successfully"]

#### Scenario: Plugin foundation - Plugin can be installed manually
@[xrayKey: 

GIVEN @[WooCommerce - Store is configured]
AND I @[uninstall, the plugin]
AND I @[visit, /wp-admin/plugin-install.php?tab=upload]
WHEN I @[upload, the package, from my computer]
AND I @[click, Install Now button]
THEN I @[should see, in the page, "Plugin installed successfully"]
