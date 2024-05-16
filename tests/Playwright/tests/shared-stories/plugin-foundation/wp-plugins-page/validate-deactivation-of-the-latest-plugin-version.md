---
Title: validate deactivation of the latest plugin version
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
I want to deactivate the latest version of the plugin without issues,
So that I can perform maintenance or troubleshoot as needed.

#### Scenario: Plugin foundation - Successfully Deactivating Plugin
@[xrayKey: 

GIVEN @[WooCommerce - Store is configured]
AND I @[visit, the WordPress plugins page]
WHEN I @[click, under the plugin name, deactivate plugin action button]
THEN I @[should see, under the plugin name, delete]
AND I @[should see, under the plugin name, activate]
