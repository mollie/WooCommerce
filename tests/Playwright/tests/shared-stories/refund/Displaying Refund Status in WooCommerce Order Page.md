---
Feature: "[[features-to-requirements-mapping]]"
Title: Displaying Refund Status in WooCommerce Order Page
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
I want to be able to perform full order refunds, partial amount refunds, and individual line item refunds from WooCommerce orders page,
So that I can efficiently manage different refund scenarios as per the customer's request and maintain accurate transaction records.

#### Scenario: Refunding a Full Order

GIVEN @[WooCommerce store is ready for checkout]
AND I @[create, a manual order]
WHEN I @[visit, the order details page]
AND I @[click, in the order details, on the 'Refund' button]
AND I @[confirm, the full order refund]
THEN I @[should see, in the order status, the status 'Refunded']
AND I @[should see, in the notifications board, the refund notice]

#### Scenario: Refunding a Partial Amount of an Order

GIVEN @[WooCommerce store is ready for checkout]
AND I @[create, a manual order]
WHEN I @[visit, the order details page]
AND I @[click, in the order details, on the 'Refund' button]
AND I @[fill-in, the refund amount, with specific partial amount]
AND I @[confirm, the partial order refund]
THEN I @[should see, in the order status, the status 'Processing']
AND I @[should see, in the notifications board, the refund notice]

#### Scenario: Refunding a Specific Line Item

GIVEN @[WooCommerce store is ready for checkout]
AND I @[create, a manual order]
WHEN I @[visit, the order details page]
AND I @[click, in the order details, on the 'Refund' button]
AND I @[select, the specific line item to refund]
AND I @[confirm, the line item refund]
THEN I @[should see, in the order status, the status 'Processing']
AND I @[should see, in the notifications board, the refund notice]
