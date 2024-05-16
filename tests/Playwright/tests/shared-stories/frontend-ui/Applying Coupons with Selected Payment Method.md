---
Feature: "[[features-to-requirements-mapping]]"
Title: Apply Coupons with Selected Payment Method
Background:
  - default
  - apply-coupons-with-selected-payment-method
tags:
  - php
Runn on: 
Design Notes: Link to Design Notes Note
Images: Link to Images Folder
Parent Jira Id: 
Jira Id: 
---

# Description

As a buyer,  
I want to ensure that when I select the plugin's payment method, coupons are applied correctly,  
So that I receive the intended discounts on my purchase.

#### Scenario: Correct Application of Coupons

GIVEN @[WooCommerce store is ready for checkout] 
AND I @[visit, the checkout page]
AND @[Coupon, type of coupon, and enabled]
WHEN I @[visit, the checkout page]
AND I @[select, in the WooCommerce gateways,the plugin's payment method]
AND I @[apply, the coupon, type of coupon]  
THEN I @[should see, in the checkout total, the discount applied]
AND I @[should see, in the thank you page, the discount applied]
AND I @[should see, in the order details, the discount applied]


Data: type of coupon
