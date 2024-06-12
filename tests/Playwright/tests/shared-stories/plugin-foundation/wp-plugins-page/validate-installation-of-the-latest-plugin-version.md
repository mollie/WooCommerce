---
Title: validate installation of the latest plugin version
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

#### Scenario: Successful Installation of Latest Version

GIVEN @[WooCommerce - Store is configured]
WHEN I @[install, the Worldline plugin]
THEN I @[should see, in the page, a message that installation was successful]
AND I @[should see, under the plugin name, activate]
