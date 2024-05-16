---
Feature: "[[features-to-requirements-mapping]]"
Title: Performing Refunds from the Plugin Back Office
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
I want to be able to perform full order refunds, partial amount refunds, and individual line item refunds from the Worldline's back office,
So that I can efficiently manage different refund scenarios as per the customer's request and maintain accurate transaction records.

#### Scenario: Refunding a Full Order

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[perform, a transaction, with any product, using Worldline as the payment method, status authorized]  
WHEN I @[visit, Worldline back office, viewing the captured payment]  
AND I @[perform, refund, with the full order amount]  
THEN I @[should see, in order details page, the full refund recorded]  
AND I @[should see, the status 'Refunded']  

#### Scenario: Refunding a Partial Amount of an Order

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[perform, a transaction, with any product, using Worldline as the payment method, status authorized]  
WHEN I @[visit, Worldline back office, viewing the completed order]  
AND I @[perform, refund, with the partial order amount]  
THEN I @[should see, in order details page, the partial refund recorded]  
AND I @[should see, the status 'Processing']  

#### Scenario: Refunding a Specific Line Item

GIVEN @[WooCommerce store is ready for checkout]  
AND I @[perform, a transaction, with any product, using Worldline as the payment method, status authorized]  
WHEN I @[visit, Worldline back office, viewing the completed order]  
AND I @[perform, refund, for a line item]  
THEN I @[should see, in order details page, the line item refund recorded]  
AND I @[should see, the status 'Processing']  
