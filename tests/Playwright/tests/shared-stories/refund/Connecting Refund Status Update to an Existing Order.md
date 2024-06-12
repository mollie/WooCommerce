---
Feature: "[[features-to-requirements-mapping]]"
Title: Connecting Refund Status Update to an Existing Order
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
I want a refund status update to be connected automatically to the corresponding WooCommerce order,
So that the order history accurately reflects all transaction activities.

#### Scenario: Linking Refund Status to WooCommerce Order

GIVEN @[WooCommerce store is ready for checkout]
AND I @[create, a manual order]
WHEN I @[visit, the WooCommerce orders page]
AND I @[click, in the order details, on the 'Refund' button]
AND I @[confirm, the refund]
THEN I @[should see, in the WooCommerce status, the refund status]
AND I @[should see, in the notifications board, the refund notice]
