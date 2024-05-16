---
Feature: "[[features-to-requirements-mapping]]"
Title: Logging
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
I want to see logs of payment process and other plugin events, 
so that I can monitor and troubleshoot payment processes.

As a supporter,
I want to receive logs from the merchants submitting support tickets,
so that I can understand the problem better and troubleshooting is made easier.

#### Scenario: Logging Errors

GIVEN @[Woocommerce store is configured]
WHEN @[an error occurred] during the plugin execution  
AND I @[visit, the WooCommerce Logs page]
THEN I @[should see, in the log, the detailed info about this error]

#### Scenario: Logging Events

GIVEN @[Woocommerce store is configured]
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]
WHEN I @[visit, the WooCommerce Logs page]
THEN I @[should see, in the log, the detailed info about this transaction like the order ID]

#### Scenario: Enabling Detailed Logs

GIVEN @[Woocommerce store is configured]
WHEN I @[visit, the Worldline settings page]
AND I @[check, the "Enable debug logging" checkbox]
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]
AND I @[visit, the WooCommerce Logs page]
THEN I @[should see, in the log, more detailed logs, such as HTTP requests and responses for the Worldline API]
