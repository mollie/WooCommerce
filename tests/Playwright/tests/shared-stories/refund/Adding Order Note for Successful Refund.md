---
Feature: "[[features-to-requirements-mapping]]"
Title: Adding Order Note for Successful Refund
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
I want a note added to the order when a refund is successfully processed,
So that there is clear documentation of the refund for future reference.

#### Scenario: Recording a Refund in Order Notes

GIVEN @[WooCommerce store is configured]
AND I @[create, a manual order]
WHEN I @[visit, the order details page]
AND I @[click on, in the order details, the refund button]
AND I @[perform, a refund, for the order]
THEN I @[should see, in the order notes, an indication of the successful refund]

