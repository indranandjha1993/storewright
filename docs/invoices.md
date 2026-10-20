# Invoices

Every order gets an invoice number the first time it is paid or marked complete. Numbers run
`INV/26-27/0001`, `INV/26-27/0002` and start again at 0001 each April, as GST requires a
consecutive series per financial year of at most 16 characters. The prefix (one to four letters
or digits) is set under Store, Settings, Taxes and charges. The counter is a single row incremented in the
database, so two orders paid at the same moment never share a number.

## What the invoice shows

- The store's name or logo, address, phone, email and GSTIN, from Settings and Brand.
- The invoice number and date, the order number and the place of supply (the delivery state with
  its GST state code).
- The buyer's billing and delivery addresses, and their business name and GSTIN when they gave one
  at checkout.
- One line per product, plus delivery and fees, with HSN or SAC code, quantity, taxable value, GST
  and the amount paid.
- Totals and the amount in words, written in lakh and crore.

Prices include GST, so each line's taxable value is worked back from what the customer paid. When
the delivery state is the store's state the tax is split into CGST and SGST, otherwise it is IGST.
Each product takes the GST rate and HSN code set on it in the advanced product editor (General tab,
"HSN code" and "GST rate"), or the store's usual rate from Settings. Delivery is taxed at the
highest rate in the order under SAC 996812. A store without a GSTIN prints a plain invoice with no
tax columns and a line saying it is not registered under GST.

## Where it is

The invoice is a page made for printing: Print or save as PDF uses the browser's own dialog, and
the page drops its background and button when printed. It is linked from the order confirmation
page, the customer's account order page and the order screen.

The address is `/?storewright-invoice=<order id>&key=<order key>`. Staff can open any invoice; a
customer can open their own orders' invoices, and anyone holding the order key (the same key as
the confirmation page) can open that one invoice. Everyone else gets a 404. Invoice pages are not
indexed.
