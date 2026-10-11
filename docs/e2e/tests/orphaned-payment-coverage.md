# E2E coverage: payment bypass through an orphaned payment

Incident `inc-2026-10-09-woocommerce-plugin-payment-bypass-through-orphaned-payment`. Affected version: 8.1.10.
Fix: `dev/release/8.1.11`, commit `ac233cee`. Tests: branch `dev/qa/8.1.11`.

## The problem in short

1. Order A: the shopper starts a card payment (P1) and leaves it unpaid.
2. The shopper switches to PayPal and pays (P2). Order A now points to P2, so P1 is linked to no order.
3. The shopper pays P1 late.
4. Order B (more expensive) is left unpaid.
5. The shopper sends `POST /wc-api/mollie_wc_gateway_creditcard/?order_id=B&key=<B key>` with `id=P1`. On 8.1.10, order B becomes "processing" (the response is HTTP 500, but the change is saved).

The fix (8.1.11): a payment that is not the order's current attempt may change the order only if Mollie's `metadata.order_id` names that order.

## What the dev tests cover (not repeated in E2E)

- `tests/Integration/spec/webhooks/ForeignPaymentWebhookTest.php`:
  - a foreign paid or authorized payment
  - a wrong order key → 401
  - an old paid attempt of the same order pays it
  - an old cancelled attempt leaves the order pending
  - the REST route pays the payment's own order
- `tests/php/Unit/Payment/MolliePaymentAttemptTest.php`: `metadata.order_id` formats, `isCurrentAttempt`

## E2E tests

| ID | Test | Where | Expected | 8.1.10 |
|---|---|---|---|---|
| A | Check `assertPaymentBelongsToOrder`: the order's Mollie payment has `metadata.order_id` = order id, and the same amount and currency | checkout, classic checkout, pay-for-order, PayPal Express, subscription, renewal scenarios | Passes for every paid order | pass |
| C4567658 | Full incident flow; checks that the cheap order's card payment is orphaned, then sends the forged legacy webhook call for the expensive order in the path form (`/wc-api/<gateway>/?order_id=…`) and the query form (`/?wc-api=<gateway>&order_id=…`) | `tests/qa/tests/08-bug-verification/orphaned-payment-bypass.spec.ts` | The expensive order stays pending and is linked to its own payment, with no "Order completed" note. Response 200 for both calls | **fail** |
| C4567659 | Classic checkout: card → back → PayPal → pay | `tests/qa/tests/05-transaction/payment-method-switch.spec.ts` | Order paid with the second attempt, check A passes, first attempt open, canceled or created (Orders API) | pass |
| C4567660 | Classic checkout: PayPal → back → card → pay | same | same as C4567659 | pass |
| C4567661 | Block checkout: card → back → PayPal → pay | same | same as C4567659 | pass |
| C4567662 | Card → back → PayPal (both unpaid), then the card attempt is paid | same | Order paid with the first attempt, one "Order completed" note. TODO: wrong note texts (dev ticket pending) | pass |
| C4567663 | Card → back → PayPal paid, then the card attempt is paid too (the shopper pays twice) | same | Order stays processing with PayPal, one "Order completed" note. TODO: expected handling of the second payment is an open dev question | pass |
| C4567664 | Classic checkout: card → back → card → pay | same | Same order; Payments API reuses the open card payment; order paid once, one "Order completed" note | pass |
| C4567665 | Card payment fails → pay-for-order page → PayPal → pay | same | Order paid with PayPal, no "Order completed" note for the card attempt | pass |

## Open questions for devs

1. C4567663 (double payment): what should happen when an order is paid twice (an order note, a refund, an admin notice)?
2. The filter `<pluginId>_payment_object_metadata` can remove `order_id`. Then every payment is ignored. Is a fallback planned?
