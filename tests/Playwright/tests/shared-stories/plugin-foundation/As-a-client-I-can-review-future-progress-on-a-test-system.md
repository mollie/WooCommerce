---
Title: As a client I can review future progress on a test system
Feature: plugin foundation
Background:
  - default
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

As a client, you have the ability to review the progress of the project at any time. 
This is facilitated through access to a test system set up by the agency. 
The test system is configured with default settings to ensure ease of access and consistency in reviewing project developments. 
Additionally, to assist you in navigating and utilizing the test system effectively, there will likely be documentation available. 
This documentation is designed to guide you through the process of accessing and understanding the test system, ensuring you can monitor the project's progress efficiently and effectively.

#### Scenario: Accessing the Test System
GIVEN I am a client
AND the test system has been set up with default configurations
WHEN I visit the test system website ->https://worldline.products.aws.wptesting.cloud/wp-admin 
AND I log in as admin using the provided admin credentials
AND I visit the plugins page
THEN I can see the Wordline plugin on the plugins page
AND I can open the documentation by clicking the Documentation link


