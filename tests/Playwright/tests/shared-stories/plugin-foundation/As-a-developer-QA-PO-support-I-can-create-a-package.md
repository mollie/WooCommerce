---
Title: As a developer/QA/PO/support, I can create a package
Feature: plugin foundation
Background:
  - github-ui
tags:
  - php
  - critical
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description
It would be great for a developer/QA/PO/support/… to be able to create a package for any specified branch from the GitHub UI, like in some of our projects. 
Also it would be convenient if there is a way to enter the version string, which will be displayed in WP and included in the .zip name.

# Scenario: Creating a Package for a Specific Branch
GIVEN I am a developer
AND have sufficient access rights for the plugin repo
WHEN I triggered the package creation for the branch X with entered version 
THEN I can download the package
AND as an admin I can install it on a WP website
AND I can see that the package does not contain files needed only for development, temporary build files
AND I can see 
AND I can see 
AND I can see that the code inside is based on the branch X
