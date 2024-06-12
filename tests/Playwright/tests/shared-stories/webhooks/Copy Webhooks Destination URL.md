---
Feature: "[[features-to-requirements-mapping]]"
Title: Copy Webhooks Destination URL
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

As a merchant,  
I want a button to copy the webhook URL in the Notification configuration in the pluging's setting tab in the WooCommerce admin panel, 
So that I can easily paste it into the Worldline Back-office.

#### Scenario: Copying Webhook URL
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
WHEN I @[visit, Worldline settings page]
AND I @[click, the 'Copy Webhook URL' button]
THEN I @[should see, webhook URL copied to my clipboard]
