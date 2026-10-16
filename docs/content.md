# Pages and the journal

A store ships with the pages a shop needs, written for a small business and ready to edit:

| Page | Path | Used for |
|---|---|---|
| About | `/about/` | The story, how the business works, where it is |
| Contact | `/contact/` | How to reach the store, wholesale, visiting |
| Shipping and returns | `/returns/` | Delivery times, return window, damaged items |
| Terms of sale | `/terms/` | Prices, orders, delivery, governing law |
| Privacy policy | `/privacy-policy/` | WordPress' privacy page, filled in |
| Journal | `/journal/` | The store's notes and guides |

They are ordinary WordPress pages, so staff edit them under Pages in the advanced admin with the
block editor; the theme's `page` template sets the reading width and typography. The footer links
to all of them.

Posts appear as the **Journal**: entries rather than posts, with the admin menu, labels and
templates renamed to match. New stores get two entries with photographs and the categories "Care
guides" and "Behind the shop"; the default category is called "Notes". The `home` template lists
entries as cards, `single` shows one with its photograph, and `404` points a lost visitor back
to the shop.

`make seed` creates all of this once and never overwrites a page that has been edited.
