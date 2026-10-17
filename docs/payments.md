# Payments

Checkout offers three ways to pay: Razorpay, Stripe and cash on delivery. Razorpay and Stripe each
open their own secure page, so card details never reach the store.

## Keys

All keys come from `.env`. The key decides the mode: `sk_test_` and `rzp_test_` keys run the
gateway in sandbox, `sk_live_` and `rzp_live_` keys in live mode. Live keys are ignored on any
environment that is not production, and keys from the environment are never stored in the
database.

```
STRIPE_PUBLISHABLE_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
```

`/version` shows each gateway as `sandbox`, `live`, `refused` or `off`.

## Webhooks

Orders complete when the shopper returns from the payment page. Webhooks cover the shopper who
pays and closes the tab. Every verified webhook is recorded once in `storewright_payment_events`;
a repeat delivery is answered without being processed again.

### Stripe

Local development needs no dashboard setup. Get the forwarding secret once and run the forwarder:

```
stripe listen --api-key "$STRIPE_SECRET_KEY" --print-secret   # put the whsec_ value in STRIPE_WEBHOOK_SECRET
make stripe-listen
```

For a deployed store, in the Stripe dashboard go to Developers, Webhooks, Add endpoint:

- URL: `https://<store domain>/wp-json/storewright/v1/webhooks/stripe`
- Events: `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
  `checkout.session.async_payment_failed`, and `charge.refunded` if refunds made in Stripe should
  be noted on the order
- Copy the endpoint's signing secret into that server's `STRIPE_WEBHOOK_SECRET`

### Razorpay

In the Razorpay dashboard, in the mode that matches the keys, go to Account & Settings, Webhooks,
Add New Webhook:

- URL: `https://<store domain>/wp/wp-admin/admin-post.php?action=rzp_wc_webhook`
- Secret: any long random string, also set as `RAZORPAY_WEBHOOK_SECRET`
- Events: the payment events and the refund events

Razorpay's plugin waits five minutes before acting on a webhook, so the shopper's own return gets
there first. An order paid in a closed tab completes five to ten minutes later, on the cron
container.

Local development needs a public URL. The store can be reached through a Cloudflare tunnel, but
Cloudflare's Bot Fight Mode challenges Razorpay's servers and the webhook never arrives. Turn it
off for the zone the tunnel uses; on the free plan it cannot be skipped for a single path.

## Payments screen

The console's Payments screen (`/payments`) shows, for Razorpay and Stripe, the mode the keys put
the gateway in, when the last webhook arrived, how many came in the last 24 hours, and how many
failed or are still waiting to be processed. Each card has the webhook address to paste into the
gateway dashboard.

Waiting for payment lists online orders that are still unpaid 15 minutes after they were placed,
from the last seven days. Check with Razorpay or Check with Stripe asks the gateway directly:
Stripe for the order's Checkout session, Razorpay for the payments on the order's Razorpay order.
A captured payment is recorded on the order at once; otherwise the answer says what the gateway
has, for example that the payment page expired or was never opened.

The webhook log lists every recorded event, newest first, with All, Failed and Waiting filters.

REST, for staff with `manage_woocommerce`:

```
GET  /wp-json/storewright/v1/payments/health
GET  /wp-json/storewright/v1/payments/events?gateway=stripe&status=failed&page=1
GET  /wp-json/storewright/v1/payments/unsettled
POST /wp-json/storewright/v1/payments/unsettled/{order id}
```

