# Delivery areas

A delivery area is a WooCommerce shipping zone. Zones are checked in their order: an address
belongs to the first zone whose regions and PIN rules it matches and that has at least one
enabled delivery method. A zone can also carry a shape, a radius around a point or a polygon;
then the shopper's location has to fall inside it, and a location outside moves on to the next
zone.

## Editing areas

Staff edit areas in the console under Delivery areas. The list shows the areas in the order
addresses are checked; Move up and Move down change that order. Everywhere else always comes last
and only its delivery methods can change.

Each area has:

- **Regions**: countries or states. Leave empty to cover every country.
- **PIN codes**, one rule per line: `560001` for one PIN code, `560*` for every PIN code that starts
  with 560, `560001-560099` (or `560001...560099`) for a range. When every region is in India, a
  rule has to be a 6 digit PIN code, a prefix or a range of 6 digit PIN codes; anywhere else
  letters are allowed.
- **Map shape**: none, a radius around a point (click the map to place the centre) or an area drawn
  on the map (click to add corners, drag a corner to move it). "Show a PIN code on the map" moves
  the map to a PIN code first.
- **Delivery methods**: a delivery charge or free delivery, optionally from an order amount. Methods
  with settings the console does not edit, such as a charge worked out per item or free delivery
  with a coupon, can be switched on or off and removed; their other settings are changed in the
  advanced settings.

"Test an address" takes a PIN code, or a click on the map, and answers with the message a shopper
would see, the area that decided it and the options they could choose.

The map shows OpenStreetMap tiles, which is fine for staff use within the
[tile usage policy](https://operations.osmfoundation.org/policies/tiles/). To use another tile
server, return its URL template and attribution from the `storewright_map_tiles` filter:

```php
add_filter( 'storewright_map_tiles', fn() => [
	'url'         => 'https://tiles.example.com/{z}/{x}/{y}.png',
	'attribution' => '&copy; Example maps',
] );
```

The console uses `storewright/v1/areas` (list, create, update, delete, reorder and test). It needs
the `manage_woocommerce` capability.

## From the command line

```
wp storewright area list
wp storewright area radius <zone id> <lat> <lng> <km>
wp storewright area polygon <zone id> <file.geojson>
wp storewright area clear <zone id>
```

Polygons are read from GeoJSON, as exported by most map tools (longitude first, as the format
requires).

To see what a shopper would get:

```
wp storewright deliver IN KA 560034
wp storewright deliver IN KA 560034 --lat=12.95 --lng=77.60
```

The answer is `deliverable`, `undeliverable` with a reason (`no_area`, `no_methods`,
`outside_area`), or `approximate` when a zone has a shape and there is no location to test.

## PIN directory

`make pins` downloads the All India Pincode Directory and imports one row per PIN with its
district, state and a representative point. The point is the median of the post offices in that
PIN after dropping placeholder coordinates and offices far from the rest, so it is good for
"which area is this PIN in", not for a doorstep. Set `PINS_SOURCE_URL` to use another copy of the
same file.

Contains data from the Department of Posts, Government of India, published on data.gov.in under
the Government Open Data License, India.
