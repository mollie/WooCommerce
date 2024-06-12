---
Title: cross devices successful transaction
Feature: cross browser
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

As a merchant,
I want to ensure that transactions are processed successfully across a variety of devices, including desktops, tablets, and smartphones,
So that customers can complete their purchases seamlessly, regardless of the device they are using.

#### Scenario: Ensuring Transaction Success Across Devices

GIVEN @[WooCommerce store is ready for checkout]
AND I @[perform, a transaction, with any product, using Worldline as the payment method, status success]
THEN I @[should see, the transaction processed successfully without any device-specific issues]

Data: major devices, desktop, tablet, smartphone
