---
Feature: "[[features-to-requirements-mapping]]"
Title: Edit Payment Button Title
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
I want to edit the title of the payment button for Full Redirection and Hosted Tokenization Page,
So that it aligns with my store’s branding and customer expectations.

#### Scenario: Plugin settings - Customizing Payment Button Title
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Worldline settings page]
WHEN I @[fill in, "Payment button title" field, with the value "My custom title"]
AND I @[click, Save settings button] 
AND I @[visit, the checkout page]
AND I @[select, in checkout payment methods, the Worldline gateway]
THEN I @[should see, on the Place order button, "My custom title"]

#### Scenario: Plugin settings - Keeping Default Payment Button Title
@[xrayKey: 

GIVEN @[WooCommerce Store is configured]
AND I @[visit, Worldline settings page]
WHEN I @[fill in, "Payment button title" field, with empty value]
AND I @[click, Save settings button]
AND I @[visit, the checkout page]
AND I @[select, in checkout payment methods, the Worldline gateway]
THEN I @[should see, on the Place order button, "Place order"]
