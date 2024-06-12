---
Title: validate activation of the latest plugin version
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
I want to install the latest version of the plugin seamlessly,
So that I can start using its functionality immediately without issues.

#### Scenario: Plugin foundation - Successful Activation of plugin
@[xrayKey: 

GIVEN @[WooCommerce - Store is configured]
AND I @[deactivate plugin, Worldline]
AND I @[visit, the WordPress plugins page]
WHEN I @[click, activate plugin action]
THEN I @[should see, settings and deactivate actions]
AND I @[should not see, delete action]
