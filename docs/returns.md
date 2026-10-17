# Returns

## For the customer

Once an order is completed, its page in the account offers Return items until the return window
closes (7 days by default, set under Settings, Checkout; 0 turns returns off). The customer picks
how many of each item are coming back, a reason (arrived damaged, wrong item, does not fit, changed
my mind, something else) and an optional note. Only goods that ship can be returned; downloads
cannot. The order page then shows each return and where it stands.

Emails go out at each step: the request is acknowledged, an approval says a pickup is coming, a
decline carries the store's message, and the refund arrives with WooCommerce's refund email.

## For staff

Returns has its own screen in the console. Open returns come first; All shows the history. Each
return moves through:

1. **New**: approve and arrange the pickup, or decline with a message.
2. **Pickup arranged**: mark it received once the parcel is back, or decline.
3. **Back with us**: refund.

Refund creates a WooCommerce refund for the returned items at the price paid and puts them back in
stock. Razorpay and Stripe orders are refunded to the customer's payment automatically; for cash on
delivery the refund is recorded and the order note reminds staff to send the money by UPI or bank
transfer. The staff address that receives new-order emails also gets an email for every new return.

Pickups are arranged by hand for now; a carrier adapter can book them later.

## Data and REST

Requests live on the order in `_storewright_returns`, with `_storewright_return_open` set while any
is unfinished. Staff with `manage_woocommerce`:

```
GET  /wp-json/storewright/v1/returns           open returns (add ?all=1 for every return)
POST /wp-json/storewright/v1/returns/{order}/{return id}   {"status": "approved|received|refunded|declined", "note": "..."}
```
