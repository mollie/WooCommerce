---
Feature: Link to Feature Note
Title: Transaction Integrity with Coupons, Taxes, and Shipping
Background:
  - default
  - taxes-coupons-shipping-matrix
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
I want to ensure that coupons, taxes, and shipping are correctly applied and calculated in transactions using the payment method,  
So that the transactions are accurate, and customers are charged correctly in any of the checkout pages that WooCommerce offers, checkout, block-checkout and pay-page

#### Scenario: Applying Coupons Correctly in Transactions

GIVEN @[WooCommerce store is ready for checkout]
AND @[Coupon, type of coupon, and enabled]
WHEN I @[visit, the checkout page]
AND I @[apply, the coupon, type of coupon]  
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]
AND I @[visit, the WooCommerce order details]
THEN I @[should see, in the WooCommerce order details, the transaction processed successfully with the correct discount applied]

#### Scenario: Warning for Inapplicable Coupons

GIVEN @[WooCommerce store is ready for checkout]
AND @[Coupon, non valid for simple product, and enabled]
WHEN I @[visit, the checkout page]
AND I @[apply, the coupon, non valid for selected product]  
AND I @[perform a transaction, with simple product, with Worldline gateway, with status authorized]
AND I @[visit, the WooCommerce order details]
THEN I @[should see, on the page, a warning that the coupon cannot be used for some products or items]

#### Scenario: Handling Coupons Equal to or Exceeding Purchase Amount

GIVEN @[WooCommerce store is ready for checkout]
AND @[Coupon, exceeding amount of selected product, and enabled]
WHEN I @[visit, the checkout page]
AND I @[apply, the coupon, exceeding amount of simple product]  
AND I @[perform a transaction, with simple product, with Worldline gateway, with status authorized]
AND I @[visit, the WooCommerce order details]
THEN I @[should see, on the page, a warning that the coupon cannot be used for some products or items]

#### Scenario: Accurate Calculation of Taxes in Transactions

GIVEN @[WooCommerce store is ready for checkout]
WHEN I @[visit, the checkout page]
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]
AND I @[visit, the WooCommerce order details]
THEN I @[should see, in the WooCommerce order details, the transaction processed successfully with the correct taxes applied]

#### Scenario: Correct Application of Shipping Charges

GIVEN @[WooCommerce store is ready for checkout]
WHEN I @[visit, the checkout page]
AND I @[perform a transaction, with any product, with Worldline gateway, with status authorized]
AND I @[visit, the WooCommerce order details]
THEN I @[should see, in the WooCommerce order details, the transaction processed successfully with the correct shipping charges applied]

#### Scenario: Transaction Completion Despite On-Hold or Failed Payment

GIVEN @[WooCommerce store is ready for checkout]
WHEN I @[perform a transaction, with any product, with Worldline gateway, with status failed]
THEN I @[should see, in the WooCommerce order details, an order created to record the transaction attempt, even if the payment is on hold or has failed]

Data: types of coupons, coupon non valid for selected product, matrix of taxes and shipping charges
