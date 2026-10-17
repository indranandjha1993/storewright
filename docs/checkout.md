# Cart and checkout

The cart and checkout are WooCommerce's block cart and checkout, styled by the theme and extended
in `storewright-core/src/Checkout/`.

## Business buyers

Two optional fields sit under the email address: **Business name** and **GSTIN**. A GSTIN is
normalised to capitals without spaces and checked against the 15-character format and its check
digit before the order is placed; a wrong one is refused with an example of the right shape. Both
values are stored on the order (`_wc_other/storewright/business`, `_wc_other/storewright/gstin`),
appear on the order confirmation page and in emails, and show in the console's order panel.

## PIN code autofill

Typing a 6-digit PIN code in the shipping or billing address fills in the state and, when it is
empty, the city from the PIN directory (see [delivery.md](delivery.md)). Nothing is overwritten
if the shopper has already typed a city.

## Free delivery progress

When the delivery area that covers the bag has free delivery from an order amount, the cart and
the checkout summary show how much more to add and a progress bar, and confirm once the order
qualifies. The script loads only on those two pages. The amount comes from the Store API cart response
(`extensions.storewright.freeDeliveryFrom` and `remaining`), so it always matches what checkout
will charge.

## Checkout header

The checkout has its own short header: the store's logo or name, a line about payment options and
a way back to the shop. The terms page seeded with the store is linked in the checkout's terms
notice.
