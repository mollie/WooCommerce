---
Feature: "[[features-to-requirements-mapping]]"
Title: User Notification on Plugin Errors
Background:
  - default
tags:
  - php
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description

As a buyer,  
I want to receive notifications if there is an issue with the plugin during checkout,  
So that I am aware of any problems affecting my transaction.

#### Scenario: Receiving Notifications on Errors

GIVEN @[WooCommerce store is ready for checkout]
AND I @[visit, the checkout page]
AND I @[select, the plugin's payment method]
AND I @[trigger, an error with a specific action (e.g., entering a 'magic number' as payment amount)]
THEN I @[should see, on the page, a notification informing me of the issue]
