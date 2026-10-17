# Customer account

The account page is WooCommerce's account with Storewright's own menu, overview, saved products
and address book.

## Menu

Overview, Orders, Saved, Downloads, Addresses, Account details and Sign out. Payment methods are not
listed because payments are taken on the gateways' own pages.

## Overview

A greeting by first name, three cards (orders placed, the default delivery PIN code, the account
email) that open their pages, and the three most recent orders with status and total. The
template is `web/app/themes/storewright/woocommerce/myaccount/dashboard.php`.

## Saved

Signed-in shoppers can press "Save for later" on any product page; guests see "Sign in to save".
The Saved page lists those products with today's price and stock, a button to add simple
products to the bag or open the options of variable ones, and Remove. Up to 100 products are kept
in the user meta `storewright_saved`. The button is the block `storewright/save-button`, which
toggles through `POST /wp-json/storewright/v1/saved/{product id}` (signed-in users, REST nonce).

## Addresses

Shoppers keep up to ten delivery addresses. The default one is WooCommerce's shipping address;
the others are stored in the user meta `storewright_addresses`. Every card says whether the store
delivers there, using the same check as the storefront (see [delivery.md](delivery.md)). An
address can be made the default, which swaps it with the current one, or removed. A new address
needs a name, street, city and, in India, a 6 digit PIN code.

The billing address is shown below the delivery addresses and edited with WooCommerce's form.

At checkout, a signed-in shopper with more than one address sees them as cards above the
shipping form; choosing one fills the form and recalculates delivery.
