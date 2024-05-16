---
Feature: "[[features-to-requirements-mapping]]"
Title: Verifying Payment Webhook Authenticity
Background:
  - default
  - webhooks
tags:
  - php
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description

As a merchant,  
I want the Webhook Module to verify the authenticity of incoming webhooks,  
So that I can prevent fraud or tampering with the transactions.

#### Scenario: Rejecting Not Authenticated Webhooks
@[xrayKey: 

GIVEN @[the plugin is enabled]  
WHEN I @[send a HTTP request, to the webhook endpoint, with a webhook data, without valid authentication values]  
THEN I @[should see, in the plugin log page, error about the webhook verification]  
AND I @[should not see, that the webhook was handled like if it was authenticated successfully]
