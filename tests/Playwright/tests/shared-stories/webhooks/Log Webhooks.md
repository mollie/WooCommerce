---
Feature: "[[features-to-requirements-mapping]]"
Title: Log Webhooks
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
I want the Webhooks Module to log all incoming webhooks,  
So that I can monitor and troubleshoot payment processes.

#### Scenario: Logging Incoming Webhooks
@[xrayKey: 

GIVEN I @[perform a transaction, with any product, with Worldline gateway, with status authorized]  
WHEN I @[visit, the plugin log page in WooCommerce]  
THEN I @[should see, detailed information about the webhooks, including their payloads and any actions taken]
