---
Feature: "[[features-to-requirements-mapping]]"
Title: Map Statuses
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
---

# Description

As a merchant,
I want to map Worldline payment statuses to the specific order statuses of WooCommerce,
So that I can maintain consistent order status tracking.

#### Scenario: Status Mapping for Payment and Order
GIVEN a payment status is received from Worldline
WHEN I view the corresponding order in the backend
THEN the order status should reflect the mapped status from Worldline.
