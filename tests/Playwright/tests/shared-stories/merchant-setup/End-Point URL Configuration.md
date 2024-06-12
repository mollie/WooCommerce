---
Feature: "[[features-to-requirements-mapping]]"
Title: End-Point URL Configuration
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

As a merchant,  
I want to view and edit the default API endpoint URL ( https://docs.direct.worldline-solutions.com/en/integration/api-developer-guide/api#environmentsandendpoints ),  
So that I can change it if necessary.

#### Scenario: Viewing the Live Endpoint URL
@[xrayKey: 

GIVEN I @[activate, the plugin] with default settings
WHEN I @[visit, Worldline settings page]   
AND I @[check, the "Live mode" checkbox]  
THEN I @[should see, the "Test API Endpoint" field, with value "https://payment.direct.worldline-solutions.com"] 
AND I @[should not see, the "Live API Endpoint" field]

#### Scenario: Viewing the Test Endpoint URL
@[xrayKey: 

GIVEN I @[activate, the plugin] with default settings
WHEN I @[visit, Worldline settings page]   
AND I @[uncheck, the "Live mode" checkbox]  
THEN I @[should see, the "Test API Endpoint" field, with value "https://payment.preprod.direct.worldline-solutions.com"] 
AND I @[should not see, the "Live API Endpoint" field]

#### Scenario: Editing the Endpoint URL
@[xrayKey: 

GIVEN I @[activate, the plugin] with default settings
WHEN I @[visit, Worldline settings page]    
AND I @[fill in,into an endpoint URL field, "  https://payment.example.com/v2/  "]
AND I @[click, save settings]
THEN I @[should see, the settings are saved successfully]
AND I @[should see, in the field content, "https://payment.example.com"] (no /v2/, end slashes, spaces)

#### Scenario: Entering invalid Endpoint URL
@[xrayKey: 

GIVEN I @[activate, the plugin] with default settings
WHEN I @[visit, Worldline settings page]    
AND I @[fill in,into an endpoint URL field, "qwerty"]
AND I @[click, save settings]
THEN I @[should see, error message]
AND I @[should see, in the field content, the default URL]
