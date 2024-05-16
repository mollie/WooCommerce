---
Feature: "[[features-to-requirements-mapping]]"
Title: Displaying Payment Methods on Checkout Page
Background:
  - default
tags:
  - php
  - critical
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
Xray Key: 
Xray Key:125607
Xray Id:
---

# Description

As a buyer on the WooCommerce platform,  
I want to see the available payment methods, including the specific one provided by the plugin, on the checkout page,  
So that I can select my preferred payment method for the transaction.

#### Scenario: Frontend ui - Viewing Payment Methods

GIVEN @[Woocommerce store is ready for checkout]
WHEN I @[select, in the checkout form, any country]
THEN I @[should see, Worldline payment methods]
