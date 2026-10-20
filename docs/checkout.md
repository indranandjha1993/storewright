# Cart and checkout

The cart and checkout are WooCommerce's block cart and checkout, styled by the theme and extended
in `storewright-core/src/Checkout/`.

## Business buyers

Two optional fields sit under the email address: **Business name** and **GSTIN**. A GSTIN is
normalised to capitals without spaces and checked against the 15-character format and its check
digit before the order is placed; a wrong one is refused with an example of the right shape. Both
values are stored on the order (`_wc_other/storewright/business`, `_wc_other/storewright/gstin`),
appear on the order confirmation page, in emails and on the order screen.

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

## Charges

Two amounts can be added to an order under Store, Settings, Taxes and charges. Packing and
handling is added to every order that ships, or only below an order amount of your choosing.
Cash on delivery handling is added the moment a shopper picks cash on delivery, and leaves again
if they pick an online method. Both appear as their own line in the bag, the checkout, the
emails and the invoice, where they carry GST at the usual rate like delivery does. The block
checkout learns the chosen method through a Store API update (`storewright` namespace), which
`assets/js/checkout-charges.js` sends when the method changes.
