# Shipping orders

Shipments use WooCommerce's own fulfilments (`woocommerce_feature_fulfillments_enabled`), which
`wp storewright setup` switches on. A fulfilment is a set of order items sent together, with a
carrier, a tracking number and a tracking link. Downloads and other virtual items are marked as
delivered by WooCommerce as soon as the order is paid, so only goods that travel are left to ship.

## On the order screen

Open an order under Store, Orders and use WooCommerce's Fulfillments panel:

1. Press Ship items. Every item still to send is listed with its quantity; lower a quantity or set
   it to 0 to send part of the order now and the rest later.
2. Pick the carrier. The list has the store's own delivery, Delhivery, Blue Dart, DTDC, India
   Post, Ekart, Xpressbees, Shadowfax, Shiprocket, DHL, FedEx and UPS. "Another carrier" asks for
   its name.
3. Add the tracking number and, if the carrier or aggregator gave one, the tracking link.
4. Leave "Email the customer that it is on its way" ticked to send the shipment email.

Each shipment can be removed again, for example after a wrong tracking number; removing one does
not email the customer. The orders list shows "Shipped" or "Part shipped" under the status.
Marking the order as completed stays a separate step.

## What the customer sees

The shipment email ("Your Storewright order 1234 is on its way") names the carrier and tracking
number, has a Track your parcel button when there is a link, lists the items in that parcel and
links to the whole order. The order page in the account, and the confirmation page for guests,
show a card per shipment and what is still to ship.

## REST

The order screen uses WooCommerce's routes: `GET` and `POST /wp-json/wc/v3/orders/{id}/fulfillments`
and `DELETE /wp-json/wc/v3/orders/{id}/fulfillments/{fulfilment id}`. Items go in the `_items`
meta as `[{"item_id": 12, "qty": 1}]`, the carrier key in `_shipment_provider`, and
`_tracking_number`, `_tracking_url` and `_provider_name` (for "Another carrier") alongside.
Carriers are registered on `woocommerce_fulfillment_shipping_providers` in
`storewright-core/src/Fulfilment/Carriers.php`.

## Tracking page

`wp storewright setup` creates a Track an order page at `/track/`, linked from the footer. A
shopper enters the order number and the email they ordered with and sees where the order is in
plain words ("is being prepared. Part of it has been sent."), a card per parcel and what is still
to ship. It is WooCommerce's `[woocommerce_order_tracking]` shortcode with Storewright's form
(`web/app/themes/storewright/woocommerce/order/form-tracking.php`); signed-in customers see the same
cards on their account's order page.
