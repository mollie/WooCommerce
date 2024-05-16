---
Feature: "[[features-to-requirements-mapping]]"
Title: Webhooks Configuration
Background:
  - default
  - webhooks
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

As a merchant,  
I need to configure the key and secret in the plugin,  
So that the webhook URL is generated correctly for use with Worldline.

#### Scenario: Entering Key and Secret
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
WHEN I @[insert API credentials]
AND I @[visit, the Worldline settings page]
THEN I @[should see, in the page, a webhook URL generated based on these credentials]

#### Scenario: Verifying Webhook URL Generation
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
WHEN I @[insert API credentials]
AND I @[visit, the Worldline settings page]
THEN I @[should see, in the page, that URL follows correct format and is valid for Worldline Back-office]
