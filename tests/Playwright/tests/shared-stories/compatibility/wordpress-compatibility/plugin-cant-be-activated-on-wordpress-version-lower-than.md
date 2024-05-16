---
Title: plugin cant be activated on wordpress version lower than
Feature: third party compatibility
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
I want the plugin to prevent activation if the WordPress version is lower than 5.0,
So that compatibility issues and potential site problems are avoided.

#### Scenario: Blocking Activation on Older WordPress Versions

GIVEN @[WordPress, version lower than 5.0]
AND I @[visit, the WordPress plugins page]
WHEN I @[click, under 'Plugin Name', the activate button]
THEN I @[should see, on the page, a message indicating the required minimum WordPress version of 5.0]

