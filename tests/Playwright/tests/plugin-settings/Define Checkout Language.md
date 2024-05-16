---
Feature: "[[features-to-requirements-mapping]]"
Title: Define Checkout Language
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
I want to send the store’s country/language to the Worldline redirected payment page,
So that the checkout process is presented in the appropriate language to the customer.(FR/NL/DE/ES/IT)

#### Scenario: Configuring Checkout Language
@[xrayKey: 

GIVEN @[WooCommerce store is ready for checkout]
WHEN I @[visit, WooCommerce settings page]
AND I @[select, the store’s country/language, from one of the expected languages (FR/NL/DE/ES/IT)]
AND I @[perform, a transaction, with any product, using Worldline as the payment method, status success]
THEN I @[should see, this language used on the Worldline Payment Page]

Data: languages: FR, NL, DE, ES, IT

#### Scenario: Configuring Checkout Language with none expected languages
@[xrayKey: 

GIVEN @[WooCommerce store is ready for checkout]
WHEN I @[visit, WooCommerce settings page]
AND I @[select, the store’s country/language, is not one of the expected languages (FR/NL/DE/ES/IT)]
AND I @[perform, a transaction, with any product, using Worldline as the payment method, status success]
THEN I @[should see, the English language to be used on the Worldline Payment Page]
